<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Enums\EstadoLaboral;
use App\Enums\TipoContrato;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Modelo de la tabla `Usuario`: la persona (cuenta de acceso y relacion laboral).
 *
 * Extiende Authenticatable porque tambien es el usuario que inicia sesion. Como la
 * contrasena se guarda en `contrasena_hash` y no en `password`, se indica con
 * $authPasswordName (manual del instructor, filas 48 y 49).
 *
 * @property int $id_usuario
 * @property string|null $cedula
 * @property string $nombre
 * @property int $id_rol
 * @property int|null $cargo_id
 * @property string|null $salario
 * @property EstadoCuenta $estado_cuenta
 */
#[Table(name: 'Usuario', key: 'id_usuario', timestamps: false)]
#[Fillable([
    'cedula', 'nombre', 'correo_acceso', 'contrasena_hash', 'id_rol', 'cargo_id', 'salario',
    'tipo_contrato', 'fecha_vinculacion', 'fecha_fin', 'estado_laboral', 'estado_cuenta',
    'intentos_fallidos', 'bloqueada_hasta', 'autorizacion_datos_en',
])]
// La contrasena nunca sale en el JSON.
#[Hidden(['contrasena_hash'])]
class Usuario extends Authenticatable
{
    /** Nombre real de la columna de la contrasena. */
    protected $authPasswordName = 'contrasena_hash';

    /** La tabla no tiene remember_token: se desactiva el "recordarme". */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'contrasena_hash' => 'hashed',          // se encripta sola al asignarla
            'salario' => 'decimal:2',
            'tipo_contrato' => TipoContrato::class,
            'estado_laboral' => EstadoLaboral::class,
            'estado_cuenta' => EstadoCuenta::class,
            'fecha_vinculacion' => 'date',
            'fecha_fin' => 'date',
            'bloqueada_hasta' => 'datetime',
            'autorizacion_datos_en' => 'datetime',
            'intentos_fallidos' => 'integer',
        ];
    }

    /** USUARIO N : 1 ROL */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    /** USUARIO N : 1 CARGO (opcional: el Super Usuario no tiene cargo). */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargo_id', 'id_cargo');
    }

    /** Empresa de la persona, a traves de su cargo (usuario -> cargo -> empresa). */
    public function empresa(): HasOneThrough
    {
        return $this->hasOneThrough(Empresa::class, Cargo::class, 'id_cargo', 'nit', 'cargo_id', 'empresa_id');
    }

    /** USUARIO 1 : N AFILIACION */
    public function afiliaciones(): HasMany
    {
        return $this->hasMany(Afiliacion::class, 'usuario_id', 'id_usuario');
    }

    /** USUARIO 1 : N NOVEDAD. Por aqui la persona llega a su planilla. */
    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class, 'empleado_id', 'id_usuario');
    }

    /** USUARIO 1 : N BITACORA (lo que hizo esta persona). */
    public function acciones(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'usuario_id', 'id_usuario');
    }

    /** Scope: personas activas laboralmente y con cuenta no eliminada. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado_laboral', EstadoLaboral::Activo)
            ->where('estado_cuenta', '<>', EstadoCuenta::Eliminada);
    }

    /** Scope: personas de una empresa (por su cargo). */
    public function scopeDeEmpresa(Builder $query, string $nit): Builder
    {
        return $query->whereHas('cargo', fn (Builder $q) => $q->where('empresa_id', $nit));
    }

    /** Accessor: true si la cuenta esta bloqueada por intentos fallidos. */
    protected function estaBloqueada(): Attribute
    {
        return Attribute::make(get: fn () => $this->bloqueada_hasta?->isFuture() ?? false);
    }
}
