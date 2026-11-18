<?php

namespace App\Enums;

/**
 * Estado de una empresa.
 */
enum EstadoEmpresa: string
{
    case Activa = 'ACTIVA';
    case Inactiva = 'INACTIVA';
}
