<?php

namespace App\Enums;

/**
 * Clase de riesgo de la ARL de una empresa cliente (define la tarifa de ARL).
 */
enum ClaseRiesgoArl: string
{
    case I = 'I';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case V = 'V';
}
