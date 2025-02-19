<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Gally to newer versions in the future.
 *
 * @package   Gally
 * @author    Gally Team <elasticsuite@smile.fr>
 * @copyright 2022-present Smile
 * @license   Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Gally\Metadata\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gally\Metadata\Model\Metadata;
use Gally\Metadata\Model\SourceField;
use Gally\Metadata\Model\SourceField\Type;

/**
 * @method SourceField|null find($id, $lockMode = null, $lockVersion = null)
 * @method SourceField|null findOneBy(array $criteria, array $orderBy = null)
 * @method SourceField[]    findAll()
 * @method SourceField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SourceFieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private MetadataRepository $metadataRepository)
    {
        parent::__construct($registry, SourceField::class);
    }

    /**
     * @return SourceField[]
     */
    public function getSortableFields(string $entityCode, array $attributeToExclude = []): array
    {
        $queryBuilder = $this->createQueryBuilder('o')
            ->where('o.metadata = :metadata')
            ->andWhere('o.isSortable = true')
            ->setParameter('metadata', $this->metadataRepository->findOneBy(['entity' => $entityCode]));

        if (!empty($attributeToExclude)) {
            $queryBuilder
                ->andWhere('o.code not in (:excluded_attribute)')
                ->setParameter('excluded_attribute', $attributeToExclude);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return SourceField[]
     */
    public function getFilterableInRequestFields(string $entityCode): array
    {
        $exprBuilder = $this->getEntityManager()->getExpressionBuilder();

        $query = $this->createQueryBuilder('o')
            ->where('o.metadata = :metadata')
            ->andWhere(
                $exprBuilder->orX(
                    $exprBuilder->eq('o.isFilterable', 'true'),
                    $exprBuilder->eq('o.isUsedForRules', 'true'),
                )
            )
            ->setParameter('metadata', $this->metadataRepository->findOneBy(['entity' => $entityCode]))
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @return SourceField[]
     */
    public function findByCodePrefix(string $codePrefix, Metadata $metadata): array
    {
        $exprBuilder = $this->getEntityManager()->getExpressionBuilder();
        $query = $this->createQueryBuilder('s')
            ->where('s.metadata = :metadata')
            ->andWhere($exprBuilder->like('s.code', $exprBuilder->concat("'$codePrefix'", "'%'")))
            ->setParameter('metadata', $metadata)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @return SourceField[]
     */
    public function getFilterableInAggregationFields(string $entityCode): array
    {
        return $this->findBy(
            [
                'metadata' => $this->metadataRepository->findBy(['entity' => $entityCode]),
                'isFilterable' => true,
            ]
        );
    }

    /**
     * @return SourceField[]
     */
    public function getComplexeFields(Metadata $metadata): array
    {
        $exprBuilder = $this->getEntityManager()->getExpressionBuilder();
        $query = $this->createQueryBuilder('s')
            ->where('s.metadata = :metadata')
            ->andWhere(
                $exprBuilder->orX(
                    $exprBuilder->like('s.code', "'%.%'"),
                    $exprBuilder->in(
                        's.type',
                        [Type::TYPE_SELECT, Type::TYPE_PRICE, Type::TYPE_STOCK, Type::TYPE_CATEGORY]
                    )
                )
            )
            ->setParameter('metadata', $metadata)
            ->getQuery();

        return $query->getResult();
    }
}
