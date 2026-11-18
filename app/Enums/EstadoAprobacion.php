<?php

namespace App\Enums;

/**
 * Estado de aprobacion de una afiliacion o de una novedad.
 */
enum EstadoAprobacion: string
{
    case Pendiente = 'PENDIENTE';
    case Aprobada = 'APROBADA';
    case Rechazada = 'RECHAZADA';
}
