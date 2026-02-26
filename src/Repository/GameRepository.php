<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Game> */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /** @return Game[] */
    public function findFiltered(array $criteria, int $offset, int $limit): array
    {
        $qb = $this->createFilteredQueryBuilder($criteria);
        $qb->orderBy('g.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(array $criteria): int
    {
        $qb = $this->createFilteredQueryBuilder($criteria);
        $qb->select('COUNT(g.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function createFilteredQueryBuilder(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g');

        if (isset($criteria['owner'])) {
            $qb->andWhere('g.owner = :owner')->setParameter('owner', $criteria['owner']);
        }

        if (isset($criteria['isPublic'])) {
            $qb->andWhere('g.isPublic = :isPublic')->setParameter('isPublic', $criteria['isPublic']);
        }

        if (isset($criteria['minElo'])) {
            $qb->andWhere('g.whiteElo >= :minElo AND g.blackElo >= :minElo')
                ->setParameter('minElo', (int) $criteria['minElo']);
        }

        if (isset($criteria['maxElo'])) {
            $qb->andWhere('g.whiteElo <= :maxElo AND g.blackElo <= :maxElo')
                ->setParameter('maxElo', (int) $criteria['maxElo']);
        }

        if (isset($criteria['player']) && $criteria['player'] !== '') {
            $qb->andWhere('LOWER(g.playerWhite) LIKE LOWER(:player) OR LOWER(g.playerBlack) LIKE LOWER(:player)')
                ->setParameter('player', '%' . $criteria['player'] . '%');
        }

        if (isset($criteria['event']) && $criteria['event'] !== '') {
            $qb->andWhere('g.event LIKE :event')
                ->setParameter('event', '%' . $criteria['event'] . '%');
        }

        if (isset($criteria['opening']) && $criteria['opening'] !== '') {
            $qb->andWhere('LOWER(g.openingName) LIKE LOWER(:opening)')
                ->setParameter('opening', '%' . $criteria['opening'] . '%');
        }

        if (isset($criteria['minDate']) && $criteria['minDate'] !== '') {
            $qb->andWhere('g.date >= :minDate')
                ->setParameter('minDate', new \DateTime($criteria['minDate']));
        }

        if (isset($criteria['maxDate']) && $criteria['maxDate'] !== '') {
            $qb->andWhere('g.date <= :maxDate')
                ->setParameter('maxDate', new \DateTime($criteria['maxDate']));
        }

        return $qb;
    }
}
