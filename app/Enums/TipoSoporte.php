<?php

namespace App\Enums;

/**
 * Formato del soporte de una incapacidad o licencia.
 */
enum TipoSoporte: string
{
    case Pdf = 'PDF';
    case Jpg = 'JPG';
    case Png = 'PNG';
}
