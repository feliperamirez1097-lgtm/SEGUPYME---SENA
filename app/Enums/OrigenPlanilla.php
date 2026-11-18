<?php

namespace App\Enums;

/**
 * Si la planilla la genero el sistema o se importo de un periodo anterior.
 */
enum OrigenPlanilla: string
{
    case Sistema = 'SISTEMA';
    case Importada = 'IMPORTADA';
}
