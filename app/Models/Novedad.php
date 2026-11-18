<?php

namespace App\Models;

use App\Enums\EstadoAprobacion;
use App\Enums\TipoSoporte;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de la tabla `Novedad`: lo que OCURRIO (salario del periodo, incapacidad, horas extra...).
 *
 * Es solo la entrada. El resultado por persona y concepto vive en Detalle_Planilla.
 * Una novedad no cruza de mes: se registra una por mes.
 *
 * `periodo` y `dias` son columnas GENERADAS por MySQL a partir de las fechas, por eso NO estan
 * en $fillable (manual, fila 45). Despues de un create() hay que llamar refresh() para leerlas.
 * `valor` lo calcula un trigger para las horas extra y recargos.
 *
 * @property int $id_novedad
 * @property int $empleado_id
 * @property int $concepto_id
 * @property Carbon $periodo Generada: primer dia del mes de fecha_inicio.
 * @property int|null $dias Generada: dias entre fecha_inicio y fecha_fin.
 * @property string|null $horas
 * @property string|null $valor
 * @property EstadoAprobacion $estado
 */
#[Table(name: 'Novedad', key: 'id_novedad', timestamps: false)]
#[Fillable([
    'empleado_id', 'concepto_id', 'fecha_inicio', 'fecha_fin', 'horas', 'valor', 'estado',
    'soporte_clave', 'soporte_sha256', 'soporte_tipo', 'soporte_bytes',
])]
class Novedad extends Model
{
    protected $attributes = ['estado' => 'PENDIENTE'];

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'dias' => 'integer',
            'horas' => 'decimal:2',
            'valor' => 'decimal:2',
            'estado' => EstadoAprobacion::class,
            'soporte_tipo' => TipoSoporte::class,
            'soporte_bytes' => 'integer',
        ];
    }

    /** NOVEDAD N : 1 USUARIO (la persona a quien le ocurrio). */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'empleado_id', 'id_usuario');
    }

    /** NOVEDAD N : 1 CONCEPTO */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id', 'id_concepto');
    }

    /** NOVEDAD 1 : N DETALLE_PLANILLA (una por cada planilla donde entra: la normal y sus correcciones). */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePlanilla::class, 'novedad_id', 'id_novedad');
    }

    /** N:M con Planilla a traves de Detalle_Planilla (manual, fila 57), con los valores calculados. */
    public function planillas(): BelongsToMany
    {
        return $this->belongsToMany(Planilla::class, 'Detalle_Planilla', 'novedad_id', 'planilla_id', 'id_novedad', 'id_planilla')
            ->withPivot(['ibc', 'valor_salud', 'valor_pension', 'valor_arl', 'valor_parafiscales']);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoAprobacion::Pendiente);
    }

    public function scopeAprobadas(Builder $query): Builder
    {
        return $query->where('estado', EstadoAprobacion::Aprobada);
    }

    /** Scope: novedades de un periodo (cualquier fecha del mes sirve). */
    public function scopeDelPeriodo(Builder $query, string $fecha): Builder
    {
        return $query->where('periodo', date('Y-m-01', strtotime($fecha)));
    }

    protected function tieneSoporte(): Attribute
    {
        return Attribute::make(get: fn () => $this->soporte_clave !== null);
    }
}
