<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile ElasticSuite to newer
 * versions in the future.
 *
 * @package   Elasticsuite
 * @author    ElasticSuite Team <elasticsuite@smile.fr>
 * @copyright 2022 Smile
 * @license   Licensed to Smile-SA. All rights reserved. No warranty, explicit or implicit, provided.
 *            Unauthorized copying of this file, via any medium, is strictly prohibited.
 */

declare(strict_types=1);

namespace Elasticsuite\Search\Tests\Unit\Elasticsearch\Builder;

use Elasticsuite\Index\Api\IndexSettingsInterface;
use Elasticsuite\Index\Service\MetadataManager;
use Elasticsuite\Metadata\Model\Metadata;
use Elasticsuite\Metadata\Repository\MetadataRepository;
use Elasticsuite\Search\Elasticsearch\Builder\Request\Query\Filter\FilterQueryBuilder;
use Elasticsuite\Search\Elasticsearch\Builder\Request\Query\QueryBuilder;
use Elasticsuite\Search\Elasticsearch\Builder\Request\SimpleRequestBuilder;
use Elasticsuite\Search\Elasticsearch\Builder\Request\SortOrder\SortOrderBuilder;
use Elasticsuite\Search\Elasticsearch\Request\Container\Configuration\GenericContainerConfigurationFactory;
use Elasticsuite\Search\Elasticsearch\Request\Query\Exists;
use Elasticsuite\Search\Elasticsearch\Request\Query\Filtered;
use Elasticsuite\Search\Elasticsearch\Request\QueryFactory;
use Elasticsuite\Search\Elasticsearch\Request\QueryInterface;
use Elasticsuite\Search\Elasticsearch\RequestFactoryInterface;
use Elasticsuite\Standard\src\Test\AbstractTest;

class SimpleRequestBuilderTest extends AbstractTest
{
    private static RequestFactoryInterface $requestFactory;

    private static QueryFactory $queryFactory;

    private static QueryBuilder $queryBuilder;

    private static FilterQueryBuilder $filterQueryBuilder;

    private static SortOrderBuilder $sortOrderBuilder;

    private static GenericContainerConfigurationFactory $containerConfigFactory;

    private static IndexSettingsInterface $indexSettings;

    private static MetadataManager $metadataManager;

    private static MetadataRepository $metadataRepository;

    private static SimpleRequestBuilder $requestBuilder;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        \assert(static::getContainer()->get(RequestFactoryInterface::class) instanceof RequestFactoryInterface);
        self::$requestFactory = static::getContainer()->get(RequestFactoryInterface::class);
        \assert(static::getContainer()->get(QueryFactory::class) instanceof QueryFactory);
        self::$queryFactory = static::getContainer()->get(QueryFactory::class);
        self::$queryBuilder = new QueryBuilder(self::$queryFactory);
        self::$filterQueryBuilder = new FilterQueryBuilder(self::$queryFactory);
        self::$sortOrderBuilder = new SortOrderBuilder(self::$filterQueryBuilder);
        self::$containerConfigFactory = new GenericContainerConfigurationFactory();
        \assert(static::getContainer()->get(IndexSettingsInterface::class) instanceof IndexSettingsInterface);
        self::$indexSettings = static::getContainer()->get(IndexSettingsInterface::class);
        self::$metadataManager = static::getContainer()->get(MetadataManager::class);
        self::$metadataRepository = static::getContainer()->get(MetadataRepository::class);
        self::$requestBuilder = new SimpleRequestBuilder(
            self::$requestFactory,
            self::$queryBuilder,
            self::$sortOrderBuilder,
            self::$containerConfigFactory,
            self::$indexSettings,
            self::$metadataManager
        );

        static::loadFixture([
            __DIR__ . '/../../../fixtures/catalogs.yaml',
            __DIR__ . '/../../../fixtures/metadata.yaml',
            __DIR__ . '/../../../fixtures/source_field.yaml',
        ]);
    }

    public function testInstantiate(): void
    {
        $reflector = new \ReflectionClass(SimpleRequestBuilder::class);
        $queryBuilderProperty = $reflector->getProperty('queryBuilder');
        $sortOrderBuilderProperty = $reflector->getProperty('sortOrderBuilder');
        $requestFactoryProperty = $reflector->getProperty('requestFactory');
        $containerConfigFactoryProperty = $reflector->getProperty('containerConfigFactory');
        $indexSettings = $reflector->getProperty('indexSettings');
        $metadataManager = $reflector->getProperty('metadataManager');

        $simpleBuilder = new SimpleRequestBuilder(
            self::$requestFactory,
            self::$queryBuilder,
            self::$sortOrderBuilder,
            self::$containerConfigFactory,
            self::$indexSettings,
            self::$metadataManager
        );
        $this->assertEquals($requestFactoryProperty->getValue($simpleBuilder), self::$requestFactory);
        $this->assertEquals($queryBuilderProperty->getValue($simpleBuilder), self::$queryBuilder);
        $this->assertEquals($sortOrderBuilderProperty->getValue($simpleBuilder), self::$sortOrderBuilder);
        $this->assertEquals($containerConfigFactoryProperty->getValue($simpleBuilder), self::$containerConfigFactory);
        $this->assertEquals($indexSettings->getValue($simpleBuilder), self::$indexSettings);
        $this->assertEquals($metadataManager->getValue($simpleBuilder), self::$metadataManager);
    }

    /**
     * @dataProvider createRequestDataProvider
     *
     * @param string $entityType        Entity type
     * @param int    $catalogId         Catalog ID
     * @param string $expectedIndexName Expected index name
     */
    public function testCreateNullQuery(string $entityType, int $catalogId, string $expectedIndexName): void
    {
        $metadata = self::$metadataRepository->findOneBy(['entity' => $entityType]);
        $this->assertNotNull($metadata);
        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertNotNull($metadata->getEntity());

        $request = self::$requestBuilder->create(
            $metadata,
            $catalogId,
            0,
            5
        );

        $this->assertEquals('raw', $request->getName());
        $this->assertEquals($expectedIndexName, $request->getIndex());
        $this->assertEquals(0, $request->getFrom());
        $this->assertEquals(5, $request->getSize());

        $query = $request->getQuery();
        $this->assertInstanceOf(QueryInterface::class, $query);
        $this->assertInstanceOf(Filtered::class, $query);
        /** @var Filtered $query */
        $this->assertEquals(QueryInterface::TYPE_FILTER, $query->getType());
        $this->assertNull($query->getName());
        $this->assertNull($query->getQuery());
        $this->assertNull($query->getFilter());
        $this->assertEquals(QueryInterface::DEFAULT_BOOST_VALUE, $query->getBoost());
    }

    /**
     * @dataProvider createRequestDataProvider
     *
     * @param string $entityType        Entity type
     * @param int    $catalogId         Catalog ID
     * @param string $expectedIndexName Expected index name
     */
    public function testCreateObjectQuery(string $entityType, int $catalogId, string $expectedIndexName): void
    {
        $metadata = self::$metadataRepository->findOneBy(['entity' => $entityType]);
        $this->assertNotNull($metadata);
        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertNotNull($metadata->getEntity());

        $request = self::$requestBuilder->create(
            $metadata,
            $catalogId,
            0,
            5,
            self::$queryFactory->create(QueryInterface::TYPE_EXISTS, ['my_field'])
        );

        $this->assertEquals('raw', $request->getName());
        $this->assertEquals($expectedIndexName, $request->getIndex());
        $this->assertEquals(0, $request->getFrom());
        $this->assertEquals(5, $request->getSize());

        $query = $request->getQuery();
        $this->assertInstanceOf(QueryInterface::class, $query);
        $this->assertInstanceOf(Filtered::class, $query);
        /** @var Filtered $query */
        $this->assertEquals(QueryInterface::TYPE_FILTER, $query->getType());
        $this->assertNull($query->getName());
        $this->assertInstanceOf(QueryInterface::class, $query->getQuery());
        $this->assertInstanceOf(Exists::class, $query->getQuery());
        $this->assertNull($query->getFilter());
        $this->assertEquals(QueryInterface::DEFAULT_BOOST_VALUE, $query->getBoost());
    }

    // TODO: implement fulltext queries first.
    /*
    public function testCreateStringQuery(): void
    {
        $request = self::$requestBuilder->create(
            'my_index',
            0,
            5,
            'my query'
        );

        $this->assertEquals('raw', $request->getName());
        $this->assertEquals('my_index', $request->getIndex());
        $this->assertEquals(0, $request->getFrom());
        $this->assertEquals(5, $request->getSize());
    }
    */

    protected function createRequestDataProvider(): array
    {
        return [
            ['product', 1, 'elasticsuite_test__elasticsuite_b2c_fr_product'],
            ['product', 2, 'elasticsuite_test__elasticsuite_b2c_en_product'],
            ['product', 3, 'elasticsuite_test__elasticsuite_b2b_en_product'],
            ['product', 4, 'elasticsuite_test__elasticsuite_b2b_fr_product'],
            ['category', 1, 'elasticsuite_test__elasticsuite_b2c_fr_category'],
            ['category', 2, 'elasticsuite_test__elasticsuite_b2c_en_category'],
            ['category', 3, 'elasticsuite_test__elasticsuite_b2b_en_category'],
            ['category', 4, 'elasticsuite_test__elasticsuite_b2b_fr_category'],
        ];
    }
}