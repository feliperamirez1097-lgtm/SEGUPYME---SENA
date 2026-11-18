<?php

namespace App\Enums;

/**
 * Tipo de entidad de seguridad social. Lo usan Empresa.subtipo_entidad y Afiliacion.tipo (deben coincidir).
 */
enum TipoEntidad: string
{
    case Eps = 'EPS';
    case Afp = 'AFP';
    case Arl = 'ARL';
    case Ccf = 'CCF';
}
