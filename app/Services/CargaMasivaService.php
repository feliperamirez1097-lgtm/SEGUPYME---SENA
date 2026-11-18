<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Support\ResultadoCarga;
use Illuminate\Database\QueryException;

/**
 * Carga masiva: la aplicacion convierte el archivo (Excel o CSV) en una lista de filas y la base la reparte.
 *
 * No hay tabla de cargas: el procedimiento valida fila por fila, guarda las buenas en las tablas que ya
 * existen (Usuario, Cargos, Afiliacion, Novedad) y devuelve el motivo de cada fila rechazada.
 */
class CargaMasivaService
{
    public function __construct(
        private NovedadRepositoryInterface $novedades,
        private UsuarioRepositoryInterface $usuarios,
    ) {}

    /**
     * Cada fila: cedula, concepto (codigo), fecha_inicio, fecha_fin (opcional) y horas (opcional).
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    public function novedades(int $idActor, string $nit, array $filas): ResultadoCarga
    {
        try {
            return ResultadoCarga::desdeProcedimiento($this->novedades->cargarMasivo($idActor, $nit, $filas));
        } catch (QueryException $e) {
            throw ReglaDeNegocioException::desde($e);
        }
    }

    /**
     * Cada fila: cedula, nombre, cargo, salario, tipo_contrato, fecha_vinculacion y, opcionales,
     * eps, afp, arl, ccf (NIT de cada entidad).
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    public function empleados(int $idActor, string $nit, array $filas): ResultadoCarga
    {
        try {
            return ResultadoCarga::desdeProcedimiento($this->usuarios->cargarMasivo($idActor, $nit, $filas));
        } catch (QueryException $e) {
            throw ReglaDeNegocioException::desde($e);
        }
    }
}
