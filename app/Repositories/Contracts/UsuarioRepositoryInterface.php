<?php

namespace App\Repositories\Contracts;

use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Usuario>
 */
interface UsuarioRepositoryInterface extends BaseRepositoryInterface
{
    public function findByCedula(string $cedula): ?Usuario;

    public function findByCorreo(string $correo): ?Usuario;

    /** @return Collection<int, Usuario> Personas de una empresa (por su cargo). */
    public function porEmpresa(string $nit, bool $soloActivos = true): Collection;

    /**
     * Buscador con filtros opcionales: nombre, cedula, id_rol y empresa (NIT).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Usuario>
     */
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator;

    /**
     * Personas de la empresa a las que les falta alguna de las 4 afiliaciones vigentes en una fecha.
     * Son las que bloquean la aprobacion de la planilla.
     *
     * @return Collection<int, Usuario>
     */
    public function conAfiliacionesIncompletas(string $nit, string $fecha): Collection;

    /** Deja constancia en la bitacora de quien creo una cuenta (sp_registrar_creacion_cuenta). */
    public function registrarCreacionCuenta(int $idActor, int $idCuenta): void;

    /**
     * CARGA MASIVA de empleados: llama al procedimiento sp_cargar_empleados, que reparte cada fila
     * en Cargos, Usuario y Afiliacion. Devuelve cuantas filas entraron y el motivo de las rechazadas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{ok: int, errores: int, detalle: string}
     */
    public function cargarMasivo(int $idActor, string $nit, array $filas): array;
}
