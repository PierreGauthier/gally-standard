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

use Gally\User\Model\User;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @codeCoverageIgnore
 */
abstract class AbstractEntityTestWithUpdate extends AbstractEntityTest
{
    /**
     * @dataProvider patchUpdateDataProvider
     * @depends testGet
     */
    public function testPatchUpdate(
        ?User $user,
        int|string $id,
        array $data,
        int $responseCode,
        ?string $message = null,
        string $validRegex = null
    ): void {
        $this->update('PATCH', $user, $id, $data, $responseCode, ['Content-Type' => 'application/merge-patch+json'], $message, $validRegex);
    }

    /**
     * @dataProvider putUpdateDataProvider
     * @depends testPatchUpdate
     */
    public function testPutUpdate(
        ?User $user,
        int|string $id,
        array $data,
        int $responseCode,
        ?string $message = null,
        string $validRegex = null
    ): void {
        $this->update('PUT', $user, $id, $data, $responseCode, ['Content-Type' => 'application/ld+json'], $message, $validRegex);
    }

    /**
     * Data provider for entity update api call
     * The data provider should return test case with :
     * - User $user: user to use in the api call
     * - int|string $id: id of the entity to update
     * - array $data: post data
     * - (optional) int $responseCode: expected response code
     * - (optional) string $message: expected error message
     * - (optional) string $validRegex: a regexp used to validate generated id.
     */
    abstract public function patchUpdateDataProvider(): iterable;

    /**
     * Data provider for entity update api call
     * The data provider should return test case with :
     * - User $user: user to use in the api call
     * - int|string $id: id of the entity to update
     * - array $data: post data
     * - (optional) int $responseCode: expected response code
     * - (optional) string $message: expected error message
     * - (optional) string $validRegex: a regexp used to validate generated id.
     */
    public function putUpdateDataProvider(): iterable
    {
        return $this->patchUpdateDataProvider();
    }

    protected function update(
        string $method,
        ?User $user,
        int|string $id,
        array $data,
        int $responseCode,
        array $headers = [],
        ?string $message = null,
        string $validRegex = null
    ): void {
        $request = new RequestToTest($method, "{$this->getApiPath()}/{$id}", $user, $data, $headers);
        $expectedResponse = new ExpectedResponse(
            $responseCode,
            function (ResponseInterface $response) use ($data, $validRegex) {
                $shortName = $this->getShortName();
                $this->assertJsonContains(
                    array_merge(
                        ['@context' => "/contexts/$shortName", '@type' => $shortName],
                        $this->getJsonUpdateValidation($data)
                    )
                );
                $this->assertMatchesRegularExpression($validRegex ?? '~^' . $this->getApiPath() . '/\d+$~', $response->toArray()['@id']);
                $this->assertMatchesResourceItemJsonSchema($this->getEntityClass());
            },
            $message
        );

        $this->validateApiCall($request, $expectedResponse);
    }

    protected function getJsonUpdateValidation(array $expectedData): array
    {
        return $expectedData;
    }

    /**
     * @dataProvider deleteDataProvider
     * @depends testPutUpdate
     */
    public function testDelete(?User $user, int|string $id, int $responseCode): void
    {
        parent::testDelete($user, $id, $responseCode);
    }
}
