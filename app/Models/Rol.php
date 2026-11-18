<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de la tabla `Roles`: los 4 roles fijos (Administrador, Contador, Empleado, Super Usuario).
 *
 * El Contador (interno o externo) es un rol, no un empleado de otra tabla.
 *
 * @property int $id_rol
 * @property string $nombre_rol
 */
// incrementing: false porque el id del rol lo define el sistema (1 a 4), no el AUTO_INCREMENT.
#[Table(name: 'Roles', key: 'id_rol', incrementing: false, timestamps: false)]
#[Fillable(['id_rol', 'nombre_rol'])]
class Rol extends Model
{
    /** ROL 1 : N USUARIO */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_rol', 'id_rol');
    }
}
