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
use Derafu\Container\Contract\StoreInterface;
use InvalidArgumentException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Structured data container with JSON Schema support.
 */
class Store extends AbstractContainer implements StoreInterface
{
    /**
     * Data schema configuration.
     *
     * @var stdClass
     */
    protected ?stdClass $schema = null;

    /**
     * Validator error formatter instance.
     *
     * @var ErrorFormatter
     */
    private ErrorFormatter $formatter;

    /**
     * Constructor.
     *
     * @param array|ArrayAccess|ArrayObject $data Initial data.
     * @param array $schema Initial schema.
     */
    public function __construct(
        array|ArrayAccess|ArrayObject $data = [],
        array $schema = []
    ) {
        $this->formatter = new ErrorFormatter();
        $this->setSchema($schema);
        if (!is_array($data)) {
            $data = $this->createFrom($data)->toArray();
        }
        $data = $this->resolve($data, $this->schema);
        $this->data = $this->createFrom($data);
    }

    /**
     * {@inheritDoc}
     */
    public function setSchema(array $schema): static
    {
        $schema = array_merge([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
        ], $schema);

        $this->schema = Helper::toJSON($schema);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getSchema(): array
    {
        return json_decode(json_encode($this->schema), true);
    }

    /**
     * {@inheritDoc}
     */
    public function validate(): void
    {
        $this->resolve($this->toArray(), $this->schema);
    }

    /**
     * Validates and resolves data using a JSON schema.
     *
     * @param array $data Data to validate.
     * @param stdClass $schema JSON schema to use.
     * @return array Validated data with defaults applied.
     */
    private function resolve(array $data, stdClass $schema): array
    {
        if (!isset($schema->properties)) {
            return $data;
        }

        $data = $this->applyDefaults($data, $schema);
        $json = json_decode(json_encode($data));

        $validator = new Validator();
        $result = $validator->validate($json, $schema);

        if ($result->hasError()) {
            $errors = [];
            foreach ($this->formatter->format($result->error()) as $section => $messages) {
                foreach ($messages as $message) {
                    $errors[] = $message . ' in ' . $section . '.';
                }
            }
            throw new InvalidArgumentException(sprintf(
                'Error al validar el esquema JSON de los datos. %s',
                implode(' ', $errors)
            ));
        }

        return $data;
    }

    /**
     * Applies default values recursively.
     *
     * @param array $data Data to process.
     * @param stdClass $schema Schema with default values.
     * @return array Data with defaults applied.
     */
    private function applyDefaults(array $data, stdClass $schema): array
    {
        foreach ($schema->properties as $key => $property) {
            // Aplicar valor por defecto si la propiedad no existe.
            if (!isset($data[$key]) && isset($property->default)) {
                $data[$key] = $property->default;
            }

            // Recursión para objetos anidados.
            if (
                isset($data[$key])
                && isset($property->type)
                && $property->type === 'object'
                && isset($property->properties)
            ) {
                $data[$key] = $this->applyDefaults($data[$key], $property);
            }
        }

        return $data;
    }
}
