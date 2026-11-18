<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\PlanillaResumen;
use App\Repositories\Contracts\PlanillaRepositoryInterface;
use Closure;
use Illuminate\Database\QueryException;

/**
 * Operaciones de planilla que coordinan al repositorio y traducen los errores de la base.
 *
 * Cada operacion recibe a quien la hace ($idActor): la base decide por su rol si puede (el Contador que atiende
 * la empresa genera y corrige; el Administrador de la empresa aprueba; etc.). Las reglas (misma empresa, bloqueo por
 * afiliaciones incompletas, planilla inmutable despues de aprobada, una planilla normal por empresa y periodo...)
 * viven en MySQL como triggers y procedimientos. El servicio no las repite: llama y, si la base se niega, entrega un mensaje claro.
 */
class PlanillaService
{
    public function __construct(private PlanillaRepositoryInterface $planillas) {}

    /** Genera la planilla normal de una empresa para un periodo (YYYY-MM-01). */
    public function generar(int $idActor, string $nit, string $periodo, string $fechaLimite): PlanillaResumen
    {
        return $this->ejecutar(function () use ($idActor, $nit, $periodo, $fechaLimite) {
            $id = $this->planillas->generar($idActor, $nit, $periodo, $fechaLimite);

            return $this->planillas->resumen($id);
        });
    }

    /** Aprueba la planilla. Si a alguien le faltan afiliaciones, la base la bloquea. */
    public function aprobar(int $idActor, int $idPlanilla): PlanillaResumen
    {
        return $this->ejecutar(function () use ($idActor, $idPlanilla) {
            $this->planillas->aprobar($idActor, $idPlanilla);

            return $this->planillas->resumen($idPlanilla);
        });
    }

    public function marcarGenerada(int $idActor, int $idPlanilla, string $claveArchivo): PlanillaResumen
    {
        return $this->ejecutar(function () use ($idActor, $idPlanilla, $claveArchivo) {
            $this->planillas->marcarGenerada($idActor, $idPlanilla, $claveArchivo);

            return $this->planillas->resumen($idPlanilla);
        });
    }

    /** Registra el pago; el monto debe ser exactamente el total de aportes. */
    public function registrarPago(int $idActor, int $idPlanilla, string $fechaPago, string $monto, string $claveComprobante, string $sha256): PlanillaResumen
    {
        return $this->ejecutar(function () use ($idActor, $idPlanilla, $fechaPago, $monto, $claveComprobante, $sha256) {
            $this->planillas->registrarPago($idActor, $idPlanilla, $fechaPago, $monto, $claveComprobante, $sha256);

            return $this->planillas->resumen($idPlanilla);
        });
    }

    /** Suma a una planilla en BORRADOR las novedades aprobadas despues de generarla y la recalcula. */
    public function refrescar(int $idActor, int $idPlanilla): PlanillaResumen
    {
        return $this->ejecutar(function () use ($idActor, $idPlanilla) {
            $this->planillas->refrescar($idActor, $idPlanilla);

            return $this->planillas->resumen($idPlanilla);
        });
    }

    /** Descarta una planilla en BORRADOR (para generarla de nuevo). */
    public function descartar(int $idActor, int $idPlanilla): void
    {
        $this->ejecutar(fn () => $this->planillas->descartar($idActor, $idPlanilla));
    }

    /** Genera una correccion a partir de una planilla normal ya aprobada. */
    public function corregir(int $idActor, int $idPlanillaOriginal): PlanillaResumen
    {
        return $this->ejecutar(function () use ($idActor, $idPlanillaOriginal) {
            $id = $this->planillas->generarCorreccion($idActor, $idPlanillaOriginal);

            return $this->planillas->resumen($id);
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $accion
     * @return T
     */
    private function ejecutar(Closure $accion): mixed
    {
        try {
            return $accion();
        } catch (QueryException $e) {
            throw ReglaDeNegocioException::desde($e);
        }
    }
}
