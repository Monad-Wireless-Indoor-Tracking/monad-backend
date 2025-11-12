<?php

namespace App\Repository;

use App\Entity\QuestEnrollment;
use App\Enum\QuestEnrollmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestEnrollment>
 */
class QuestEnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestEnrollment::class);
    }

    /**
     * Find enrollment by user and quest
     *
     * @param string $userId
     * @param string $questId
     * @return QuestEnrollment|null
     */
    public function findByUserAndQuest(string $userId, string $questId): ?QuestEnrollment
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :userId')
            ->andWhere('e.quest = :questId')
            ->setParameter('userId', $userId)
            ->setParameter('questId', $questId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all enrollments for a user
     *
     * @param string $userId
     * @return QuestEnrollment[]
     */
    public function findByUser(string $userId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all enrollments for a quest
     *
     * @param string $questId
     * @return QuestEnrollment[]
     */
    public function findByQuest(string $questId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.quest = :questId')
            ->setParameter('questId', $questId)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find enrollments by status
     *
     * @param QuestEnrollmentStatus $status
     * @return QuestEnrollment[]
     */
    public function findByStatus(QuestEnrollmentStatus $status): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', $status)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active (in-progress) enrollments for a user
     *
     * @param string $userId
     * @return QuestEnrollment[]
     */
    public function findActiveByUser(string $userId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :userId')
            ->andWhere('e.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', QuestEnrollmentStatus::IN_PROGRESS)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed enrollments for a user
     *
     * @param string $userId
     * @return QuestEnrollment[]
     */
    public function findCompletedByUser(string $userId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :userId')
            ->andWhere('e.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', QuestEnrollmentStatus::COMPLETED)
            ->orderBy('e.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
