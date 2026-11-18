<?php

namespace App\Models;

use App\Enums\ClaseRiesgoArl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de la tabla `Parametro_Legal`: los valores legales, una fila por vigencia.
 *
 * Salario minimo, divisor de hora, tarifas (salud, pension, caja, SENA/ICBF, ARL por clase) y recargo
 * dominical. Una vigencia que ya uso una planilla no se modifica (lo impide un trigger): si cambia la
 * ley, se agrega una fila nueva. Cada planilla apunta a la vigencia con la que se liquido.
 *
 * @property int $id_parametro
 * @property string $salario_minimo
 * @property int $divisor_hora
 */
#[Table(name: 'Parametro_Legal', key: 'id_parametro', timestamps: false)]
#[Fillable([
    'vigente_desde', 'salario_minimo', 'divisor_hora', 'recargo_dominical',
    'tarifa_salud_empleador', 'tarifa_salud_empleado', 'tarifa_pension_empleador', 'tarifa_pension_empleado',
    'tarifa_caja', 'tarifa_sena_icbf', 'tarifa_arl_i', 'tarifa_arl_ii', 'tarifa_arl_iii', 'tarifa_arl_iv', 'tarifa_arl_v',
])]
class ParametroLegal extends Model
{
    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'salario_minimo' => 'decimal:2',
            'divisor_hora' => 'integer',
            'recargo_dominical' => 'decimal:2',
            'tarifa_salud_empleador' => 'decimal:2',
            'tarifa_salud_empleado' => 'decimal:2',
            'tarifa_pension_empleador' => 'decimal:2',
            'tarifa_pension_empleado' => 'decimal:2',
            'tarifa_caja' => 'decimal:2',
            'tarifa_sena_icbf' => 'decimal:2',
            'tarifa_arl_i' => 'decimal:3',
            'tarifa_arl_ii' => 'decimal:3',
            'tarifa_arl_iii' => 'decimal:3',
            'tarifa_arl_iv' => 'decimal:3',
            'tarifa_arl_v' => 'decimal:3',
        ];
    }

    /** PARAMETRO_LEGAL 1 : N PLANILLA */
    public function planillas(): HasMany
    {
        return $this->hasMany(Planilla::class, 'parametro_id', 'id_parametro');
    }

    /** Tarifa de ARL (%) segun la clase de riesgo de la empresa. */
    public function tarifaArl(ClaseRiesgoArl $clase): string
    {
        return match ($clase) {
            ClaseRiesgoArl::I => $this->tarifa_arl_i,
            ClaseRiesgoArl::II => $this->tarifa_arl_ii,
            ClaseRiesgoArl::III => $this->tarifa_arl_iii,
            ClaseRiesgoArl::IV => $this->tarifa_arl_iv,
            ClaseRiesgoArl::V => $this->tarifa_arl_v,
        };
    }

    /** Scope: la vigencia que rige en una fecha (la ultima con vigente_desde <= fecha). */
    public function scopeVigenteEn(Builder $query, string $fecha): Builder
    {
        return $query->where('vigente_desde', '<=', $fecha)->orderByDesc('vigente_desde')->limit(1);
    }
}
