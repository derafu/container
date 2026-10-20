<?php

declare(strict_types=1);

return [
    'The container offset must be a bool, int, float, string, Stringable, or null; {type} given.' => 'El índice del contenedor debe ser bool, int, float, string, Stringable o null; se recibió {type}.',
    'ArrayAccess must implement Traversable to be convertible.' => 'ArrayAccess debe implementar Traversable para ser convertible.',
    'The container schema must resolve to an object.' => 'El esquema del contenedor debe convertirse en un objeto.',
    'Failed to convert the container schema to JSON: {error}' => 'No se pudo convertir el esquema del contenedor a JSON: {error}',
    'The decoded container schema must be an array.' => 'El esquema decodificado del contenedor debe ser un arreglo.',
    'Failed to convert the container data to JSON: {error}' => 'No se pudieron convertir los datos del contenedor a JSON: {error}',
    '{message} in {section}.' => '{message} en {section}.',
    'Failed to validate the data against the JSON schema. {errors}' => 'No se pudieron validar los datos contra el esquema JSON. {errors}',
    'The __allowUndefinedKeys setting must be a bool; {type} given.' => 'La opción __allowUndefinedKeys debe ser bool; se recibió {type}.',
    'The configuration of field {field} must be an array; {type} given.' => 'La configuración del campo {field} debe ser un arreglo; se recibió {type}.',
    'The types entry {index} of field {field} must be a string; {type} given.' => 'La entrada {index} de types del campo {field} debe ser string; se recibió {type}.',
    'The types of field {field} must be a string or an array of strings; {type} given.' => 'Los tipos del campo {field} deben ser un string o un arreglo de strings; se recibió {type}.',
    'The value of {key} must be of type {expected}; {type} given.' => 'El valor de {key} debe ser de tipo {expected}; se recibió {type}.',
    'The value of {key} is required but it is not defined.' => 'El valor de {key} es obligatorio pero no está definido.',
    'The element type {type} is not a scalar type, array, class or interface.' => 'El tipo de elemento {type} no es un tipo escalar, array, clase ni interfaz.',
];
