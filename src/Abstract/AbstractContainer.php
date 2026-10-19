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
use Derafu\Container\Contract\ContainerInterface;
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
        $this->data = $this->createFrom($data);

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
            $this->data = $this->createFrom([]);
        } else {
            $data = $this->toArray();
            Selector::clear($data, $key);
            $this->data = $this->createFrom($data);
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
