<?php

namespace App\Enums;

/**
 * Acciones que quedan en la bitacora (solo se inserta, nunca se modifica).
 */
enum AccionBitacora: string
{
    case CrearCuenta = 'CREAR_CUENTA';
    case RegistrarNovedad = 'REGISTRAR_NOVEDAD';
    case AprobarNovedad = 'APROBAR_NOVEDAD';
    case RechazarNovedad = 'RECHAZAR_NOVEDAD';
    case SubirSoporte = 'SUBIR_SOPORTE';
    case VerSoporte = 'VER_SOPORTE';
}
