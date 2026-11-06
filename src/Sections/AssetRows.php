<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Db\Model\RowValues;

/**
 * The assets a record's blocks name, read through the entity a site maps its assets with, which has an
 * `assetId`.
 */
final readonly class AssetRows
{
    /**
     * @param class-string $entity
     */
    public function __construct(
        private EntityManager $em,
        private RowValues $rows,
        private string $entity,
    ) {}

    /**
     * Each asset's columns by name, keyed by its id.
     *
     * @param list<string> $ids
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws DbModelException When the assets can't be read.
     */
    public function of(array $ids): array
    {
        $found = [];
        foreach ($this->em
            ->getRepository($this->entity)
            ->findBy(['assetId' => $ids]) as $asset) {
            $row                                      = $this->rows->of($asset);
            $found[(string) ($row['asset_id'] ?? '')] = $row;
        }

        return $found;
    }
}
