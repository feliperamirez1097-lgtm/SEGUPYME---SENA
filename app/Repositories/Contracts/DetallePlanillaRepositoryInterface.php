<?php

namespace App\Repositories\Contracts;

use App\Models\DetallePlanilla;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<DetallePlanilla>
 */
interface DetallePlanillaRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, DetallePlanilla> Lineas de una planilla con novedad, concepto y persona. */
    public function lineas(int $idPlanilla): Collection;

    /**
     * Reporte por persona de una planilla (SQL nativo con varios JOIN, como recomienda el manual):
     * IBC, salud, pension, ARL, parafiscales y total de cada persona.
     *
     * @return array<int, object>
     */
    public function totalesPorPersona(int $idPlanilla): array;
}
