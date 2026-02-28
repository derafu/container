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

/**
 * Interface for sequential storage of elements.
 */
interface JournalInterface extends ContainerInterface
{
    /**
     * Appends an item to the end of the journal.
     *
     * @param mixed $item Item to add.
     * @return static Allows method chaining.
     */
    public function add(mixed $item): static;

    /**
     * Returns elements in reverse order (newest to oldest).
     *
     * @return array Elements in reverse order.
     */
    public function reverse(): array;
}
