<?php

namespace App\Command;

use App\Entity\Quest;
use App\Entity\QuestStep;
use App\Entity\User;
use App\Enum\QuestStepType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Load quest definitions from JSON files.
 *
 * The alternative was a JWT and a curl loop, which meant a password on the command line, a token
 * pasted between terminals, and no way to re-run a file after fixing a typo in it. A quest
 * definition is configuration that ships with the image, so importing it belongs where the other
 * operational commands are — reachable with `docker exec` and nothing else.
 *
 * The file format is deliberately identical to the POST /api/admin/quests body, so a definition
 * can be sent either way and the repository holds one shape, not two.
 *
 * Idempotent on the quest **name**: re-running skips what already exists, and `--update` replaces
 * the definition in place. Names are what an operator recognises on the console, and no natural
 * key exists in the file format; a UUID would make the files unwriteable by hand.
 */
#[AsCommand(
    name: 'app:quest:import',
    description: 'Import quest definitions from JSON files (idempotent on quest name)',
)]
class ImportQuestsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        // Bound here rather than in services.yaml: it is this command's own default, not a
        // project-wide convention, and a scalar argument does not autowire by itself.
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY,
                'JSON files or directories (default: the quests/ directory shipped with the image)',
            )
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Replace quests that already exist, instead of skipping them')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse, validate and report — write nothing')
            ->addOption('author', 'a', InputOption::VALUE_REQUIRED, 'Email of the authoring account (default: the first superadmin)')
            ->setHelp(<<<'HELP'
                Import everything that ships with the image:

                    docker exec monad_api php bin/console app:quest:import

                Check first, write nothing:

                    docker exec monad_api php bin/console app:quest:import --dry-run

                Re-import after editing a definition:

                    docker exec monad_api php bin/console app:quest:import quests/showcase-30min.json --update

                The file format is the POST /api/admin/quests body: name, description, available_from,
                available_to, points, estimated_duration, featured_image, and steps[] of
                {order, name, type, config}.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $files = $this->resolveFiles($input->getArgument('paths') ?: [$this->projectDir . '/quests']);
        if ($files === []) {
            $io->error('No JSON files found. Pass a file or a directory, or ship definitions in quests/.');

            return Command::FAILURE;
        }

        $author = $this->resolveAuthor($input->getOption('author'));
        if ($author === null) {
            $io->error($input->getOption('author') !== null
                ? sprintf('No account found for %s.', $input->getOption('author'))
                : 'No superadmin exists to attribute these quests to. Create one with app:user:create --admin.');

            return Command::FAILURE;
        }

        $rows = [];
        $failed = 0;

        // The whole batch is one transaction, so a run that fails halfway leaves nothing behind
        // and can simply be re-run after the fix. It also lets importOne() flush mid-way, which
        // it must: replacing a quest's steps needs the DELETEs to reach the database before the
        // new INSERTs, or the unique index on (quest_id, order) rejects the first new step.
        $connection = $this->entityManager->getConnection();
        if (!$dryRun) {
            $connection->beginTransaction();
        }

        foreach ($files as $file) {
            $name = basename($file);

            try {
                $definition = $this->readDefinition($file);
                [$action, $steps] = $this->importOne($definition, $author, (bool) $input->getOption('update'), $dryRun);
                $rows[] = [$name, $action, $steps, $definition['name']];
            } catch (\Throwable $e) {
                $rows[] = [$name, '<error>failed</error>', '-', $e->getMessage()];
                ++$failed;
                break;
            }
        }

        if (!$dryRun) {
            if ($failed === 0) {
                $this->entityManager->flush();
                $connection->commit();
            } else {
                $connection->rollBack();
            }
        }

        $io->table(['file', 'action', 'steps', 'quest'], $rows);

        if ($failed > 0) {
            $io->error(sprintf('%d file(s) failed — nothing was written.', $failed));

            return Command::FAILURE;
        }

        $io->success($dryRun
            ? sprintf('%d file(s) parsed and validated. Nothing written (--dry-run).', count($files))
            : sprintf('%d file(s) imported.', count($files)));

        return Command::SUCCESS;
    }

    /** @param list<string> $paths @return list<string> */
    private function resolveFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $found = glob(rtrim($path, '/') . '/*.json') ?: [];
                sort($found);
                $files = [...$files, ...$found];
            } elseif (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private function resolveAuthor(?string $email): ?User
    {
        $repository = $this->entityManager->getRepository(User::class);

        if ($email !== null) {
            return $repository->findOneBy(['email' => $email]);
        }

        // roles is a json column and PostgreSQL has no LIKE for json, so the cast is not optional.
        $id = $this->entityManager->getConnection()->fetchOne(
            "SELECT id FROM users WHERE roles::text LIKE '%ROLE_SUPERADMIN%' ORDER BY created_at LIMIT 1"
        );

        return $id ? $repository->find($id) : null;
    }

    /** @return array<string, mixed> */
    private function readDefinition(string $file): array
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException('unreadable');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid JSON: ' . json_last_error_msg());
        }

        foreach (['name', 'description', 'available_from', 'steps'] as $required) {
            if (!isset($data[$required])) {
                throw new \RuntimeException(sprintf('missing "%s"', $required));
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{0: string, 1: int}
     */
    private function importOne(array $definition, User $author, bool $update, bool $dryRun): array
    {
        $existing = $this->entityManager->getRepository(Quest::class)->findOneBy(['name' => $definition['name']]);

        if ($existing !== null && !$update) {
            return ['skipped', $existing->getSteps()->count()];
        }

        $quest = $existing ?? new Quest();
        $quest->setName($definition['name']);
        $quest->setDescription($definition['description']);
        $quest->setAvailableFrom(new \DateTime($definition['available_from']));
        $quest->setAvailableTo(isset($definition['available_to']) ? new \DateTime($definition['available_to']) : null);
        $quest->setPoints((float) ($definition['points'] ?? 0.0));
        $quest->setEstimatedDuration($definition['estimated_duration'] ?? null);
        $quest->setFeaturedImage($definition['featured_image'] ?? null);
        $quest->setRequiredCapabilities($definition['required_capabilities'] ?? []);
        $quest->setCreatedBy($author);

        // Replace rather than merge: the file is the definition of record, and a step deleted from
        // it must disappear rather than linger at whatever order it had. orphanRemoval turns the
        // removal into DELETEs — which have to be executed before the replacements are inserted,
        // because (quest_id, order) is unique and the new step 0 collides with the old step 0
        // otherwise. Doctrine orders INSERTs before DELETEs within one flush, so this cannot be
        // left to the batch flush at the end. Safe to flush here: the caller holds a transaction.
        if ($existing !== null) {
            foreach ($quest->getSteps()->toArray() as $step) {
                $quest->removeStep($step);
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }
        }

        foreach ($definition['steps'] as $stepData) {
            $step = new QuestStep();
            $step->setName($stepData['name'] ?? null);
            $step->setType(QuestStepType::from($stepData['type'] ?? ''));
            $step->setOrder($stepData['order'] ?? null);
            $step->setConfig($stepData['config'] ?? []);
            $quest->addStep($step);
            $this->entityManager->persist($step);
        }

        $errors = $this->validator->validate($quest);
        if (count($errors) > 0) {
            throw new \RuntimeException((string) $errors->get(0)->getPropertyPath() . ': ' . $errors->get(0)->getMessage());
        }

        $this->entityManager->persist($quest);

        return [$existing === null ? 'created' : 'updated', count($definition['steps'])];
    }
}
