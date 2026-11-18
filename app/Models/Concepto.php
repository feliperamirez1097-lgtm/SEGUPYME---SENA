<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de la tabla `Concepto`: el catalogo que dice COMO SE TRANSFORMA cada tipo de novedad.
 *
 * Una fila por tipo (SALARIO, INCAPACIDAD, HORA_EXTRA_DIURNA...). Las reglas son datos, no codigo:
 * codigo PILA, columna destino, si afecta el IBC, porcentaje y si suma la hora base o el dominical.
 *
 * @property int $id_concepto
 * @property string $codigo
 * @property string|null $codigo_pila
 * @property bool $afecta_ibc
 * @property string|null $porcentaje_fijo Solo las horas extra y recargos lo tienen.
 * @property bool $suma_dominical Suma el recargo dominical vigente al porcentaje fijo.
 * @property bool $incluye_hora_base Horas extra: pagan la hora mas el porcentaje; recargos: solo el porcentaje.
 */
#[Table(name: 'Concepto', key: 'id_concepto', incrementing: false, timestamps: false)]
#[Fillable([
    'id_concepto', 'codigo', 'nombre', 'codigo_pila', 'columna_destino', 'afecta_ibc',
    'requiere_dias', 'requiere_soporte', 'porcentaje_fijo', 'suma_dominical', 'incluye_hora_base',
])]
class Concepto extends Model
{
    protected function casts(): array
    {
        return [
            'afecta_ibc' => 'boolean',
            'requiere_dias' => 'boolean',
            'requiere_soporte' => 'boolean',
            'suma_dominical' => 'boolean',
            'incluye_hora_base' => 'boolean',
            'porcentaje_fijo' => 'decimal:2',
        ];
    }

    /** CONCEPTO 1 : N NOVEDAD */
    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class, 'concepto_id', 'id_concepto');
    }

    /** Un concepto es de horas cuando tiene porcentaje fijo (no hay columna "requiere_horas"). */
    protected function esDeHoras(): Attribute
    {
        return Attribute::make(get: fn () => $this->porcentaje_fijo !== null);
    }

    /** Scope: horas extra y recargos. */
    public function scopeDeHoras(Builder $query): Builder
    {
        return $query->whereNotNull('porcentaje_fijo');
    }
}
