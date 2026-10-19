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

use ArrayAccess;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Bag;
use Derafu\Container\Store;
use Derafu\Container\Translation\ContainerTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Store::class)]
#[CoversClass(AbstractContainer::class)]
#[CoversClass(Bag::class)]
#[CoversClass(ContainerTranslationResourceProvider::class)]
final class StoreValidationTest extends TestCase
{
    /** @return array<string, array{mixed, int}> */
    public static function invalidJsonValues(): array
    {
        return [
            'invalid UTF-8' => ["\xB1\x31", JSON_ERROR_UTF8],
            'infinity' => [INF, JSON_ERROR_INF_OR_NAN],
            'not a number' => [NAN, JSON_ERROR_INF_OR_NAN],
        ];
    }

    #[DataProvider('invalidJsonValues')]
    public function testInvalidJsonDataPreservesTheEncodingCause(mixed $value, int $errorCode): void
    {
        try {
            new Store(['value' => $value], ['properties' => ['value' => true]]);
            $this->fail('Data that cannot be encoded must be rejected.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $cause = $exception->getPrevious();
            $this->assertInstanceOf(JsonException::class, $cause);
            $this->assertSame($errorCode, $cause->getCode());
            $this->assertSame('Failed to convert the container data to JSON: ' . $cause->getMessage(), $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new ContainerTranslationResourceProvider()]);
            $this->assertSame('No se pudieron convertir los datos del contenedor a JSON: ' . $cause->getMessage(), $exception->trans($translator));
        }
    }

    #[DataProvider('invalidJsonValues')]
    public function testInvalidJsonSchemaPreservesTheEncodingCause(mixed $value, int $errorCode): void
    {
        $store = new Store(['keep' => 'unchanged']);
        $store->setSchema(['description' => $value]);

        try {
            $store->getSchema();
            $this->fail('A schema that cannot be encoded must be rejected.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $cause = $exception->getPrevious();
            $this->assertInstanceOf(JsonException::class, $cause);
            $this->assertSame($errorCode, $cause->getCode());
            $this->assertSame('Failed to convert the container schema to JSON: ' . $cause->getMessage(), $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new ContainerTranslationResourceProvider()]);
            $this->assertSame('No se pudo convertir el esquema del contenedor a JSON: ' . $cause->getMessage(), $exception->trans($translator));
            $this->assertSame(['keep' => 'unchanged'], $store->all());
        }
    }

    public function testValidationRejectsResourcesWithoutChangingOrClosingThem(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        $store = new Store(['value' => 'valid'], ['properties' => ['value' => true]]);
        $store->set('value', $resource);

        try {
            try {
                $store->validate();
                $this->fail('A resource cannot be encoded as JSON.');
            } catch (TranslatableInvalidArgumentException $exception) {
                $cause = $exception->getPrevious();
                $this->assertInstanceOf(JsonException::class, $cause);
                $this->assertSame(JSON_ERROR_UNSUPPORTED_TYPE, $cause->getCode());
                $this->assertSame($resource, $store->get('value'));
                $this->assertSame(10, fwrite($resource, 'still open'));
            }
        } finally {
            fclose($resource);
        }
    }

    public function testRecursiveDataReportsAJsonError(): void
    {
        $data = [];
        $data['value'] = &$data;

        try {
            new Store($data, ['properties' => ['value' => true]]);
            $this->fail('Recursive data cannot be encoded as JSON.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $cause = $exception->getPrevious();
            $this->assertInstanceOf(JsonException::class, $cause);
            $this->assertSame(JSON_ERROR_RECURSION, $cause->getCode());
        }
    }

    public function testANestedScalarIsReportedByTheSchemaValidator(): void
    {
        $store = new Store(['user' => ['name' => 'Ada']], [
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string', 'default' => 'default name']],
                ],
            ],
        ]);
        $store->set('user', 'invalid scalar');

        try {
            $store->validate();
            $this->fail('A scalar must not satisfy an object schema.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertStringContainsString('object', $exception->getMessage());
            $this->assertStringContainsString('/user', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertSame(['user' => 'invalid scalar'], $store->all());
        }
    }

    public function testBooleanPropertySchemasRemainSupported(): void
    {
        $store = new Store(['value' => ['anything']], ['properties' => ['value' => true]]);
        $store->validate();
        $this->assertSame(['value' => ['anything']], $store->all());
        $store->setSchema(['properties' => ['value' => false]]);
        $this->expectException(TranslatableInvalidArgumentException::class);
        $store->validate();
    }

    public function testValidationErrorsTranslateTheirWrapperAndSection(): void
    {
        $store = new Store(['age' => 18], ['properties' => ['age' => ['type' => 'integer']]]);
        $store->set('age', 'invalid');

        try {
            $store->validate();
            $this->fail('An invalid field must be reported.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertStringStartsWith('Failed to validate the data against the JSON schema. ', $exception->getMessage());
            $this->assertStringContainsString(' in /age.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new ContainerTranslationResourceProvider()]);
            $translated = $exception->trans($translator);
            $this->assertStringStartsWith('No se pudieron validar los datos contra el esquema JSON. ', $translated);
            $this->assertStringContainsString(' en /age.', $translated);
            $this->assertStringContainsString('integer', $translated);
            $this->assertSame(['age' => 'invalid'], $store->all());
        }
    }

    public function testNonTraversableArrayAccessHasATranslatableError(): void
    {
        try {
            new Bag(new NonTraversableContainerData());
            $this->fail('Non-traversable input cannot be converted to an array.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('ArrayAccess must implement Traversable to be convertible.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new ContainerTranslationResourceProvider()]);
            $this->assertSame('ArrayAccess debe implementar Traversable para ser convertible.', $exception->trans($translator));
        }
    }
}

/** @implements ArrayAccess<mixed, mixed> */
final class NonTraversableContainerData implements ArrayAccess
{
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }
}
