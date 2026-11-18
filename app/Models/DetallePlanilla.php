<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Modelo de la tabla `Detalle_Planilla`: el RESULTADO por persona y concepto.
 *
 * Une la planilla con la novedad (y por la novedad llegan la persona y el concepto: no se repiten
 * aqui). Guarda el IBC y los valores de salud, pension, ARL y parafiscales. Estos valores los
 * calcula el procedimiento sp_calcular_planilla; no se escriben a mano.
 *
 * @property int $id_detalle
 * @property int $planilla_id
 * @property int $novedad_id
 * @property string $ibc
 * @property string $valor_salud
 * @property string $valor_pension
 * @property string $valor_arl
 * @property string $valor_parafiscales
 */
#[Table(name: 'Detalle_Planilla', key: 'id_detalle', timestamps: false)]
// Solo las dos llaves: los valores los pone sp_calcular_planilla, por eso no estan en $fillable.
#[Fillable(['planilla_id', 'novedad_id'])]
class DetallePlanilla extends Model
{
    protected function casts(): array
    {
        return [
            'ibc' => 'decimal:2',
            'valor_salud' => 'decimal:2',
            'valor_pension' => 'decimal:2',
            'valor_arl' => 'decimal:2',
            'valor_parafiscales' => 'decimal:2',
        ];
    }

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(Planilla::class, 'planilla_id', 'id_planilla');
    }

    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad::class, 'novedad_id', 'id_novedad');
    }

    /** La persona de la linea, a traves de su novedad (detalle -> novedad -> usuario). */
    public function persona(): HasOneThrough
    {
        return $this->hasOneThrough(Usuario::class, Novedad::class, 'id_novedad', 'id_usuario', 'novedad_id', 'empleado_id');
    }

    /** Columna calculada al leer (manual, fila 44): lo que aporta esta linea en total. */
    protected function totalAportes(): Attribute
    {
        return Attribute::make(
            get: fn () => bcadd(
                bcadd($this->valor_salud, $this->valor_pension, 2),
                bcadd($this->valor_arl, $this->valor_parafiscales, 2),
                2,
            ),
        );
    }
}
