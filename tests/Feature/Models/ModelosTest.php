<?php

/*
|--------------------------------------------------------------------------
| Prueba de consulta (SELECT) a la tabla de cada modelo
|--------------------------------------------------------------------------
|
| Hace SELECT * a la tabla de cada modelo en MySQL y comprueba cuantos registros
| trae el ejemplo (Textiles Andina y Panaderia El Trigal). Solo lee.
|
| Grupo "mysql":
|   php artisan test --group=mysql
|
*/

use App\Models\Afiliacion;
use App\Models\Bitacora;
use App\Models\Cargo;
use App\Models\Concepto;
use App\Models\DetallePlanilla;
use App\Models\Empresa;
use App\Models\Novedad;
use App\Models\ParametroLegal;
use App\Models\Planilla;
use App\Models\PlanillaResumen;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

beforeEach(fn () => baseNueva(__FILE__));

test('hace SELECT a la tabla del modelo', function (string $claseModelo, int $esperados) {
    // SELECT * FROM <tabla>
    $registros = $claseModelo::all();

    $tabla = (new $claseModelo)->getTable();
    fwrite(STDOUT, "\n  {$tabla}: {$registros->count()} registros encontrados.\n");

    expect($registros)->toBeInstanceOf(Collection::class)
        ->each->toBeInstanceOf($claseModelo);
    expect($registros)->toHaveCount($esperados);
})->with([
    'Rol' => [Rol::class, 4],
    'Empresa' => [Empresa::class, 7],
    'Cargo' => [Cargo::class, 6],
    'Usuario' => [Usuario::class, 9],
    'Bitacora' => [Bitacora::class, 0],
    'Afiliacion' => [Afiliacion::class, 28],
    'Concepto' => [Concepto::class, 12],
    'Novedad' => [Novedad::class, 4],
    'ParametroLegal' => [ParametroLegal::class, 3],
    'Planilla' => [Planilla::class, 0],
    'DetallePlanilla' => [DetallePlanilla::class, 0],
    'PlanillaResumen (vista)' => [PlanillaResumen::class, 0],
])->group('mysql');

test('cada modelo apunta a su tabla y a su llave primaria reales', function (string $claseModelo, string $tabla, string $llave) {
    $modelo = new $claseModelo;

    expect($modelo->getTable())->toBe($tabla)
        ->and($modelo->getKeyName())->toBe($llave)
        ->and($modelo->timestamps)->toBeFalse();
})->with([
    [Rol::class, 'Roles', 'id_rol'],
    [Empresa::class, 'Empresa', 'nit'],
    [Cargo::class, 'Cargos', 'id_cargo'],
    [Usuario::class, 'Usuario', 'id_usuario'],
    [Bitacora::class, 'Bitacora', 'id_bitacora'],
    [Afiliacion::class, 'Afiliacion', 'id_afiliacion'],
    [Concepto::class, 'Concepto', 'id_concepto'],
    [Novedad::class, 'Novedad', 'id_novedad'],
    [ParametroLegal::class, 'Parametro_Legal', 'id_parametro'],
    [Planilla::class, 'Planilla', 'id_planilla'],
    [DetallePlanilla::class, 'Detalle_Planilla', 'id_detalle'],
    [PlanillaResumen::class, 'v_planilla_total', 'id_planilla'],
])->group('mysql');

test('Empresa usa el NIT (texto) como llave primaria', function () {
    $empresa = new Empresa;

    expect($empresa->getKeyType())->toBe('string')
        ->and($empresa->incrementing)->toBeFalse();
})->group('mysql');
