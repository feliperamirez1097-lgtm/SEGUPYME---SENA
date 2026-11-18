<?php

namespace App\Repositories\Eloquent;

use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Usuario>
 */
class UsuarioRepository extends BaseRepository implements UsuarioRepositoryInterface
{
    public function __construct(Usuario $model)
    {
        parent::__construct($model);
    }

    public function findByCedula(string $cedula): ?Usuario
    {
        return $this->query()->where('cedula', $cedula)->first();
    }

    public function findByCorreo(string $correo): ?Usuario
    {
        return $this->query()->where('correo_acceso', mb_strtolower(trim($correo)))->first();
    }

    public function porEmpresa(string $nit, bool $soloActivos = true): Collection
    {
        return $this->query()
            ->deEmpresa($nit)
            ->when($soloActivos, fn (Builder $q) => $q->activos())
            ->with(['cargo', 'rol'])
            ->orderBy('nombre')
            ->get();
    }

    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['cargo.empresa', 'rol'])
            ->when($filtros['nombre'] ?? null, fn (Builder $q, string $n) => $q->where('nombre', 'like', "%{$n}%"))
            ->when($filtros['cedula'] ?? null, fn (Builder $q, string $c) => $q->where('cedula', $c))
            ->when($filtros['id_rol'] ?? null, fn (Builder $q, $r) => $q->where('id_rol', $r))
            ->when($filtros['empresa'] ?? null, fn (Builder $q, string $nit) => $q->deEmpresa($nit))
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function conAfiliacionesIncompletas(string $nit, string $fecha): Collection
    {
        // Eloquent + una funcion de MySQL: whereRaw llama a fn_afiliaciones_completas con parametros enlazados.
        return $this->query()
            ->deEmpresa($nit)
            ->activos()
            ->whereRaw('fn_afiliaciones_completas(Usuario.id_usuario, ?) = 0', [$fecha])
            ->orderBy('nombre')
            ->get();
    }

    public function registrarCreacionCuenta(int $idActor, int $idCuenta): void
    {
        DB::statement('CALL sp_registrar_creacion_cuenta(?, ?)', [$idActor, $idCuenta]);
    }

    public function cargarMasivo(int $idActor, string $nit, array $filas): array
    {
        // La aplicacion convierte el archivo (Excel o CSV) en filas; la base las valida y reparte.
        DB::statement('CALL sp_cargar_empleados(?, ?, ?, @ok, @errores, @detalle)', [
            $idActor, $nit, json_encode($filas, JSON_UNESCAPED_UNICODE),
        ]);
        $r = DB::selectOne('SELECT @ok AS ok, @errores AS errores, @detalle AS detalle');

        return ['ok' => (int) $r->ok, 'errores' => (int) $r->errores, 'detalle' => (string) $r->detalle];
    }
}
