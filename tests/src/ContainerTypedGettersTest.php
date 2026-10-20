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
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Bag;
use Derafu\Container\Exception\MissingValueException;
use Derafu\Container\Exception\UnexpectedTypeException;
use Derafu\Container\Journal;
use Derafu\Container\Store;
use Derafu\Container\Vault;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
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
        $this->assertSame('text', $this->bag->getString('string'));
        $this->assertSame(993, $this->bag->getInt('int'));
        $this->assertSame(1.5, $this->bag->getFloat('float'));
        $this->assertFalse($this->bag->getBool('bool'));
        $this->assertSame(['a' => 1, 'b' => 2], $this->bag->getArray('array'));
        $this->assertSame(['x', 'y'], $this->bag->getList('list'));
        $this->assertSame(25, $this->bag->getInt('nested.inner.port'));
    }

    public function testRequiredGettersReturnTheValueWhenTheTypeMatches(): void
    {
        $this->assertSame('text', $this->bag->requireString('string'));
        $this->assertSame(993, $this->bag->requireInt('int'));
        $this->assertSame(1.5, $this->bag->requireFloat('float'));
        $this->assertFalse($this->bag->requireBool('bool'));
        $this->assertSame(['a' => 1, 'b' => 2], $this->bag->requireArray('array'));
        $this->assertSame(['x', 'y'], $this->bag->requireList('list'));
    }

    public function testGettersReturnTheDefaultWhenTheKeyIsNotDefinedOrNull(): void
    {
        foreach (['missing', 'null'] as $key) {
            $this->assertNull($this->bag->getString($key));
            $this->assertSame('d', $this->bag->getString($key, 'd'));
            $this->assertSame(7, $this->bag->getInt($key, 7));
            $this->assertSame(2.5, $this->bag->getFloat($key, 2.5));
            $this->assertTrue($this->bag->getBool($key, true));
            $this->assertSame([1], $this->bag->getArray($key, [1]));
            $this->assertSame([1], $this->bag->getList($key, [1]));
            $this->assertNull($this->bag->getEnum($key, TypedGettersSuit::class));
            $this->assertNull($this->bag->getObject($key, stdClass::class));
            $this->assertNull($this->bag->getContainer($key));
        }
    }

    public function testFalsyValuesAreNotConfusedWithMissingValues(): void
    {
        $bag = new Bag(['int' => 0, 'string' => '', 'bool' => false, 'array' => []]);

        $this->assertSame(0, $bag->getInt('int', 9));
        $this->assertSame('', $bag->getString('string', 'd'));
        $this->assertFalse($bag->getBool('bool', true));
        $this->assertSame([], $bag->getArray('array', [1]));
        $this->assertSame(0, $bag->requireInt('int'));
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
                $this->bag->$method($key);
                $this->fail('MissingValueException expected.');
            } catch (MissingValueException $exception) {
                $this->assertSame(
                    "The value of {key} is required but it is not defined.",
                    $exception->getTranslatableMessage()->getMessage()
                );
            }
        }
    }

    public function testRequiredEnumAndObjectThrowWhenTheValueIsNotDefined(): void
    {
        $this->expectException(MissingValueException::class);
        $this->bag->requireEnum('missing', TypedGettersSuit::class);
    }

    public function testRequiredObjectThrowsWhenTheValueIsNotDefined(): void
    {
        $this->expectException(MissingValueException::class);
        $this->bag->requireObject('missing', stdClass::class);
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
        $this->expectException(UnexpectedTypeException::class);
        $this->bag->$method($key);
    }

    #[DataProvider('wrongTypes')]
    public function testRequiredGettersThrowWhenTheTypeDoesNotMatch(string $method, string $key): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->bag->{str_replace('get', 'require', $method)}($key);
    }

    public function testWrongTypeIsNotReplacedByTheDefault(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->bag->getInt('string', 7);
    }

    public function testStringsAreNeverConvertedToNumbers(): void
    {
        $bag = new Bag(['port' => '993', 'flag' => '1']);

        $this->expectException(UnexpectedTypeException::class);
        $bag->getInt('port');
    }

    public function testBoolIsNeverConvertedFromString(): void
    {
        $bag = new Bag(['flag' => 'true']);

        $this->expectException(UnexpectedTypeException::class);
        $bag->getBool('flag');
    }

    public function testFloatAcceptsIntAndReturnsFloat(): void
    {
        $this->assertSame(993.0, $this->bag->getFloat('int'));
    }

    public function testExceptionMessageHasTheKeyTheExpectedAndTheGivenType(): void
    {
        try {
            $this->bag->getInt('string');
            $this->fail('UnexpectedTypeException expected.');
        } catch (UnexpectedTypeException $exception) {
            $this->assertSame(
                'The value of {key} must be of type {expected}; {type} given.',
                $exception->getTranslatableMessage()->getMessage()
            );
            $this->assertSame(
                ['key' => 'string', 'expected' => 'int', 'type' => 'string'],
                $exception->getTranslatableMessage()->getParameters()
            );
        }
    }

    public function testTypedElementsOfArraysAndLists(): void
    {
        $this->assertSame(['x', 'y'], $this->bag->getList('list', of: 'string'));
        $this->assertSame([1, 2], $this->bag->requireList('ints', of: 'int'));
        $this->assertSame(['a' => 1, 'b' => 2], $this->bag->getArray('array', of: 'int'));
        $this->assertSame([1.0, 2.0], $this->bag->requireList('ints', of: 'float'));
        $this->assertSame(
            [['a' => 1]],
            (new Bag(['rows' => [['a' => 1]]]))->requireList('rows', of: 'array')
        );
        $object = new stdClass();
        $this->assertSame(
            [$object],
            (new Bag(['objects' => [$object]]))->requireList('objects', of: stdClass::class)
        );
        $this->assertSame(
            [true, false],
            (new Bag(['flags' => [true, false]]))->requireList('flags', of: 'bool')
        );
    }

    public function testWrongElementTypeNamesTheOffendingIndex(): void
    {
        try {
            $this->bag->getList('ints', of: 'string');
            $this->fail('UnexpectedTypeException expected.');
        } catch (UnexpectedTypeException $exception) {
            $this->assertSame('ints.0', $exception->getTranslatableMessage()->getParameters()['key']);
            $this->assertSame('string', $exception->getTranslatableMessage()->getParameters()['expected']);
        }
    }

    public function testUnknownElementTypeIsRejected(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        (new ReflectionMethod($this->bag, 'getList'))
            ->invoke($this->bag, 'list', null, 'NotAClass');
    }

    public function testEnumAcceptsCasesAndBackingValues(): void
    {
        $this->assertSame(TypedGettersSuit::Hearts, $this->bag->getEnum('enum', TypedGettersSuit::class));
        $this->assertSame(TypedGettersSuit::Hearts, $this->bag->requireEnum('enum', TypedGettersSuit::class));

        $bag = new Bag(['suit' => TypedGettersSuit::Spades]);
        $this->assertSame(TypedGettersSuit::Spades, $bag->requireEnum('suit', TypedGettersSuit::class));

        $this->assertSame(
            TypedGettersSuit::Spades,
            $this->bag->getEnum('missing', TypedGettersSuit::class, TypedGettersSuit::Spades)
        );
    }

    public function testEnumRejectsUnknownBackingValuesAndOtherTypes(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        (new Bag(['suit' => 'clubs']))->getEnum('suit', TypedGettersSuit::class);
    }

    public function testEnumRejectsOtherTypes(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        (new Bag(['suit' => 1.5]))->getEnum('suit', TypedGettersSuit::class);
    }

    public function testObjectChecksTheClass(): void
    {
        $this->assertInstanceOf(stdClass::class, $this->bag->getObject('object', stdClass::class));
        $this->assertInstanceOf(stdClass::class, $this->bag->requireObject('object', stdClass::class));

        $this->expectException(UnexpectedTypeException::class);
        $this->bag->getObject('object', ArrayObject::class);
    }

    public function testObjectRejectsNonObjects(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->bag->getObject('string', stdClass::class);
    }

    public function testContainerReturnsAContainerOfTheSameKind(): void
    {
        $container = $this->bag->requireContainer('nested');

        $this->assertInstanceOf(Bag::class, $container);
        $this->assertSame(25, $container->requireInt('inner.port'));
        $this->assertSame(['inner' => ['port' => 25]], $container->all());
        $this->assertSame(
            25,
            $container->requireContainer('inner')->requireInt('port')
        );
    }

    public function testContainerFromDefaultData(): void
    {
        $container = $this->bag->getContainer('missing', ['a' => 1]);

        $this->assertInstanceOf(Bag::class, $container);
        $this->assertSame(1, $container->getInt('a'));
    }

    public function testContainerIsAChildCopyAndDoesNotWriteBack(): void
    {
        $container = $this->bag->requireContainer('nested');
        $container->set('inner.port', 99);

        $this->assertSame(25, $this->bag->getInt('nested.inner.port'));
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
        $this->assertSame(['a' => 1], $bag->getArray('array'));
        $this->assertSame([1, 2], $bag->requireList('list', of: 'int'));
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

        $this->assertSame('imap.example.com', $vault->requireString('host'));
        $this->assertSame(993, $vault->requireInt('port'));
    }
}
