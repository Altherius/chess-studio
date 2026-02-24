<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return User[] */
    public function findPaginated(int $offset, int $limit, string $search = ''): array
    {
        $qb = $this->createSearchQueryBuilder($search);
        $qb->orderBy('u.email', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(string $search = ''): int
    {
        $qb = $this->createSearchQueryBuilder($search);
        $qb->select('COUNT(u.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function createSearchQueryBuilder(string $search): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        if ($search !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :search OR LOWER(u.firstName) LIKE :search OR LOWER(u.lastName) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb;
    }
}
