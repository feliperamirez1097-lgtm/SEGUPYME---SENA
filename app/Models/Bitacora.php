<?php

namespace App\Models;

use App\Enums\AccionBitacora;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo de la tabla `Bitacora`: quien hizo que y cuando.
 *
 * La escriben los procedimientos (la aplicacion no puede insertar): la base la protege con triggers
 * (no se modifica ni se borra). Tambien dice quien cargo cada novedad o cuenta en una carga masiva.
 *
 * @property int $id_bitacora
 * @property int $usuario_id
 * @property AccionBitacora $accion
 * @property string $tabla_afectada
 * @property int $registro_id
 */
#[Table(name: 'Bitacora', key: 'id_bitacora', timestamps: false)]
// Sin #[Fillable]: la aplicacion no escribe la bitacora. fecha_hora la pone MySQL (DEFAULT CURRENT_TIMESTAMP).
class Bitacora extends Model
{
    protected function casts(): array
    {
        return [
            'accion' => AccionBitacora::class,
            'fecha_hora' => 'datetime',
        ];
    }

    /** BITACORA N : 1 USUARIO (quien lo hizo). */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }

    /**
     * Relacion polimorfica (manual, fila 59): el registro afectado puede ser una Novedad,
     * un Usuario, etc. `tabla_afectada` hace de "tipo" y `registro_id` de "id".
     * El mapa de nombres esta en AppServiceProvider (Relation::enforceMorphMap).
     */
    public function registro(): MorphTo
    {
        return $this->morphTo('registro', 'tabla_afectada', 'registro_id');
    }
}
