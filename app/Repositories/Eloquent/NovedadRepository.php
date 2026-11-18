<?php

namespace App\Repositories\Eloquent;

use App\Enums\EstadoAprobacion;
use App\Enums\TipoSoporte;
use App\Models\Novedad;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Novedad>
 */
class NovedadRepository extends BaseRepository implements NovedadRepositoryInterface
{
    public function __construct(Novedad $model)
    {
        parent::__construct($model);
    }

    public function delEmpleado(int $idEmpleado, ?string $periodo = null): Collection
    {
        return $this->query()
            ->where('empleado_id', $idEmpleado)
            ->when($periodo, fn (Builder $q, string $p) => $q->delPeriodo($p))
            ->with('concepto')
            ->orderBy('fecha_inicio')
            ->get();
    }

    public function pendientesDeEmpresa(string $nit): Collection
    {
        // La empresa de una novedad se obtiene por la persona: novedad -> empleado -> cargo -> empresa.
        return $this->query()
            ->pendientes()
            ->whereHas('empleado.cargo', fn (Builder $q) => $q->where('empresa_id', $nit))
            ->with(['empleado', 'concepto'])
            ->orderBy('fecha_inicio')
            ->get();
    }

    public function aprobadasDelPeriodo(string $nit, string $periodo): Collection
    {
        return $this->query()
            ->aprobadas()
            ->delPeriodo($periodo)
            ->whereHas('empleado.cargo', fn (Builder $q) => $q->where('empresa_id', $nit))
            ->with(['empleado', 'concepto'])
            ->orderBy('empleado_id')
            ->get();
    }

    public function registrar(int $idActor, int $idEmpleado, string $codigoConcepto, string $fechaInicio, ?string $fechaFin = null, ?float $horas = null): Novedad
    {
        // La aplicacion no puede insertar en Novedad: solo ejecutar el procedimiento, que valida los permisos.
        DB::statement('CALL sp_registrar_novedad(?, ?, ?, ?, ?, ?, @id_novedad)', [
            $idActor, $idEmpleado, $codigoConcepto, $fechaInicio, $fechaFin, $horas,
        ]);
        $id = (int) DB::selectOne('SELECT @id_novedad AS id')->id;

        // Se lee de nuevo: periodo, dias y valor los calcula MySQL.
        return $this->findOrFail($id);
    }

    public function gestionar(int $idActor, int $idNovedad, EstadoAprobacion $decision): Novedad
    {
        DB::statement('CALL sp_gestionar_novedad(?, ?, ?)', [$idActor, $idNovedad, $decision->value]);

        return $this->findOrFail($idNovedad);
    }

    public function adjuntarSoporte(int $idActor, int $idNovedad, string $clave, string $sha256, TipoSoporte $tipo, int $bytes): Novedad
    {
        DB::statement('CALL sp_adjuntar_soporte(?, ?, ?, ?, ?, ?)', [$idActor, $idNovedad, $clave, $sha256, $tipo->value, $bytes]);

        return $this->findOrFail($idNovedad);
    }

    public function verSoporte(int $idActor, int $idNovedad, ?string $motivo = null): ?object
    {
        // El procedimiento devuelve una fila con la clave, el tipo y el tamano del soporte.
        return DB::select('CALL sp_ver_soporte(?, ?, ?)', [$idActor, $idNovedad, $motivo])[0] ?? null;
    }

    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['empleado', 'concepto'])
            ->when($filtros['empresa'] ?? null, fn (Builder $q, string $nit) => $q->whereHas('empleado.cargo', fn (Builder $c) => $c->where('empresa_id', $nit)))
            ->when($filtros['id_empleado'] ?? null, fn (Builder $q, $id) => $q->where('empleado_id', $id))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $estado) => $q->where('estado', $estado))
            ->when($filtros['id_concepto'] ?? null, fn (Builder $q, $c) => $q->where('concepto_id', $c))
            ->when($filtros['periodo'] ?? null, fn (Builder $q, string $p) => $q->delPeriodo($p))
            ->orderByDesc($this->model->getKeyName())
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function cargarMasivo(int $idActor, string $nit, array $filas): array
    {
        DB::statement('CALL sp_cargar_novedades(?, ?, ?, @ok, @errores, @detalle)', [
            $idActor, $nit, json_encode($filas, JSON_UNESCAPED_UNICODE),
        ]);
        $r = DB::selectOne('SELECT @ok AS ok, @errores AS errores, @detalle AS detalle');

        return ['ok' => (int) $r->ok, 'errores' => (int) $r->errores, 'detalle' => (string) $r->detalle];
    }
}
