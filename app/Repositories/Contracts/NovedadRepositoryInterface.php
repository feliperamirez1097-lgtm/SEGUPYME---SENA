<?php

namespace App\Repositories\Contracts;

use App\Enums\EstadoAprobacion;
use App\Enums\TipoSoporte;
use App\Models\Novedad;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Novedad>
 */
interface NovedadRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Novedad> Novedades de una persona, opcionalmente de un periodo. */
    public function delEmpleado(int $idEmpleado, ?string $periodo = null): Collection;

    /** @return Collection<int, Novedad> Novedades pendientes de aprobar de una empresa. */
    public function pendientesDeEmpresa(string $nit): Collection;

    /** @return Collection<int, Novedad> Novedades aprobadas de una empresa en un periodo. */
    public function aprobadasDelPeriodo(string $nit, string $periodo): Collection;

    /**
     * Registra una novedad llamando sp_registrar_novedad. La base decide si $idActor puede hacerlo
     * (Contador que atiende la empresa, Empleado solo las propias, Super Usuario) y deja la bitacora.
     * Devuelve la novedad ya leida, con las columnas que calcula MySQL (periodo, dias, valor).
     */
    public function registrar(int $idActor, int $idEmpleado, string $codigoConcepto, string $fechaInicio, ?string $fechaFin = null, ?float $horas = null): Novedad;

    /** Aprueba o rechaza una novedad pendiente (sp_gestionar_novedad: Administrador de la empresa o Super Usuario). */
    public function gestionar(int $idActor, int $idNovedad, EstadoAprobacion $decision): Novedad;

    /** Adjunta el soporte de una incapacidad o licencia (sp_adjuntar_soporte). */
    public function adjuntarSoporte(int $idActor, int $idNovedad, string $clave, string $sha256, TipoSoporte $tipo, int $bytes): Novedad;

    /** Lee la ubicacion del soporte y deja constancia en la bitacora (sp_ver_soporte). El Super Usuario debe dar un motivo. */
    public function verSoporte(int $idActor, int $idNovedad, ?string $motivo = null): ?object;

    /**
     * Buscador con filtros opcionales: empresa (NIT), id_empleado, estado, id_concepto, periodo.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Novedad>
     */
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator;

    /**
     * CARGA MASIVA de novedades: llama al procedimiento sp_cargar_novedades, que valida fila por fila
     * y guarda las buenas en Novedad (pendientes). Devuelve cuantas entraron y el motivo de las rechazadas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{ok: int, errores: int, detalle: string}
     */
    public function cargarMasivo(int $idActor, string $nit, array $filas): array;
}
