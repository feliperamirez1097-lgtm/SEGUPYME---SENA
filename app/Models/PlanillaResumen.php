<?php

namespace App\Models;

use App\Enums\EstadoPlanilla;
use App\Enums\TipoPlanilla;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de SOLO LECTURA sobre la VISTA `v_planilla_total`.
 *
 * Aqui se ve lo que la planilla no guarda: la empresa (se obtiene de las personas de sus lineas),
 * la cantidad de lineas y el total de aportes (suma del detalle).
 *
 * No tiene $fillable ni se usa para escribir: una vista no se inserta ni se actualiza.
 *
 * @property int $id_planilla
 * @property string|null $empresa_id
 * @property int $lineas
 * @property string $total_aportes
 */
#[Table(name: 'v_planilla_total', key: 'id_planilla', timestamps: false)]
class PlanillaResumen extends Model
{
    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'tipo_planilla' => TipoPlanilla::class,
            'estado' => EstadoPlanilla::class,
            'correccion_nro' => 'integer',
            'lineas' => 'integer',
            'total_aportes' => 'decimal:2',
        ];
    }

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(Planilla::class, 'id_planilla', 'id_planilla');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'nit');
    }
}
