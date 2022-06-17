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

namespace Elasticsuite\Search\Type\Definition;

use ApiPlatform\Core\GraphQl\Type\Definition\TypeInterface;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class SortInputType extends InputObjectType implements TypeInterface
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'field' => Type::nonNull(Type::string()),
                'direction' => new SortEnumType(),
            ],
        ];

        parent::__construct($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
}