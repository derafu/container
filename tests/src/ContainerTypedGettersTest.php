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

use ArrayObject;
use Derafu\Array\Exception\MissingValueException as ArrayMissingValueException;
use Derafu\Array\Exception\TypeMismatchException;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Bag;
use Derafu\Container\Exception\MissingValueException;
use Derafu\Container\Exception\UnexpectedTypeException;
use Derafu\Container\Journal;
use Derafu\Container\Store;
use Derafu\Container\Vault;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

enum TypedGettersSuit: string
{
    case Hearts = 'hearts';
    case Spades = 'spades';
}

/** Container whose get() wraps arrays, like `Derafu\Config\Options` does. */
final class TypedGettersWrappingBag extends Bag
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = parent::get($key, $default);

        return is_array($value) ? new ArrayObject($value) : $value;
    }
}

#[CoversClass(AbstractContainer::class)]
#[CoversClass(Bag::class)]
#[CoversClass(Journal::class)]
#[CoversClass(Store::class)]
#[CoversClass(Vault::class)]
#[CoversClass(MissingValueException::class)]
#[CoversClass(UnexpectedTypeException::class)]
final class ContainerTypedGettersTest extends TestCase
{
    private Bag $bag;

    protected function setUp(): void
    {
        $this->bag = new Bag([
            'string' => 'text',
            'int' => 993,
            'float' => 1.5,
            'bool' => false,
            'null' => null,
            'array' => ['a' => 1, 'b' => 2],
            'list' => ['x', 'y'],
            'ints' => [1, 2],
            'enum' => 'hearts',
            'object' => new stdClass(),
            'nested' => ['inner' => ['port' => 25]],
        ]);
    }

    public function testGettersReturnTheValueWhenTheTypeMatches(): void
    {
        $this->assertSame('text', $this->bag->getReader()->getString('string'));
        $this->assertSame(993, $this->bag->getReader()->getInt('int'));
        $this->assertSame(1.5, $this->bag->getReader()->getFloat('float'));
        $this->assertFalse($this->bag->getReader()->getBool('bool'));
        $this->assertSame(['a' => 1, 'b' => 2], $this->bag->getReader()->getArray('array'));
        $this->assertSame(['x', 'y'], $this->bag->getReader()->getList('list'));
        $this->assertSame(25, $this->bag->getReader()->getInt('nested.inner.port'));
    }

    public function testRequiredGettersReturnTheValueWhenTheTypeMatches(): void
    {
        $this->assertSame('text', $this->bag->getReader()->requireString('string'));
        $this->assertSame(993, $this->bag->getReader()->requireInt('int'));
        $this->assertSame(1.5, $this->bag->getReader()->requireFloat('float'));
        $this->assertFalse($this->bag->getReader()->requireBool('bool'));
        $this->assertSame(['a' => 1, 'b' => 2], $this->bag->getReader()->requireArray('array'));
        $this->assertSame(['x', 'y'], $this->bag->getReader()->requireList('list'));
    }

    public function testGettersReturnTheDefaultWhenTheKeyIsNotDefinedOrNull(): void
    {
        foreach (['missing', 'null'] as $key) {
            $this->assertNull($this->bag->getReader()->getString($key));
            $this->assertSame('d', $this->bag->getReader()->getString($key, 'd'));
            $this->assertSame(7, $this->bag->getReader()->getInt($key, 7));
            $this->assertSame(2.5, $this->bag->getReader()->getFloat($key, 2.5));
            $this->assertTrue($this->bag->getReader()->getBool($key, true));
            $this->assertSame([1], $this->bag->getReader()->get($key, ValueType::array(), [1]));
            $this->assertSame([1], $this->bag->getReader()->get($key, ValueType::list(), [1]));
            $this->assertNull($this->bag->getReader()->get($key, ValueType::backedEnum(TypedGettersSuit::class)));
            $this->assertNull($this->bag->getReader()->get($key, ValueType::instanceOf(stdClass::class)));
            $this->assertNull($this->bag->getContainer($key));
        }
    }

    public function testFalsyValuesAreNotConfusedWithMissingValues(): void
    {
        $bag = new Bag(['int' => 0, 'string' => '', 'bool' => false, 'array' => []]);

        $this->assertSame(0, $bag->getReader()->getInt('int', 9));
        $this->assertSame('', $bag->getReader()->getString('string', 'd'));
        $this->assertFalse($bag->getReader()->getBool('bool', true));
        $this->assertSame([], $bag->getReader()->get('array', ValueType::array(), [1]));
        $this->assertSame(0, $bag->getReader()->requireInt('int'));
    }

    /** @return array<string, array{string}> */
    public static function requiredGetters(): array
    {
        return [
            'string' => ['requireString'],
            'int' => ['requireInt'],
            'float' => ['requireFloat'],
            'bool' => ['requireBool'],
            'array' => ['requireArray'],
            'list' => ['requireList'],
            'container' => ['requireContainer'],
        ];
    }

    #[DataProvider('requiredGetters')]
    public function testRequiredGettersThrowWhenTheValueIsNotDefined(string $method): void
    {
        foreach (['missing', 'null'] as $key) {
            try {
                $target = str_contains($method, 'Container') ? $this->bag : $this->bag->getReader();
                $target->$method($key);
                $this->fail('MissingValueException expected.');
            } catch (ArrayMissingValueException|MissingValueException $exception) {
                $this->assertSame(
                    $method === 'requireContainer'
                        ? 'The value of {key} is required but it is not defined.'
                        : 'The key "{key}" is required.',
                    $exception->getTranslatableMessage()->getMessage()
                );
            }
        }
    }

    public function testRequiredEnumAndObjectThrowWhenTheValueIsNotDefined(): void
    {
        $this->expectException(ArrayMissingValueException::class);
        $this->bag->getReader()->require('missing', ValueType::backedEnum(TypedGettersSuit::class));
    }

    public function testRequiredObjectThrowsWhenTheValueIsNotDefined(): void
    {
        $this->expectException(ArrayMissingValueException::class);
        $this->bag->getReader()->require('missing', ValueType::instanceOf(stdClass::class));
    }

    /** @return array<string, array{string, string}> */
    public static function wrongTypes(): array
    {
        return [
            'string gets int' => ['getString', 'int'],
            'int gets string' => ['getInt', 'string'],
            'int does not accept float' => ['getInt', 'float'],
            'int does not accept numeric string' => ['getInt', 'string'],
            'float gets string' => ['getFloat', 'string'],
            'bool gets int' => ['getBool', 'int'],
            'array gets string' => ['getArray', 'string'],
            'list gets assoc array' => ['getList', 'array'],
            'container gets string' => ['getContainer', 'string'],
        ];
    }

    #[DataProvider('wrongTypes')]
    public function testGettersThrowWhenTheTypeDoesNotMatch(string $method, string $key): void
    {
        $this->expectException($method === 'getContainer' ? UnexpectedTypeException::class : TypeMismatchException::class);
        $target = $method === 'getContainer' ? $this->bag : $this->bag->getReader();
        $target->$method($key);
    }

    #[DataProvider('wrongTypes')]
    public function testRequiredGettersThrowWhenTheTypeDoesNotMatch(string $method, string $key): void
    {
        $this->expectException($method === 'getContainer' ? UnexpectedTypeException::class : TypeMismatchException::class);
        $target = $method === 'getContainer' ? $this->bag : $this->bag->getReader();
        $target->{str_replace('get', 'require', $method)}($key);
    }

    public function testWrongTypeIsNotReplacedByTheDefault(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->bag->getReader()->getInt('string', 7);
    }

    public function testStringsAreNeverConvertedToNumbers(): void
    {
        $bag = new Bag(['port' => '993', 'flag' => '1']);

        $this->expectException(TypeMismatchException::class);
        $bag->getReader()->getInt('port');
    }

    public function testBoolIsNeverConvertedFromString(): void
    {
        $bag = new Bag(['flag' => 'true']);

        $this->expectException(TypeMismatchException::class);
        $bag->getReader()->getBool('flag');
    }

    public function testFloatAcceptsIntAndReturnsFloat(): void
    {
        $this->assertSame(993.0, $this->bag->getReader()->getAsFloat('int'));
    }

    public function testExceptionMessageHasTheKeyTheExpectedAndTheGivenType(): void
    {
        try {
            $this->bag->getReader()->getInt('string');
            $this->fail('UnexpectedTypeException expected.');
        } catch (TypeMismatchException $exception) {
            $this->assertSame(
                'The key "{key}" must be of type {expected}, {given} given.',
                $exception->getTranslatableMessage()->getMessage()
            );
            $this->assertSame(
                ['key' => 'string', 'expected' => 'int', 'given' => 'string'],
                $exception->getTranslatableMessage()->getParameters()
            );
        }
    }

    public function testTypedElementsOfArraysAndLists(): void
    {
        $this->assertSame(['x', 'y'], $this->bag->getReader()->getListOf('list', ValueType::string()));
        $this->assertSame([1, 2], $this->bag->getReader()->requireListOf('ints', ValueType::int()));
        $this->assertSame(['a' => 1, 'b' => 2], $this->bag->getReader()->getArrayOf('array', ValueType::int()));
        $this->assertSame([1.0, 2.0], $this->bag->getReader()->requireListOf('ints', ValueType::toFloat()));
        $this->assertSame(
            [['a' => 1]],
            (new Bag(['rows' => [['a' => 1]]]))->getReader()->requireListOf('rows', ValueType::array())
        );
        $object = new stdClass();
        $this->assertSame(
            [$object],
            (new Bag(['objects' => [$object]]))->getReader()->requireListOf('objects', ValueType::instanceOf(stdClass::class))
        );
        $this->assertSame(
            [true, false],
            (new Bag(['flags' => [true, false]]))->getReader()->requireListOf('flags', ValueType::bool())
        );
    }

    public function testWrongElementTypeNamesTheOffendingIndex(): void
    {
        try {
            $this->bag->getReader()->getListOf('ints', ValueType::string());
            $this->fail('UnexpectedTypeException expected.');
        } catch (TypeMismatchException $exception) {
            $this->assertSame('ints.0', $exception->getTranslatableMessage()->getParameters()['key']);
            $this->assertSame('string', $exception->getTranslatableMessage()->getParameters()['expected']);
        }
    }

    public function testEnumAcceptsCasesAndBackingValues(): void
    {
        $this->assertSame(TypedGettersSuit::Hearts, $this->bag->getReader()->get('enum', ValueType::backedEnum(TypedGettersSuit::class)));
        $this->assertSame(TypedGettersSuit::Hearts, $this->bag->getReader()->require('enum', ValueType::backedEnum(TypedGettersSuit::class)));

        $bag = new Bag(['suit' => TypedGettersSuit::Spades]);
        $this->assertSame(TypedGettersSuit::Spades, $bag->getReader()->require('suit', ValueType::backedEnum(TypedGettersSuit::class)));

        $this->assertSame(
            TypedGettersSuit::Spades,
            $this->bag->getReader()->get('missing', ValueType::backedEnum(TypedGettersSuit::class), TypedGettersSuit::Spades)
        );
    }

    public function testEnumRejectsUnknownBackingValuesAndOtherTypes(): void
    {
        $this->expectException(TypeMismatchException::class);
        (new Bag(['suit' => 'clubs']))->getReader()->get('suit', ValueType::backedEnum(TypedGettersSuit::class));
    }

    public function testEnumRejectsOtherTypes(): void
    {
        $this->expectException(TypeMismatchException::class);
        (new Bag(['suit' => 1.5]))->getReader()->get('suit', ValueType::backedEnum(TypedGettersSuit::class));
    }

    public function testObjectChecksTheClass(): void
    {
        $this->assertInstanceOf(stdClass::class, $this->bag->getReader()->get('object', ValueType::instanceOf(stdClass::class)));
        $this->assertInstanceOf(stdClass::class, $this->bag->getReader()->require('object', ValueType::instanceOf(stdClass::class)));

        $this->expectException(TypeMismatchException::class);
        $this->bag->getReader()->get('object', ValueType::instanceOf(ArrayObject::class));
    }

    public function testObjectRejectsNonObjects(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->bag->getReader()->get('string', ValueType::instanceOf(stdClass::class));
    }

    public function testContainerReturnsAContainerOfTheSameKind(): void
    {
        $container = $this->bag->requireContainer('nested');

        $this->assertInstanceOf(Bag::class, $container);
        $this->assertSame(25, $container->getReader()->requireInt('inner.port'));
        $this->assertSame(['inner' => ['port' => 25]], $container->all());
        $this->assertSame(
            25,
            $container->requireContainer('inner')->getReader()->requireInt('port')
        );
    }

    public function testContainerFromDefaultData(): void
    {
        $container = $this->bag->getContainer('missing', ['a' => 1]);

        $this->assertInstanceOf(Bag::class, $container);
        $this->assertSame(1, $container->getReader()->getInt('a'));
    }

    public function testContainerIsAChildCopyAndDoesNotWriteBack(): void
    {
        $container = $this->bag->requireContainer('nested');
        $container->set('inner.port', 99);

        $this->assertSame(25, $this->bag->getReader()->getInt('nested.inner.port'));
    }

    public function testContainerWorksForVaultAndJournalAndStore(): void
    {
        $data = ['child' => ['a' => 1]];

        $this->assertInstanceOf(Vault::class, (new Vault($data))->requireContainer('child'));
        $this->assertInstanceOf(Journal::class, (new Journal($data))->requireContainer('child'));
        $this->assertInstanceOf(Store::class, (new Store($data))->requireContainer('child'));
    }

    public function testGettersReadTheStoredValueEvenIfGetWrapsArrays(): void
    {
        $bag = new TypedGettersWrappingBag(['array' => ['a' => 1], 'list' => [1, 2]]);

        $this->assertInstanceOf(ArrayObject::class, $bag->get('array'));
        $this->assertSame(['a' => 1], $bag->getReader()->getArray('array'));
        $this->assertSame([1, 2], $bag->getReader()->requireListOf('list', ValueType::int()));
        $this->assertInstanceOf(TypedGettersWrappingBag::class, $bag->requireContainer('array'));
    }

    public function testGettersWorkThroughTheVaultSchemaDefaults(): void
    {
        $vault = new Vault(
            ['host' => 'imap.example.com'],
            [
                'host' => ['types' => 'string', 'required' => true],
                'port' => ['types' => 'int', 'default' => 993],
            ]
        );

        $this->assertSame('imap.example.com', $vault->getReader()->requireString('host'));
        $this->assertSame(993, $vault->getReader()->requireInt('port'));
    }
}
