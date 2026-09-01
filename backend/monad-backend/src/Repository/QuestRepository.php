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
     * @param bool $includeOperator Include `audience = operator` quests (IP-145).
     *   FALSE by default so a caller that has not thought about it cannot leak one.
     * @return Quest[]
     */
    public function findAvailableAt(\DateTimeInterface $date, bool $includeOperator = false): array
    {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.availableFrom <= :date')
            ->andWhere('q.availableTo IS NULL OR q.availableTo >= :date')
            ->setParameter('date', $date)
            ->orderBy('q.availableFrom', 'DESC');

        // Filtered here rather than refused at start with a reason. A reason would
        // tell a participant that a withheld quest exists, which is worse than not
        // showing it; filtering leaks nothing. The start endpoint checks separately,
        // because a filtered list is a convenience and never the authorisation.
        //
        // Default-deny: the parameter defaults to false so a future caller that
        // forgets it under-shows rather than over-shows.
        if (!$includeOperator) {
            $qb->andWhere('q.audience = :publicAudience')
               ->setParameter('publicAudience', Quest::AUDIENCE_PUBLIC);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find currently available quests
     *
     * @param bool $includeOperator See {@see findAvailableAt()}.
     * @return Quest[]
     */
    public function findCurrentlyAvailable(bool $includeOperator = false): array
    {
        return $this->findAvailableAt(new \DateTime(), $includeOperator);
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
