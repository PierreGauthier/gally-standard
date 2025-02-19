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

namespace Gally\Search\Tests\Api\GraphQl;

use Gally\Entity\Service\PriceGroupProvider;
use Gally\Fixture\Service\ElasticsearchFixturesInterface;
use Gally\Search\Elasticsearch\Request\SortOrderInterface;
use Gally\Test\AbstractTest;
use Gally\Test\ExpectedResponse;
use Gally\Test\RequestGraphQlToTest;
use Gally\User\Constant\Role;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SearchDocumentsTest extends AbstractTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::loadFixture([
            __DIR__ . '/../../fixtures/facet_configuration_search_documents.yaml',
            __DIR__ . '/../../fixtures/facet_configuration.yaml',
            __DIR__ . '/../../fixtures/source_field_option_label.yaml',
            __DIR__ . '/../../fixtures/source_field_option.yaml',
            __DIR__ . '/../../fixtures/source_field_label.yaml',
            __DIR__ . '/../../fixtures/source_field_search_documents.yaml',
            __DIR__ . '/../../fixtures/source_field.yaml',
            __DIR__ . '/../../fixtures/category_configurations.yaml',
            __DIR__ . '/../../fixtures/categories.yaml',
            __DIR__ . '/../../fixtures/catalogs.yaml',
            __DIR__ . '/../../fixtures/metadata.yaml',
        ]);
        self::createEntityElasticsearchIndices('product');
        self::createEntityElasticsearchIndices('category');
        self::loadElasticsearchDocumentFixtures([__DIR__ . '/../../fixtures/documents.json']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::deleteEntityElasticsearchIndices('product');
        self::deleteEntityElasticsearchIndices('category');
    }

    /**
     * @dataProvider basicSearchDataProvider
     *
     * @param string  $entityType           Entity Type
     * @param string  $catalogId            Catalog ID or code
     * @param ?int    $pageSize             Pagination size
     * @param ?int    $currentPage          Current page
     * @param ?array  $expectedError        Expected error
     * @param ?int    $expectedItemsCount   Expected items count in (paged) response
     * @param ?int    $expectedTotalCount   Expected total items count
     * @param ?int    $expectedItemsPerPage Expected pagination items per page
     * @param ?int    $expectedLastPage     Expected number of the last page
     * @param ?string $expectedIndexAlias   Expected index alias
     * @param ?float  $expectedScore        Expected documents score
     */
    public function testBasicSearchDocuments(
        string $entityType,
        string $catalogId,
        ?int $pageSize,
        ?int $currentPage,
        ?array $expectedError,
        ?int $expectedItemsCount,
        ?int $expectedTotalCount,
        ?int $expectedItemsPerPage,
        ?int $expectedLastPage,
        ?string $expectedIndexAlias,
        ?float $expectedScore
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s"',
            $entityType,
            $catalogId
        );
        if (null !== $pageSize) {
            $arguments .= sprintf(', pageSize: %d', $pageSize);
        }
        if (null !== $currentPage) {
            $arguments .= sprintf(', currentPage: %d', $currentPage);
        }

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                              score
                              index
                            }
                            paginationInfo {
                              itemsPerPage
                              lastPage
                              totalCount
                            }
                        }
                    }
                GQL,
                $user
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use (
                        $expectedError,
                        $expectedItemsCount,
                        $expectedTotalCount,
                        $expectedItemsPerPage,
                        $expectedLastPage,
                        $expectedIndexAlias,
                        $expectedScore
                    ) {
                    if (!empty($expectedError)) {
                        $this->assertJsonContains($expectedError);
                        $this->assertJsonContains([
                            'data' => [
                                'documents' => null,
                            ],
                        ]);
                    } else {
                        $this->assertJsonContains([
                            'data' => [
                                'documents' => [
                                    'paginationInfo' => [
                                        'itemsPerPage' => $expectedItemsPerPage,
                                        'lastPage' => $expectedLastPage,
                                        'totalCount' => $expectedTotalCount,
                                    ],
                                    'collection' => [],
                                ],
                            ],
                        ]);

                        $responseData = $response->toArray();
                        $this->assertIsArray($responseData['data']['documents']['collection']);
                        $this->assertCount($expectedItemsCount, $responseData['data']['documents']['collection']);
                        foreach ($responseData['data']['documents']['collection'] as $document) {
                            $this->assertArrayHasKey('score', $document);
                            $this->assertEquals($expectedScore, $document['score']);

                            $this->assertArrayHasKey('index', $document);
                            $this->assertStringStartsWith($expectedIndexAlias, $document['index']);
                        }
                    }
                }
            )
        );
    }

    public function basicSearchDataProvider(): array
    {
        return [
            [
                'people',   // entity type.
                'b2c_fr',   // catalog ID.
                null,   // page size.
                null,   // current page.
                ['errors' => [['message' => 'Internal server error', 'debugMessage' => 'Entity type [people] does not exist']]], // expected error.
                null,   // expected items count.
                null,   // expected total count.
                null,   // expected items per page.
                null,   // expected last page.
                null,   // expected index alias.
                null,   // expected score.
            ],
            [
                'category', // entity type.
                'b2c_uk',   // catalog ID.
                null,   // page size.
                null,   // current page.
                ['errors' => [['message' => 'Internal server error', 'debugMessage' => 'Missing localized catalog [b2c_uk]']]], // expected error.
                null,   // expected items count.
                null,   // expected total count.
                null,   // expected items per page.
                null,   // expected last page.
                null,   // expected index alias.
                null,   // expected score.
            ],
            [
                'category', // entity type.
                'b2c_fr',   // catalog ID.
                null,   // page size.
                null,   // current page.
                [],     // expected error.
                0,      // expected items count.
                0,      // expected total count.
                30,     // expected items per page.
                1,      // expected last page.
                null,   // expected index alias.
                1.0,    // expected score.
            ],
            [
                'product',  // entity type.
                '2',    // catalog ID.
                10,     // page size.
                1,      // current page.
                [],     // expected error.
                10,     // expected items count.
                14,     // expected total count.
                10,     // expected items per page.
                2,      // expected last page.
                ElasticsearchFixturesInterface::PREFIX_TEST_INDEX . 'gally_b2c_en_product', // expected index alias.
                1.0,    // expected score.
            ],
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [],     // expected error.
                10,     // expected items count.
                14,     // expected total count.
                10,     // expected items per page.
                2,      // expected last page.
                ElasticsearchFixturesInterface::PREFIX_TEST_INDEX . 'gally_b2c_en_product', // expected index alias.
                1.0,    // expected score.
            ],
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                2,      // current page.
                [],     // expected error.
                4,      // expected items count.
                14,     // expected total count.
                10,     // expected items per page.
                2,      // expected last page.
                ElasticsearchFixturesInterface::PREFIX_TEST_INDEX . 'gally_b2c_en_product', // expected index alias.
                1.0,    // expected score.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                null,   // page size.
                null,   // current page.
                [],     // expected error.
                12,     // expected items count.
                12,     // expected total count.
                30,     // expected items per page.
                1,      // expected last page.
                ElasticsearchFixturesInterface::PREFIX_TEST_INDEX . 'gally_b2b_fr_product', // expected index alias.
                1.0,    // expected score.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,      // page size.
                2,      // current page.
                [],     // expected error.
                5,      // expected items count.
                12,     // expected total count.
                5,      // expected items per page.
                3,      // expected last page.
                ElasticsearchFixturesInterface::PREFIX_TEST_INDEX . 'gally_b2b_fr_product', // expected index alias.
                1.0,    // expected score.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                1000,   // page size.
                null,   // current page.
                [],     // expected error.
                12,     // expected items count.
                12,     // expected total count.
                100,    // expected items per page.
                1,      // expected last page.
                ElasticsearchFixturesInterface::PREFIX_TEST_INDEX . 'gally_b2b_fr_product', // expected index alias.
                1.0,    // expected score.
            ],
        ];
    }

    /**
     * @dataProvider sortedSearchDocumentsProvider
     *
     * @param string $entityType            Entity Type
     * @param string $catalogId             Catalog ID or code
     * @param int    $pageSize              Pagination size
     * @param int    $currentPage           Current page
     * @param array  $sortOrders            Sort order specifications
     * @param string $documentIdentifier    Document identifier to check ordered results
     * @param array  $expectedOrderedDocIds Expected ordered document identifiers
     * @param string $priceGroupId          Price group id
     */
    public function testSortedSearchDocuments(
        string $entityType,
        string $catalogId,
        int $pageSize,
        int $currentPage,
        array $sortOrders,
        string $documentIdentifier,
        array $expectedOrderedDocIds,
        string $priceGroupId = '0',
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d',
            $entityType,
            $catalogId,
            $pageSize,
            $currentPage
        );

        $this->addSortOrders($sortOrders, $arguments);

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                              score
                              source
                            }
                            paginationInfo {
                              itemsPerPage
                            }
                        }
                    }
                GQL,
                $user,
                [PriceGroupProvider::PRICE_GROUP_ID => $priceGroupId]
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use (
                    $pageSize,
                    $documentIdentifier,
                    $expectedOrderedDocIds
                ) {
                    $this->assertJsonContains([
                        'data' => [
                            'documents' => [
                                'paginationInfo' => [
                                    'itemsPerPage' => $pageSize,
                                ],
                                'collection' => [],
                            ],
                        ],
                    ]);

                    $responseData = $response->toArray();
                    $this->assertIsArray($responseData['data']['documents']['collection']);
                    $this->assertCount(\count($expectedOrderedDocIds), $responseData['data']['documents']['collection']);
                    foreach ($responseData['data']['documents']['collection'] as $index => $document) {
                        /*
                        $this->assertArrayHasKey('score', $document);
                        $this->assertEquals($expectedScore, $document['score']);
                        */
                        $this->assertArrayHasKey('id', $document);
                        $this->assertEquals("/documents/{$expectedOrderedDocIds[$index]}", $document['id']);

                        $this->assertArrayHasKey('source', $document);
                        if (\array_key_exists($documentIdentifier, $document['source'])) {
                            $this->assertEquals($expectedOrderedDocIds[$index], $document['source'][$documentIdentifier]);
                        }
                    }
                }
            )
        );
    }

    public function sortedSearchDocumentsProvider(): array
    {
        return [
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [],     // sort order specifications.
                'entity_id', // document data identifier.
                // score DESC first, then id DESC but field 'id' is not present, so missing _first
                // which means the document will be sorted as they were imported.
                // the document.id matched here is the document._id which is entity_id (see fixtures import)
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],    // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['_score' => SortOrderInterface::SORT_DESC], // sort order specifications.
                'entity_id', // document data identifier.
                // Explicite _score sort definition
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],    // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [],     // sort order specifications.
                'id', // document data identifier.
                // score DESC first, then id DESC which exists in 'b2b_fr'
                // but id DESC w/missing _first, so doc w/entity_id="1" is first
                // as id are string, "2" is considered greater than "12"
                [1, 9, 8, 7, 6, 5, 4, 3, 2, 12],    // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['_score' => SortOrderInterface::SORT_DESC], // sort order specifications.
                'id', // document data identifier.
                // score DESC first, then id DESC which exists in 'b2b_fr'
                // but id DESC w/missing _first, so doc w/entity_id="1" is first
                // as id are string, "2" is considered greater than "12"
                [1, 9, 8, 7, 6, 5, 4, 3, 2, 12],    // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'id', // document data identifier.
                // id ASC (missing _last), then score DESC (but not for first doc w/ entity_id="1")
                [10, 11, 12, 2, 3, 4, 5, 6, 7, 8],    // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['size' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'id', // document data identifier.
                // size ASC, then score DESC first, then id DESC (missing _first)
                [5, 8, 2, 11, 4, 3, 6, 9, 7, 1],   // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['size' => SortOrderInterface::SORT_DESC], // sort order specifications.
                'id', // document data identifier.
                // size DESC, then score ASC first, then id ASC (missing _last)
                [10, 12, 1, 7, 9, 6, 3, 4, 11, 2],   // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,     // page size.
                1,      // current page.
                ['price.price' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'id', // document data identifier.
                // price.price ASC, then score DESC first, then id DESC (missing _first)
                [2, 1, 3, 9, 8],   // expected ordered document IDs
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,     // page size.
                1,      // current page.
                ['my_price.price' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'id', // document data identifier.
                // price.price ASC, then score DESC first, then id DESC (missing _first)
                [2, 1, 3, 9, 8],   // expected ordered document IDs
                '0',
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,     // page size.
                1,      // current page.
                ['my_price.price' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'id', // document data identifier.
                // price.price ASC, then score DESC first, then id DESC (missing _first)
                [1, 2, 3, 9, 8],   // expected ordered document IDs
                '1',
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,     // page size.
                1,      // current page.
                ['my_price.price' => SortOrderInterface::SORT_DESC], // sort order specifications.
                'id', // document data identifier.
                // price.price ASC, then score DESC first, then id DESC (missing _first)
                [10, 11, 12, 2, 3],   // expected ordered document IDs
                'fake_price_group_id',
            ],
        ];
    }

    /**
     * @dataProvider sortInfoSearchDocumentsProvider
     *
     * @param string $entityType                 Entity Type
     * @param string $catalogId                  Catalog ID or code
     * @param int    $pageSize                   Pagination size
     * @param int    $currentPage                Current page
     * @param array  $sortOrders                 Sort order specifications
     * @param string $expectedSortOrderField     Expected sort order field
     * @param string $expectedSortOrderDirection Expected sort order direction
     */
    public function testSortInfoSearchDocuments(
        string $entityType,
        string $catalogId,
        int $pageSize,
        int $currentPage,
        array $sortOrders,
        string $expectedSortOrderField,
        string $expectedSortOrderDirection
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d',
            $entityType,
            $catalogId,
            $pageSize,
            $currentPage
        );

        $this->addSortOrders($sortOrders, $arguments);

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                              score
                              source
                            }
                            paginationInfo {
                              itemsPerPage
                            }
                            sortInfo {
                              current {
                                field
                                direction
                              }
                            }
                        }
                    }
                GQL,
                $user
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use (
                    $pageSize,
                    $expectedSortOrderField,
                    $expectedSortOrderDirection
                ) {
                    $this->assertJsonContains([
                        'data' => [
                            'documents' => [
                                'paginationInfo' => [
                                    'itemsPerPage' => $pageSize,
                                ],
                                'sortInfo' => [
                                    'current' => [
                                        [
                                            'field' => $expectedSortOrderField,
                                            'direction' => $expectedSortOrderDirection,
                                        ],
                                    ],
                                ],
                                'collection' => [],
                            ],
                        ],
                    ]);
                }
            )
        );
    }

    public function sortInfoSearchDocumentsProvider(): array
    {
        return [
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [],     // sort order specifications.
                '_score', // expected sort order field.
                SortOrderInterface::SORT_DESC, // expected sort order direction.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [],     // sort order specifications.
                '_score', // expected sort order field.
                SortOrderInterface::SORT_DESC, // expected sort order direction.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'id', // expected sort order field.
                SortOrderInterface::SORT_ASC, // expected sort order direction.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['size' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'size', // expected sort order field.
                SortOrderInterface::SORT_ASC, // expected sort order direction.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                ['size' => SortOrderInterface::SORT_DESC], // sort order specifications.
                'size', // expected sort order field.
                SortOrderInterface::SORT_DESC, // expected sort order direction.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,     // page size.
                1,      // current page.
                ['price.price' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'price__price', // expected sort order field.
                SortOrderInterface::SORT_ASC, // expected sort order direction.
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                5,     // page size.
                1,      // current page.
                ['price__price' => SortOrderInterface::SORT_ASC], // sort order specifications.
                'price__price', // expected sort order field.
                SortOrderInterface::SORT_ASC, // expected sort order direction.
            ],
        ];
    }

    /**
     * @dataProvider searchWithAggregationDataProvider
     *
     * @param string $entityType           Entity Type
     * @param string $catalogId            Catalog ID or code
     * @param int    $pageSize             Pagination size
     * @param int    $currentPage          Current page
     * @param array  $expectedAggregations expected aggregations sample
     * @param string $priceGroupId         Price group id
     */
    public function testSearchDocumentsWithAggregation(
        string $entityType,
        string $catalogId,
        int $pageSize,
        int $currentPage,
        array $expectedAggregations,
        string $priceGroupId = '0',
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d',
            $entityType,
            $catalogId,
            $pageSize,
            $currentPage
        );

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                              score
                              source
                            }
                            aggregations {
                              field
                              count
                              label
                              type
                              hasMore
                              options {
                                label
                                value
                                count
                              }
                            }
                        }
                    }
                GQL,
                $user,
                [PriceGroupProvider::PRICE_GROUP_ID => $priceGroupId]
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use ($expectedAggregations) {
                    // Extra test on response structure because all exceptions might not throw an HTTP error code.
                    $this->assertJsonContains([
                        'data' => [
                            'documents' => [
                                'aggregations' => $expectedAggregations,
                            ],
                        ],
                    ]);
                    $responseData = $response->toArray();
                    $this->assertIsArray($responseData['data']['documents']['aggregations']);
                    foreach ($responseData['data']['documents']['aggregations'] as $data) {
                        $this->assertArrayHasKey('field', $data);
                        $this->assertArrayHasKey('count', $data);
                        $this->assertArrayHasKey('label', $data);
                        $this->assertArrayHasKey('options', $data);
                    }
                }
            )
        );
    }

    public function searchWithAggregationDataProvider(): array
    {
        return [
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [       // expected aggregations sample.
                    ['field' => 'is_eco_friendly', 'label' => 'Is_eco_friendly', 'type' => 'checkbox', 'hasMore' => false],
                    ['field' => 'weight', 'label' => 'Weight', 'type' => 'slider', 'hasMore' => false],
                    [
                        'field' => 'category__id',
                        'label' => 'Category',
                        'type' => 'category',
                        'hasMore' => false,
                        'options' => [
                            [
                                'label' => 'One',
                                'value' => 'cat_1',
                                'count' => 2,
                            ],
                            [
                                'label' => 'Five',
                                'value' => 'cat_5',
                                'count' => 1,
                            ],
                        ],
                    ],
                    ['field' => 'size', 'label' => 'Size', 'type' => 'slider', 'hasMore' => false],
                    [
                        'field' => 'color__value',
                        'label' => 'Color',
                        'type' => 'checkbox',
                        'hasMore' => true,
                        'options' => [
                            [
                                'label' => 'Red',
                                'value' => 'red',
                                'count' => 1,
                            ],
                            [
                                'label' => 'Grey',
                                'value' => 'grey',
                                'count' => 6,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [       // expected aggregations sample.
                    ['field' => 'is_eco_friendly', 'label' => 'Is_eco_friendly', 'type' => 'checkbox', 'hasMore' => false],
                    ['field' => 'weight', 'label' => 'Weight', 'type' => 'slider'],
                    [
                        'field' => 'category__id',
                        'label' => 'Category',
                        'type' => 'category',
                        'hasMore' => false,
                        'options' => [
                            [
                                'label' => 'Un',
                                'value' => 'cat_1',
                                'count' => 2,
                            ],
                            [
                                'label' => 'Deux',
                                'value' => 'cat_2',
                                'count' => 1,
                            ],
                        ],
                    ],
                    [
                        'field' => 'size',
                        'label' => 'Taille',
                        'type' => 'slider',
                        'hasMore' => false,
                    ],
                    [
                        'field' => 'my_price__price',
                        'label' => 'My_price',
                        'type' => 'slider',
                        'options' => [
                            [
                                'label' => '8',
                                'value' => '8',
                                'count' => 1,
                            ],
                            [
                                'label' => '10',
                                'value' => '10',
                                'count' => 1,
                            ],
                        ],
                    ],
                    [
                        'field' => 'color__value',
                        'label' => 'Couleur',
                        'type' => 'checkbox',
                        'hasMore' => true,
                        'options' => [
                            [
                                'label' => 'Rouge',
                                'value' => 'red',
                                'count' => 1,
                            ],
                            [
                                'label' => 'Gris',
                                'value' => 'grey',
                                'count' => 5,
                            ],
                        ],
                    ],
                ],
                '0',
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [       // expected aggregations sample.
                    ['field' => 'is_eco_friendly', 'label' => 'Is_eco_friendly', 'type' => 'checkbox', 'hasMore' => false],
                    ['field' => 'weight', 'label' => 'Weight', 'type' => 'slider'],
                    [
                        'field' => 'category__id',
                        'label' => 'Category',
                        'type' => 'category',
                        'hasMore' => false,
                        'options' => [
                            [
                                'label' => 'Un',
                                'value' => 'cat_1',
                                'count' => 2,
                            ],
                            [
                                'label' => 'Deux',
                                'value' => 'cat_2',
                                'count' => 1,
                            ],
                        ],
                    ],
                    [
                        'field' => 'size',
                        'label' => 'Taille',
                        'type' => 'slider',
                        'hasMore' => false,
                    ],
                    [
                        'field' => 'my_price__price',
                        'label' => 'My_price',
                        'type' => 'slider',
                        'options' => [
                            [
                                'label' => '10',
                                'value' => '10',
                                'count' => 1,
                            ],
                            [
                                'label' => '17',
                                'value' => '17',
                                'count' => 1,
                            ],
                        ],
                    ],
                    [
                        'field' => 'color__value',
                        'label' => 'Couleur',
                        'type' => 'checkbox',
                        'hasMore' => true,
                        'options' => [
                            [
                                'label' => 'Rouge',
                                'value' => 'red',
                                'count' => 1,
                            ],
                            [
                                'label' => 'Gris',
                                'value' => 'grey',
                                'count' => 5,
                            ],
                        ],
                    ],
                ],
                '1',
            ],
            [
                'product',  // entity type.
                'b2b_fr',   // catalog ID.
                10,     // page size.
                1,      // current page.
                [       // expected aggregations sample.
                    ['field' => 'is_eco_friendly', 'label' => 'Is_eco_friendly', 'type' => 'checkbox', 'hasMore' => false],
                    ['field' => 'weight', 'label' => 'Weight', 'type' => 'slider'],
                    [
                        'field' => 'category__id',
                        'label' => 'Category',
                        'type' => 'category',
                        'hasMore' => false,
                        'options' => [
                            [
                                'label' => 'Un',
                                'value' => 'cat_1',
                                'count' => 2,
                            ],
                            [
                                'label' => 'Deux',
                                'value' => 'cat_2',
                                'count' => 1,
                            ],
                        ],
                    ],
                    [
                        'field' => 'size',
                        'label' => 'Taille',
                        'type' => 'slider',
                        'hasMore' => false,
                    ],
                    [
                        'field' => 'color__value',
                        'label' => 'Couleur',
                        'type' => 'checkbox',
                        'hasMore' => true,
                        'options' => [
                            [
                                'label' => 'Rouge',
                                'value' => 'red',
                                'count' => 1,
                            ],
                            [
                                'label' => 'Gris',
                                'value' => 'grey',
                                'count' => 5,
                            ],
                        ],
                    ],
                ],
                'fake_price_group_id',
            ],
        ];
    }

    /**
     * @dataProvider filteredSearchDocumentsValidationProvider
     *
     * @param string $entityType   Entity Type
     * @param string $catalogId    Catalog ID or code
     * @param string $filter       Filters to apply
     * @param string $debugMessage Expected debug message
     */
    public function testFilteredSearchDocumentsGraphQlValidation(
        string $entityType,
        string $catalogId,
        string $filter,
        string $debugMessage
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", filter: [%s]',
            $entityType,
            $catalogId,
            $filter
        );

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                            }
                        }
                    }
                GQL,
                $user
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use (
                    $debugMessage
                ) {
                    $this->assertJsonContains([
                        'errors' => [
                            [
                                'debugMessage' => $debugMessage,
                            ],
                        ],
                    ]);
                }
            )
        );
    }

    public function filteredSearchDocumentsValidationProvider(): array
    {
        return [
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{matchFilter: {field:"fake_source_field_match", match:"sacs"}}', // Filters.
                "The field 'fake_source_field_match' does not exist", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{equalFilter: {field:"fake_source_field_equal", eq: "24-MB03"}}', // Filters.
                "The field 'fake_source_field_equal' does not exist", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{rangeFilter: {field:"fake_source_field_range", gt: "0"}}', // Filters.
                "The field 'fake_source_field_range' does not exist", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{matchFilter: {field:"fake_source_field", match:"sacs"}}', // Filters.
                "The field 'fake_source_field' does not exist", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{rangeFilter: {field:"id"}}', // Filters.
                "Filter argument rangeFilter: At least 'gt', 'lt', 'gte' or 'lte' should be filled.", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{rangeFilter: {field:"id", gt: "1", gte: "1"}}', // Filters.
                "Filter argument rangeFilter: Do not use 'gt' and 'gte' in the same filter.", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{equalFilter:{field:"id"}}', // Filters.
                "Filter argument equalFilter: At least 'eq' or 'in' should be filled.", // debug message
            ],
            [
                'product', // entity type.
                'b2c_en', // catalog ID.
                '{equalFilter:{field:"id" eq: "1" in:["1"]}}', // Filters.
                "Filter argument equalFilter: Only 'eq' or only 'in' should be filled, not both.", // debug message
            ],
        ];
    }

    /**
     * @dataProvider filteredSearchDocumentsProvider
     *
     * @param string $entityType            Entity Type
     * @param string $catalogId             Catalog ID or code
     * @param int    $pageSize              Pagination size
     * @param int    $currentPage           Current page
     * @param string $filter                Filters to apply
     * @param array  $sortOrders            Sort order specifications
     * @param string $documentIdentifier    Document identifier to check ordered results
     * @param array  $expectedOrderedDocIds Expected ordered document identifiers
     */
    public function testFilteredSearchDocuments(
        string $entityType,
        string $catalogId,
        int $pageSize,
        int $currentPage,
        array $sortOrders,
        string $filter,
        string $documentIdentifier,
        array $expectedOrderedDocIds
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d, filter: [%s]',
            $entityType,
            $catalogId,
            $pageSize,
            $currentPage,
            $filter
        );

        $this->addSortOrders($sortOrders, $arguments);

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                              source
                            }
                        }
                    }
                GQL,
                $user
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use (
                    $documentIdentifier,
                    $expectedOrderedDocIds
                ) {
                    // Extra test on response structure because all exceptions might not throw an HTTP error code.
                    $this->assertJsonContains([
                        'data' => [
                            'documents' => [
                                'collection' => [],
                            ],
                        ],
                    ]);

                    $responseData = $response->toArray();
                    $this->assertIsArray($responseData['data']['documents']['collection']);
                    $this->assertCount(\count($expectedOrderedDocIds), $responseData['data']['documents']['collection']);
                    foreach ($responseData['data']['documents']['collection'] as $index => $document) {
                        $this->assertArrayHasKey('id', $document);
                        $this->assertEquals("/documents/{$expectedOrderedDocIds[$index]}", $document['id']);

                        $this->assertArrayHasKey('source', $document);
                        if (\array_key_exists($documentIdentifier, $document['source'])) {
                            $this->assertEquals($expectedOrderedDocIds[$index], $document['source'][$documentIdentifier]);
                        }
                    }
                }
            )
        );
    }

    public function filteredSearchDocumentsProvider(): array
    {
        return [
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                [], // sort order specifications.
                '{equalFilter: {field: "sku", eq: "24-MB03"}}',
                'entity_id', // document data identifier.
                [3], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                '{equalFilter: {field: "sku", in: ["24-MB02", "24-WB01"]}}', // filter.
                'entity_id', // document data identifier.
                [6, 8], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                '{rangeFilter: {field:"id", gte: "10", lte: "12"}}', // filter.
                'entity_id', // document data identifier.
                [10, 11, 12], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                '{rangeFilter: {field:"id", gt: "10", lt: "12"}}', // filter.
                'entity_id', // document data identifier.
                [11], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                '{matchFilter: {field: "name", match: "Compete Track"}}', // filter.
                'entity_id', // document data identifier.
                [9], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                '{existFilter: {field: "size"}}', // filter.
                'entity_id', // document data identifier.
                [11, 12, 2, 3, 4, 5, 6, 7, 8, 9], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {matchFilter: {field:"name", match:"Sac"}}
                  {equalFilter: {field: "sku", in: ["24-WB06", "24-WB03"]}}
                GQL, // filter.
                'entity_id', // document data identifier.
                [11, 12], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _must: [
                      {matchFilter: {field:"name", match:"Sac"}}
                      {equalFilter: {field: "sku", in: ["24-WB06", "24-WB03"]}}
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [11, 12], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _not: [
                      {existFilter: {field:"size"}}
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [10], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _should: [
                      {equalFilter: {field: "sku", eq: "24-MB05"}}
                      {equalFilter: {field: "sku", eq: "24-UB02"}}
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [4, 7], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _not: [
                      {matchFilter: {field:"name", match:"Sac"}}
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [10, 5, 9], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _must: [
                      {matchFilter: {field:"name", match:"Sac"}}
                    ]
                    _should: [
                      {equalFilter: {field: "sku", eq: "24-WB06"}}
                      {equalFilter: {field: "sku", eq: "24-WB03"}}
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [11, 12], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _must: [
                      {matchFilter: {field:"name", match:"Sac"}}
                    ]
                    _should: [
                      {equalFilter: {field: "sku", eq: "24-WB01"}}
                      {equalFilter: {field: "sku", eq: "24-WB06"}}
                      {equalFilter: {field: "sku", eq: "24-WB03"}}
                    ]
                    _not: [
                      {equalFilter: {field: "id", eq: "11"}}
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [12, 8], // expected ordered document IDs
            ],
            [
                'product', // entity type.
                'b2b_fr', // catalog ID.
                10, // page size.
                1,  // current page.
                ['id' => SortOrderInterface::SORT_ASC], // sort order specifications.
                <<<GQL
                  {boolFilter: {
                    _must: [
                      {matchFilter: {field:"name", match:"Sac"}}
                      {boolFilter: {
                        _should: [
                          {equalFilter: {field: "sku", eq: "24-WB06"}}
                          {equalFilter: {field: "sku", eq: "24-WB03"}}
                        ]}
                      }
                    ]}
                  }
                GQL, // filter.
                'entity_id', // document data identifier.
                [11, 12], // expected ordered document IDs
            ],
        ];
    }

    /**
     * @dataProvider searchWithQueryDataProvider
     *
     * @param string   $entityType          Entity Type
     * @param string   $catalogId           Catalog ID or code
     * @param string   $query               Query text
     * @param int      $expectedResultCount Expected result count
     * @param string[] $expectedResultNames Expected result names
     */
    public function testSearchDocumentsWithQuery(
        string $entityType,
        string $catalogId,
        string $query,
        int $expectedResultCount,
        array $expectedResultNames,
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d, search: "%s"',
            $entityType,
            $catalogId,
            10,
            1,
            $query
        );

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            collection {
                              id
                              score
                              source
                            }
                        }
                    }
                GQL,
                $user
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use ($expectedResultCount, $expectedResultNames) {
                    // Extra test on response structure because all exceptions might not throw an HTTP error code.
                    $this->assertJsonContains([
                        'data' => [
                            'documents' => [
                                'collection' => [],
                            ],
                        ],
                    ]);
                    $responseData = $response->toArray();
                    $results = $responseData['data']['documents']['collection'];
                    $names = array_map(fn (array $item) => $item['source']['name'], $results);
                    $this->assertCount($expectedResultCount, $results);
                    $this->assertEquals($expectedResultNames, $names);
                }
            )
        );
    }

    public function searchWithQueryDataProvider(): array
    {
        return [
            // Search reference field
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'striveshoulder',   // query.
                1,                  // expected result count.
                [                   // expected result name.
                    'Strive Shoulder Pack',
                ],
            ],

            // Search a word
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'bag',              // query.
                7,                  // expected result count.
                [                   // expected result name.
                    'Wayfarer Messenger Bag',
                    'Joust Duffle Bag',
                    'Voyage Yoga Bag',
                    'Push It Messenger Bag',
                    'Rival Field Messenger',
                    'Strive Shoulder Pack',
                    'Crown Summit Backpack',
                ],
            ],

            // Search a non-existing word
            [
                'product',          // entity type.
                'b2c_fr',           // catalog ID.
                'bag',              // query.
                0,                  // expected result count.
                [],                // expected result name.
            ],

            // Search in description field
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'summer',           // query.
                2,                  // expected result count.
                [                   // expected result name.
                    'Rival Field Messenger',
                    'Crown Summit Backpack',
                ],
            ],

            // Search in multiple field
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'yoga',             // query.
                2,                  // expected result count.
                [                   // expected result name.
                    'Voyage Yoga Bag',
                    'Crown Summit Backpack',
                ],
            ],

            // Search with multiple words
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'bag autumn',       // query.
                1,                  // expected result count.
                [                   // expected result name.
                    'Wayfarer Messenger Bag',
                ],
            ],

            // Search with misspelled word
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'bag automn',       // query.
                1,                  // expected result count.
                [                   // expected result name.
                    'Wayfarer Messenger Bag',
                ],
            ],

            // Search with word with same phonetic
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'bohqpaq',          // query.
                4,                  // expected result count.
                [                   // expected result name.
                    'Fusion Backpack',
                    'Driven Backpack',
                    'Endeavor Daytrip Backpack',
                    'Crown Summit Backpack',
                ],
            ],

            // Search with words from name and select attribute spellchecked
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'Testtt Duffle',     // query.
                1,                  // expected result count.
                [                   // expected result name.
                    'Joust Duffle Bag',
                ],
            ],

            // Search with words from name and select attribute not spellchecked
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                'red backpack',     // query.
                1,                  // expected result count.
                [                   // expected result name.
                    'Fusion Backpack',
                ],
            ],

            // Search with sku
            [
                'product',          // entity type.
                'b2c_en',           // catalog ID.
                '24-MB04',          // query.
                1,                  // expected result count.
                [                   // expected result name.
                    'Strive Shoulder Pack',
                ],
            ],
        ];
    }

    /**
     * @dataProvider searchWithAggregationAndFilterDataProvider
     *
     * @param string      $entityType           Entity Type
     * @param string      $catalogId            Catalog ID or code
     * @param int         $pageSize             Pagination size
     * @param int         $currentPage          Current page
     * @param string|null $filter               Filters to apply
     * @param array       $expectedOptionsCount expected aggregation option count
     */
    public function testSearchDocumentsWithAggregationAndFilter(
        string $entityType,
        string $catalogId,
        int $pageSize,
        int $currentPage,
        ?string $filter,
        array $expectedOptionsCount,
    ): void {
        $user = $this->getUser(Role::ROLE_CONTRIBUTOR);

        $arguments = sprintf(
            'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d',
            $entityType,
            $catalogId,
            $pageSize,
            $currentPage,
        );
        if ($filter) {
            $arguments = sprintf(
                'entityType: "%s", localizedCatalog: "%s", pageSize: %d, currentPage: %d, filter: [%s]',
                $entityType,
                $catalogId,
                $pageSize,
                $currentPage,
                $filter,
            );
        }

        $this->validateApiCall(
            new RequestGraphQlToTest(
                <<<GQL
                    {
                        documents({$arguments}) {
                            aggregations {
                              field
                              count
                              options {
                                value
                              }
                            }
                        }
                    }
                GQL,
                $user
            ),
            new ExpectedResponse(
                200,
                function (ResponseInterface $response) use ($expectedOptionsCount) {
                    $responseData = $response->toArray();
                    $this->assertIsArray($responseData['data']['documents']['aggregations']);
                    foreach ($responseData['data']['documents']['aggregations'] as $data) {
                        if (\array_key_exists($data['field'], $expectedOptionsCount)) {
                            $this->assertCount($expectedOptionsCount[$data['field']], $data['options'] ?? []);
                        }
                    }
                }
            )
        );
    }

    public function searchWithAggregationAndFilterDataProvider(): array
    {
        return [
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                null, // filter.
                [ // expected option result
                    'color__value' => 2,
                    'category__id' => 2,
                    'is_eco_friendly' => 2,
                ],
            ],
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                '{equalFilter: {field: "sku", eq: "24-WB05"}}', // filter.
                [ // expected option result
                    'color__value' => 1,
                    'category__id' => 0,
                    'is_eco_friendly' => 1,
                ],
            ],
            [
                'product',  // entity type.
                'b2c_en',   // catalog ID.
                10,     // page size.
                1,      // current page.
                '{equalFilter: {field: "color.value", in: ["pink"]}}', // filter.
                [ // expected option result
                    'color__value' => 2,
                    'category__id' => 0,
                    'is_eco_friendly' => 1,
                ],
            ],
        ];
    }

    private function addSortOrders(array $sortOrders, string &$arguments): void
    {
        if (!empty($sortOrders)) {
            $sortArguments = [];
            foreach ($sortOrders as $field => $direction) {
                $sortArguments[] = sprintf('field: "%s", direction: %s', $field, $direction);
            }
            $arguments .= sprintf(', sort: {%s}', implode(', ', $sortArguments));
        }
    }
}
