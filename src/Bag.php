<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Container;

use ArrayAccess;
use ArrayObject;
use Derafu\Container\Abstract\AbstractContainer;
use Derafu\Container\Contract\BagInterface;

/**
 * Simple data container implementation.
 */
class Bag extends AbstractContainer implements BagInterface
{
    /**
     * Constructor.
     *
     * @param array|ArrayAccess|ArrayObject $data Initial data.
     */
    public function __construct(array|ArrayAccess|ArrayObject $data = [])
    {
        $this->data = $this->createFrom($data);
    }

    /**
     * {@inheritDoc}
     */
    public function replace(array|ArrayAccess|ArrayObject $data): static
    {
        $this->data = $this->createFrom($data);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function merge(array|ArrayAccess|ArrayObject $data): static
    {
        if (!is_array($data)) {
            $data = $this->createFrom($data)->toArray();
        }

        $this->data = $this->createFrom(
            array_replace_recursive($this->toArray(), $data)
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $key): static
    {
        if ($this->has($key)) {
            unset($this->data[$key]);
        }

        return $this;
    }
}
