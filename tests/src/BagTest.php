<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContainer;

use Derafu\Container\Bag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bag::class)]
class BagTest extends TestCase
{
    private Bag $bag;

    /**
     * Sets up the test fixture.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->bag = new Bag();
    }

    /**
     * Tests constructor with initial data.
     *
     * @return void
     */
    public function testConstructorWithInitialData(): void
    {
        $data = ['foo' => 'bar'];
        $bag = new Bag($data);

        $this->assertSame($data, $bag->all());
    }

    /**
     * Tests get and set operations.
     *
     * @return void
     */
    public function testGetAndSet(): void
    {
        $this->bag->set('foo', 'bar');
        $this->assertSame('bar', $this->bag->get('foo'));
        $this->assertSame('default', $this->bag->get('nonexistent', 'default'));
    }

    /**
     * Tests get with nested key notation.
     *
     * @return void
     */
    public function testGetWithNestedKey(): void
    {
        $this->bag->set('foo.bar', 'baz');
        $this->assertSame('baz', $this->bag->get('foo.bar'));
    }

    /**
     * Tests has key check.
     *
     * @return void
     */
    public function testHas(): void
    {
        $this->bag->set('foo', 'bar');

        $this->assertTrue($this->bag->has('foo'));
        $this->assertFalse($this->bag->has('nonexistent'));
    }

    /**
     * Tests removing a key.
     *
     * @return void
     */
    public function testRemove(): void
    {
        $this->bag->set('foo', 'bar');
        $this->bag->remove('foo');

        $this->assertFalse($this->bag->has('foo'));
    }

    /**
     * Tests replacing all data.
     *
     * @return void
     */
    public function testReplace(): void
    {
        $this->bag->set('foo', 'bar');
        $this->bag->replace(['baz' => 'qux']);

        $this->assertSame(['baz' => 'qux'], $this->bag->all());
        $this->assertFalse($this->bag->has('foo'));
    }

    /**
     * Tests merging data with existing values.
     *
     * @return void
     */
    public function testMerge(): void
    {
        $this->bag->set('foo', 'bar');
        $this->bag->merge(['baz' => 'qux']);

        $expected = [
            'foo' => 'bar',
            'baz' => 'qux',
        ];
        $this->assertSame($expected, $this->bag->all());
    }

    /**
     * Tests clearing all data.
     *
     * @return void
     */
    public function testClear(): void
    {
        $this->bag->set('foo', 'bar');
        $this->bag->clear();

        $this->assertSame([], $this->bag->all());
    }

    /**
     * Documents PHP ArrayAccess limitation: double-bracket syntax on nested
     * keys does NOT persist changes. $container['a']['b'] = 'x' is silently
     * lost because offsetGet() returns a value copy, not a reference. Same
     * applies to unset($container['a']['b']).
     *
     * Use dot notation instead: $container['a.b'] = 'x'.
     *
     * @return void
     */
    public function testNestedBracketSyntaxDoesNotPersist(): void
    {
        $bag = new Bag(['search' => ['daysAgo' => 7]]);

        // Looks valid but silently does nothing: PHP calls offsetGet('search'),
        // gets a copy of the array, sets 'criteria' on it, then discards it.
        // PHP itself emits a notice "Indirect modification of overloaded element
        // has no effect" — suppressed here because it is the expected behavior
        // being documented.
        @($bag['search']['criteria'] = 'UNSEEN SINCE 2025-01-01');
        $this->assertNull($bag->get('search.criteria'));

        // Same for unset($bag['search']['daysAgo']): also a no-op that emits
        // the same notice. PHP 8.5+ disallows @unset(), so the mechanism is
        // shown explicitly — this is what PHP does internally either way.
        $searchCopy = $bag['search'];    // offsetGet returns a value copy
        unset($searchCopy['daysAgo']);   // unsets from the copy only
        $this->assertSame(7, $bag->get('search.daysAgo'));
    }

    /**
     * Tests that dot notation correctly persists nested key operations.
     *
     * @return void
     */
    public function testNestedDotNotationPersists(): void
    {
        $bag = new Bag(['search' => ['daysAgo' => 7]]);

        $bag['search.criteria'] = 'UNSEEN SINCE 2025-01-01';
        $this->assertSame('UNSEEN SINCE 2025-01-01', $bag->get('search.criteria'));

        unset($bag['search.daysAgo']);
        $this->assertNull($bag->get('search.daysAgo'));
    }

    /**
     * Tests method chaining returns correct instance.
     *
     * @return void
     */
    public function testMethodChaining(): void
    {
        $result = $this->bag
            ->set('foo', 'bar')
            ->set('baz', 'qux')
            ->remove('foo')
            ->merge(['another' => 'value'])
        ;

        $this->assertInstanceOf(Bag::class, $result);
    }
}
