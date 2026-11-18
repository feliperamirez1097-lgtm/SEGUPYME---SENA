<?php

namespace App\Repositories\Contracts;

use App\Enums\EstadoAprobacion;
use App\Enums\TipoEntidad;
use App\Models\Afiliacion;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Afiliacion>
 */
interface AfiliacionRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Afiliacion> */
    public function delUsuario(int $idUsuario): Collection;

    /** @return Collection<int, Afiliacion> Afiliaciones aprobadas y vigentes en una fecha. */
    public function vigentesDe(int $idUsuario, string $fecha): Collection;

    /** @return Collection<int, Afiliacion> Afiliaciones pendientes de aprobar, con persona y entidad. */
    public function pendientes(): Collection;

    /** Registra una afiliacion pendiente (sp_registrar_afiliacion: Contador que atiende la empresa o Super Usuario). */
    public function registrar(int $idActor, int $idUsuario, TipoEntidad $tipo, string $nitEntidad, string $fechaInicio): Afiliacion;

    /** Aprueba o rechaza una afiliacion pendiente (sp_gestionar_afiliacion: Administrador de la empresa o Super Usuario). */
    public function gestionar(int $idActor, int $idAfiliacion, EstadoAprobacion $decision): Afiliacion;

    /** ¿La persona tiene las 4 afiliaciones aprobadas y vigentes en la fecha? (funcion de MySQL). */
    public function estaCompleta(int $idUsuario, string $fecha): bool;
}
