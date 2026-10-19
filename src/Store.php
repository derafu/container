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
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatableList;
use Derafu\Translation\TranslatableMessage;
use JsonException;
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
    protected stdClass $schema;

    /**
     * Validator error formatter instance.
     *
     * @var ErrorFormatter
     */
    private ErrorFormatter $formatter;

    /**
     * Constructor.
     *
     * @param array<array-key, mixed>|ArrayAccess<*, *>|ArrayObject<*, *> $data Initial data.
     * @param array<array-key, mixed> $schema Initial schema.
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

        $resolved = Helper::toJSON($schema);
        if (!$resolved instanceof stdClass) {
            throw new InvalidArgumentException('The container schema must resolve to an object.');
        }
        $this->schema = $resolved;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getSchema(): array
    {
        try {
            $schema = json_decode(
                json_encode($this->schema, JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException([
                'Failed to convert the container schema to JSON: {error}',
                'error' => $exception->getMessage(),
            ], 0, $exception);
        }

        if (!is_array($schema)) {
            throw new InvalidArgumentException('The decoded container schema must be an array.');
        }

        return $schema;
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
     * @param array<array-key, mixed> $data Data to validate.
     * @param stdClass $schema JSON schema to use.
     * @return array<array-key, mixed> Validated data with defaults applied.
     */
    private function resolve(array $data, stdClass $schema): array
    {
        if (!isset($schema->properties)) {
            return $data;
        }

        $data = $this->applyDefaults($data, $schema);
        try {
            $json = json_decode(
                json_encode($data, JSON_THROW_ON_ERROR),
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException([
                'Failed to convert the container data to JSON: {error}',
                'error' => $exception->getMessage(),
            ], 0, $exception);
        }

        $validator = new Validator();
        $result = $validator->validate($json, $schema);

        $error = $result->error();
        if ($error !== null) {
            $errors = [];
            foreach ($this->formatter->format($error) as $section => $messages) {
                foreach ($messages as $message) {
                    $errors[] = new TranslatableMessage(
                        '{message} in {section}.',
                        ['message' => $message, 'section' => $section],
                        domain: 'errors'
                    );
                }
            }
            throw new InvalidArgumentException([
                'Failed to validate the data against the JSON schema. {errors}',
                'errors' => TranslatableList::separated($errors, ' '),
            ]);
        }

        return $data;
    }

    /**
     * Applies default values recursively.
     *
     * @param array<array-key, mixed> $data Data to process.
     * @param stdClass $schema Schema with default values.
     * @return array<array-key, mixed> Data with defaults applied.
     */
    private function applyDefaults(array $data, stdClass $schema): array
    {
        foreach ($schema->properties as $key => $property) {
            // Apply the default value when the property is not set.
            if (!isset($data[$key]) && isset($property->default)) {
                $data[$key] = $property->default;
            }

            // Apply defaults recursively to nested objects.
            if (
                isset($data[$key])
                && is_array($data[$key])
                && $property instanceof stdClass
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
