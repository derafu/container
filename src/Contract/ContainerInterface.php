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
use BackedEnum;
use Derafu\Container\Exception\MissingValueException;
use Derafu\Container\Exception\UnexpectedTypeException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;

/**
 * Base interface for all storage implementations.
 *
 * @extends ArrayAccess<mixed, mixed>
 */
interface ContainerInterface extends ArrayAccess, JsonSerializable
{
    /**
     * Returns the collection of stored data.
     *
     * @return ArrayCollection<array-key, mixed> Collection of all stored values.
     */
    public function collection(): ArrayCollection;

    /**
     * Returns all stored values.
     *
     * @return array<array-key, mixed> Array of all stored values.
     */
    public function all(): array;

    /**
     * Sets a value for a key.
     *
     * @param string $key Key where the value will be stored.
     * @param mixed $value Value to store.
     * @return static Allows method chaining.
     */
    public function set(string $key, mixed $value): static;

    /**
     * Returns a stored value.
     *
     * @param string $key Key of the value to retrieve.
     * @param mixed $default Default value when key does not exist.
     * @return mixed Stored value or default.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Checks whether a value exists for a key.
     *
     * @param string $key Key to check.
     * @return bool True if key exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Clears all stored values or a specific one.
     *
     * @param string|null $key Key to remove, or null for all.
     * @return void
     */
    public function clear(?string $key = null): void;

    /**
     * Applies criteria to filter stored elements.
     *
     * Filters and orders elements in the collection according to the
     * conditions defined in a Criteria object.
     *
     * Returns a new ArrayCollection containing only elements that match.
     *
     * @param Criteria $criteria The Criteria defining conditions, order and
     * limits for the results.
     * @return ArrayCollection<array-key, mixed> New collection with elements matching the
     * criteria.
     * @see \Doctrine\Common\Collections\Criteria
     * @see \Doctrine\Common\Collections\ArrayCollection
     */
    public function matching(Criteria $criteria): ArrayCollection;

    /**
     * Returns the value of a key as a string.
     *
     * @param string $key Key of the value to retrieve.
     * @param string|null $default Value to return when the key is not defined.
     * @return string|null Stored value, or the default if the key does not exist
     * or its value is `null`.
     * @throws UnexpectedTypeException If the value is not a string.
     */
    public function getString(string $key, ?string $default = null): ?string;

    /**
     * Returns the value of a key as a string, which must be defined.
     *
     * @param string $key Key of the value to retrieve.
     * @return string Stored value.
     * @throws MissingValueException If the key does not exist or its value is
     * `null`.
     * @throws UnexpectedTypeException If the value is not a string.
     */
    public function requireString(string $key): string;

    /**
     * Returns the value of a key as an int.
     *
     * @param string $key Key of the value to retrieve.
     * @param int|null $default Value to return when the key is not defined.
     * @return int|null Stored value, or the default if the key does not exist
     * or its value is `null`.
     * @throws UnexpectedTypeException If the value is not an int.
     */
    public function getInt(string $key, ?int $default = null): ?int;

    /**
     * Returns the value of a key as an int, which must be defined.
     *
     * @param string $key Key of the value to retrieve.
     * @return int Stored value.
     * @throws MissingValueException If the key does not exist or its value is
     * `null`.
     * @throws UnexpectedTypeException If the value is not an int.
     */
    public function requireInt(string $key): int;

    /**
     * Returns the value of a key as a float (an int is accepted and converted).
     *
     * @param string $key Key of the value to retrieve.
     * @param float|null $default Value to return when the key is not defined.
     * @return float|null Stored value, or the default if the key does not exist
     * or its value is `null`.
     * @throws UnexpectedTypeException If the value is not a float (an int is accepted and converted).
     */
    public function getFloat(string $key, ?float $default = null): ?float;

    /**
     * Returns the value of a key as a float (an int is accepted and converted), which must be defined.
     *
     * @param string $key Key of the value to retrieve.
     * @return float Stored value.
     * @throws MissingValueException If the key does not exist or its value is
     * `null`.
     * @throws UnexpectedTypeException If the value is not a float (an int is accepted and converted).
     */
    public function requireFloat(string $key): float;

    /**
     * Returns the value of a key as a bool.
     *
     * @param string $key Key of the value to retrieve.
     * @param bool|null $default Value to return when the key is not defined.
     * @return bool|null Stored value, or the default if the key does not exist
     * or its value is `null`.
     * @throws UnexpectedTypeException If the value is not a bool.
     */
    public function getBool(string $key, ?bool $default = null): ?bool;

    /**
     * Returns the value of a key as a bool, which must be defined.
     *
     * @param string $key Key of the value to retrieve.
     * @return bool Stored value.
     * @throws MissingValueException If the key does not exist or its value is
     * `null`.
     * @throws UnexpectedTypeException If the value is not a bool.
     */
    public function requireBool(string $key): bool;

    /**
     * Returns the value of a key as an array.
     *
     * When `$of` is given, every element is also checked against that type
     * (`int` is accepted and converted for `float`; a class name checks with
     * `instanceof`).
     *
     * @template T of object
     * @param string $key Key of the value to retrieve.
     * @param array<array-key, mixed>|null $default Value to return when the key is not defined.
     * @param 'mixed'|'string'|'int'|'float'|'bool'|'array'|class-string<T> $of Type of the elements.
     * @return ($of is 'string' ? array<array-key, string>|null : ($of is 'int' ? array<array-key, int>|null : ($of is 'float' ? array<array-key, float>|null : ($of is 'bool' ? array<array-key, bool>|null : ($of is 'array' ? array<array-key, array<array-key, mixed>>|null : ($of is 'mixed' ? array<array-key, mixed>|null : array<array-key, T>|null))))))
     * @throws UnexpectedTypeException If the value, or one of its elements, has another type.
     */
    public function getArray(string $key, ?array $default = null, string $of = 'mixed'): ?array;

    /**
     * Returns the value of a key as an array, which must be defined.
     *
     * @template T of object
     * @param string $key Key of the value to retrieve.
     * @param 'mixed'|'string'|'int'|'float'|'bool'|'array'|class-string<T> $of Type of the elements.
     * @return ($of is 'string' ? array<array-key, string> : ($of is 'int' ? array<array-key, int> : ($of is 'float' ? array<array-key, float> : ($of is 'bool' ? array<array-key, bool> : ($of is 'array' ? array<array-key, array<array-key, mixed>> : ($of is 'mixed' ? array<array-key, mixed> : array<array-key, T>))))))
     * @throws MissingValueException If the key does not exist or its value is `null`.
     * @throws UnexpectedTypeException If the value, or one of its elements, has another type.
     */
    public function requireArray(string $key, string $of = 'mixed'): array;

    /**
     * Returns the value of a key as a list (an array with sequential integer keys).
     *
     * When `$of` is given, every element is also checked against that type
     * (`int` is accepted and converted for `float`; a class name checks with
     * `instanceof`).
     *
     * @template T of object
     * @param string $key Key of the value to retrieve.
     * @param list<mixed>|null $default Value to return when the key is not defined.
     * @param 'mixed'|'string'|'int'|'float'|'bool'|'array'|class-string<T> $of Type of the elements.
     * @return ($of is 'string' ? list<string>|null : ($of is 'int' ? list<int>|null : ($of is 'float' ? list<float>|null : ($of is 'bool' ? list<bool>|null : ($of is 'array' ? list<array<array-key, mixed>>|null : ($of is 'mixed' ? list<mixed>|null : list<T>|null))))))
     * @throws UnexpectedTypeException If the value, or one of its elements, has another type.
     */
    public function getList(string $key, ?array $default = null, string $of = 'mixed'): ?array;

    /**
     * Returns the value of a key as a list (an array with sequential integer keys), which must be defined.
     *
     * @template T of object
     * @param string $key Key of the value to retrieve.
     * @param 'mixed'|'string'|'int'|'float'|'bool'|'array'|class-string<T> $of Type of the elements.
     * @return ($of is 'string' ? list<string> : ($of is 'int' ? list<int> : ($of is 'float' ? list<float> : ($of is 'bool' ? list<bool> : ($of is 'array' ? list<array<array-key, mixed>> : ($of is 'mixed' ? list<mixed> : list<T>))))))
     * @throws MissingValueException If the key does not exist or its value is `null`.
     * @throws UnexpectedTypeException If the value, or one of its elements, has another type.
     */
    public function requireList(string $key, string $of = 'mixed'): array;

    /**
     * Returns the value of a key as a backed enum case.
     *
     * Accepts a case of the enum or one of its backing values.
     *
     * @template T of BackedEnum
     * @param string $key Key of the value to retrieve.
     * @param class-string<T> $class Enum class.
     * @param T|null $default Value to return when the key is not defined.
     * @return T|null
     * @throws UnexpectedTypeException If the value is not a case or a backing value of the enum.
     */
    public function getEnum(string $key, string $class, ?BackedEnum $default = null): ?BackedEnum;

    /**
     * Returns the value of a key as a backed enum case, which must be defined.
     *
     * @template T of BackedEnum
     * @param string $key Key of the value to retrieve.
     * @param class-string<T> $class Enum class.
     * @return T
     * @throws MissingValueException If the key does not exist or its value is `null`.
     * @throws UnexpectedTypeException If the value is not a case or a backing value of the enum.
     */
    public function requireEnum(string $key, string $class): BackedEnum;

    /**
     * Returns the value of a key as an instance of a class.
     *
     * @template T of object
     * @param string $key Key of the value to retrieve.
     * @param class-string<T> $class Class or interface the value must implement.
     * @param T|null $default Value to return when the key is not defined.
     * @return T|null
     * @throws UnexpectedTypeException If the value is not an instance of the class.
     */
    public function getObject(string $key, string $class, ?object $default = null): ?object;

    /**
     * Returns the value of a key as an instance of a class, which must be defined.
     *
     * @template T of object
     * @param string $key Key of the value to retrieve.
     * @param class-string<T> $class Class or interface the value must implement.
     * @return T
     * @throws MissingValueException If the key does not exist or its value is `null`.
     * @throws UnexpectedTypeException If the value is not an instance of the class.
     */
    public function requireObject(string $key, string $class): object;

    /**
     * Returns the value of a key (an array) as a container of the same kind.
     *
     * Unlike `get()`, the result is always a container (or `null`).
     *
     * @param string $key Key of the value to retrieve.
     * @param array<array-key, mixed>|null $default Data to use when the key is not defined.
     * @return static|null
     * @throws UnexpectedTypeException If the value is not an array.
     */
    public function getContainer(string $key, ?array $default = null): ?static;

    /**
     * Returns the value of a key (an array) as a container of the same kind,
     * which must be defined.
     *
     * @param string $key Key of the value to retrieve.
     * @return static
     * @throws MissingValueException If the key does not exist or its value is `null`.
     * @throws UnexpectedTypeException If the value is not an array.
     */
    public function requireContainer(string $key): static;
}
