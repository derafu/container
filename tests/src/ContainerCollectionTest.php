<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContainer;

use ArrayObject;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Bag;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractContainer::class)]
#[CoversClass(Bag::class)]
final class ContainerCollectionTest extends TestCase
{
    public function testMatchingFiltersOrdersAndPaginatesWithoutChangingTheContainer(): void
    {
        $rows = [
            'first' => ['active' => true, 'score' => 30],
            'inactive' => ['active' => false, 'score' => 100],
            'last' => ['active' => true, 'score' => 10],
            'middle' => ['active' => true, 'score' => 20],
        ];
        $bag = new Bag($rows);
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('active', true))
            ->orderBy(['score' => Order::Descending])
            ->setFirstResult(1)
            ->setMaxResults(1)
        ;
        $matches = $bag->matching($criteria);
        $this->assertSame(['middle' => $rows['middle']], $matches->toArray());
        $this->assertNotSame($bag->collection(), $matches);
        $matches->clear();
        $this->assertSame($rows, $bag->all());

        $noMatches = $bag->matching(Criteria::create()->where(Criteria::expr()->gt('score', 100)));
        $this->assertTrue($noMatches->isEmpty());
        $this->assertSame($rows, $bag->all());
    }

    public function testJsonEncodingUsesStoredDataAndKeepsItsValueTypes(): void
    {
        $data = [
            'name' => 'Zoë',
            'enabled' => false,
            'count' => 0,
            'optional' => null,
            'nested' => ['items' => ['first', 'second']],
        ];
        $bag = new Bag($data);
        $this->assertSame($data, $bag->jsonSerialize());
        $json = json_encode($bag, JSON_THROW_ON_ERROR);
        $this->assertSame($data, json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        $bag->set('nested.items[1]', 'updated');
        $decoded = json_decode(json_encode($bag, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'name' => 'Zoë',
            'enabled' => false,
            'count' => 0,
            'optional' => null,
            'nested' => ['items' => ['first', 'updated']],
        ], $decoded);
        $this->assertSame([], json_decode(json_encode(new Bag(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, array{bool}> */
    public static function collectionSources(): array
    {
        return [
            'ArrayObject' => [true],
            'traversable ArrayAccess collection' => [false],
        ];
    }

    #[DataProvider('collectionSources')]
    public function testMergingCollectionSourcesPreservesSiblingsAndNumericKeys(bool $arrayObject): void
    {
        $incoming = ['settings' => ['theme' => 'dark'], 'items' => [1 => 'replacement'], 'added' => null];
        $source = $arrayObject ? new ArrayObject($incoming) : new ArrayCollection($incoming);
        $bag = new Bag([
            'settings' => ['theme' => 'light', 'locale' => 'en'],
            'items' => ['first', 'second'],
            'keep' => true,
        ]);
        $this->assertSame($bag, $bag->merge($source));
        $this->assertSame([
            'settings' => ['theme' => 'dark', 'locale' => 'en'],
            'items' => ['first', 'replacement'],
            'keep' => true,
            'added' => null,
        ], $bag->all());
        $this->assertSame($incoming, iterator_to_array($source));
    }
}
