<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Container\Exception;

use Derafu\Translation\Exception\Runtime\TranslatableOutOfBoundsException;

/**
 * Thrown when a value required from a container is not defined (the key does
 * not exist or its value is `null`).
 */
class MissingValueException extends TranslatableOutOfBoundsException
{
}
