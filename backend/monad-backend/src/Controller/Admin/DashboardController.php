<?php

namespace App\Controller\Admin;

use App\Entity\GroundTruthConflict;
use App\Entity\GroundTruthScan;
use App\Entity\News;
use App\Entity\QrCode;
use App\Entity\Device;
use App\Entity\Quest;
use App\Entity\QuestEnrollment;
use App\Entity\QuestStep;
use App\Entity\QuestStepCompletion;
use App\Entity\QuestStepSkipRecord;
use App\Entity\User;
use App\Service\GroundTruthService;
use App\Service\LabConfigService;
use App\Service\MarkerService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The management interface's front door.
 *
 * Ordered by what an operator needs mid-session, not by entity count: the lab views come first
 * because they are the ones consulted while people are still in the room and something can still
 * be fixed. Quest content is the largest surface and the least urgent, so it sits last.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_SUPERADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GroundTruthService $groundTruth,
        private readonly LabConfigService $labConfig,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'counts' => $this->counts(),
            'recent_sessions' => $this->recentLabSessions(),
        ]);
    }

    /**
     * Every lab session that has produced ground truth.
     *
     * Custom pages live on this controller rather than one of their own so they inherit the
     * dashboard's EasyAdmin context — the sidebar, the user menu and the layout come from the
     * same place the CRUD pages get them, instead of a second half-styled shell.
     */
    #[AdminRoute(path: '/lab/sessions', name: 'lab_sessions')]
    public function labSessions(): Response
    {
        return $this->render('admin/lab_sessions.html.twig', [
            'sessions' => $this->recentLabSessions(200),
        ]);
    }

    /**
     * One session's live tally.
     *
     * Deliberately the same GroundTruthService the phones and the /api/lab/ground-truth/{id}
     * endpoint use, not a second query written for the screen: an operator comparing the console
     * on the wall with a handset in the room must not be able to see two different numbers.
     */
    #[AdminRoute(path: '/lab/sessions/{labSessionId}', name: 'lab_session')]
    public function labSession(string $labSessionId): Response
    {
        return $this->render('admin/lab_session.html.twig', [
            'lab_session_id' => $labSessionId,
            'aggregate' => $this->groundTruth->aggregate($labSessionId),
            'conflicts' => $this->entityManager->getRepository(GroundTruthConflict::class)
                ->findBy(['labSessionId' => $labSessionId], ['observedAt' => 'DESC'], 50),
            'recent_scans' => $this->entityManager->getRepository(GroundTruthScan::class)
                ->findBy(['labSessionId' => $labSessionId], ['receivedAt' => 'DESC'], 50),
        ]);
    }

    /**
     * The printable marker sheet.
     *
     * Derived from the quests rather than kept as files: the codes and the strings the app matches
     * cannot drift apart if one is generated from the other.
     */
    #[AdminRoute(path: '/lab/markers', name: 'lab_markers')]
    public function labMarkers(MarkerService $markers): Response
    {
        return $this->render('admin/markers.html.twig', ['markers' => $markers->markers()]);
    }

    /**
     * The lab bundle exactly as /api/lab/config serves it.
     *
     * Read-only on purpose: the bundle is operator-authored physical reality, rendered by Ansible
     * from inventory (roles/monad_api, lab.json.j2) and bind-mounted read-only. A form here would
     * write a file the next Ansible run silently reverts — the worst kind of edit, because it
     * appears to work. What the page is for is answering "what are the phones actually being
     * told?" without shelling into the container, plus the two checks that are easy to get wrong.
     */
    #[AdminRoute(path: '/lab/bundle', name: 'lab_bundle')]
    public function labBundle(): Response
    {
        $bundle = $this->labConfig->bundle();
        $collectorHost = (string) ($bundle['collector']['host'] ?? '');
        // `beacons` is an object — {uuid, majors, zones} — and it is the zones that become
        // CoreLocation regions. Counting the object's own keys would have reported 3 forever.
        $beaconCount = is_array($bundle['beacons']['zones'] ?? null) ? count($bundle['beacons']['zones']) : 0;

        return $this->render('admin/lab_bundle.html.twig', [
            'bundle' => $bundle,
            'json' => json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'warnings' => $this->bundleWarnings($bundle, $collectorHost, $beaconCount),
        ]);
    }

    /**
     * The two mistakes config/lab/README.md warns about, checked rather than documented.
     *
     * @param array<string, mixed> $bundle
     * @return list<string>
     */
    private function bundleWarnings(array $bundle, string $collectorHost, int $beaconCount): array
    {
        $warnings = [];

        if ($collectorHost === '') {
            $warnings[] = 'No collector host. Phones have nowhere to send traffic, and /api/lab/config is serving an empty bundle.';
        } elseif (filter_var($collectorHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $warnings[] = sprintf(
                'collector.host is "%s". It must be a literal IPv4 address: the phone pins its socket to an interface, and a name resolved over the wrong one silently sends the stream out of the building.',
                $collectorHost,
            );
        }

        // Zero is the failure this page exists to catch, and it is the one the >20 check below
        // would have sailed past: with no zones there is nothing for CoreLocation to monitor, so
        // the app runs, reports itself healthy, and produces no witness events at all. Same for
        // an empty `majors` — a region is identified by UUID *and* major, so an empty list leaves
        // the UUID unusable.
        if ($beaconCount === 0) {
            $warnings[] = 'No beacon zones declared. The witness channel has nothing to monitor: the app will run and report healthy while producing no zone transitions.';
        } elseif (($bundle['beacons']['majors'] ?? []) === []) {
            $warnings[] = 'Beacon zones are declared but `majors` is empty. A CoreLocation region is identified by UUID and major together, so nothing would be monitored.';
        }

        // iOS monitors at most 20 CoreLocation beacon regions per app, and the excess is not an
        // error — the regions past the limit are simply never delivered.
        if ($beaconCount > 20) {
            $warnings[] = sprintf(
                '%d beacons declared. iOS monitors at most 20 regions and drops the rest silently, so the last %d would never be witnessed.',
                $beaconCount,
                $beaconCount - 20,
            );
        }

        if (($bundle['access_points'] ?? []) === []) {
            $warnings[] = 'No access points declared. Emit sessions will log "no AP commanded" and use whatever network the handset is already on.';
        }

        return $warnings;
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('MonadCount')
            ->setFaviconPath('/favicon.ico')
            // The API's own docs, one click away: the admin and the phone see the same backend and
            // an operator debugging a device is usually one tab away from needing the schema.
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Overview', 'fa fa-gauge');

        yield MenuItem::section('Lab operations');
        yield MenuItem::linkToRoute('Lab sessions', 'fa fa-satellite-dish', 'admin_lab_sessions');
        yield MenuItem::linkToCrud('Ground-truth scans', 'fa fa-qrcode', GroundTruthScan::class);
        yield MenuItem::linkToCrud('Scan conflicts (E3)', 'fa fa-triangle-exclamation', GroundTruthConflict::class);
        yield MenuItem::linkToRoute('Scan markers', 'fa fa-qrcode', 'admin_lab_markers');
        yield MenuItem::linkToRoute('Lab bundle', 'fa fa-sliders', 'admin_lab_bundle');

        yield MenuItem::section('People');
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);

        yield MenuItem::section('Content');
        yield MenuItem::linkToCrud('Devices (fleet)', 'fa fa-microchip', Device::class);
        yield MenuItem::linkToCrud('Quests', 'fa fa-flag', Quest::class);
        yield MenuItem::linkToCrud('Quest steps', 'fa fa-list-ol', QuestStep::class);
        yield MenuItem::linkToCrud('Enrollments', 'fa fa-user-check', QuestEnrollment::class);
        yield MenuItem::linkToCrud('Step completions', 'fa fa-circle-check', QuestStepCompletion::class);
        yield MenuItem::linkToCrud('Skip records', 'fa fa-forward', QuestStepSkipRecord::class);
        yield MenuItem::linkToCrud('News', 'fa fa-newspaper', News::class);
        yield MenuItem::linkToCrud('QR codes', 'fa fa-square-full', QrCode::class);

        yield MenuItem::section();
        yield MenuItem::linkToUrl('API docs', 'fa fa-book', '/api/doc');
        yield MenuItem::linkToLogout('Sign out', 'fa fa-right-from-bracket');
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $count = function (string $class): int {
            return (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(e.id)')->from($class, 'e')
                ->getQuery()->getSingleScalarResult();
        };

        return [
            'users' => $count(User::class),
            // Roles live in a json column, and PostgreSQL has no LIKE operator for json — the
            // value has to be cast to text first, which DQL cannot express. Hence native SQL
            // rather than a query builder. A substring match is honest here: the column holds a
            // JSON array of role strings, and this is a headline count, not an authorisation
            // decision (those go through the security layer, which reads the array properly).
            'admins' => (int) $this->entityManager->getConnection()->fetchOne(
                "SELECT COUNT(id) FROM users WHERE roles::text LIKE '%ROLE_SUPERADMIN%'"
            ),
            'quests' => $count(Quest::class),
            'enrollments' => $count(QuestEnrollment::class),
            'scans' => $count(GroundTruthScan::class),
            'conflicts' => $count(GroundTruthConflict::class),
        ];
    }

    /**
     * Lab sessions that have produced scans, newest first.
     *
     * There is no LabSession entity — a session is whatever `lab_session_id` the phones were told
     * to stamp, and it comes into existence when the first scan arrives. So the list is a GROUP BY
     * over the scans rather than a table, which also means a session with no scans is invisible
     * here: that is the correct reading, since a session nobody checked into produced no ground
     * truth.
     *
     * @return list<array{lab_session_id: string, scans: int, participants: int, last_seen: mixed}>
     */
    private function recentLabSessions(int $limit = 8): array
    {
        return $this->entityManager->createQuery(
            'SELECT s.labSessionId AS lab_session_id,
                    COUNT(s.id) AS scans,
                    COUNT(DISTINCT s.participantToken) AS participants,
                    MAX(s.receivedAt) AS last_seen
             FROM App\Entity\GroundTruthScan s
             GROUP BY s.labSessionId
             ORDER BY last_seen DESC'
        )->setMaxResults($limit)->getArrayResult();
    }
}
