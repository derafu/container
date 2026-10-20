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

use Derafu\Translation\Exception\Runtime\TranslatableUnexpectedValueException;

/**
 * Thrown when a value of a container does not have the requested type.
 */
class UnexpectedTypeException extends TranslatableUnexpectedValueException
{
}
