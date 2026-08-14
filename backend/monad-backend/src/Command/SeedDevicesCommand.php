<?php

namespace App\Command;

use App\Entity\Device;
use App\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seed the `devices` table from the printed-label registry (IP-128).
 *
 * The registry (`infra/labels/fleet.toml` in monad-knowledge) is the file the
 * physical stickers were printed from, which makes it the right *bootstrap*
 * source: a slug in the database that nobody can be holding is useless, and a
 * sticker with no row behind it is a dead scan.
 *
 * It is a bootstrap, not a sync. After seeding, the database is authoritative —
 * label, location and public blurb are edited in `/admin`, and this command will
 * not overwrite them. Re-running only adds nodes that are missing, so it is safe
 * on a populated database and safe to run twice.
 *
 * The TOML is parsed by hand rather than by pulling in a parser dependency: the
 * needed subset is `[[nodes]] host = "..."`, which is one regex, and this is a
 * one-shot operator command rather than a hot path.
 */
#[AsCommand(
    name: 'app:devices:seed',
    description: 'Create device rows from the printed-label registry (idempotent).',
)]
class SeedDevicesCommand extends Command
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'registry',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to fleet.toml (the file the labels were printed from)',
            )
            ->addOption(
                'slug',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Seed only these slugs (repeatable). Default: every node in the registry.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be created and exit.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = $input->getOption('registry');
        if (!is_string($path) || '' === $path) {
            $io->error('Pass --registry=/path/to/fleet.toml (the monad-knowledge label registry).');

            return Command::INVALID;
        }
        if (!is_file($path) || !is_readable($path)) {
            $io->error(sprintf('Registry not readable: %s', $path));

            return Command::INVALID;
        }

        $slugs = $this->parseSlugs((string) file_get_contents($path));
        if ([] === $slugs) {
            $io->error('No [[nodes]] entries found — is that really the label registry?');

            return Command::FAILURE;
        }

        $only = $input->getOption('slug');
        if (is_array($only) && [] !== $only) {
            $slugs = array_values(array_intersect($slugs, $only));
            $missing = array_diff($only, $slugs);
            if ([] !== $missing) {
                $io->warning(sprintf('Not in the registry, skipped: %s', implode(', ', $missing)));
            }
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $created = [];
        $existing = [];

        foreach ($slugs as $slug) {
            if (null !== $this->devices->findBySlug($slug)) {
                // Already there. Never overwrite: label / location / blurb are
                // operator content owned by /admin from this point on.
                $existing[] = $slug;
                continue;
            }
            $created[] = $slug;
            if ($dryRun) {
                continue;
            }

            $device = (new Device())
                ->setSlug($slug)
                // A placeholder an operator will improve in /admin. Naming it
                // after the slug beats an empty string in every listing.
                ->setLabel($slug);
            $this->entityManager->persist($device);
        }

        if (!$dryRun && [] !== $created) {
            $this->entityManager->flush();
        }

        $io->definitionList(
            ['Registry' => $path],
            ['In registry' => count($slugs)],
            ['Already present' => count($existing)],
            [$dryRun ? 'Would create' : 'Created' => count($created)],
        );
        if ([] !== $created) {
            $io->listing($created);
        }
        if ($dryRun) {
            $io->note('Dry run — nothing was written.');
        } elseif ([] !== $created) {
            $io->success('Seeded. Set label / location / public blurb in /admin.');
        } else {
            $io->success('Nothing to do — every registry node already has a row.');
        }

        return Command::SUCCESS;
    }

    /**
     * Pull `host = "monadNN"` out of the registry's `[[nodes]]` tables.
     *
     * Anchored to the start of a line so a `host` mentioned inside one of the
     * file's long explanatory comments cannot be mistaken for an entry.
     *
     * @return string[] unique, in file order
     */
    private function parseSlugs(string $toml): array
    {
        preg_match_all('/^\s*host\s*=\s*"([a-z0-9-]+)"/m', $toml, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
