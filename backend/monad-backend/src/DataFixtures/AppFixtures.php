<?php

namespace App\DataFixtures;

use App\Entity\News;
use App\Entity\QrCode;
use App\Entity\User;
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

        // Create news items
        $this->createNews($manager, $users['admin']);

        // Quests are created in QuestFixtures

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
}
