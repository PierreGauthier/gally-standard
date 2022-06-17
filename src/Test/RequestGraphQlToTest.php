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

namespace Elasticsuite\Standard\src\Test;

use Elasticsuite\User\Model\User;

/**
 * @codeCoverageIgnore
 */
class RequestGraphQlToTest extends RequestToTest
{
    public function __construct(
        string $query,
        ?User $user,
        array $headers = [],
    ) {
        parent::__construct(
            'POST',
            '/graphql',
            $user,
            ['operationName' => null, 'query' => $query, 'variables' => []],
            $headers
        );
    }
}