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
use ArrayObject;

/**
 * Interface for simple data container.
 */
interface BagInterface extends ContainerInterface
{
    /**
     * Replaces all stored values with new ones.
     *
     * @param array|ArrayAccess|ArrayObject $data New values to store.
     * @return static Allows method chaining.
     */
    public function replace(array|ArrayAccess|ArrayObject $data): static;

    /**
     * Merges stored values with new values.
     *
     * @param array|ArrayAccess|ArrayObject $data Values to merge with
     * existing ones.
     * @return static Allows method chaining.
     */
    public function merge(array|ArrayAccess|ArrayObject $data): static;

    /**
     * Removes a stored value.
     *
     * @param string $key Key of the value to remove.
     * @return static Allows method chaining.
     */
    public function remove(string $key): static;
}
