<?php

namespace App\DataFixtures;

use App\Entity\News;
use App\Entity\QrCode;
use App\Entity\Quest;
use App\Entity\QuestEnrollment;
use App\Entity\QuestStep;
use App\Entity\User;
use App\Enum\QuestEnrollmentStatus;
use App\Enum\QuestStepType;
use App\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create users
        $users = $this->createUsers($manager);

        // Create QR codes
        $this->createQrCodes($manager, $users['admin']);

        // Create quests with steps
        $quests = $this->createQuests($manager, $users['admin']);

        // Create news items
        $this->createNews($manager, $users['admin']);

        // Create some quest enrollments
        $this->createQuestEnrollments($manager, $users, $quests);

        $manager->flush();
    }

    private function createUsers(ObjectManager $manager): array
    {
        $users = [];

        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@monad.sk');
        $admin->setName('Admin User');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $admin->setStatus(UserStatus::ACTIVE);
        $manager->persist($admin);
        $users['admin'] = $admin;

        // Create 5 regular users
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user{$i}@monad.sk");
            $user->setName("User {$i}");
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setStatus(UserStatus::ACTIVE);
            $manager->persist($user);
            $users["user{$i}"] = $user;
        }

        return $users;
    }

    private function createQrCodes(ObjectManager $manager, User $createdBy): array
    {
        $qrCodes = [];

        // QR code locations in Bratislava area (realistic GPS coordinates)
        $locations = [
            ['lat' => 48.1486, 'lon' => 17.1077, 'name' => 'Main Square QR'],
            ['lat' => 48.1439, 'lon' => 17.1097, 'name' => 'Bratislava Castle QR'],
            ['lat' => 48.1516, 'lon' => 17.1093, 'name' => 'St. Martin\'s Cathedral QR'],
            ['lat' => 48.1452, 'lon' => 17.1062, 'name' => 'Presidential Palace QR'],
            ['lat' => 48.1500, 'lon' => 17.1089, 'name' => 'Michalska Tower QR'],
            ['lat' => 48.1473, 'lon' => 17.1124, 'name' => 'Slovak National Theatre QR'],
            ['lat' => 48.1458, 'lon' => 17.1135, 'name' => 'Danube Riverbank QR'],
            ['lat' => 48.1511, 'lon' => 17.1110, 'name' => 'UFO Tower QR'],
            ['lat' => 48.1490, 'lon' => 17.1070, 'name' => 'Hviezdoslav Square QR'],
            ['lat' => 48.1530, 'lon' => 17.1150, 'name' => 'Park Bridge QR'],
        ];

        for ($i = 1; $i <= 10; $i++) {
            $qrCode = new QrCode();
            $qrCode->setName($locations[$i - 1]['name']);
            $qrCode->setValue("QRM-{$i}");
            $qrCode->setPosition(json_encode([
                'latitude' => $locations[$i - 1]['lat'],
                'longitude' => $locations[$i - 1]['lon'],
            ]));
            $qrCode->setCreatedBy($createdBy);
            $manager->persist($qrCode);
            $qrCodes["QRM-{$i}"] = $qrCode;
        }

        return $qrCodes;
    }

    private function createQuests(ObjectManager $manager, User $createdBy): array
    {
        $quests = [];
        $now = new \DateTime();

        // 1. Past Quest
        $pastQuest = new Quest();
        $pastQuest->setName('Historic Bratislava Tour');
        $pastQuest->setDescription('Explore the historical landmarks of Bratislava\'s Old Town. Visit iconic locations and learn about the city\'s rich history.');
        $pastQuest->setAvailableFrom((clone $now)->modify('-5 days'));
        $pastQuest->setAvailableTo((clone $now)->modify('-1 day'));
        $pastQuest->setCreatedBy($createdBy);
        $pastQuest->setPoints(100);
        $pastQuest->setEstimatedDuration(60);

        $this->addQuestSteps($pastQuest, [
            ['name' => 'Start Historic Tour', 'type' => QuestStepType::START, 'config' => ['description' => 'Welcome to the Historic Bratislava Tour!']],
            ['name' => 'Visit Main Square', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-1', 'description' => 'Find and scan the QR code at Main Square']],
            ['name' => 'Walk to Cathedral', 'type' => QuestStepType::WALK_TO, 'config' => ['latitude' => 48.1516, 'longitude' => 17.1093, 'radius' => 50]],
            ['name' => 'Scan Cathedral QR', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-3', 'description' => 'Scan the QR code at St. Martin\'s Cathedral']],
            ['name' => 'Complete Tour', 'type' => QuestStepType::FINISH, 'config' => ['message' => 'Congratulations on completing the Historic Bratislava Tour!']],
        ]);

        $manager->persist($pastQuest);
        $quests['past'] = $pastQuest;

        // 2. Active Quest 1 - Castle Adventure
        $activeQuest1 = new Quest();
        $activeQuest1->setName('Castle Adventure');
        $activeQuest1->setDescription('Discover the secrets of Bratislava Castle and its surroundings. A perfect blend of history and modern technology.');
        $activeQuest1->setAvailableFrom((clone $now)->modify('-2 days'));
        $activeQuest1->setAvailableTo((clone $now)->modify('+7 days'));
        $activeQuest1->setCreatedBy($createdBy);
        $activeQuest1->setPoints(150);
        $activeQuest1->setEstimatedDuration(90);

        $this->addQuestSteps($activeQuest1, [
            ['name' => 'Begin Castle Adventure', 'type' => QuestStepType::START, 'config' => ['description' => 'Start your journey to explore Bratislava Castle!']],
            ['name' => 'Scan Castle Entrance', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-2', 'description' => 'Find the QR code at the castle entrance']],
            ['name' => 'Connect to Castle WiFi', 'type' => QuestStepType::CONNECT_TO_AP, 'config' => ['ssid' => 'Castle_Guest_WiFi', 'description' => 'Connect to the castle guest WiFi network']],
            ['name' => 'Visit Presidential Palace', 'type' => QuestStepType::WALK_TO, 'config' => ['latitude' => 48.1452, 'longitude' => 17.1062, 'radius' => 30]],
            ['name' => 'Find Palace Beacon', 'type' => QuestStepType::FIND_BLE_DEVICE, 'config' => ['device_name' => 'Palace_Beacon', 'description' => 'Find the Bluetooth beacon near the palace']],
            ['name' => 'Adventure Complete', 'type' => QuestStepType::FINISH, 'config' => ['message' => 'Great job exploring the castle area!']],
        ]);

        $manager->persist($activeQuest1);
        $quests['active1'] = $activeQuest1;

        // 3. Active Quest 2 - Riverbank Discovery
        $activeQuest2 = new Quest();
        $activeQuest2->setName('Riverbank Discovery');
        $activeQuest2->setDescription('Follow the Danube river and discover amazing spots along Bratislava\'s waterfront. Enjoy scenic views and hidden gems.');
        $activeQuest2->setAvailableFrom((clone $now)->modify('-2 days'));
        $activeQuest2->setAvailableTo((clone $now)->modify('+7 days'));
        $activeQuest2->setCreatedBy($createdBy);
        $activeQuest2->setPoints(120);
        $activeQuest2->setEstimatedDuration(75);

        $this->addQuestSteps($activeQuest2, [
            ['name' => 'Start Riverbank Tour', 'type' => QuestStepType::START, 'config' => ['description' => 'Begin your journey along the beautiful Danube!']],
            ['name' => 'Scan Riverbank Point', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-7', 'description' => 'Scan the QR code at the riverbank']],
            ['name' => 'Walk to UFO Tower', 'type' => QuestStepType::WALK_TO, 'config' => ['latitude' => 48.1511, 'longitude' => 17.1110, 'radius' => 40]],
            ['name' => 'Scan UFO Tower', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-8', 'description' => 'Find and scan the QR at UFO Tower']],
            ['name' => 'Finish Riverbank Tour', 'type' => QuestStepType::FINISH, 'config' => ['message' => 'You\'ve completed the Riverbank Discovery!']],
        ]);

        $manager->persist($activeQuest2);
        $quests['active2'] = $activeQuest2;

        // 4. Active Quest 3 - Cultural Journey
        $activeQuest3 = new Quest();
        $activeQuest3->setName('Cultural Journey');
        $activeQuest3->setDescription('Immerse yourself in Bratislava\'s vibrant cultural scene. Visit theaters, squares, and cultural landmarks.');
        $activeQuest3->setAvailableFrom((clone $now)->modify('-2 days'));
        $activeQuest3->setAvailableTo((clone $now)->modify('+7 days'));
        $activeQuest3->setCreatedBy($createdBy);
        $activeQuest3->setPoints(130);
        $activeQuest3->setEstimatedDuration(80);

        $this->addQuestSteps($activeQuest3, [
            ['name' => 'Begin Cultural Journey', 'type' => QuestStepType::START, 'config' => ['description' => 'Start exploring Bratislava\'s cultural treasures!']],
            ['name' => 'Visit Michalska Tower', 'type' => QuestStepType::WALK_TO, 'config' => ['latitude' => 48.1500, 'longitude' => 17.1089, 'radius' => 25]],
            ['name' => 'Scan Tower QR', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-5', 'description' => 'Scan the QR code at Michalska Tower']],
            ['name' => 'Wait for Theater Opening', 'type' => QuestStepType::WAIT, 'config' => ['duration' => 300, 'description' => 'Wait 5 minutes at the theater']],
            ['name' => 'Scan Theater QR', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-6', 'description' => 'Scan at Slovak National Theatre']],
            ['name' => 'Complete Cultural Journey', 'type' => QuestStepType::FINISH, 'config' => ['message' => 'Well done completing the Cultural Journey!']],
        ]);

        $manager->persist($activeQuest3);
        $quests['active3'] = $activeQuest3;

        // 5. Future Quest
        $futureQuest = new Quest();
        $futureQuest->setName('Summer Festival Trail');
        $futureQuest->setDescription('Get ready for the upcoming summer festival! This quest will take you through all the festival venues and preparation sites.');
        $futureQuest->setAvailableFrom((clone $now)->modify('+5 days'));
        $futureQuest->setAvailableTo((clone $now)->modify('+12 days'));
        $futureQuest->setCreatedBy($createdBy);
        $futureQuest->setPoints(200);
        $futureQuest->setEstimatedDuration(120);

        $this->addQuestSteps($futureQuest, [
            ['name' => 'Start Festival Trail', 'type' => QuestStepType::START, 'config' => ['description' => 'Welcome to the Summer Festival Trail!']],
            ['name' => 'Visit Hviezdoslav Square', 'type' => QuestStepType::WALK_TO, 'config' => ['latitude' => 48.1490, 'longitude' => 17.1070, 'radius' => 35]],
            ['name' => 'Scan Square QR', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-9', 'description' => 'Find the festival preparation QR']],
            ['name' => 'Connect to Festival Network', 'type' => QuestStepType::CONNECT_TO_AP, 'config' => ['ssid' => 'Festival_Setup', 'description' => 'Connect to the festival setup network']],
            ['name' => 'Walk to Park Bridge', 'type' => QuestStepType::WALK_TO, 'config' => ['latitude' => 48.1530, 'longitude' => 17.1150, 'radius' => 45]],
            ['name' => 'Scan Final Location', 'type' => QuestStepType::SCAN_QR, 'config' => ['qr_code' => 'QRM-10', 'description' => 'Scan the QR at Park Bridge']],
            ['name' => 'Festival Trail Complete', 'type' => QuestStepType::FINISH, 'config' => ['message' => 'Excellent! You\'re ready for the summer festival!']],
        ]);

        $manager->persist($futureQuest);
        $quests['future'] = $futureQuest;

        return $quests;
    }

    private function addQuestSteps(Quest $quest, array $stepsData): void
    {
        foreach ($stepsData as $index => $stepData) {
            $step = new QuestStep();
            $step->setName($stepData['name']);
            $step->setType($stepData['type']);
            $step->setOrder($index);
            $step->setConfig($stepData['config']);
            $quest->addStep($step);
        }
    }

    private function createNews(ObjectManager $manager, User $createdBy): void
    {
        $newsItems = [
            [
                'title' => 'Welcome to Monad Quest Platform!',
                'content' => 'We are excited to announce the launch of Monad, your new interactive quest platform for exploring Bratislava! Discover hidden gems, complete challenges, and earn points while exploring the beautiful Slovak capital. Start your first quest today and join our growing community of urban explorers!',
            ],
            [
                'title' => 'New Castle Adventure Quest Released',
                'content' => 'Attention all adventurers! We\'ve just released a brand new quest - Castle Adventure. This exciting journey will take you through Bratislava Castle and its historic surroundings. With 150 points up for grabs and an estimated duration of 90 minutes, this is perfect for a weekend activity. Don\'t forget to charge your phone and wear comfortable shoes!',
            ],
            [
                'title' => 'Summer Festival Trail Coming Soon',
                'content' => 'Mark your calendars! The highly anticipated Summer Festival Trail quest will be available starting next week. This special quest will guide you through all the summer festival venues and give you an exclusive behind-the-scenes look at the festival preparations. Worth 200 points, this is our most rewarding quest yet!',
            ],
            [
                'title' => 'Top Questers of the Week',
                'content' => 'Congratulations to our top questers this week! We\'ve seen amazing participation with over 50 completed quests. Special shoutout to our most active explorers who completed multiple quests and discovered new ways to explore Bratislava. Keep up the great work, and remember - new quests are being added regularly!',
            ],
            [
                'title' => 'Tips for Better Quest Experience',
                'content' => 'Want to make the most of your Monad quests? Here are some pro tips: 1) Always check the weather before starting outdoor quests. 2) Make sure your GPS and camera permissions are enabled for smooth QR code scanning. 3) Bring a friend - quests are more fun together! 4) Take time to enjoy the locations, not just complete the steps. 5) Share your experience and photos with the community. Happy questing!',
            ],
        ];

        foreach ($newsItems as $newsData) {
            $news = new News();
            $news->setTitle($newsData['title']);
            $news->setContent($newsData['content']);
            $news->setCreatedBy($createdBy);
            $manager->persist($news);
        }
    }

    private function createQuestEnrollments(ObjectManager $manager, array $users, array $quests): void
    {
        // User 1 enrolled in past quest (completed)
        $enrollment1 = new QuestEnrollment();
        $enrollment1->setUser($users['user1']);
        $enrollment1->setQuest($quests['past']);
        $enrollment1->setStatus(QuestEnrollmentStatus::COMPLETED);
        $enrollment1->setCompletedAt((new \DateTime())->modify('-2 days'));
        $manager->persist($enrollment1);

        // User 2 enrolled in active quest 1 (in progress)
        $enrollment2 = new QuestEnrollment();
        $enrollment2->setUser($users['user2']);
        $enrollment2->setQuest($quests['active1']);
        $enrollment2->setStatus(QuestEnrollmentStatus::IN_PROGRESS);
        $manager->persist($enrollment2);

        // User 3 enrolled in active quest 2 (in progress)
        $enrollment3 = new QuestEnrollment();
        $enrollment3->setUser($users['user3']);
        $enrollment3->setQuest($quests['active2']);
        $enrollment3->setStatus(QuestEnrollmentStatus::IN_PROGRESS);
        $manager->persist($enrollment3);

        // User 4 enrolled in active quest 3 (in progress)
        $enrollment4 = new QuestEnrollment();
        $enrollment4->setUser($users['user4']);
        $enrollment4->setQuest($quests['active3']);
        $enrollment4->setStatus(QuestEnrollmentStatus::IN_PROGRESS);
        $manager->persist($enrollment4);

        // User 5 enrolled in active quest 1 (completed)
        $enrollment5 = new QuestEnrollment();
        $enrollment5->setUser($users['user5']);
        $enrollment5->setQuest($quests['active1']);
        $enrollment5->setStatus(QuestEnrollmentStatus::COMPLETED);
        $enrollment5->setCompletedAt((new \DateTime())->modify('-1 day'));
        $manager->persist($enrollment5);
    }
}
