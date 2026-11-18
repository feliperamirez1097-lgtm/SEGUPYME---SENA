<?php

namespace App\Models;

use App\Enums\EstadoAprobacion;
use App\Enums\TipoEntidad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de la tabla `Afiliacion`: la afiliacion de una persona a una entidad (EPS, AFP, ARL o caja),
 * con fecha de inicio y de fin. Una planilla solo se aprueba si cada persona tiene las 4 vigentes.
 *
 * @property int $id_afiliacion
 * @property int $usuario_id
 * @property TipoEntidad $tipo
 * @property string $entidad_id NIT de la entidad (sin FK: la valida un trigger de la base).
 * @property EstadoAprobacion $estado
 */
#[Table(name: 'Afiliacion', key: 'id_afiliacion', timestamps: false)]
#[Fillable(['usuario_id', 'tipo', 'entidad_id', 'fecha_inicio', 'fecha_fin', 'estado'])]
class Afiliacion extends Model
{
    /** Valor por defecto en PHP (manual, fila 23); en MySQL tambien es PENDIENTE. */
    protected $attributes = ['estado' => 'PENDIENTE'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEntidad::class,
            'estado' => EstadoAprobacion::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id_usuario');
    }

    /** La entidad (EPS, AFP, ARL o caja) es una Empresa de tipo SERVICIOS. */
    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'entidad_id', 'nit');
    }

    public function scopeAprobadas(Builder $query): Builder
    {
        return $query->where('estado', EstadoAprobacion::Aprobada);
    }

    public function scopeDeTipo(Builder $query, TipoEntidad $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    /** Scope: vigentes en una fecha (ya iniciaron y no han terminado). */
    public function scopeVigentesEn(Builder $query, string $fecha): Builder
    {
        return $query->where('fecha_inicio', '<=', $fecha)
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $fecha));
    }
}
