<?php

namespace App\Repository;

use App\Entity\Quest;
use App\Entity\QuestStep;
use App\Enum\QuestStepType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestStep>
 */
class QuestStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestStep::class);
    }

    /**
     * Find all steps for a specific quest, ordered by step order
     *
     * @param Quest $quest
     * @return QuestStep[]
     */
    public function findByQuestOrdered(Quest $quest): array
    {
        return $this->createQueryBuilder('qs')
            ->andWhere('qs.quest = :quest')
            ->setParameter('quest', $quest)
            ->orderBy('qs.order', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find steps by type
     *
     * @param QuestStepType $type
     * @return QuestStep[]
     */
    public function findByType(QuestStepType $type): array
    {
        return $this->createQueryBuilder('qs')
            ->andWhere('qs.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the next step in a quest after a given order
     *
     * @param Quest $quest
     * @param int $currentOrder
     * @return QuestStep|null
     */
    public function findNextStep(Quest $quest, int $currentOrder): ?QuestStep
    {
        return $this->createQueryBuilder('qs')
            ->andWhere('qs.quest = :quest')
            ->andWhere('qs.order > :currentOrder')
            ->setParameter('quest', $quest)
            ->setParameter('currentOrder', $currentOrder)
            ->orderBy('qs.order', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the first step of a quest
     *
     * @param Quest $quest
     * @return QuestStep|null
     */
    public function findFirstStep(Quest $quest): ?QuestStep
    {
        return $this->createQueryBuilder('qs')
            ->andWhere('qs.quest = :quest')
            ->orderBy('qs.order', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
