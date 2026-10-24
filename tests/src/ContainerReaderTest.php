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
use Derafu\Container\Bag;
use Derafu\Container\Contract\ContainerInterface;
use Derafu\Container\Exception\UnexpectedTypeException;
use Derafu\Container\Journal;
use Derafu\Container\Store;
use Derafu\Container\Vault;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Value\Exception\TypeMismatchException as ValueTypeMismatchException;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(UnexpectedTypeException::class)]
#[CoversClass(AbstractContainer::class)]
#[CoversClass(Bag::class)]
#[CoversClass(Journal::class)]
#[CoversClass(Store::class)]
#[CoversClass(Vault::class)]
final class ContainerReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(array<array-key, mixed>): ContainerInterface}>
     */
    public static function containers(): iterable
    {
        yield 'bag' => [static fn (array $data): ContainerInterface => new Bag($data)];
        yield 'journal' => [static fn (array $data): ContainerInterface => new Journal($data)];
        yield 'vault' => [static fn (array $data): ContainerInterface => new Vault($data)];
        yield 'store' => [static fn (array $data): ContainerInterface => new Store($data)];
    }

    /**
     * @param Closure(array<array-key, mixed>): ContainerInterface $create
     */
    #[DataProvider('containers')]
    public function testAllContainersExposeRawArraysAndIntegerKeys(Closure $create): void
    {
        $data = [8 => 'Alice', 'items' => [3 => ['active' => false]], 'note' => null];
        $reader = $create($data)->getReader();

        $this->assertSame($data, $reader->toArray());
        $this->assertSame('Alice', $reader->requireString(8));
        $this->assertFalse($reader->requireReader('items')->requireReader(3)->requireBool('active'));
        $this->assertTrue($reader->hasKey('note'));
        $this->assertFalse($reader->hasValue('note'));
    }

    public function testReadersAreSnapshotsAndNewReadersSeeMutations(): void
    {
        $bag = new Bag(['connection' => ['port' => 5432]]);
        $before = $bag->getReader();
        $bag->set('connection.port', 3306);
        $after = $bag->getReader();

        $this->assertNotSame($before, $after);
        $this->assertSame(5432, $before->requireReader('connection')->requireInt('port'));
        $this->assertSame(3306, $after->requireReader('connection')->requireInt('port'));
        $this->assertSame(3306, $bag->getReader()->requireInt('connection.port'));
    }

    public function testReadersSeeResolvedDefaultsAndSubsequentValidatedValues(): void
    {
        $vault = new Vault([], ['port' => ['types' => 'int', 'default' => 5432]]);
        $before = $vault->getReader();

        $vault->set('port', 3306);
        $this->assertSame(5432, $before->requireInt('port'));
        $this->assertSame(3306, $vault->getReader()->requireInt('port'));
    }

    public function testReaderValidationUsesArrayExceptions(): void
    {
        $bag = new Bag(['port' => '5432']);

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "port" must be of type int, string given.');

        $bag->getReader()->requireInt('port');
    }

    public function testSnapshotsDoNotCloneNestedObjects(): void
    {
        $object = new stdClass();
        $bag = new Bag(['object' => $object]);

        $this->assertSame($object, $bag->getReader()->require('object', ValueType::instanceOf(stdClass::class)));
    }

    public function testReadersUseDotPathsLiteralPrecedenceAndExplicitConversions(): void
    {
        $bag = new Bag([
            'connection.port' => null,
            'connection' => ['port' => 5432, 'enabled' => 'off'],
            'amounts' => ['12.5', 2],
        ]);
        $reader = $bag->getReader();

        $this->assertSame(8080, $reader->getInt('connection.port', 8080));
        $this->assertTrue($reader->hasKey('connection.port'));
        $this->assertFalse($reader->hasValue('connection.port'));
        $this->assertSame(5432, $reader->requireReader('connection')->requireInt('port'));
        $this->assertFalse($reader->requireAsBool('connection.enabled'));
        $this->assertSame([12.5, 2.0], $reader->requireListOf('amounts', ValueType::toFloat()));
        $this->assertSame(['12.5', 2], $bag->all()['amounts']);
    }

    /**
     * @param Closure(array<array-key, mixed>): ContainerInterface $create
     */
    #[DataProvider('containers')]
    public function testChildContainersKeepSelectorNavigationAndCopySemantics(Closure $create): void
    {
        $parent = $create(['children' => [['port' => 5432]]]);
        $child = $parent->requireContainer('children[0]');

        $this->assertSame($parent::class, $child::class);
        $this->assertSame(5432, $child->getReader()->requireInt('port'));
        $child->set('port', 3306);
        $this->assertSame(3306, $child->getReader()->requireInt('port'));
        $this->assertSame(5432, $parent->get('children[0].port'));

        foreach (['missing', 'empty'] as $key) {
            $parent->set('empty', null);
            $default = $parent->getContainer($key, ['port' => 993]);
            $this->assertNotNull($default);
            $this->assertSame($parent::class, $default::class);
            $this->assertSame(993, $default->getReader()->requireInt('port'));
            $this->assertNull($parent->getContainer($key));
        }
    }

    public function testInvalidChildDataKeepsTheContainerErrorAndValueCause(): void
    {
        $bag = new Bag(['children' => [42]]);

        try {
            $bag->getContainer('children[0]', ['fallback' => true]);
            $this->fail('A default must not replace an invalid child value.');
        } catch (UnexpectedTypeException $exception) {
            $this->assertSame('The value of children[0] must be of type array; int given.', $exception->getMessage());
            $cause = $exception->getPrevious();
            $this->assertInstanceOf(ValueTypeMismatchException::class, $cause);
            $this->assertSame('children[0]', $cause->getTranslatableMessage()->getParameters()['path']);
            $this->assertSame('array', $cause->getTranslatableMessage()->getParameters()['expected']);
        }
        $this->assertSame(['children' => [42]], $bag->all());
    }
}
