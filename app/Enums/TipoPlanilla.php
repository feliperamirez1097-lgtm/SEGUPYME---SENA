<?php

namespace App\Enums;

/**
 * Una planilla normal por empresa y periodo; las correcciones se numeran.
 */
enum TipoPlanilla: string
{
    case Normal = 'NORMAL';
    case Correccion = 'CORRECCION';
}
