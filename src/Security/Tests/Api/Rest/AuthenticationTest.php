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

namespace Elasticsuite\Security\Tests\Api\Rest;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Elasticsuite\User\DataFixtures\LoginTrait;

class AuthenticationTest extends ApiTestCase
{
    use LoginTrait;

    public function testLogin(): void
    {
        $client = self::createClient();
        $loginJson = $this->login(
            $client,
            static::getContainer()->get('doctrine')->getManager(), // @phpstan-ignore-line
            static::getContainer()->get('security.user_password_hasher')
        );

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $loginJson);

        $catalog = ['code' => 'login_rest_catalog', 'name' => 'Login Rest catalog'];

        // Test not authorized.
        $client->request('POST', '/catalogs', ['json' => $catalog]);
        $this->assertResponseStatusCodeSame(401);

        // Test authorized.
        $client->request('POST', '/catalogs', ['auth_bearer' => $loginJson['token'], 'json' => $catalog]);
        $this->assertResponseStatusCodeSame(201);
    }
}