<?php

declare(strict_types=1);

/**
 * Derafu: Container - Lightweight and Flexible PHP Containers.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContainer;

use ArrayObject;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Bag;
use Derafu\Container\Contract\ContainerInterface;
use Derafu\Container\Journal;
use Derafu\Container\Store;
use Derafu\Container\Vault;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Throwable;

#[CoversClass(AbstractContainer::class)]
#[CoversClass(Bag::class)]
#[CoversClass(Journal::class)]
#[CoversClass(Store::class)]
#[CoversClass(Vault::class)]
final class ContainerFromContainerTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private const DATA = [
        'name' => 'Ada',
        'options' => ['strategy' => 'default', 'list' => [1, 2]],
    ];

    /**
     * Every kind of container as a source and as a target.
     *
     * @return array<string, array{class-string<ContainerInterface>, class-string<ContainerInterface>}>
     */
    public static function containerPairs(): array
    {
        $classes = [Bag::class, Journal::class, Store::class, Vault::class];

        $pairs = [];
        foreach ($classes as $source) {
            foreach ($classes as $target) {
                $pairs[self::short($source) . ' to ' . self::short($target)] = [$source, $target];
            }
        }

        return $pairs;
    }

    private static function short(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    /**
     * @param class-string<ContainerInterface> $class
     * @param array<array-key, mixed>|ContainerInterface $data
     */
    private function create(string $class, array|ContainerInterface $data): ContainerInterface
    {
        return new $class($data);
    }

    /**
     * @param class-string<ContainerInterface> $source
     * @param class-string<ContainerInterface> $target
     */
    #[DataProvider('containerPairs')]
    public function testContainerIsCreatedFromAnotherContainer(string $source, string $target): void
    {
        $from = $this->create($source, self::DATA);

        $to = $this->create($target, $from);

        $this->assertSame(self::DATA, $to->all());
        $this->assertSame($from->all(), $to->all());
    }

    /**
     * @param class-string<ContainerInterface> $source
     * @param class-string<ContainerInterface> $target
     */
    #[DataProvider('containerPairs')]
    public function testContainerCreatedFromAnotherIsACopy(string $source, string $target): void
    {
        $from = $this->create($source, self::DATA);
        $to = $this->create($target, $from);

        $to->set('name', 'Grace');
        $to->set('options.list', [3]);
        $from->set('other', 'value');

        $this->assertSame('Ada', $from->get('name'));
        $this->assertSame([1, 2], $from->getReader()->getArray('options.list'));
        $this->assertFalse($to->has('other'));
    }

    public function testBagIsReplacedAndMergedWithAContainer(): void
    {
        $other = new Vault(['name' => 'Grace', 'options' => ['strategy' => 'other']]);

        $replaced = (new Bag(['x' => 1]))->replace($other);
        $merged = (new Bag(self::DATA))->merge($other);

        $this->assertSame($other->all(), $replaced->all());
        $this->assertSame('Grace', $merged->get('name'));
        $this->assertSame('other', $merged->get('options.strategy'));
        $this->assertSame([1, 2], $merged->getReader()->getArray('options.list'));
    }

    public function testVaultFromAContainerUsesItsOwnSchema(): void
    {
        $schema = [
            'name' => ['types' => 'string', 'required' => true],
            'strategy' => ['types' => 'string', 'default' => 'default'],
            'debug' => ['types' => 'bool', 'default' => false],
        ];

        $vault = new Vault(new Bag(['name' => 'Ada']), $schema);

        // The defaults are the ones of the schema of the new container.
        $resolved = $vault->all();
        ksort($resolved);
        $this->assertSame(['debug' => false, 'name' => 'Ada', 'strategy' => 'default'], $resolved);

        // The schema of the source is not given: the new container is only
        // valid for its own.
        $source = new Vault(['name' => 'Ada', 'extra' => 1], ['name' => ['types' => 'string'], 'extra' => ['types' => 'int']]);
        $this->expectException(UndefinedOptionsException::class);

        new Vault($source, $schema);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('invalidSources')]
    public function testVaultFromAContainerValidatesItsData(array $data, string $exception): void
    {
        $this->expectException($exception);

        new Vault(new Bag($data), [
            'name' => ['types' => 'string', 'required' => true],
            'debug' => ['types' => 'bool', 'default' => false],
        ]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>, class-string<Throwable>}>
     */
    public static function invalidSources(): array
    {
        return [
            'a value of another type' => [['name' => 'Ada', 'debug' => 'yes'], InvalidOptionsException::class],
            'a key that the schema does not define' => [['name' => 'Ada', 'unknown' => 1], UndefinedOptionsException::class],
        ];
    }

    public function testStoreFromAContainerValidatesItsData(): void
    {
        $schema = ['properties' => ['age' => ['type' => 'integer']]];

        $store = new Store(new Bag(['age' => 18]), $schema);
        $this->assertSame(['age' => 18], $store->all());

        $this->expectException(Throwable::class);

        new Store(new Bag(['age' => 'invalid']), $schema);
    }

    public function testContainerThatIsNotAContainerKeepsItsConversion(): void
    {
        $this->assertSame(['a' => 1], (new Bag(new ArrayObject(['a' => 1])))->all());
    }
}
