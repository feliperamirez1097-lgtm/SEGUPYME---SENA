<?php

namespace App\Enums;

/**
 * Roles fijos del sistema (tabla Roles).
 */
enum RolUsuario: int
{
    case Administrador = 1;
    case Contador = 2;
    case Empleado = 3;
    case SuperUsuario = 4;
}
