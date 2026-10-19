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

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Closure;
use Derafu\Container\Vault;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Options;
use Throwable;
use Traversable;

#[CoversClass(Vault::class)]
class VaultTest extends TestCase
{
    /**
     * Provides test cases from fixtures for schema validation.
     *
     * @return array<string, array{array<array-key, mixed>, array<array-key, mixed>, array<string, mixed>|class-string<Throwable>}>
     */
    public static function provideTestCases(): array
    {
        $tests = require __DIR__ . '/../fixtures/vault.php';

        $testCases = [];

        foreach ($tests as $name => $test) {
            $testCases[$name] = [
                $test['data'],
                $test['schema'],
                $test['expected'],
            ];
        }

        return $testCases;
    }

    /**
     * Tests schema getter and setter.
     *
     * @return void
     */
    public function testVaultSchemaGetterAndSetter(): void
    {
        $schema = [
            'foo' => [
                'required' => true,
            ],
        ];

        $container = new Vault();
        $container->setSchema($schema);
        $this->assertSame($schema, $container->getSchema());
    }

    /**
     * Tests vault with fixture data and schema.
     *
     * @param array<array-key, mixed> $data Input data.
     * @param array<array-key, mixed> $schema Schema configuration.
     * @param array<string, mixed>|class-string<Throwable> $expected Expected values or exception class.
     * @return void
     */
    #[DataProvider('provideTestCases')]
    public function testVaultCase(
        array $data,
        array $schema,
        array|string $expected
    ): void {
        if (is_string($expected)) {
            $this->expectException($expected);
        }

        $this->resolveNormalizers($schema);

        $container = new Vault($data, $schema);

        $this->assertIsArray($expected);
        foreach ($expected as $selector => $expectedValue) {
            $this->assertSame($expectedValue, $container->get($selector));
        }
    }

    /**
     * Resolves normalizer strings to closures in schema.
     *
     * @param array<array-key, mixed> $schema Schema by reference.
     * @return void
     */
    private function resolveNormalizers(&$schema): void
    {
        foreach ($schema as $key => &$rules) {
            $this->assertIsArray($rules);
            if (!empty($rules['normalizer'])) {
                $this->assertIsString($rules['normalizer']);
                $rules['normalizer'] = $this->resolveNormalizer(
                    $rules['normalizer']
                );
            }
            if (!empty($rules['schema'])) {
                $this->assertIsArray($rules['schema']);
                $this->resolveNormalizers($rules['schema']);
            }
        }
    }

    /**
     * Resolves a normalizer name to a closure.
     *
     * @param string $normalizer Normalizer name.
     * @return Closure Normalizer closure.
     */
    private function resolveNormalizer($normalizer): Closure
    {
        switch ($normalizer) {
            case 'float':
                return fn (Options $options, $value) => (float) $value;
            default:
                return fn (Options $options, $value) => $value;
        }
    }

    /**
     * Tests that a simple array is converted correctly.
     *
     * @return void
     */
    public function testVaultInDataContainerWithArray(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $container = new Vault($data);

        $this->assertSame(
            $data,
            $container->all(),
            'Failed to convert from array.'
        );
    }

    /**
     * Tests conversion from standard ArrayObject.
     *
     * @return void
     */
    public function testVaultInDataContainerWithArrayObject(): void
    {
        $data = new ArrayObject(['key1' => 'value1', 'key2' => 'value2']);
        $container = new Vault($data);

        $this->assertSame(
            ['key1' => 'value1', 'key2' => 'value2'],
            $container->all(),
            'Failed to convert from ArrayObject.'
        );
    }

    /**
     * Tests conversion from custom ArrayAccess and Traversable implementation.
     *
     * @return void
     */
    public function testVaultInDataContainerWithArrayAccess(): void
    {
        $data = new MyArrayAccess();
        $container = new Vault($data);

        $this->assertSame(
            ['key1' => 'value1', 'key2' => 'value2'],
            $container->all(),
            'Failed to convert from ArrayAccess/Traversable.'
        );
    }
}

/**
 * Test double implementing ArrayAccess and IteratorAggregate.
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 */
class MyArrayAccess implements ArrayAccess, IteratorAggregate
{
    /** @var array<array-key, mixed> */
    private array $container = ['key1' => 'value1', 'key2' => 'value2'];

    /** @param int|string $offset */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /** @param int|string $offset */
    public function offsetGet($offset): mixed
    {
        return $this->container[$offset] ?? null;
    }

    /** @param int|string $offset */
    public function offsetSet($offset, $value): void
    {
        $this->container[$offset] = $value;
    }

    /** @param int|string $offset */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    /** @return Traversable<array-key, mixed> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->container);
    }
}
