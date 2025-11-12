<?php

namespace App\Repository;

use App\Entity\QuestStepSkipRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestStepSkipRecord>
 */
class QuestStepSkipRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestStepSkipRecord::class);
    }

    /**
     * Find all skip records for a step completion
     *
     * @param string $stepCompletionId
     * @return QuestStepSkipRecord[]
     */
    public function findByStepCompletion(string $stepCompletionId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.stepCompletion = :stepCompletionId')
            ->setParameter('stepCompletionId', $stepCompletionId)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find skip records by error code
     *
     * @param string $errorCode
     * @return QuestStepSkipRecord[]
     */
    public function findByErrorCode(string $errorCode): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.errorCode = :errorCode')
            ->setParameter('errorCode', $errorCode)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the most recent skip record for a step completion
     *
     * @param string $stepCompletionId
     * @return QuestStepSkipRecord|null
     */
    public function findLatestByStepCompletion(string $stepCompletionId): ?QuestStepSkipRecord
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.stepCompletion = :stepCompletionId')
            ->setParameter('stepCompletionId', $stepCompletionId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all skip records created within a date range
     *
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @return QuestStepSkipRecord[]
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
