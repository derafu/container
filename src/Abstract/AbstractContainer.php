<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Container\Abstract;

use ArrayAccess;
use ArrayObject;
use BackedEnum;
use Derafu\Container\Contract\ContainerInterface;
use Derafu\Container\Exception\MissingValueException;
use Derafu\Container\Exception\UnexpectedTypeException;
use Derafu\Selector\Selector;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Stringable;
use Traversable;

/**
 * Base class for all storage implementations.
 */
abstract class AbstractContainer implements ContainerInterface
{
    /**
     * Stored data collection.
     *
     * @var ArrayCollection<array-key, mixed>
     */
    protected ArrayCollection $data;

    /**
     * {@inheritDoc}
     */
    public function collection(): ArrayCollection
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): static
    {
        $data = $this->toArray();
        Selector::set($data, $key, $value);
        $this->data = $this->createFrom($this->prepare($data));

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Selector::get($this->toArray(), $key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return Selector::has($this->toArray(), $key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(?string $key = null): void
    {
        if ($key === null) {
            $this->data = $this->createFrom($this->prepare([]));
        } else {
            $data = $this->toArray();
            Selector::clear($data, $key);
            $this->data = $this->createFrom($this->prepare($data));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function matching(Criteria $criteria): ArrayCollection
    {
        return $this->createFrom($this->data->matching($criteria)->toArray());
    }

    /**
     * {@inheritDoc}
     */
    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (!is_string($value)) {
            throw $this->unexpectedType($key, 'string', $value);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function requireString(string $key): string
    {
        return $this->getString($key) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (!is_int($value)) {
            throw $this->unexpectedType($key, 'int', $value);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function requireInt(string $key): int
    {
        return $this->getInt($key) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getFloat(string $key, ?float $default = null): ?float
    {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }

        throw $this->unexpectedType($key, 'float', $value);
    }

    /**
     * {@inheritDoc}
     */
    public function requireFloat(string $key): float
    {
        return $this->getFloat($key) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (!is_bool($value)) {
            throw $this->unexpectedType($key, 'bool', $value);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function requireBool(string $key): bool
    {
        return $this->getBool($key) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getArray(
        string $key,
        ?array $default = null,
        string $of = 'mixed'
    ): ?array {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (!is_array($value)) {
            throw $this->unexpectedType($key, 'array', $value);
        }

        return $this->checkElements($key, $value, $of);
    }

    /**
     * {@inheritDoc}
     */
    public function requireArray(string $key, string $of = 'mixed'): array
    {
        return $this->getArray($key, null, $of) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getList(
        string $key,
        ?array $default = null,
        string $of = 'mixed'
    ): ?array {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->unexpectedType($key, 'list', $value);
        }

        return array_values($this->checkElements($key, $value, $of));
    }

    /**
     * {@inheritDoc}
     */
    public function requireList(string $key, string $of = 'mixed'): array
    {
        return $this->getList($key, null, $of) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getEnum(
        string $key,
        string $class,
        ?BackedEnum $default = null
    ): ?BackedEnum {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if ($value instanceof $class) {
            return $value;
        }
        if (is_int($value) || is_string($value)) {
            $case = $class::tryFrom($value);
            if ($case !== null) {
                return $case;
            }
        }

        throw $this->unexpectedType($key, $class, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function requireEnum(string $key, string $class): BackedEnum
    {
        return $this->getEnum($key, $class) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getObject(
        string $key,
        string $class,
        ?object $default = null
    ): ?object {
        $value = $this->raw($key);
        if ($value === null) {
            return $default;
        }
        if (!$value instanceof $class) {
            throw $this->unexpectedType($key, $class, $value);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function requireObject(string $key, string $class): object
    {
        return $this->getObject($key, $class) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getContainer(string $key, ?array $default = null): ?static
    {
        $value = $this->raw($key);
        if ($value === null) {
            return $default === null ? null : $this->createChild($default, $key);
        }
        if (!is_array($value)) {
            throw $this->unexpectedType($key, 'array', $value);
        }

        return $this->createChild($value, $key);
    }

    /**
     * {@inheritDoc}
     */
    public function requireContainer(string $key): static
    {
        return $this->getContainer($key) ?? throw $this->missingValue($key);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($this->normalizeOffset($offset), $value);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($this->normalizeOffset($offset));
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($this->normalizeOffset($offset));
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->clear($this->normalizeOffset($offset));
    }

    /**
     * Prepares the data that will replace the stored data after `set()` or
     * `clear()`.
     *
     * It runs before the stored data is replaced, so an exception thrown here
     * leaves the container unchanged. Containers that validate their data
     * override it; by default the data is stored as it is.
     *
     * @param array<array-key, mixed> $data Data that would be stored.
     * @return array<array-key, mixed> Data to store.
     */
    protected function prepare(array $data): array
    {
        return $data;
    }

    /**
     * Creates a container of the same kind for the data stored under a key.
     *
     * Containers whose constructor does not take the data as its first
     * argument, or that need to pass more than the data (for example, the
     * part of the schema that applies to the key), must override this method.
     *
     * @param array<array-key, mixed> $data Data of the child container.
     * @param string $key Key where the data is stored.
     * @return static
     */
    protected function createChild(array $data, string $key): static
    {
        return new (static::class)($data);
    }

    /**
     * Returns the value stored under a key exactly as it is stored.
     *
     * Unlike `get()`, it is not affected by the containers that override
     * `get()` to transform the value.
     */
    private function raw(string $key): mixed
    {
        return Selector::get($this->toArray(), $key);
    }

    /**
     * Checks the elements of an array against a type.
     *
     * @param string $key Key of the array, for the error messages.
     * @param array<array-key, mixed> $items Elements to check.
     * @param string $of `mixed`, `string`, `int`, `float`, `bool`, `array` or
     * the name of a class or interface.
     * @return array<array-key, mixed> Elements, with ints converted to float
     * when `$of` is `float`.
     */
    private function checkElements(string $key, array $items, string $of): array
    {
        if ($of === 'mixed') {
            return $items;
        }

        $isClass = !in_array($of, ['string', 'int', 'float', 'bool', 'array'], true);
        if ($isClass && !class_exists($of) && !interface_exists($of)) {
            throw new InvalidArgumentException([
                'The element type {type} is not a scalar type, array, class or interface.',
                'type' => $of,
            ]);
        }

        $checked = [];
        foreach ($items as $index => $item) {
            if ($of === 'float' && is_int($item)) {
                $checked[$index] = (float) $item;
                continue;
            }
            $valid = match ($of) {
                'string' => is_string($item),
                'int' => is_int($item),
                'float' => is_float($item),
                'bool' => is_bool($item),
                'array' => is_array($item),
                default => $item instanceof $of,
            };
            if (!$valid) {
                throw $this->unexpectedType($key . '.' . $index, $of, $item);
            }
            $checked[$index] = $item;
        }

        return $checked;
    }

    /**
     * Creates the exception for a value that has another type.
     */
    private function unexpectedType(
        string $key,
        string $expected,
        mixed $value
    ): UnexpectedTypeException {
        return new UnexpectedTypeException([
            'The value of {key} must be of type {expected}; {type} given.',
            'key' => $key,
            'expected' => $expected,
            'type' => get_debug_type($value),
        ]);
    }

    /**
     * Creates the exception for a required value that is not defined.
     */
    private function missingValue(string $key): MissingValueException
    {
        return new MissingValueException([
            'The value of {key} is required but it is not defined.',
            'key' => $key,
        ]);
    }

    /**
     * Converts an ArrayAccess offset to a selector path.
     *
     * @throws TranslatableInvalidArgumentException If the offset cannot be converted.
     */
    private function normalizeOffset(mixed $offset): string
    {
        if ($offset === null || is_scalar($offset) || $offset instanceof Stringable) {
            return (string) $offset;
        }

        throw new TranslatableInvalidArgumentException([
            'The container offset must be a bool, int, float, string, Stringable, or null; {type} given.',
            'type' => get_debug_type($offset),
        ]);
    }

    /**
     * Creates a new ArrayCollection for use or assignment to $data.
     *
     * @param array<array-key, mixed>|ArrayAccess<*, *>|ArrayObject<*, *> $data Source data.
     * @return ArrayCollection<array-key, mixed> New collection instance.
     */
    protected function createFrom(
        array|ArrayAccess|ArrayObject $data
    ): ArrayCollection {
        if ($data instanceof ArrayObject) {
            $data = (array) $data;
        } elseif ($data instanceof ArrayAccess) {
            if ($data instanceof Traversable) {
                $data = iterator_to_array($data);
            } else {
                throw new InvalidArgumentException(
                    'ArrayAccess must implement Traversable to be convertible.'
                );
            }
        }

        return new ArrayCollection($data);
    }

    /**
     * Returns the elements of $data as an array.
     *
     * @return array<array-key, mixed> Data as array.
     */
    protected function toArray(): array
    {
        return $this->data->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
