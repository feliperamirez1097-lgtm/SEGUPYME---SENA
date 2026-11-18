<?php

/*
|--------------------------------------------------------------------------
| Carga masiva: un procedimiento reparte el archivo en las tablas existentes
|--------------------------------------------------------------------------
|
| No existe una tabla de cargas. La aplicacion convierte el Excel en una lista de filas; el procedimiento
| valida fila por fila, guarda las buenas en Usuario, Cargos, Afiliacion y Novedad, y devuelve el motivo de
| cada fila rechazada. Cada fila se guarda completa o no se guarda.
|
*/

use App\Enums\AccionBitacora;
use App\Enums\EstadoAprobacion;
use App\Enums\EstadoCuenta;
use App\Models\Novedad;
use App\Models\Usuario;
use App\Repositories\Contracts\BitacoraRepositoryInterface;
use App\Services\CargaMasivaService;

beforeEach(fn () => baseNueva(__FILE__));

function actorContador(): int
{
    return Usuario::where('cedula', '1078901234')->value('id_usuario');     // Carlos Ramirez, contador externo
}

test('novedades: entran las filas buenas y se explica cada rechazo', function () {
    $resultado = app(CargaMasivaService::class)->novedades(actorContador(), '900111222', [
        ['cedula' => '1023456789', 'concepto' => 'RECARGO_NOCTURNO', 'fecha_inicio' => '2026-10-26', 'horas' => 3],
        ['cedula' => '1012345678', 'concepto' => 'LICENCIA', 'fecha_inicio' => '2026-10-27', 'fecha_fin' => '2026-10-28'],
        ['cedula' => '99999', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-10-26', 'horas' => 2],
        ['cedula' => '1012345678', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-10-29', 'horas' => 30],
        ['cedula' => '1056789012', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-10-30', 'horas' => 2],
    ]);

    expect($resultado->ok)->toBe(2)
        ->and($resultado->errores)->toBe(3)
        ->and($resultado->hayErrores())->toBeTrue()
        ->and(collect($resultado->rechazadas)->pluck('fila')->all())->toBe([3, 4, 5])
        ->and($resultado->rechazadas[0]['motivo'])->toBe('La cedula no pertenece a una persona de esta empresa')
        ->and($resultado->rechazadas[1]['motivo'])->toContain('ck_novedad_horas')      // mas de 24 horas
        ->and($resultado->rechazadas[2]['motivo'])->toBe('La cedula no pertenece a una persona de esta empresa'); // Sofia es de otra empresa
})->group('mysql');

test('novedades: las filas buenas quedaron pendientes y con su rastro en la bitacora', function () {
    $nuevas = Novedad::where('estado', EstadoAprobacion::Pendiente)
        ->whereIn('fecha_inicio', ['2026-10-26', '2026-10-27'])->with('concepto')->get();

    $bitacora = app(BitacoraRepositoryInterface::class)->porUsuario(actorContador());

    expect($nuevas->pluck('concepto.codigo')->sort()->values()->all())->toBe(['LICENCIA', 'RECARGO_NOCTURNO'])
        ->and($nuevas->firstWhere('concepto.codigo', 'LICENCIA')->dias)->toBe(2)
        ->and($bitacora)->toHaveCount(2)
        ->and($bitacora->every(fn ($f) => $f->accion === AccionBitacora::RegistrarNovedad))->toBeTrue();
})->group('mysql');

test('empleados: crea el cargo, la persona y sus 4 afiliaciones; las filas con error no dejan nada a medias', function () {
    $resultado = app(CargaMasivaService::class)->empleados(Usuario::where('cedula', '79000222')->value('id_usuario'), '900333444', [
        ['cedula' => '1067000111', 'nombre' => 'Luis Rojas', 'cargo' => 'Auxiliar', 'salario' => 1800000, 'tipo_contrato' => 'TERMINO_FIJO',
            'fecha_vinculacion' => '2026-10-15', 'eps' => '800000001', 'afp' => '800000002', 'arl' => '800000003', 'ccf' => '800000004'],
        ['cedula' => '1056789012', 'nombre' => 'Cedula repetida', 'cargo' => 'Panadero', 'salario' => 1800000, 'tipo_contrato' => 'TERMINO_FIJO', 'fecha_vinculacion' => '2026-10-15'],
        ['cedula' => '1067000222', 'nombre' => 'Salario bajo', 'cargo' => 'Panadero', 'salario' => 900000, 'tipo_contrato' => 'TERMINO_FIJO', 'fecha_vinculacion' => '2026-10-15'],
        ['cedula' => '1067000333', 'nombre' => 'Eps equivocada', 'cargo' => 'Panadero', 'salario' => 1800000, 'tipo_contrato' => 'TERMINO_FIJO', 'fecha_vinculacion' => '2026-10-15', 'eps' => '800000002'],
    ]);

    expect($resultado->ok)->toBe(1)
        ->and($resultado->errores)->toBe(3)
        ->and(collect($resultado->rechazadas)->pluck('fila')->all())->toBe([2, 3, 4])
        ->and($resultado->rechazadas[0]['motivo'])->toContain('Duplicate')
        ->and($resultado->rechazadas[1]['motivo'])->toBe('El salario no puede ser menor al salario minimo vigente')
        ->and($resultado->rechazadas[2]['motivo'])->toBe('La entidad debe ser una empresa de servicios del mismo tipo que la afiliacion');

    $luis = Usuario::with(['cargo', 'afiliaciones'])->where('cedula', '1067000111')->first();

    expect($luis->cargo->nombre_cargo)->toBe('Auxiliar')
        ->and($luis->estado_cuenta)->toBe(EstadoCuenta::PendienteActivacion)
        ->and($luis->afiliaciones)->toHaveCount(4)
        ->and($luis->afiliaciones->every(fn ($a) => $a->estado === EstadoAprobacion::Pendiente))->toBeTrue()
        ->and(Usuario::whereIn('cedula', ['1067000222', '1067000333'])->count())->toBe(0);   // se deshicieron
})->group('mysql');

test('una carga vacia no hace nada y no falla', function () {
    $resultado = app(CargaMasivaService::class)->novedades(actorContador(), '900111222', []);

    expect($resultado->ok)->toBe(0)->and($resultado->errores)->toBe(0)->and($resultado->hayErrores())->toBeFalse();
})->group('mysql');

test('permisos de la carga: quien no puede registrar o crear personas, tampoco carga', function () {
    $servicio = app(CargaMasivaService::class);
    $mensaje = fn (Closure $a) => capturar($a)->getMessage();

    // Empleados: el Empleado y el Contador de otra empresa no cargan personas.
    expect($mensaje(fn () => $servicio->empleados(Usuario::where('cedula', '1012345678')->value('id_usuario'), '900111222', [])))
        ->toBe('No tiene permiso para cargar personas en esta empresa')
        ->and($mensaje(fn () => $servicio->empleados(Usuario::where('cedula', '1089012345')->value('id_usuario'), '900111222', [])))
        ->toBe('No tiene permiso para cargar personas en esta empresa');

    // Una cuenta sin activar no carga nada.
    expect($mensaje(fn () => $servicio->novedades(Usuario::where('cedula', '1045678901')->value('id_usuario'), '900111222', [])))
        ->toContain('no esta activa');

    // Novedades: los permisos se aplican fila por fila (el Empleado solo registra las propias).
    $r = $servicio->novedades(Usuario::where('cedula', '1012345678')->value('id_usuario'), '900111222', [
        ['cedula' => '1012345678', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-11-12', 'horas' => 1],
        ['cedula' => '1023456789', 'concepto' => 'HORA_EXTRA_DIURNA', 'fecha_inicio' => '2026-11-12', 'horas' => 1],
    ]);
    expect($r->ok)->toBe(1)
        ->and($r->errores)->toBe(1)
        ->and($r->rechazadas[0])->toBe(['fila' => 2, 'motivo' => 'El Empleado solo registra novedades propias']);
})->group('mysql');
