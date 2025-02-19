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

namespace Gally\Category\Service;

use Gally\Metadata\Repository\SourceFieldRepository;
use Gally\Product\GraphQl\Type\Definition\SortOrder\SortOrderProviderInterface as ProductSortOrderProviderInterface;
use Gally\Search\Elasticsearch\Request\SortOrderInterface;

class CategoryProductsSortingOptionsProvider
{
    private ?array $sortingOptions;

    public function __construct(
        private SourceFieldRepository $sourceFieldRepository,
        private iterable $sortOrderProviders
    ) {
        $this->sortingOptions = null;
    }

    /**
     * Return all products sorting options for categories.
     */
    public function getAllSortingOptions(): array
    {
        if (null === $this->sortingOptions) {
            $sortOptions = [];

            // Id source field need to be sortable to be used as default sort option,
            // but we don't want to have it in the list
            $sortableFields = $this->sourceFieldRepository->getSortableFields('product', ['id']);
            foreach ($sortableFields as $sourceField) {
                /** @var ProductSortOrderProviderInterface $sortOrderProvider */
                foreach ($this->sortOrderProviders as $sortOrderProvider) {
                    if ($sortOrderProvider->supports($sourceField)) {
                        $sortOptions[] = [
                            'code' => $sortOrderProvider->getSortOrderField($sourceField),
                            'label' => $sortOrderProvider->getSimplifiedLabel($sourceField),
                        ];
                    }
                }
            }

            $sortOptions[] = [
                'code' => SortOrderInterface::DEFAULT_SORT_FIELD,
                'label' => 'Relevance',
            ];

            $this->sortingOptions = $sortOptions;
        }

        return $this->sortingOptions;
    }

    /**
     * Return the default sorting field.
     */
    public function getDefaultSortingField(): ?string
    {
        $defaultSortingField = null;

        $defaultSortOption = current($this->getAllSortingOptions());
        if (\is_array($defaultSortOption) && \array_key_exists('code', $defaultSortOption)) {
            $defaultSortingField = $defaultSortOption['code'];
        }

        return $defaultSortingField;
    }
}
