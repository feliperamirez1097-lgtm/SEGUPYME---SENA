<?php

namespace App\Exceptions;

use App\Support\ErroresMysql;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Una regla de la base de datos o del negocio impidio la operacion.
 *
 * Los servicios la lanzan en lugar de dejar pasar el QueryException crudo; asi el controlador
 * (o el manejador de excepciones) responde con un mensaje claro y el codigo HTTP correcto.
 */
class ReglaDeNegocioException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly int $status = 422, public readonly ?int $codigoMysql = null)
    {
        parent::__construct($mensaje);
    }

    public static function desde(QueryException $e): self
    {
        $t = ErroresMysql::traducir($e);

        return new self($t['mensaje'], $t['status'], $t['codigo']);
    }

    /** Laravel usa este metodo para convertir la excepcion en respuesta. */
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
