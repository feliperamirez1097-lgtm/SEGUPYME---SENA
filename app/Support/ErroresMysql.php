<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Traduce un error de MySQL a un codigo HTTP y un mensaje para el usuario.
 *
 * Sigue la tabla "Errores de MySQL" del manual del instructor (UNIQUE -> 409, FK -> 409/422, CHECK -> 422...)
 * y agrega el 1142 (permiso denegado -> 403) y el error 1644: es el que lanzan nuestros triggers y procedimientos con SIGNAL SQLSTATE '45000'
 * (p. ej. "la liquidacion esta bloqueada"). Para ese error el mensaje es el de la propia base.
 */
class ErroresMysql
{
    /**
     * @return array{status: int, mensaje: string, codigo: int|null}
     */
    public static function traducir(QueryException $e): array
    {
        $codigo = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
        $textoBase = (string) ($e->errorInfo[2] ?? $e->getMessage());

        [$status, $mensaje] = match ($codigo) {
            1062 => [409, 'Ya existe un registro con esos datos.'],
            1451 => [409, 'El registro esta en uso y no se puede borrar.'],
            1452 => [422, 'El registro relacionado no existe.'],
            3819 => [422, 'Un valor no cumple una regla de la base de datos.'],
            1406 => [422, 'Un texto es demasiado largo.'],
            1048 => [422, 'Falta un dato obligatorio.'],
            1265, 1264, 1292, 1366 => [422, 'Un valor no es valido para su columna.'],  // enum, rango, fecha o numero invalido
            1142, 1143, 1044, 1370 => [403, 'No tiene permiso para esta operacion.'], // la cuenta de la aplicacion no puede escribir ahi
            1644 => [422, $textoBase], // regla de negocio: el mensaje lo escribio la base
            default => [500, 'Error de base de datos.'],
        };

        return ['status' => $status, 'mensaje' => $mensaje, 'codigo' => $codigo];
    }
}
