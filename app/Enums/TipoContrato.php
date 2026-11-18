<?php

namespace App\Enums;

/**
 * Tipo de contrato. El aprendizaje es el unico que puede ganar menos del salario minimo.
 */
enum TipoContrato: string
{
    case TerminoFijo = 'TERMINO_FIJO';
    case TerminoIndefinido = 'TERMINO_INDEFINIDO';
    case ObraLabor = 'OBRA_LABOR';
    case Aprendizaje = 'APRENDIZAJE';
}
