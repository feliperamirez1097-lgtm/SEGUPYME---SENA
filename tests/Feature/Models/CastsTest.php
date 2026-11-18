<?php

/*
|--------------------------------------------------------------------------
| Casts, enums, columnas generadas y atributos ocultos
|--------------------------------------------------------------------------
|
| Comprueba que Eloquent entrega cada columna con el tipo correcto de PHP
| (manual, filas 12 a 20 y 40 a 49). Solo lee.
|
*/

use App\Enums\ClaseRiesgoArl;
use App\Enums\EstadoAprobacion;
use App\Enums\EstadoCuenta;
use App\Enums\TipoEmpresa;
use App\Enums\TipoSoporte;
use App\Models\Empresa;
use App\Models\Novedad;
use App\Models\ParametroLegal;
use App\Models\Usuario;
use Carbon\CarbonInterface;

beforeEach(fn () => baseNueva(__FILE__));

test('Empresa entrega enums, booleano, fecha y decimal con su tipo', function () {
    $empresa = Empresa::find('900111222');

    expect($empresa->tipo_empresa)->toBe(TipoEmpresa::Cliente)
        ->and($empresa->clase_riesgo_arl)->toBe(ClaseRiesgoArl::II)
        ->and($empresa->exonerada_art_114_1)->toBeTrue()
        ->and($empresa->fecha_registro)->toBeInstanceOf(CarbonInterface::class)
        ->and($empresa->honorarios_contador)->toBe('800000.00');
})->group('mysql');

test('Usuario: la contrasena no sale en el JSON y el nombre real de la columna se respeta', function () {
    $ana = Usuario::where('cedula', '1012345678')->first();

    expect($ana->toArray())->not->toHaveKey('contrasena_hash')
        ->and($ana->getAuthPasswordName())->toBe('contrasena_hash')
        ->and($ana->estado_cuenta)->toBe(EstadoCuenta::Activa)
        ->and($ana->salario)->toBe('1800000.00')
        ->and($ana->estaBloqueada)->toBeFalse();
})->group('mysql');

test('Novedad: periodo y dias son columnas generadas por MySQL y se leen como fecha y entero', function () {
    $incapacidad = Novedad::find(2); // incapacidad de Ana del 5 al 7 de octubre

    expect($incapacidad->periodo->toDateString())->toBe('2026-10-01')
        ->and($incapacidad->dias)->toBe(3)
        ->and($incapacidad->soporte_tipo)->toBe(TipoSoporte::Pdf)
        ->and($incapacidad->soporte_bytes)->toBe(182000)
        ->and($incapacidad->tieneSoporte)->toBeTrue()
        ->and($incapacidad->estado)->toBe(EstadoAprobacion::Aprobada);
})->group('mysql');

test('Novedad: periodo y dias no estan en $fillable (columnas generadas)', function () {
    $novedad = new Novedad;

    expect($novedad->isFillable('periodo'))->toBeFalse()
        ->and($novedad->isFillable('dias'))->toBeFalse()
        ->and($novedad->isFillable('horas'))->toBeTrue();
})->group('mysql');

test('Novedad: el calculo de MySQL se lee con decimales (4 horas extra diurnas de Juan)', function () {
    $horas = Novedad::find(1);

    expect($horas->horas)->toBe('4.00')
        ->and($horas->valor)->toBe('41688.20');
})->group('mysql');

test('ParametroLegal: la tarifa de ARL sale por clase de riesgo', function () {
    $vigencia = ParametroLegal::find(3);

    expect($vigencia->divisor_hora)->toBe(210)
        ->and($vigencia->tarifaArl(ClaseRiesgoArl::I))->toBe('0.522')
        ->and($vigencia->tarifaArl(ClaseRiesgoArl::II))->toBe('1.044')
        ->and($vigencia->tarifaArl(ClaseRiesgoArl::V))->toBe('6.960');
})->group('mysql');
