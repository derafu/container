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
     * @var array
     */
    protected array $schema = [];

    /**
     * Constructor.
     *
     * @param array|ArrayAccess|ArrayObject $data Initial data.
     * @param array $schema Initial schema.
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
     * @param array $data Data to validate.
     * @param array $schema Schema to use.
     * @param bool $allowUndefinedKeys Whether to allow undefined keys.
     * @return array Validated and normalized data.
     */
    private function resolve(
        array $data,
        array $schema,
        bool $allowUndefinedKeys = false
    ): array {
        // Si no hay esquema los datos son válidos como vienen.
        if (empty($schema)) {
            return $data;
        }

        // Determinar si se permitirán opciones no definidas en el esquema.
        $allowUndefinedKeys = $schema['__allowUndefinedKeys']
            ?? $allowUndefinedKeys
        ;
        unset($schema['__allowUndefinedKeys']);

        // Crear resolver y configurar.
        $resolver = new OptionsResolver();
        foreach ($schema as $key => $config) {
            // Configurar el nivel actual.
            if (!empty($config['types'])) {
                $resolver->setDefined([$key]);
                $resolver->setAllowedTypes($key, $config['types']);
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

            // Si hay un schema anidado, configurar el normalizador.
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

        // Permitir opciones adicionales no definidas.
        if ($allowUndefinedKeys) {
            $resolver->setDefined(array_keys($data));
        }

        // Resolver las opciones.
        return $resolver->resolve($data);
    }
}
