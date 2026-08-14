<?php

namespace App\Repository;

use App\Entity\Device;
use App\Entity\Quest;
use App\Entity\QuestEnrollment;
use App\Entity\User;
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

    /**
     * The participant's open (IN_PROGRESS) run of this quest, if any (IP-128).
     *
     * `$device` null means "anywhere" — used for a per-quest recurrence scope, and
     * for the plain "are you mid-quest" check. Ordered newest-first and limited to
     * one because history may legitimately hold several: nothing has ever
     * prevented a replay in this backend.
     */
    public function findOpenFor(User $user, Quest $quest, ?Device $device = null): ?QuestEnrollment
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.quest = :quest')
            ->andWhere('e.status = :status')
            ->setParameter('user', $user)
            ->setParameter('quest', $quest)
            ->setParameter('status', QuestEnrollmentStatus::IN_PROGRESS)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1);

        if (null !== $device) {
            $qb->andWhere('e.device = :device')->setParameter('device', $device);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * The participant's most recent COMPLETED run, for the cooldown clock (IP-128).
     *
     * Ordered by `completionReceivedAt` — the server-stamped column — because
     * `completedAt` arrives in the request body and a backdated value would both
     * clear the gate and reorder this query.
     */
    public function findLastCompletedFor(User $user, Quest $quest, ?Device $device = null): ?QuestEnrollment
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.quest = :quest')
            ->andWhere('e.status = :status')
            ->setParameter('user', $user)
            ->setParameter('quest', $quest)
            ->setParameter('status', QuestEnrollmentStatus::COMPLETED)
            ->orderBy('e.completionReceivedAt', 'DESC')
            ->setMaxResults(1);

        if (null !== $device) {
            $qb->andWhere('e.device = :device')->setParameter('device', $device);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Completed runs per device slug for one user — the passport (IP-128).
     *
     * A stamp is a completed enrollment at a device, derived rather than stored:
     * a mutable counter on `User` could disagree with this history, which is the
     * failure the ground-truth design already refuses.
     *
     * @return array<string, int> slug => completed run count
     */
    public function countCompletedByDevice(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('d.slug AS slug, COUNT(e.id) AS runs')
            ->join('e.device', 'd')
            ->andWhere('e.user = :user')
            ->andWhere('e.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', QuestEnrollmentStatus::COMPLETED)
            ->groupBy('d.slug')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['slug']] = (int) $row['runs'];
        }

        return $out;
    }

    /**
     * How many DISTINCT participants have completed a run at this device (IP-128).
     *
     * Feeds the public "N people have done this here" line, which is why it counts
     * people rather than runs: runs would let one enthusiastic visitor look like a
     * crowd. The caller applies the k-anonymity floor — this returns the raw truth
     * and the presentation layer decides what is safe to say.
     */
    public function countDistinctParticipantsAtDevice(Device $device): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.user)')
            ->andWhere('e.device = :device')
            ->andWhere('e.status = :status')
            ->setParameter('device', $device)
            ->setParameter('status', QuestEnrollmentStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
