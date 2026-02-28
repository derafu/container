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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;

/**
 * Base interface for all storage implementations.
 */
interface ContainerInterface extends ArrayAccess, JsonSerializable
{
    /**
     * Returns the collection of stored data.
     *
     * @return ArrayCollection Collection of all stored values.
     */
    public function collection(): ArrayCollection;

    /**
     * Returns all stored values.
     *
     * @return array Array of all stored values.
     */
    public function all(): array;

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
     * @return ArrayCollection New collection with elements matching the
     * criteria.
     * @see \Doctrine\Common\Collections\Criteria
     * @see \Doctrine\Common\Collections\ArrayCollection
     */
    public function matching(Criteria $criteria): ArrayCollection;
}
