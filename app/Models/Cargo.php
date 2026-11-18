<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de la tabla `Cargos`: los cargos de cada empresa.
 *
 * El cargo de una persona define su empresa; por eso la empresa no se guarda en Usuario.
 *
 * @property int $id_cargo
 * @property string $nombre_cargo
 * @property string $empresa_id NIT de la empresa.
 */
#[Table(name: 'Cargos', key: 'id_cargo', timestamps: false)]
#[Fillable(['nombre_cargo', 'empresa_id'])]
class Cargo extends Model
{
    /** CARGO N : 1 EMPRESA */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'nit');
    }

    /** CARGO 1 : N USUARIO */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'cargo_id', 'id_cargo');
    }
}
