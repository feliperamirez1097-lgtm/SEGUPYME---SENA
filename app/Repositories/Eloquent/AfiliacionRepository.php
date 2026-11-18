<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoAprobacion;
use App\Enums\TipoEntidad;
use App\Models\Afiliacion;
use App\Repositories\Contracts\AfiliacionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Afiliacion>
 */
class AfiliacionRepository extends BaseRepository implements AfiliacionRepositoryInterface
{
    public function __construct(Afiliacion $model)
    {
        parent::__construct($model);
    }

    public function delUsuario(int $idUsuario): Collection
    {
        return $this->query()->where('usuario_id', $idUsuario)->with('entidad')->orderBy('tipo')->get();
    }

    public function vigentesDe(int $idUsuario, string $fecha): Collection
    {
        return $this->query()
            ->where('usuario_id', $idUsuario)
            ->aprobadas()
            ->vigentesEn($fecha)
            ->with('entidad')
            ->get();
    }

    public function pendientes(): Collection
    {
        return $this->query()
            ->where('estado', EstadoAprobacion::Pendiente)
            ->with(['usuario', 'entidad'])
            ->orderBy('fecha_inicio')
            ->get();
    }

    public function registrar(int $idActor, int $idUsuario, TipoEntidad $tipo, string $nitEntidad, string $fechaInicio): Afiliacion
    {
        DB::statement('CALL sp_registrar_afiliacion(?, ?, ?, ?, ?, @id_afiliacion)', [$idActor, $idUsuario, $tipo->value, $nitEntidad, $fechaInicio]);

        return $this->findOrFail((int) DB::selectOne('SELECT @id_afiliacion AS id')->id);
    }

    public function gestionar(int $idActor, int $idAfiliacion, EstadoAprobacion $decision): Afiliacion
    {
        DB::statement('CALL sp_gestionar_afiliacion(?, ?, ?)', [$idActor, $idAfiliacion, $decision->value]);

        return $this->findOrFail($idAfiliacion);
    }

    public function estaCompleta(int $idUsuario, string $fecha): bool
    {
        // SQL nativo: se llama a la funcion de MySQL que usa tambien el trigger que bloquea la planilla.
        return (bool) DB::scalar('SELECT fn_afiliaciones_completas(?, ?)', [$idUsuario, $fecha]);
    }
}
