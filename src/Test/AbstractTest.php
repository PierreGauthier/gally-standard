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

namespace Gally\Test;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Gally\Fixture\Service\ElasticsearchFixtures;
use Gally\Fixture\Service\EntityIndicesFixturesInterface;
use Gally\User\Tests\LoginTrait;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @codeCoverageIgnore
 */
abstract class AbstractTest extends ApiTestCase
{
    use LoginTrait;

    protected static function loadFixture(array $paths)
    {
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadAliceFixture(array_merge(static::getUserFixtures(), $paths));
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
    }

    protected static function createEntityElasticsearchIndices(string $entityType, string|int|null $localizedCatalogIdentifier = null)
    {
        $entityIndicesFixtures = static::getContainer()->get(EntityIndicesFixturesInterface::class);
        $entityIndicesFixtures->createEntityElasticsearchIndices($entityType, $localizedCatalogIdentifier);
    }

    protected static function deleteEntityElasticsearchIndices(string $entityType, string|int|null $localizedCatalogIdentifier = null)
    {
        $entityIndicesFixtures = static::getContainer()->get(EntityIndicesFixturesInterface::class);
        $entityIndicesFixtures->deleteEntityElasticsearchIndices($entityType, $localizedCatalogIdentifier);
    }

    protected static function loadElasticsearchIndexFixtures(array $paths)
    {
        $elasticFixtures = static::getContainer()->get(ElasticsearchFixtures::class);
        $elasticFixtures->loadFixturesIndexFiles($paths);
    }

    protected static function loadElasticsearchDocumentFixtures(array $paths)
    {
        $elasticFixtures = static::getContainer()->get(ElasticsearchFixtures::class);
        $elasticFixtures->loadFixturesDocumentFiles($paths);
    }

    protected static function deleteElasticsearchFixtures()
    {
        $elasticFixtures = static::getContainer()->get(ElasticsearchFixtures::class);
        $elasticFixtures->deleteTestFixtures();
    }

    protected function request(RequestToTest $request): ResponseInterface
    {
        $client = static::createClient();
        $data = ['headers' => $request->getHeaders()];
        if (
            \in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)
            || ('DELETE' == $request->getMethod() && $request->getData())
        ) {
            $data['json'] = $request->getData();
        }

        if ($request->getUser()) {
            $data['auth_bearer'] = $this->loginRest($client, $request->getUser());
        }

        return $client->request($request->getMethod(), $request->getPath(), $data);
    }

    protected function validateApiCall(RequestToTest $request, ExpectedResponse $expectedResponse): void
    {
        $response = $this->request($request);
        $this->assertResponseStatusCodeSame($expectedResponse->getResponseCode());

        if (401 === $expectedResponse->getResponseCode()) {
            $this->assertJsonContains(
                [
                    'code' => 401,
                    'message' => 'JWT Token not found',
                ]
            );
        } elseif ($expectedResponse->getResponseCode() >= 400) {
            $errorType = 'hydra:Error';
            $errorContext = 'Error';
            if (\array_key_exists('violations', $response->toArray(false))) {
                $errorType = $errorContext = 'ConstraintViolationList';
            }

            if ($expectedResponse->getMessage()) {
                $this->assertJsonContains(
                    [
                        '@context' => "/contexts/$errorContext",
                        '@type' => "$errorType",
                        'hydra:title' => 'An error occurred',
                        'hydra:description' => $expectedResponse->getMessage(),
                    ]
                );
            } else {
                $this->assertJsonContains(['@context' => "/contexts/$errorContext", '@type' => "$errorType"]);
            }

            if ($expectedResponse->isValidateErrorResponse() && $expectedResponse->getValidateResponseCallback()) {
                $expectedResponse->getValidateResponseCallback()($response);
            }
        } elseif (204 != $expectedResponse->getResponseCode() && $expectedResponse->getValidateResponseCallback()) {
            $expectedResponse->getValidateResponseCallback()($response);
        } elseif (204 != $expectedResponse->getResponseCode()) {
            $data = $response->toArray();
            $this->assertArrayNotHasKey(
                'errors',
                $data,
                isset($data['errors']) ? $data['errors'][0]['debugMessage'] : ''
            );
        }
    }
}
