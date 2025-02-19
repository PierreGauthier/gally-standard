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

namespace Gally\Metadata\Model;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gally\Entity\Filter\BooleanFilter;
use Gally\Entity\Filter\SearchColumnsFilter;
use Gally\Metadata\Model\SourceField\Type;
use Gally\Metadata\Model\SourceField\Weight;
use Gally\User\Constant\Role;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    collectionOperations: [
        'get' => ['security' => "is_granted('" . Role::ROLE_CONTRIBUTOR . "')"],
        'post' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
    ],
    itemOperations: [
        'get' => ['security' => "is_granted('" . Role::ROLE_CONTRIBUTOR . "')"],
        'put' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
        'patch' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
        'delete' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
    ],
    graphql: [
        'item_query' => ['security' => "is_granted('" . Role::ROLE_CONTRIBUTOR . "')"],
        'collection_query' => ['security' => "is_granted('" . Role::ROLE_CONTRIBUTOR . "')"],
        'create' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
        'update' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
        'delete' => ['security' => "is_granted('" . Role::ROLE_ADMIN . "')"],
    ],
    normalizationContext: ['groups' => ['source_field:api']],
    denormalizationContext: ['groups' => ['source_field:api']],
)]

#[ApiFilter(SearchFilter::class, properties: ['code' => 'ipartial', 'type' => 'exact', 'metadata.entity' => 'exact', 'weight' => 'exact', 'search' => 'ipartial'])]
#[ApiFilter(SearchColumnsFilter::class, properties: ['defaultLabel' => ['code', 'labels.label']])]
#[ApiFilter(BooleanFilter::class, properties: ['isSearchable', 'isFilterable', 'isSortable', 'isSpellchecked', 'isUsedForRules'], arguments: ['treatNullAsFalse' => true])]
class SourceField
{
    private int $id;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Attribute code',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => false,
                    'position' => 10,
                ],
            ],
        ],
    )]
    private string $code;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Attribute label',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => false,
                    'position' => 20,
                ],
            ],
        ],
    )]
    private ?string $defaultLabel = null;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Attribute type',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => false,
                    'position' => 30,
                    'input' => 'select',
                    'options' => [
                        'values' => Type::AVAILABLE_TYPES_OPTIONS,
                    ],
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private ?string $type = null;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Filterable',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => true,
                    'position' => 40,
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private ?bool $isFilterable = null;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Searchable',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => true,
                    'position' => 50,
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private ?bool $isSearchable = null;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Sortable',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => true,
                    'position' => 60,
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private ?bool $isSortable = null;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Use in rule engine',
                ],
                'gally' => [
                    'visible' => true,
                    'editable' => true,
                    'position' => 70,
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private ?bool $isUsedForRules = null;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Search weight',
                ],
                'gally' => [
                    'visible' => false,
                    'editable' => true,
                    'position' => 80,
                    'input' => 'select',
                    'options' => [
                        'values' => Weight::WEIGHT_VALID_VALUES_OPTIONS,
                    ],
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => true,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private int $weight = 1;

    #[ApiProperty(
        attributes: [
            'hydra:supportedProperty' => [
                'hydra:property' => [
                    'rdfs:label' => 'Used in spellcheck',
                ],
                'gally' => [
                    'visible' => false,
                    'editable' => true,
                    'position' => 90,
                    'context' => [
                        'search_configuration_attributes' => [
                            'visible' => true,
                        ],
                    ],
                ],
            ],
        ],
    )]
    private ?bool $isSpellchecked = null;

    private bool $isSystem = false;

    private Metadata $metadata;

    private ?bool $isNested = null;

    private ?string $nestedPath = null;

    private ?string $nestedCode = null;

    private ?string $search = null;

    /** @var Collection<SourceFieldLabel> */
    private Collection $labels;

    /** @var Collection<SourceFieldOption> */
    private Collection $options;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
        $this->options = new ArrayCollection();
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getId(): int
    {
        return $this->id;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getCode(): string
    {
        return $this->code;
    }

    #[Groups(['source_field:api'])]
    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getDefaultLabel(): string
    {
        foreach ($this->getLabels() as $label) {
            if ($label->getLocalizedCatalog()->getIsDefault()) {
                return $label->getLabel();
            }
        }

        return $this->defaultLabel ?: ucfirst($this->getCode());
    }

    #[Groups(['source_field:api'])]
    public function setDefaultLabel(?string $defaultLabel): self
    {
        $this->defaultLabel = $defaultLabel;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getType(): ?string
    {
        return $this->type;
    }

    #[Groups(['source_field:api'])]
    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getWeight(): ?int
    {
        return $this->weight;
    }

    #[Groups(['source_field:api'])]
    public function setWeight(?int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getIsSearchable(): ?bool
    {
        return $this->isSearchable;
    }

    #[Groups(['source_field:api'])]
    public function setIsSearchable(?bool $isSearchable): self
    {
        $this->isSearchable = $isSearchable;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getIsFilterable(): ?bool
    {
        return $this->isFilterable;
    }

    #[Groups(['source_field:api'])]
    public function setIsFilterable(?bool $isFilterable): self
    {
        $this->isFilterable = $isFilterable;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getIsSortable(): ?bool
    {
        return $this->isSortable;
    }

    #[Groups(['source_field:api'])]
    public function setIsSortable(?bool $isSortable): self
    {
        $this->isSortable = $isSortable;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getIsSpellchecked(): ?bool
    {
        return $this->isSpellchecked;
    }

    #[Groups(['source_field:api'])]
    public function setIsSpellchecked(?bool $isSpellchecked): self
    {
        $this->isSpellchecked = $isSpellchecked;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getIsUsedForRules(): ?bool
    {
        return $this->isUsedForRules;
    }

    #[Groups(['source_field:api'])]
    public function setIsUsedForRules(?bool $isUsedForRules): self
    {
        $this->isUsedForRules = $isUsedForRules;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): self
    {
        $this->search = $search;

        return $this;
    }

    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getMetadata(): ?Metadata
    {
        return $this->metadata;
    }

    #[Groups(['source_field:api'])]
    public function setMetadata(?Metadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function isNested(): bool
    {
        if (null == $this->isNested) {
            $this->isNested = str_contains($this->getCode(), '.');
        }

        return $this->isNested;
    }

    public function getNestedPath(): ?string
    {
        if ($this->isNested() && (null === $this->nestedPath)) {
            // Alternative: all elements minus the last one.
            $path = explode('.', $this->getCode());
            $this->nestedPath = current($path);
        }

        return $this->nestedPath;
    }

    public function getNestedCode(): ?string
    {
        if (null === $this->nestedCode) {
            $this->nestedCode = $this->getCode();
            if ($this->isNested() && (null !== $this->getNestedPath())) {
                $this->nestedCode = substr($this->nestedCode, \strlen($this->getNestedPath()) + 1);
            }
        }

        return $this->nestedCode;
    }

    /**
     * @return Collection<SourceFieldLabel>
     */
    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function getLabel(int $catalogId): string
    {
        foreach ($this->getLabels() as $label) {
            if ($catalogId === $label->getLocalizedCatalog()->getId()) {
                return $label->getLabel();
            }
        }

        return $this->getDefaultLabel();
    }

    public function addLabel(SourceFieldLabel $label): self
    {
        if (!$this->labels->contains($label)) {
            $this->labels[] = $label;
            $label->setSourceField($this);
        }

        return $this;
    }

    public function removeLabel(SourceFieldLabel $label): self
    {
        if ($this->labels->removeElement($label)) {
            if ($label->getSourceField() === $this) {
                $label->setSourceField(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<SourceFieldOption>
     */
    #[Groups(['source_field:api', 'facet_configuration:graphql_read'])]
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(SourceFieldOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options[] = $option;
            $option->setSourceField($this);
        }

        return $this;
    }

    public function removeOption(SourceFieldOption $option): self
    {
        if ($this->options->removeElement($option)) {
            if ($option->getSourceField() === $this) {
                $option->setSourceField(null);
            }
        }

        return $this;
    }
}
