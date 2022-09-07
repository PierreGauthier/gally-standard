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

namespace Elasticsuite\Product\Model;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiResource;
use Elasticsuite\Entity\Model\Attribute\AttributeInterface;
use Elasticsuite\Product\GraphQl\Type\Definition\SortInputType;
use Elasticsuite\Search\Model\Document;
use Elasticsuite\Search\Resolver\DummyDocumentResolver;

#[
    ApiResource(
        collectionOperations: [],
        graphql: [
            'search' => [
                'collection_query' => DummyDocumentResolver::class,
                'pagination_type' => 'page',
                'args' => [
                    'catalogId' => ['type' => 'String!', 'description' => 'Catalog ID'],
                    'currentPage' => ['type' => 'Int'],
                    'pageSize' => ['type' => 'Int'],
                    'sort' => ['type' => SortInputType::NAME],
                ],
                'read' => true, // Required so the dataprovider is called.
                'deserialize' => true,
                'write' => false,
                'serialize' => true,
            ],
        ],
        itemOperations: [
            'get' => [
                'controller' => NotFoundAction::class,
                'read' => false,
                'output' => false,
            ],
        ],
        attributes: [
            'elasticsuite' => [
                'stitching' => ['property' => 'attributes'],
                'metadata' => ['entity' => 'product'],
            ],
        ],
        paginationClientEnabled: true,
        paginationClientItemsPerPage: true,
        paginationClientPartial: false,
        paginationEnabled: true,
        paginationItemsPerPage: 30, // Default items per page if pageSize not provided.
        paginationMaximumItemsPerPage: 100, // Max. allowed items per page.
    ),
]
class Product extends Document
{
    public const DEFAULT_ATTRIBUTES = ['_id', 'id', 'data', 'source', 'index', 'type', 'score'];

    /** @var AttributeInterface[] */
    public array $attributes = [];

    public function addAttribute(AttributeInterface $attribute)
    {
        $this->attributes[$attribute->getAttributeCode()] = $attribute;
    }
}
