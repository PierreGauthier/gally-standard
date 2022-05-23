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

namespace Elasticsuite\Search\Elasticsearch\Request\Aggregation\Pipeline;

use Elasticsuite\Search\Elasticsearch\Request\PipelineInterface;

/**
 * Abstract pipeline implementation.
 */
abstract class AbstractPipeline implements PipelineInterface
{
    private string $name;

    /**
     * @var array<mixed>|string|null
     */
    private array|string|null $bucketsPath;

    /**
     * Pipeline constructor.
     *
     * @param string                   $name        Pipeline name
     * @param array<mixed>|string|null $bucketsPath Pipeline buckets path
     */
    public function __construct(string $name, array|string $bucketsPath = null)
    {
        $this->name = $name;
        $this->bucketsPath = $bucketsPath;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getBucketsPath(): array|string|null
    {
        return $this->bucketsPath;
    }

    /**
     * {@inheritDoc}
     */
    public function hasBucketsPath(): bool
    {
        return null !== $this->bucketsPath;
    }
}
