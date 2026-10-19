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
use Derafu\Container\Contract\VaultInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Structured data container with schema support.
 */
class Vault extends AbstractContainer implements VaultInterface
{
    /**
     * Data schema configuration.
     *
     * @var array<array-key, mixed>
     */
    protected array $schema = [];

    /**
     * Constructor.
     *
     * @param array<array-key, mixed>|ArrayAccess<*, *>|ArrayObject<*, *> $data Initial data.
     * @param array<array-key, mixed> $schema Initial schema.
     * @param bool $allowUndefinedKeys Whether to allow undefined keys.
     */
    public function __construct(
        array|ArrayAccess|ArrayObject $data = [],
        array $schema = [],
        bool $allowUndefinedKeys = false
    ) {
        $this->setSchema($schema);
        if (!is_array($data)) {
            $data = $this->createFrom($data)->toArray();
        }
        $data = $this->resolve($data, $this->schema, $allowUndefinedKeys);
        $this->data = $this->createFrom($data);
    }

    /**
     * {@inheritDoc}
     */
    public function setSchema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(bool $allowUndefinedKeys = false): void
    {
        $this->resolve($this->toArray(), $this->schema, $allowUndefinedKeys);
    }

    /**
     * Validates and resolves data using a schema.
     *
     * @param array<array-key, mixed> $data Data to validate.
     * @param array<array-key, mixed> $schema Schema to use.
     * @param bool $allowUndefinedKeys Whether to allow undefined keys.
     * @return array<array-key, mixed> Validated and normalized data.
     */
    private function resolve(
        array $data,
        array $schema,
        bool $allowUndefinedKeys = false
    ): array {
        // Without a schema, accept the data as provided.
        if (empty($schema)) {
            return $data;
        }

        // Determine whether options not defined in the schema are allowed.
        $allowUndefinedKeys = $schema['__allowUndefinedKeys']
            ?? $allowUndefinedKeys
        ;
        if (!is_bool($allowUndefinedKeys)) {
            throw new TranslatableInvalidArgumentException([
                'The __allowUndefinedKeys setting must be a bool; {type} given.',
                'type' => get_debug_type($allowUndefinedKeys),
            ]);
        }
        unset($schema['__allowUndefinedKeys']);

        // Create and configure the resolver.
        $resolver = new OptionsResolver();
        foreach ($schema as $key => $config) {
            if (!is_array($config)) {
                throw new TranslatableInvalidArgumentException([
                    'The configuration of field {field} must be an array; {type} given.',
                    'field' => $key,
                    'type' => get_debug_type($config),
                ]);
            }

            // Configure the current level.
            if (!empty($config['types'])) {
                $types = $config['types'];
                if (is_array($types)) {
                    foreach ($types as $index => $type) {
                        if (!is_string($type)) {
                            throw new TranslatableInvalidArgumentException([
                                'The types entry {index} of field {field} must be a string; {type} given.',
                                'index' => $index,
                                'field' => $key,
                                'type' => get_debug_type($type),
                            ]);
                        }
                    }
                } elseif (!is_string($types)) {
                    throw new TranslatableInvalidArgumentException([
                        'The types of field {field} must be a string or an array of strings; {type} given.',
                        'field' => $key,
                        'type' => get_debug_type($types),
                    ]);
                }

                $resolver->setDefined([$key]);
                $resolver->setAllowedTypes($key, $types);
            }

            if (!empty($config['required'])) {
                $resolver->setRequired([$key]);
            }

            if (!empty($config['choices'])) {
                $resolver->setAllowedValues($key, $config['choices']);
            }

            if (array_key_exists('default', $config)) {
                $resolver->setDefault($key, $config['default']);
            }

            // Configure the normalizer for a nested schema.
            if (!empty($config['schema'])) {
                $resolver->setDefault($key, []);
                $resolver->setAllowedTypes($key, 'array');

                $resolver->setNormalizer(
                    $key,
                    fn (Options $options, $value) =>
                        $this->resolve(
                            $value ?? [],
                            $config['schema'],
                            $allowUndefinedKeys
                        )
                );
            } elseif (!empty($config['normalizer'])) {
                $resolver->setNormalizer($key, $config['normalizer']);
            }
        }

        // Allow additional undefined options.
        if ($allowUndefinedKeys) {
            $resolver->setDefined(array_keys($data));
        }

        // Resolve the options.
        return $resolver->resolve($data);
    }
}
