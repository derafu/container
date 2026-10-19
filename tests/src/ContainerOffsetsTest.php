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

use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Bag;
use Derafu\Container\Translation\ContainerTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

#[CoversClass(AbstractContainer::class)]
#[CoversClass(Bag::class)]
#[CoversClass(ContainerTranslationResourceProvider::class)]
final class ContainerOffsetsTest extends TestCase
{
    /** @return array<string, array{bool|int|float|string|Stringable, string, array<array-key, mixed>}> */
    public static function usableOffsets(): array
    {
        return [
            'string path' => ['user.name', 'user.name', ['user' => ['name' => 'value']]],
            'integer' => [7, '7', [7 => 'value']],
            'zero' => [0, '0', [0 => 'value']],
            'true keeps its numeric representation' => [true, '1', [1 => 'value']],
            'float keeps its selector representation' => [1.5, '1.5', [1 => [5 => 'value']]],
            'stringable path' => [new ContainerOffsetString('user.name'), 'user.name', ['user' => ['name' => 'value']]],
        ];
    }

    /** @param array<array-key, mixed> $expected */
    #[DataProvider('usableOffsets')]
    public function testSupportedOffsetsKeepTheirStringConversion(
        bool|int|float|string|Stringable $offset,
        string $path,
        array $expected
    ): void {
        $bag = new Bag(['keep' => 'untouched']);
        $this->assertFalse(isset($bag[$offset]));
        $this->assertNull($bag[$offset]);
        $bag[$offset] = 'value';
        $this->assertSame(['keep' => 'untouched'] + $expected, $bag->all());
        $this->assertTrue(isset($bag[$offset]));
        $this->assertSame('value', $bag[$offset]);
        $this->assertSame('value', $bag->get($path));
        unset($bag[$offset]);
        $this->assertSame(['keep' => 'untouched'], $bag->all());
        $this->assertFalse(isset($bag[$offset]));
        unset($bag[$offset]);
        $this->assertSame(['keep' => 'untouched'], $bag->all());
    }

    /** @return array<string, array{bool|string|Stringable|null}> */
    public static function emptyOffsets(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'empty string' => [''],
            'empty stringable' => [new ContainerOffsetString('')],
        ];
    }

    #[DataProvider('emptyOffsets')]
    public function testEmptyConvertedOffsetsKeepTheirExistingNoOpBehavior(bool|string|Stringable|null $offset): void
    {
        $data = ['keep' => 'untouched', '' => 'not selected by an empty path'];
        $bag = new Bag($data);
        $this->assertNull($bag[$offset]);
        $this->assertFalse(isset($bag[$offset]));
        $bag[$offset] = 'replacement';
        $this->assertSame($data, $bag->all());
        $bag = new Bag($data);
        unset($bag[$offset]);
        $this->assertSame($data, $bag->all());
        $bag = new Bag($data);
        $bag[] = 'not appended';
        $this->assertSame($data, $bag->all());
    }

    /** @return array<string, array{'get'|'set'|'exists'|'unset'}> */
    public static function offsetOperations(): array
    {
        return [
            'get' => ['get'],
            'set' => ['set'],
            'exists' => ['exists'],
            'unset' => ['unset'],
        ];
    }

    /** @param 'get'|'set'|'exists'|'unset' $operation */
    #[DataProvider('offsetOperations')]
    public function testUnsupportedOffsetsFailExplicitlyBeforeChangingData(string $operation): void
    {
        $resource = fopen('php://memory', 'r+');
        $closedResource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        $this->assertIsResource($closedResource);
        fclose($closedResource);
        $translator = TranslatorFactory::create('es', resourceProviders: [new ContainerTranslationResourceProvider()]);
        $data = ['Array' => 'must not be selected', 'keep' => ['value' => 1]];
        $bag = new Bag($data);
        $collection = $bag->collection();

        try {
            foreach ([[], ['keep'], new stdClass(), static fn (): string => 'keep', $resource, $closedResource] as $offset) {
                try {
                    match ($operation) {
                        'get' => $bag->offsetGet($offset),
                        'set' => $bag->offsetSet($offset, 'replacement'),
                        'exists' => $bag->offsetExists($offset),
                        'unset' => $bag->offsetUnset($offset),
                    };
                    $this->fail('An unsupported offset must be rejected.');
                } catch (TranslatableInvalidArgumentException $exception) {
                    $type = get_debug_type($offset);
                    $this->assertSame(
                        'The container offset must be a bool, int, float, string, Stringable, or null; ' . $type . ' given.',
                        $exception->getMessage()
                    );
                    $this->assertSame(
                        'El índice del contenedor debe ser bool, int, float, string, Stringable o null; se recibió ' . $type . '.',
                        $exception->trans($translator)
                    );
                }
                $this->assertSame($data, $bag->all());
                $this->assertSame($collection, $bag->collection());
            }
            $this->assertSame(10, fwrite($resource, 'still open'));
        } finally {
            fclose($resource);
        }
    }
}

final class ContainerOffsetString implements Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
