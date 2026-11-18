<?php

/*
|--------------------------------------------------------------------------
| Relaciones entre modelos
|--------------------------------------------------------------------------
|
| Cada relacion traduce una llave foranea (manual, seccion 6). Siempre se usa with() antes
| de leer la relacion: asi no se dispara una consulta por fila (problema N+1). Solo lee.
|
*/

use App\Enums\TipoEntidad;
use App\Models\Afiliacion;
use App\Models\Cargo;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Novedad;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => baseNueva(__FILE__));

test('Usuario llega a su empresa por el cargo (usuario -> cargo -> empresa)', function () {
    $ana = Usuario::with(['cargo', 'empresa', 'rol'])->where('cedula', '1012345678')->first();

    expect($ana->cargo->nombre_cargo)->toBe('Operario')
        ->and($ana->empresa->razon_social)->toBe('Textiles Andina SAS')
        ->and($ana->rol->nombre_rol)->toBe('EMPLEADO');
})->group('mysql');

test('Empresa: personas (a traves de los cargos), cargos, contadora y clientes', function () {
    $textiles = Empresa::with(['usuarios', 'cargos', 'contadora'])->find('900111222');
    $contadora = Empresa::with('clientes')->find('900000001');

    expect($textiles->usuarios)->toHaveCount(4)               // Laura, Ana, Juan y Pedro
        ->and($textiles->cargos->pluck('nombre_cargo')->sort()->values()->all())->toBe(['Gerente', 'Operario'])
        ->and($textiles->contadora->razon_social)->toBe('Contadores Asociados SAS')
        ->and($contadora->clientes->pluck('nit')->sort()->values()->all())->toBe(['900111222', '900333444']);
})->group('mysql');

test('Una entidad (EPS) conoce las afiliaciones que apuntan a ella', function () {
    $eps = Empresa::with('afiliacionesComoEntidad')->find('800000001');

    expect($eps->subtipo_entidad)->toBe(TipoEntidad::Eps)
        ->and($eps->afiliacionesComoEntidad)->toHaveCount(7)
        ->each->toBeInstanceOf(Afiliacion::class);
})->group('mysql');

test('Pedro tiene 4 afiliaciones y la ARL esta pendiente', function () {
    $pedro = Usuario::with('afiliaciones.entidad')->where('cedula', '1045678901')->first();

    $arl = $pedro->afiliaciones->firstWhere('tipo', TipoEntidad::Arl);

    expect($pedro->afiliaciones)->toHaveCount(4)
        ->and($arl->estado->value)->toBe('PENDIENTE')
        ->and($arl->entidad->razon_social)->toBe('ARL Sura (prueba)');
})->group('mysql');

test('Novedad llega a la persona y al concepto; una incapacidad no es de horas', function () {
    $novedad = Novedad::with(['empleado', 'concepto'])->find(1);

    expect($novedad->empleado->nombre)->toBe('Juan Torres')
        ->and($novedad->concepto->codigo)->toBe('HORA_EXTRA_DIURNA')
        ->and($novedad->concepto->esDeHoras)->toBeTrue()
        ->and(Concepto::where('codigo', 'INCAPACIDAD')->first()->esDeHoras)->toBeFalse();
})->group('mysql');

test('Roles y cargos: relaciones inversas', function () {
    $rol = Rol::withCount('usuarios')->find(3);
    $cargo = Cargo::with(['empresa', 'usuarios'])->where('nombre_cargo', 'Operario')->first();

    expect($rol->usuarios_count)->toBe(4)                       // Ana, Juan, Sofia y Pedro
        ->and($cargo->empresa->nit)->toBe('900111222')
        ->and($cargo->usuarios)->toHaveCount(3);                // Ana, Juan y Pedro
})->group('mysql');

test('Con with() la lista de novedades se carga en pocas consultas, no una por fila', function () {
    $consultas = 0;
    DB::listen(function () use (&$consultas) {
        $consultas++;
    });

    $novedades = Novedad::with(['empleado.cargo.empresa', 'concepto'])->get();
    $nombres = $novedades->map(fn ($n) => $n->empleado->cargo->empresa->razon_social.' / '.$n->concepto->codigo);

    expect($nombres)->toHaveCount(4)
        ->and($consultas)->toBeLessThanOrEqual(5);              // novedades + empleados + cargos + empresas + conceptos
})->group('mysql');
