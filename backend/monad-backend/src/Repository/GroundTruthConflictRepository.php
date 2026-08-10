<?php

namespace App\Repository;

use App\Entity\GroundTruthConflict;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroundTruthConflict>
 */
class GroundTruthConflictRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroundTruthConflict::class);
    }

    /**
     * @return GroundTruthConflict[]
     */
    public function findForSession(string $labSessionId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.labSessionId = :labSessionId')
            ->setParameter('labSessionId', $labSessionId)
            ->orderBy('c.observedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
