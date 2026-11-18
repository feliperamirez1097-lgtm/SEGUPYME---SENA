<?php

namespace App\Enums;

/**
 * Tipo de empresa: PYME cliente, entidad de seguridad social (EPS, AFP, ARL o caja) o empresa contadora.
 */
enum TipoEmpresa: string
{
    case Cliente = 'CLIENTE';
    case Servicios = 'SERVICIOS';
    case Contadora = 'CONTADORA';
}
