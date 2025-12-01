<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const SUPERADMIN_REFERENCE = 'superadmin-user';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $superadmin = new User();
        $superadmin->setEmail('admin@fiit.stuba.sk');
        $superadmin->setName('FIIT Admin');
        $superadmin->setPassword(
            $this->passwordHasher->hashPassword($superadmin, 'admin123')
        );
        $superadmin->addRole(UserRole::SUPERADMIN);

        $manager->persist($superadmin);
        $manager->flush();

        $this->addReference(self::SUPERADMIN_REFERENCE, $superadmin);
    }
}
