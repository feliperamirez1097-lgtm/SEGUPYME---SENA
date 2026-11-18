<?php

namespace App\Models;

use App\Enums\EstadoPlanilla;
use App\Enums\OrigenPlanilla;
use App\Enums\TipoPlanilla;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo de la tabla `Planilla`: el encabezado (periodo, vigencia legal, estado y pago).
 *
 * La planilla NO guarda la empresa ni el total:
 *  - llega a las personas solo por las novedades (Usuario -> Novedad -> Detalle_Planilla -> Planilla);
 *  - la empresa y el total se obtienen de sus lineas: ver el modelo PlanillaResumen (vista).
 *
 * Los estados avanzan en orden BORRADOR -> APROBADA -> GENERADA -> PAGADA, y despues de aprobada
 * la base impide modificarla. Para generar, calcular o aprobar se usan los procedimientos
 * almacenados (PlanillaRepository), no create()/update().
 *
 * @property int $id_planilla
 * @property int $parametro_id
 * @property Carbon $periodo
 * @property TipoPlanilla $tipo_planilla
 * @property int $correccion_nro 0 = normal; 1, 2... = correcciones.
 * @property EstadoPlanilla $estado
 */
#[Table(name: 'Planilla', key: 'id_planilla', timestamps: false)]
#[Fillable([
    'parametro_id', 'periodo', 'tipo_planilla', 'correccion_nro', 'origen', 'estado', 'fecha_limite',
    'archivo_pila_clave', 'fecha_pago', 'monto_pagado', 'comprobante_clave', 'comprobante_sha256',
])]
class Planilla extends Model
{
    protected $attributes = ['estado' => 'BORRADOR', 'tipo_planilla' => 'NORMAL', 'correccion_nro' => 0, 'origen' => 'SISTEMA'];

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'fecha_limite' => 'date',
            'fecha_pago' => 'date',
            'monto_pagado' => 'decimal:2',
            'correccion_nro' => 'integer',
            'tipo_planilla' => TipoPlanilla::class,
            'origen' => OrigenPlanilla::class,
            'estado' => EstadoPlanilla::class,
        ];
    }

    /** PLANILLA N : 1 PARAMETRO_LEGAL (la vigencia con la que se liquido). */
    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroLegal::class, 'parametro_id', 'id_parametro');
    }

    /** PLANILLA 1 : N DETALLE_PLANILLA */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePlanilla::class, 'planilla_id', 'id_planilla');
    }

    /** N:M con Novedad a traves de Detalle_Planilla (manual, fila 57). */
    public function novedades(): BelongsToMany
    {
        return $this->belongsToMany(Novedad::class, 'Detalle_Planilla', 'planilla_id', 'novedad_id', 'id_planilla', 'id_novedad')
            ->withPivot(['ibc', 'valor_salud', 'valor_pension', 'valor_arl', 'valor_parafiscales']);
    }

    /** Empresa, lineas y total, tomados de la vista v_planilla_total. */
    public function resumen(): HasOne
    {
        return $this->hasOne(PlanillaResumen::class, 'id_planilla', 'id_planilla');
    }

    public function scopeBorrador(Builder $query): Builder
    {
        return $query->where('estado', EstadoPlanilla::Borrador);
    }

    /** Scope: planillas de un periodo (cualquier fecha del mes sirve). */
    public function scopeDelPeriodo(Builder $query, string $fecha): Builder
    {
        return $query->where('periodo', date('Y-m-01', strtotime($fecha)));
    }
}
