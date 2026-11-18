<?php

namespace App\Enums;

/**
 * Ciclo de vida de una planilla. Solo avanza en este orden.
 */
enum EstadoPlanilla: string
{
    case Borrador = 'BORRADOR';
    case Aprobada = 'APROBADA';
    case Generada = 'GENERADA';
    case Pagada = 'PAGADA';
}
