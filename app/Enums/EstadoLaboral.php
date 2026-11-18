<?php

namespace App\Enums;

/**
 * Estado laboral de una persona en su empresa.
 */
enum EstadoLaboral: string
{
    case Activo = 'ACTIVO';
    case Retirado = 'RETIRADO';
}
