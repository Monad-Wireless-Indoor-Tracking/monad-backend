<?php

namespace App\Repository;

use App\Entity\Quest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quest>
 */
class QuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quest::class);
    }

    /**
     * Find quests available at a specific date
     *
     * @param \DateTimeInterface $date
     * @return Quest[]
     */
    public function findAvailableAt(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.availableFrom <= :date')
            ->andWhere('q.availableTo IS NULL OR q.availableTo >= :date')
            ->setParameter('date', $date)
            ->orderBy('q.availableFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find currently available quests
     *
     * @return Quest[]
     */
    public function findCurrentlyAvailable(): array
    {
        return $this->findAvailableAt(new \DateTime());
    }

    /**
     * Find quests created by a specific user
     *
     * @param string $userId
     * @return Quest[]
     */
    public function findByCreator(string $userId): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active quests (available_from < now < available_to)
     * Ordered by created_at
     *
     * @return Quest[]
     */
    public function findActiveQuests(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('q')
            ->andWhere('q.availableFrom < :now')
            ->andWhere('q.availableTo IS NULL OR q.availableTo > :now')
            ->setParameter('now', $now)
            ->orderBy('q.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired quests (available_to < now)
     * Ordered by created_at
     *
     * @return Quest[]
     */
    public function findExpiredQuests(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('q')
            ->andWhere('q.availableTo IS NOT NULL')
            ->andWhere('q.availableTo < :now')
            ->setParameter('now', $now)
            ->orderBy('q.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
