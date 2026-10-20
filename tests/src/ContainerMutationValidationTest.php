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
use Derafu\Container\Store;
use Derafu\Container\Vault;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

#[CoversClass(AbstractContainer::class)]
#[CoversClass(Vault::class)]
#[CoversClass(Store::class)]
#[CoversClass(Bag::class)]
final class ContainerMutationValidationTest extends TestCase
{
    /** @return array<array-key, mixed> */
    private static function schema(): array
    {
        return [
            'host' => ['types' => 'string', 'required' => true],
            'port' => ['types' => 'int', 'default' => 993],
        ];
    }

    public function testVaultSetRejectsAnInvalidValueAndKeepsTheStoredData(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema());
        $before = $vault->all();

        try {
            $vault->set('port', 'abc');
            $this->fail('An invalid value must be rejected.');
        } catch (InvalidOptionsException) {
            $this->assertSame($before, $vault->all());
        }
    }

    public function testVaultSetRejectsAnUndefinedKey(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema());

        $this->expectException(UndefinedOptionsException::class);
        $vault->set('endpoint', 'x');
    }

    public function testVaultSetThroughOffsetsIsAlsoValidated(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema());

        $this->expectException(InvalidOptionsException::class);
        $vault['port'] = 'abc';
    }

    public function testVaultSetStoresTheNormalizedData(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], [
            'host' => [
                'types' => 'string',
                'normalizer' => static fn ($options, string $value): string => strtolower($value),
            ],
            'port' => ['types' => 'int', 'default' => 993],
        ]);

        $vault->set('host', 'B.EXAMPLE.COM');

        $this->assertSame('b.example.com', $vault->getString('host'));
        $this->assertSame(993, $vault->getInt('port'));
    }

    public function testVaultSetAcceptsUndefinedKeysWhenThePolicyAllowsIt(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema(), allowUndefinedKeys: true);

        $vault->set('extra', 1);

        $this->assertSame(1, $vault->getInt('extra'));
    }

    public function testVaultClearRejectsRemovingARequiredKeyAndKeepsTheStoredData(): void
    {
        $vault = new Vault(['host' => 'a.example.com', 'port' => 25], self::schema());

        try {
            $vault->clear('host');
            $this->fail('Removing a required key must be rejected.');
        } catch (MissingOptionsException) {
            $this->assertSame('a.example.com', $vault->getString('host'));
            $this->assertSame(25, $vault->getInt('port'));
        }
    }

    public function testVaultClearAcceptsRemovingAKeyWithDefault(): void
    {
        $vault = new Vault(['host' => 'a.example.com', 'port' => 25], self::schema());

        $vault->clear('port');

        $this->assertSame(993, $vault->getInt('port'));
    }

    public function testVaultClearAllIsValidatedLikeAnyOtherChange(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema());

        $this->expectException(MissingOptionsException::class);
        $vault->clear();
    }

    public function testVaultWithoutSchemaAcceptsAnyChange(): void
    {
        $vault = new Vault(['a' => 1]);

        $vault->set('b', 'anything')->clear('a');

        $this->assertSame(['b' => 'anything'], $vault->all());
    }

    public function testVaultSetSchemaChecksAndNormalizesTheStoredData(): void
    {
        $vault = new Vault(['host' => 'a.example.com']);

        $vault->setSchema(self::schema());

        $this->assertSame('a.example.com', $vault->getString('host'));
        $this->assertSame(993, $vault->getInt('port'));
    }

    public function testVaultSetSchemaRejectsDataThatDoesNotComplyAndKeepsTheSchema(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema());

        try {
            $vault->setSchema(['host' => ['types' => 'int'], 'port' => ['types' => 'int']]);
            $this->fail('Stored data that does not comply must be rejected.');
        } catch (InvalidOptionsException) {
            $this->assertSame(self::schema(), $vault->getSchema());
            $this->assertSame('a.example.com', $vault->getString('host'));
            $this->assertSame(993, $vault->getInt('port'));
        }
    }

    public function testVaultValidateUsesThePolicyOfTheConstructor(): void
    {
        $vault = new Vault(['host' => 'a.example.com', 'extra' => 1], self::schema(), allowUndefinedKeys: true);

        $vault->validate();
        $this->assertSame(1, $vault->getInt('extra'));

        $this->expectException(UndefinedOptionsException::class);
        $vault->validate(false);
    }

    public function testVaultValidateAcceptsAValidContainerWithOrWithoutPolicy(): void
    {
        $vault = new Vault(['host' => 'a.example.com'], self::schema());

        $vault->validate();
        $vault->validate(true);

        $this->assertSame('a.example.com', $vault->getString('host'));
    }

    public function testStoreSetRejectsAnInvalidValueAndKeepsTheStoredData(): void
    {
        $store = new Store(['age' => 18], ['properties' => ['age' => ['type' => 'integer']]]);

        try {
            $store->set('age', 'abc');
            $this->fail('An invalid value must be rejected.');
        } catch (TranslatableInvalidArgumentException) {
            $this->assertSame(['age' => 18], $store->all());
        }
    }

    public function testStoreClearRejectsRemovingARequiredKeyAndKeepsTheStoredData(): void
    {
        $store = new Store(['name' => 'Ada'], [
            'required' => ['name'],
            'properties' => ['name' => ['type' => 'string']],
        ]);

        try {
            $store->clear('name');
            $this->fail('Removing a required key must be rejected.');
        } catch (TranslatableInvalidArgumentException) {
            $this->assertSame(['name' => 'Ada'], $store->all());
        }
    }

    public function testStoreSetSchemaChecksTheStoredDataAndKeepsTheSchema(): void
    {
        $store = new Store(['age' => 'abc']);

        try {
            $store->setSchema(['properties' => ['age' => ['type' => 'integer']]]);
            $this->fail('Stored data that does not comply must be rejected.');
        } catch (TranslatableInvalidArgumentException) {
            $this->assertArrayNotHasKey('properties', $store->getSchema());
            $this->assertSame(['age' => 'abc'], $store->all());
        }
    }

    public function testStoreSetSchemaAppliesDefaultsToTheStoredData(): void
    {
        $store = new Store(['name' => 'Ada']);

        $store->setSchema(['properties' => ['lang' => ['type' => 'string', 'default' => 'es']]]);

        $this->assertSame(['name' => 'Ada', 'lang' => 'es'], $store->all());
    }

    public function testStoreWithAnEmptyObjectValidatesAgainstItsSchema(): void
    {
        $store = new Store([], ['properties' => ['lang' => ['type' => 'string', 'default' => 'es']]]);

        $this->assertSame(['lang' => 'es'], $store->all());
    }

    public function testBagIsNotValidated(): void
    {
        $bag = new Bag(['a' => 1]);

        $bag->set('a', 'anything')->clear('a');

        $this->assertSame([], $bag->all());
    }
}
