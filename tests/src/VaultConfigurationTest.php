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

use Closure;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Translation\ContainerTranslationResourceProvider;
use Derafu\Container\Vault;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\Options;

#[CoversClass(Vault::class)]
#[CoversClass(AbstractContainer::class)]
#[CoversClass(ContainerTranslationResourceProvider::class)]
final class VaultConfigurationTest extends TestCase
{
    /** @return array<string, array{array<array-key, mixed>, string, string}> */
    public static function invalidConfigurations(): array
    {
        $cases = [];
        foreach ([null, false, 0, 'string', new stdClass(), static fn (): string => 'value'] as $value) {
            $type = get_debug_type($value);
            $cases['field configuration: ' . $type] = [
                ['value' => $value],
                'The configuration of field value must be an array; ' . $type . ' given.',
                'La configuración del campo value debe ser un arreglo; se recibió ' . $type . '.',
            ];
        }
        foreach ([true, 7, 1.5, new stdClass()] as $value) {
            $type = get_debug_type($value);
            $cases['types container: ' . $type] = [
                ['value' => ['types' => $value]],
                'The types of field value must be a string or an array of strings; ' . $type . ' given.',
                'Los tipos del campo value deben ser un string o un arreglo de strings; se recibió ' . $type . '.',
            ];
        }
        foreach ([null, false, 7, [], new stdClass()] as $value) {
            $type = get_debug_type($value);
            $cases['types entry: ' . $type] = [
                ['value' => ['types' => ['valid' => 'string', 'invalid' => $value]]],
                'The types entry invalid of field value must be a string; ' . $type . ' given.',
                'La entrada invalid de types del campo value debe ser string; se recibió ' . $type . '.',
            ];
        }
        foreach (['true', 'false', 0, 1, 0.0, [], new stdClass()] as $value) {
            $type = get_debug_type($value);
            $cases['undefined keys flag: ' . var_export($value, true)] = [
                ['__allowUndefinedKeys' => $value],
                'The __allowUndefinedKeys setting must be a bool; ' . $type . ' given.',
                'La opción __allowUndefinedKeys debe ser bool; se recibió ' . $type . '.',
            ];
        }

        return $cases;
    }

    /** @param array<array-key, mixed> $schema */
    #[DataProvider('invalidConfigurations')]
    public function testConstructorRejectsInvalidConfigurationWithATranslatableError(array $schema, string $message, string $translation): void
    {
        $this->assertConfigurationError(
            static fn (): Vault => new Vault(['value' => 'unchanged'], $schema),
            $message,
            $translation
        );
    }

    /** @param array<array-key, mixed> $schema */
    #[DataProvider('invalidConfigurations')]
    public function testSetSchemaRejectsInvalidConfigurationWithoutChangingData(array $schema, string $message, string $translation): void
    {
        $vault = new Vault(['value' => 'unchanged']);
        $collection = $vault->collection();

        $this->assertConfigurationError(
            static fn () => $vault->setSchema($schema),
            $message,
            $translation
        );
        $this->assertSame(['value' => 'unchanged'], $vault->all());
        $this->assertSame($collection, $vault->collection());
        $this->assertSame([], $vault->getSchema());
    }

    public function testResourcesAreRejectedWithoutBeingClosed(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            foreach ([
                ['value' => $resource],
                ['value' => ['types' => $resource]],
                ['value' => ['types' => [$resource]]],
                ['__allowUndefinedKeys' => $resource],
            ] as $schema) {
                try {
                    new Vault([], $schema);
                    $this->fail('A resource is not valid schema configuration.');
                } catch (TranslatableInvalidArgumentException $exception) {
                    $this->assertStringContainsString('resource (stream)', $exception->getMessage());
                }
            }
            $this->assertSame(10, fwrite($resource, 'still open'));
        } finally {
            fclose($resource);
        }
    }

    /** @return array<string, array{array<array-key, mixed>, bool, bool}> */
    public static function undefinedKeysPolicies(): array
    {
        return [
            'missing inherits true' => [[], true, true],
            'missing inherits false' => [[], false, false],
            'null inherits true' => [['__allowUndefinedKeys' => null], true, true],
            'null inherits false' => [['__allowUndefinedKeys' => null], false, false],
            'true overrides false' => [['__allowUndefinedKeys' => true], false, true],
            'false overrides true' => [['__allowUndefinedKeys' => false], true, false],
        ];
    }

    /** @param array<array-key, mixed> $settings */
    #[DataProvider('undefinedKeysPolicies')]
    public function testUndefinedKeysPolicyKeepsItsPrecedence(array $settings, bool $argument, bool $allowed): void
    {
        $schema = $settings + ['known' => ['types' => 'string']];
        $data = ['known' => 'valid', 'extra' => 42];
        if (!$allowed) {
            $this->expectException(UndefinedOptionsException::class);
        }
        $vault = new Vault($data, $schema, $argument);
        $vault->validate($argument);
        $this->assertSame($data, $vault->all());
    }

    public function testNestedSchemasInheritAndCanOverrideTheUndefinedKeysPolicy(): void
    {
        $schema = [
            '__allowUndefinedKeys' => true,
            'user' => ['schema' => ['name' => ['types' => 'string']]],
        ];
        $data = ['user' => ['name' => 'Ada', 'extra' => 42], 'root_extra' => true];
        $vault = new Vault($data, $schema);
        $vault->validate();
        $this->assertSame($data, $vault->all());

        $original = $schema;
        $schema['user']['schema']['__allowUndefinedKeys'] = false;
        try {
            $vault->setSchema($schema);
            $this->fail('The nested policy must reject undefined keys.');
        } catch (UndefinedOptionsException $exception) {
            $this->assertStringContainsString('extra', $exception->getMessage());
            $this->assertSame($data, $vault->all());
            $this->assertSame($original, $vault->getSchema());
        }
    }

    public function testNestedConfigurationsAreCheckedByTheSameValidation(): void
    {
        $this->assertConfigurationError(
            static fn (): Vault => new Vault(['user' => []], ['user' => ['schema' => ['name' => 'invalid']]]),
            'The configuration of field name must be an array; string given.',
            'La configuración del campo name debe ser un arreglo; se recibió string.'
        );
        $this->assertConfigurationError(
            static fn (): Vault => new Vault(['user' => []], ['user' => ['schema' => ['name' => ['types' => [7]]]]]),
            'The types entry 0 of field name must be a string; int given.',
            'La entrada 0 de types del campo name debe ser string; se recibió int.'
        );
        $this->assertConfigurationError(
            static fn (): Vault => new Vault(['user' => []], ['user' => ['schema' => ['__allowUndefinedKeys' => 'false']]]),
            'The __allowUndefinedKeys setting must be a bool; string given.',
            'La opción __allowUndefinedKeys debe ser bool; se recibió string.'
        );
    }

    /** @return array<string, array{mixed}> */
    public static function emptyTypes(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'zero' => [0],
            'zero float' => [0.0],
            'empty string' => [''],
            'zero string' => ['0'],
            'empty array' => [[]],
        ];
    }

    #[DataProvider('emptyTypes')]
    public function testEmptyTypesKeepTheirExistingUnrestrictedBehavior(mixed $types): void
    {
        $data = ['value' => ['arbitrary' => true]];
        $schema = ['value' => ['types' => $types, 'default' => null]];
        $vault = new Vault($data, $schema);
        $vault->validate();
        $this->assertSame($data, $vault->all());
        $this->assertSame($schema, $vault->getSchema());
    }

    public function testValidTypesChoicesDefaultsAndNormalizersKeepWorking(): void
    {
        $vault = new Vault(['price' => '12.50', 'status' => 'active'], [
            'price' => [
                'types' => ['text' => 'string', 'number' => 'float'],
                'required' => true,
                'normalizer' => static fn (Options $options, float|string $value): float => (float) $value,
            ],
            'status' => ['types' => 'string', 'choices' => ['active', 'inactive']],
            'note' => ['default' => null],
        ]);
        $vault->validate();
        $this->assertSame(['note' => null, 'price' => 12.5, 'status' => 'active'], $vault->all());
        $this->expectException(InvalidOptionsException::class);
        $vault->set('status', 'invalid');
    }

    /** @param Closure(): mixed $operation */
    private function assertConfigurationError(Closure $operation, string $message, string $translation): void
    {
        try {
            $operation();
            $this->fail('Invalid configuration must be rejected.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new ContainerTranslationResourceProvider()]);
            $this->assertSame($translation, $exception->trans($translator));
        }
    }
}
