<?php

namespace App\Repositories\Eloquent;

use App\Models\DetallePlanilla;
use App\Repositories\Contracts\DetallePlanillaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<DetallePlanilla>
 */
class DetallePlanillaRepository extends BaseRepository implements DetallePlanillaRepositoryInterface
{
    public function __construct(DetallePlanilla $model)
    {
        parent::__construct($model);
    }

    public function lineas(int $idPlanilla): Collection
    {
        return $this->query()
            ->where('planilla_id', $idPlanilla)
            ->with(['novedad.concepto', 'persona'])
            ->orderBy('id_detalle')
            ->get();
    }

    public function totalesPorPersona(int $idPlanilla): array
    {
        // Reporte con varios JOIN y GROUP BY: aqui el SQL nativo es mas claro que Eloquent (manual, seccion 12).
        return DB::select(<<<'SQL'
            SELECT u.id_usuario,
                   u.nombre                                  AS persona,
                   SUM(d.ibc)                                AS ibc,
                   SUM(d.valor_salud)                        AS salud,
                   SUM(d.valor_pension)                      AS pension,
                   SUM(d.valor_arl)                          AS arl,
                   SUM(d.valor_parafiscales)                 AS parafiscales,
                   SUM(d.valor_salud + d.valor_pension + d.valor_arl + d.valor_parafiscales) AS total
              FROM Detalle_Planilla d
              JOIN Novedad n ON n.id_novedad = d.novedad_id
              JOIN Usuario u ON u.id_usuario = n.empleado_id
             WHERE d.planilla_id = ?
             GROUP BY u.id_usuario, u.nombre
             ORDER BY u.nombre
            SQL, [$idPlanilla]);
    }
}
