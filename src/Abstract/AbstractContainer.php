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
use Derafu\Array\ArrayReader;
use Derafu\Container\Contract\ContainerInterface;
use Derafu\Container\Exception\MissingValueException;
use Derafu\Container\Exception\UnexpectedTypeException;
use Derafu\Selector\Selector;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Value\Exception\TypeMismatchException as ValueTypeMismatchException;
use Derafu\Value\ValueType;
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
    public function getReader(): ArrayReader
    {
        return ArrayReader::fromArray($this->toArray());
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
    public function getContainer(string $key, ?array $default = null): ?static
    {
        $value = Selector::get($this->toArray(), $key);
        if ($value === null) {
            return $default === null ? null : $this->createChild($default, $key);
        }
        try {
            $value = ValueType::array()->parse($value, $key);
        } catch (ValueTypeMismatchException $exception) {
            throw new UnexpectedTypeException([
                'The value of {key} must be of type {expected}; {type} given.',
                'key' => $key,
                'expected' => 'array',
                'type' => get_debug_type($value),
            ], previous: $exception);
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
