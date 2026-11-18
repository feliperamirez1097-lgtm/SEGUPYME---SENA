<?php

namespace App\Models;

use App\Enums\ClaseRiesgoArl;
use App\Enums\EstadoEmpresa;
use App\Enums\TipoEmpresa;
use App\Enums\TipoEntidad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Modelo de la tabla `Empresa`.
 *
 * Una misma tabla guarda tres clases de empresa (columna tipo_empresa):
 *  - CLIENTE:    la PYME a la que se le liquidan los aportes.
 *  - SERVICIOS:  una entidad de seguridad social (EPS, AFP, ARL o caja) -> usa subtipo_entidad.
 *  - CONTADORA:  la empresa de contadores externos que atiende a clientes.
 *
 * La llave primaria es el NIT (texto), no un entero autoincremental.
 *
 * @property string $nit
 * @property TipoEmpresa $tipo_empresa
 * @property TipoEntidad|null $subtipo_entidad
 * @property string $razon_social
 * @property string|null $contador_id NIT de la contadora (sin FK: la valida un trigger).
 * @property bool|null $exonerada_art_114_1 Solo clientes: exoneracion de aportes de salud y parafiscales.
 * @property ClaseRiesgoArl|null $clase_riesgo_arl Solo clientes.
 */
// PK de texto (manual, fila 9): incrementing=false y keyType='string'.
#[Table(name: 'Empresa', key: 'nit', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable([
    'nit', 'tipo_empresa', 'subtipo_entidad', 'razon_social', 'representante_legal', 'correo',
    'fecha_registro', 'estado', 'tipo_alerta_preferido', 'contador_id', 'honorarios_contador',
    'exonerada_art_114_1', 'clase_riesgo_arl',
])]
class Empresa extends Model
{
    protected function casts(): array
    {
        return [
            'tipo_empresa' => TipoEmpresa::class,
            'subtipo_entidad' => TipoEntidad::class,
            'estado' => EstadoEmpresa::class,
            'clase_riesgo_arl' => ClaseRiesgoArl::class,
            'fecha_registro' => 'date',
            'honorarios_contador' => 'decimal:2',
            'exonerada_art_114_1' => 'boolean',
        ];
    }

    /** EMPRESA 1 : N CARGO */
    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class, 'empresa_id', 'nit');
    }

    /** Personas de la empresa, a traves de sus cargos (la empresa no se guarda en Usuario). */
    public function usuarios(): HasManyThrough
    {
        return $this->hasManyThrough(Usuario::class, Cargo::class, 'empresa_id', 'cargo_id', 'nit', 'id_cargo');
    }

    /** Autorreferencia sin FK (manual, fila 64): la contadora que atiende a este cliente. */
    public function contadora(): BelongsTo
    {
        return $this->belongsTo(self::class, 'contador_id', 'nit');
    }

    /** Clientes que atiende esta contadora. */
    public function clientes(): HasMany
    {
        return $this->hasMany(self::class, 'contador_id', 'nit');
    }

    /** Afiliaciones de personas que apuntan a esta entidad (cuando tipo_empresa = SERVICIOS). */
    public function afiliacionesComoEntidad(): HasMany
    {
        return $this->hasMany(Afiliacion::class, 'entidad_id', 'nit');
    }

    /** Scope: empresas cliente. */
    public function scopeClientes(Builder $query): Builder
    {
        return $query->where('tipo_empresa', TipoEmpresa::Cliente);
    }

    /** Scope: entidades de seguridad social, opcionalmente de un tipo (EPS, AFP, ARL o CCF). */
    public function scopeEntidades(Builder $query, ?TipoEntidad $tipo = null): Builder
    {
        return $query->where('tipo_empresa', TipoEmpresa::Servicios)
            ->when($tipo, fn (Builder $q, TipoEntidad $t) => $q->where('subtipo_entidad', $t));
    }

    /** Scope: empresas contadoras. */
    public function scopeContadoras(Builder $query): Builder
    {
        return $query->where('tipo_empresa', TipoEmpresa::Contadora);
    }

    /** Scope: solo empresas activas. */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', EstadoEmpresa::Activa);
    }
}
