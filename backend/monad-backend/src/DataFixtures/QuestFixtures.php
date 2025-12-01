<?php

namespace App\DataFixtures;

use App\Entity\Quest;
use App\Entity\QuestStep;
use App\Entity\User;
use App\Enum\QuestStepType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class QuestFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var User $superadmin */
        $superadmin = $this->getReference(UserFixtures::SUPERADMIN_REFERENCE, User::class);

        $quest = $this->createFiitTreasureHunt($superadmin);
        $manager->persist($quest);

        $manager->flush();
    }

    private function createFiitTreasureHunt(User $createdBy): Quest
    {
        $quest = new Quest();
        $quest->setName('FIIT Treasure Hunt');
        $quest->setDescription(
            "Welcome to the FIIT Treasure Hunt! Your mission is to explore the Faculty of Informatics " .
            "and Information Technologies building and find all 6 hidden BLE beacons (MONAD1-MONAD6). " .
            "Each beacon is placed at a significant location within the faculty. " .
            "Use your phone's Bluetooth to detect each beacon and complete this exciting indoor adventure!\n\n" .
            "Good luck, explorer!"
        );
        $quest->setAvailableFrom(new \DateTime('2025-01-01 00:00:00'));
        $quest->setAvailableTo(new \DateTime('2025-12-31 23:59:59'));
        $quest->setPoints(150.0);
        $quest->setEstimatedDuration(45);
        $quest->setCreatedBy($createdBy);

        // Step 0: Start
        $startStep = new QuestStep();
        $startStep->setName('Begin Your Adventure');
        $startStep->setType(QuestStepType::START);
        $startStep->setOrder(0);
        $startStep->setConfig([
            'description' => "Welcome to the FIIT Treasure Hunt! You're about to embark on an exciting " .
                "journey through the faculty building. Make sure your Bluetooth is enabled and your " .
                "phone is charged. Tap 'Start' when you're ready to begin!"
        ]);
        $quest->addStep($startStep);

        // Step 1: Find MONAD1
        $step1 = new QuestStep();
        $step1->setName('Find MONAD1 - Main Entrance');
        $step1->setType(QuestStepType::FIND_BLE_DEVICE);
        $step1->setOrder(1);
        $step1->setConfig([
            'device_name' => 'MONAD1',
            'device_id' => 'AA:BB:CC:DD:EE:01',
            'description' => "Your first beacon awaits near the main entrance of the FIIT building. " .
                "Look for MONAD1 close to the information desk. This is where every student's " .
                "journey at FIIT begins!"
        ]);
        $quest->addStep($step1);

        // Step 2: Find MONAD2
        $step2 = new QuestStep();
        $step2->setName('Find MONAD2 - Student Lounge');
        $step2->setType(QuestStepType::FIND_BLE_DEVICE);
        $step2->setOrder(2);
        $step2->setConfig([
            'device_name' => 'MONAD2',
            'device_id' => 'AA:BB:CC:DD:EE:02',
            'description' => "Head to the student lounge area on the ground floor. MONAD2 is hidden " .
                "somewhere in this popular hangout spot where students gather between lectures " .
                "to relax and collaborate."
        ]);
        $quest->addStep($step2);

        // Step 3: Find MONAD3
        $step3 = new QuestStep();
        $step3->setName('Find MONAD3 - Library');
        $step3->setType(QuestStepType::FIND_BLE_DEVICE);
        $step3->setOrder(3);
        $step3->setConfig([
            'device_name' => 'MONAD3',
            'device_id' => 'AA:BB:CC:DD:EE:03',
            'description' => "Make your way to the faculty library. MONAD3 is placed among the " .
                "knowledge repositories where countless students have studied and prepared " .
                "for their exams. Keep quiet while searching!"
        ]);
        $quest->addStep($step3);

        // Step 4: Find MONAD4
        $step4 = new QuestStep();
        $step4->setName('Find MONAD4 - Computer Labs');
        $step4->setType(QuestStepType::FIND_BLE_DEVICE);
        $step4->setOrder(4);
        $step4->setConfig([
            'device_name' => 'MONAD4',
            'device_id' => 'AA:BB:CC:DD:EE:04',
            'description' => "Navigate to the computer laboratory area. MONAD4 is hidden in the " .
                "heart of practical learning, where students code, debug, and bring their " .
                "projects to life. Check near the workstations!"
        ]);
        $quest->addStep($step4);

        // Step 5: Find MONAD5
        $step5 = new QuestStep();
        $step5->setName('Find MONAD5 - Lecture Hall');
        $step5->setType(QuestStepType::FIND_BLE_DEVICE);
        $step5->setOrder(5);
        $step5->setConfig([
            'device_name' => 'MONAD5',
            'device_id' => 'AA:BB:CC:DD:EE:05',
            'description' => "Head to the main lecture hall. MONAD5 awaits in the space where " .
                "knowledge is shared daily. This auditorium has witnessed countless " .
                "lectures, presentations, and academic discussions."
        ]);
        $quest->addStep($step5);

        // Step 6: Find MONAD6
        $step6 = new QuestStep();
        $step6->setName('Find MONAD6 - Cafeteria');
        $step6->setType(QuestStepType::FIND_BLE_DEVICE);
        $step6->setOrder(6);
        $step6->setConfig([
            'device_name' => 'MONAD6',
            'device_id' => 'AA:BB:CC:DD:EE:06',
            'description' => "Your final beacon is in the cafeteria! MONAD6 is placed in this " .
                "social hub where students refuel and recharge. Find it to complete your " .
                "treasure hunt adventure!"
        ]);
        $quest->addStep($step6);

        // Step 7: Finish
        $finishStep = new QuestStep();
        $finishStep->setName('Congratulations!');
        $finishStep->setType(QuestStepType::FINISH);
        $finishStep->setOrder(7);
        $finishStep->setConfig([
            'description' => "Amazing work, explorer! You've successfully found all 6 MONAD beacons " .
                "and completed the FIIT Treasure Hunt. You've earned 150 points and discovered " .
                "key locations throughout the faculty. Thank you for participating in this " .
                "indoor positioning experiment!"
        ]);
        $quest->addStep($finishStep);

        return $quest;
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
