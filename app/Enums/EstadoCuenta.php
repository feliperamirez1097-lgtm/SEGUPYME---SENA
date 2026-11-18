<?php

namespace App\Enums;

/**
 * Estado de la cuenta de acceso de un usuario.
 */
enum EstadoCuenta: string
{
    case PendienteActivacion = 'PENDIENTE_ACTIVACION';
    case Activa = 'ACTIVA';
    case Eliminada = 'ELIMINADA';
}
