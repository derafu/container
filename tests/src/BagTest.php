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
