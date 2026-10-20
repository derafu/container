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

use ArrayObject;
use Derafu\Container\Store;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(Store::class)]
class StoreTest extends TestCase
{
    /**
     * Provides test cases from fixtures for schema validation.
     *
     * @return array<string, array{array<array-key, mixed>, array<array-key, mixed>, array<string, mixed>|class-string<Throwable>}>
     */
    public static function provideTestCases(): array
    {
        $tests = require __DIR__ . '/../fixtures/store.php';

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
    public function testStoreSchemaGetterAndSetter(): void
    {
        $schema = [
            'required' => ['foo'],
            'properties' => [
                'foo' => [
                    'type' => 'string',
                ],
            ],
        ];

        $expected = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['foo'],
            'properties' => [
                'foo' => [
                    'type' => 'string',
                ],
            ],
        ];

        $container = new Store(['foo' => 'bar']);
        $container->setSchema($schema);
        $this->assertSame($expected, $container->getSchema());
    }

    public function testSetReportsTheFieldAndKeepsTheStoredData(): void
    {
        $container = new Store(['age' => 18], [
            'properties' => ['age' => ['type' => 'integer']],
        ]);

        try {
            $container->set('age', 'invalid');
            $this->fail('Setting an invalid value must be reported.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('age', $exception->getMessage());
            $this->assertStringContainsString('integer', $exception->getMessage());
            $this->assertSame(18, $container->get('age'));
        }
    }

    public function testSchemaValidationAcceptsDataFromAnArrayObject(): void
    {
        $container = new Store(new ArrayObject(['name' => 'John']), [
            'properties' => ['name' => ['type' => 'string']],
        ]);

        $container->validate();

        $this->assertSame(['name' => 'John'], $container->all());
    }

    /**
     * Tests store with fixture data and schema.
     *
     * @param array<array-key, mixed> $data Input data.
     * @param array<array-key, mixed> $schema JSON schema.
     * @param array<string, mixed>|class-string<Throwable> $expected Expected values or exception class.
     * @return void
     */
    #[DataProvider('provideTestCases')]
    public function testStoreCase(
        array $data,
        array $schema,
        array|string $expected
    ): void {
        if (is_string($expected)) {
            $this->expectException($expected);
        }

        $container = new Store($data, $schema);

        $this->assertIsArray($expected);
        foreach ($expected as $selector => $expectedValue) {
            $this->assertSame($expectedValue, $container->get($selector));
        }
    }
}
