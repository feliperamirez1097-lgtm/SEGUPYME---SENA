<?php

namespace App\Repositories\Eloquent;

use App\Models\Planilla;
use App\Models\PlanillaResumen;
use App\Repositories\Contracts\PlanillaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Planilla>
 */
class PlanillaRepository extends BaseRepository implements PlanillaRepositoryInterface
{
    public function __construct(Planilla $model)
    {
        parent::__construct($model);
    }

    public function resumen(int $idPlanilla): ?PlanillaResumen
    {
        return PlanillaResumen::query()->find($idPlanilla);
    }

    public function conDetalle(int $idPlanilla): Planilla
    {
        return $this->query()
            ->with(['parametro', 'resumen', 'detalles.novedad.concepto', 'detalles.persona'])
            ->findOrFail($idPlanilla);
    }

    public function listarPorEmpresa(string $nit, int $porPagina = 15): LengthAwarePaginator
    {
        return PlanillaResumen::query()
            ->where('empresa_id', $nit)
            ->orderByDesc('periodo')
            ->orderBy('correccion_nro')
            ->paginate($porPagina)
            ->withQueryString();
    }

    // ---------------------------------------------------------------------------------------------
    // Procedimientos almacenados. La base hace el trabajo (y se defiende con triggers); aqui solo se
    // llama. Los parametros OUT se leen con una variable de usuario de MySQL en la misma conexion.
    // ---------------------------------------------------------------------------------------------

    public function generar(int $idActor, string $nit, string $periodo, string $fechaLimite): int
    {
        DB::statement('CALL sp_generar_planilla(?, ?, ?, ?, @id_planilla)', [$idActor, $nit, $periodo, $fechaLimite]);

        return (int) DB::selectOne('SELECT @id_planilla AS id')->id;
    }

    public function calcular(int $idPlanilla): void
    {
        DB::statement('CALL sp_calcular_planilla(?)', [$idPlanilla]);
    }

    public function aprobar(int $idActor, int $idPlanilla): void
    {
        DB::statement('CALL sp_aprobar_planilla(?, ?)', [$idActor, $idPlanilla]);
    }

    public function marcarGenerada(int $idActor, int $idPlanilla, string $claveArchivo): void
    {
        DB::statement('CALL sp_marcar_generada(?, ?, ?)', [$idActor, $idPlanilla, $claveArchivo]);
    }

    public function registrarPago(int $idActor, int $idPlanilla, string $fechaPago, string $monto, string $claveComprobante, string $sha256): void
    {
        DB::statement('CALL sp_registrar_pago(?, ?, ?, ?, ?, ?)', [$idActor, $idPlanilla, $fechaPago, $monto, $claveComprobante, $sha256]);
    }

    public function refrescar(int $idActor, int $idPlanilla): void
    {
        DB::statement('CALL sp_refrescar_planilla(?, ?)', [$idActor, $idPlanilla]);
    }

    public function descartar(int $idActor, int $idPlanilla): void
    {
        DB::statement('CALL sp_descartar_planilla(?, ?)', [$idActor, $idPlanilla]);
    }

    public function generarCorreccion(int $idActor, int $idPlanillaOriginal): int
    {
        DB::statement('CALL sp_generar_correccion(?, ?, @id_correccion)', [$idActor, $idPlanillaOriginal]);

        return (int) DB::selectOne('SELECT @id_correccion AS id')->id;
    }
}
