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

use Exception;

/**
 * Interface for structured data container with schema.
 */
interface VaultInterface extends ContainerInterface
{
    /**
     * Sets the schema used to validate data.
     *
     * @param array $schema New schema to use.
     * @return static Allows method chaining.
     */
    public function setSchema(array $schema): static;

    /**
     * Returns the defined data schema.
     *
     * @return array Current schema.
     */
    public function getSchema(): array;

    /**
     * Validates that stored data complies with the schema.
     *
     * @param bool $allowUndefinedKeys Whether to allow undefined keys.
     * @return void
     * @throws Exception Thrown when validation fails.
     */
    public function validate(bool $allowUndefinedKeys = false): void;
}
