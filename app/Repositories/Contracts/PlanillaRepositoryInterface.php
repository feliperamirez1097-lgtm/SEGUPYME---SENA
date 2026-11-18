<?php

namespace App\Repositories\Contracts;

use App\Models\Planilla;
use App\Models\PlanillaResumen;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepositoryInterface<Planilla>
 */
interface PlanillaRepositoryInterface extends BaseRepositoryInterface
{
    /** Encabezado con empresa, lineas y total (vista v_planilla_total). */
    public function resumen(int $idPlanilla): ?PlanillaResumen;

    /** Planilla con sus lineas, y de cada linea la novedad, el concepto y la persona. */
    public function conDetalle(int $idPlanilla): Planilla;

    /**
     * Planillas de una empresa (la empresa se obtiene de las personas de las lineas).
     *
     * @return LengthAwarePaginator<int, PlanillaResumen>
     */
    public function listarPorEmpresa(string $nit, int $porPagina = 15): LengthAwarePaginator;

    // ---- Operaciones de la base (procedimientos almacenados) ----

    /** CALL sp_generar_planilla (Contador que atiende la empresa o Super Usuario): crea la planilla de una empresa y un periodo, con su calculo. Devuelve el id. */
    public function generar(int $idActor, string $nit, string $periodo, string $fechaLimite): int;

    /** CALL sp_calcular_planilla: recalcula una planilla en BORRADOR. */
    public function calcular(int $idPlanilla): void;

    /** CALL sp_aprobar_planilla (Administrador de la empresa o Super Usuario): la base bloquea si a alguien le faltan afiliaciones. */
    public function aprobar(int $idActor, int $idPlanilla): void;

    /** CALL sp_marcar_generada: registra la clave del archivo PILA. */
    public function marcarGenerada(int $idActor, int $idPlanilla, string $claveArchivo): void;

    /** CALL sp_registrar_pago: el monto debe coincidir con el total de aportes. */
    public function registrarPago(int $idActor, int $idPlanilla, string $fechaPago, string $monto, string $claveComprobante, string $sha256): void;

    /** CALL sp_refrescar_planilla: a una planilla en BORRADOR le suma las novedades aprobadas despues y el salario vigente, y la recalcula. */
    public function refrescar(int $idActor, int $idPlanilla): void;

    /** CALL sp_descartar_planilla: borra una planilla en BORRADOR con sus lineas (la aplicacion no puede hacer DELETE). */
    public function descartar(int $idActor, int $idPlanilla): void;

    /** CALL sp_generar_correccion: crea la correccion numero N a partir de una planilla normal. Devuelve el id. */
    public function generarCorreccion(int $idActor, int $idPlanillaOriginal): int;
}
