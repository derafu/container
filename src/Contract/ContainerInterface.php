<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Container\Contract;

use ArrayAccess;
use Derafu\Array\ArrayReader;
use Derafu\Container\Exception\MissingValueException;
use Derafu\Container\Exception\UnexpectedTypeException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;

/**
 * Base interface for all storage implementations.
 *
 * @extends ArrayAccess<mixed, mixed>
 */
interface ContainerInterface extends ArrayAccess, JsonSerializable
{
    /**
     * Returns the collection of stored data.
     *
     * @return ArrayCollection<array-key, mixed> Collection of all stored values.
     */
    public function collection(): ArrayCollection;

    /**
     * Returns all stored values.
     *
     * @return array<array-key, mixed> Array of all stored values.
     */
    public function all(): array;

    /**
     * Returns a typed reader of the current raw data.
     *
     * The reader is a snapshot. Obtain a new reader after changing the
     * container to read its updated data. Nested objects are not cloned.
     */
    public function getReader(): ArrayReader;

    /**
     * Sets a value for a key.
     *
     * @param string $key Key where the value will be stored.
     * @param mixed $value Value to store.
     * @return static Allows method chaining.
     */
    public function set(string $key, mixed $value): static;

    /**
     * Returns a stored value.
     *
     * @param string $key Key of the value to retrieve.
     * @param mixed $default Default value when key does not exist.
     * @return mixed Stored value or default.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Checks whether a value exists for a key.
     *
     * @param string $key Key to check.
     * @return bool True if key exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Clears all stored values or a specific one.
     *
     * @param string|null $key Key to remove, or null for all.
     * @return void
     */
    public function clear(?string $key = null): void;

    /**
     * Applies criteria to filter stored elements.
     *
     * Filters and orders elements in the collection according to the
     * conditions defined in a Criteria object.
     *
     * Returns a new ArrayCollection containing only elements that match.
     *
     * @param Criteria $criteria The Criteria defining conditions, order and
     * limits for the results.
     * @return ArrayCollection<array-key, mixed> New collection with elements matching the
     * criteria.
     * @see \Doctrine\Common\Collections\Criteria
     * @see \Doctrine\Common\Collections\ArrayCollection
     */
    public function matching(Criteria $criteria): ArrayCollection;

    /**
     * Returns the value of a key (an array) as a container of the same kind.
     *
     * Unlike `get()`, the result is always a container (or `null`).
     *
     * @param string $key Key of the value to retrieve.
     * @param array<array-key, mixed>|null $default Data to use when the key is not defined.
     * @return static|null
     * @throws UnexpectedTypeException If the value is not an array.
     */
    public function getContainer(string $key, ?array $default = null): ?static;

    /**
     * Returns the value of a key (an array) as a container of the same kind,
     * which must be defined.
     *
     * @param string $key Key of the value to retrieve.
     * @return static
     * @throws MissingValueException If the key does not exist or its value is `null`.
     * @throws UnexpectedTypeException If the value is not an array.
     */
    public function requireContainer(string $key): static;
}
