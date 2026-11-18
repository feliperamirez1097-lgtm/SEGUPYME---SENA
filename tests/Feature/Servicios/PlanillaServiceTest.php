<?php

/*
|--------------------------------------------------------------------------
| Servicio de planillas: el ejemplo de Textiles Andina de punta a punta
|--------------------------------------------------------------------------
|
| Las pruebas de este archivo van EN ORDEN y comparten estado (cada una continua la anterior):
| generar -> ver el calculo -> intentar aprobar (bloqueada) -> aprobar -> pagar -> corregir.
| Las cifras son las del ejemplo del proyecto (octubre de 2026).
|
*/

use App\Enums\EstadoAprobacion;
use App\Enums\EstadoPlanilla;
use App\Enums\TipoEntidad;
use App\Enums\TipoPlanilla;
use App\Exceptions\ReglaDeNegocioException;
use App\Models\Usuario;
use App\Repositories\Contracts\AfiliacionRepositoryInterface;
use App\Repositories\Contracts\DetallePlanillaRepositoryInterface;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use App\Repositories\Contracts\PlanillaRepositoryInterface;
use App\Services\PlanillaService;
use App\Support\ErroresMysql;
use Illuminate\Database\QueryException;

beforeEach(fn () => baseNueva(__FILE__));

/** Quien actua: busca a la persona por su cedula. */
function quien(string $cedula): int
{
    return Usuario::where('cedula', $cedula)->value('id_usuario');
}

test('1. generar la planilla de Textiles Andina de octubre: 6 lineas y 2.252.105,04', function () {
    $resumen = app(PlanillaService::class)->generar(quien('1078901234'), '900111222', '2026-10-01', '2026-11-10');

    expect($resumen->empresa_id)->toBe('900111222')          // la empresa NO se guarda: sale de las personas
        ->and($resumen->estado)->toBe(EstadoPlanilla::Borrador)
        ->and($resumen->tipo_planilla)->toBe(TipoPlanilla::Normal)
        ->and($resumen->lineas)->toBe(6)                     // 4 salarios + incapacidad + hora extra
        ->and($resumen->total_aportes)->toBe('2252105.04');
})->group('mysql');

test('2. el reporte por persona (SQL nativo): IBC y total de cada una', function () {
    $filas = collect(app(DetallePlanillaRepositoryInterface::class)->totalesPorPersona(1))->keyBy('persona');

    expect($filas->keys()->all())->toBe(['Ana Perez', 'Juan Torres', 'Laura Gomez', 'Pedro Diaz'])
        ->and($filas['Ana Perez']->ibc)->toBe('1800000.00')
        ->and($filas['Ana Perez']->total)->toBe('450792.00')       // exonerada: salud 4%
        ->and($filas['Juan Torres']->ibc)->toBe('1792593.20')      // salario + 4 horas extra (41.688,20)
        ->and($filas['Laura Gomez']->total)->toBe('876540.00')
        ->and($filas['Pedro Diaz']->total)->toBe('475836.00');
})->group('mysql');

test('3. la planilla con su detalle: persona, novedad, concepto y vigencia', function () {
    $planilla = app(PlanillaRepositoryInterface::class)->conDetalle(1);

    $linea = $planilla->detalles->first(fn ($d) => $d->novedad->concepto->codigo === 'HORA_EXTRA_DIURNA');

    expect($planilla->detalles)->toHaveCount(6)
        ->and($planilla->parametro->divisor_hora)->toBe(210)
        ->and($planilla->resumen->total_aportes)->toBe('2252105.04')
        ->and($linea->persona->nombre)->toBe('Juan Torres')
        ->and($linea->ibc)->toBe('41688.20')
        ->and($linea->totalAportes)->toBe('10440.39');             // accessor: suma de los 4 valores de la linea (25,044 % de 41.688,20)
})->group('mysql');

test('4. aprobar: la base bloquea porque a Pedro le falta la ARL aprobada', function () {
    $e = capturar(fn () => app(PlanillaService::class)->aprobar(quien('52000111'), 1));

    expect($e)->toBeInstanceOf(ReglaDeNegocioException::class)
        ->and($e->status)->toBe(422)
        ->and($e->getMessage())->toBe('Hay personas con afiliaciones incompletas: la liquidacion esta bloqueada');
})->group('mysql');

test('5. con la ARL de Pedro aprobada por su Administradora, la planilla se aprueba', function () {
    $afiliaciones = app(AfiliacionRepositoryInterface::class);
    $laura = quien('52000111');
    $arl = $afiliaciones->delUsuario(quien('1045678901'))->firstWhere('tipo', TipoEntidad::Arl);
    $afiliaciones->gestionar($laura, $arl->id_afiliacion, EstadoAprobacion::Aprobada);

    // Solo el Administrador de la empresa (o el Super Usuario) aprueba: el Contador no.
    $e = capturar(fn () => app(PlanillaService::class)->aprobar(quien('1078901234'), 1));
    expect($e->getMessage())->toBe('Solo el Administrador de la empresa o el Super Usuario aprueban la planilla');

    expect(app(PlanillaService::class)->aprobar($laura, 1)->estado)->toBe(EstadoPlanilla::Aprobada);
})->group('mysql');

test('6. la aplicacion no modifica ni borra una planilla directo: solo por procedimiento', function () {
    $repo = app(PlanillaRepositoryInterface::class);

    // La cuenta de la aplicacion ni siquiera tiene permiso de UPDATE ni DELETE sobre Planilla.
    $e = capturar(fn () => $repo->update(1, ['fecha_limite' => '2026-12-01']));
    expect($e)->toBeInstanceOf(QueryException::class)
        ->and($e->errorInfo[1])->toBe(1142)
        ->and(ErroresMysql::traducir($e)['status'])->toBe(403);

    expect(capturar(fn () => $repo->delete(1))->errorInfo[1])->toBe(1142);

    // Y por el procedimiento, una planilla aprobada tampoco se vuelve a aprobar.
    $e = capturar(fn () => app(PlanillaService::class)->aprobar(quien('52000111'), 1));
    expect($e->getMessage())->toBe('Solo se aprueban planillas en BORRADOR');
})->group('mysql');

test('7. generar el archivo y pagar: el monto debe ser exactamente el total', function () {
    $servicio = app(PlanillaService::class);

    $carlos = quien('1078901234');

    // La clave del archivo y la del comprobante deben ser hexadecimales (32 a 64 caracteres).
    $e = capturar(fn () => $servicio->marcarGenerada($carlos, 1, 'clave-mala'));
    expect($e->getMessage())->toBe('La clave del archivo no es valida');

    expect($servicio->marcarGenerada($carlos, 1, md5('archivo-pila-textiles-2026-10'))->estado)->toBe(EstadoPlanilla::Generada);

    $e = capturar(fn () => $servicio->registrarPago($carlos, 1, '2026-11-05', '1000.00', md5('comprobante-1'), hash('sha256', 'c1')));
    expect($e)->toBeInstanceOf(ReglaDeNegocioException::class)
        ->and($e->getMessage())->toBe('El monto pagado no coincide con el total de aportes');

    // Un Empleado no registra el pago; el Administrador de la empresa si.
    $e = capturar(fn () => $servicio->registrarPago(quien('1012345678'), 1, '2026-11-05', '2252105.04', md5('comprobante-1'), hash('sha256', 'c1')));
    expect($e->getMessage())->toBe('No tiene permiso para registrar el pago de esta planilla');

    expect($servicio->registrarPago(quien('52000111'), 1, '2026-11-05', '2252105.04', md5('comprobante-1'), hash('sha256', 'c1'))->estado)
        ->toBe(EstadoPlanilla::Pagada);
})->group('mysql');

test('8. correccion: reutiliza las novedades, suma la que se aprobo despues y no toca la original', function () {
    // La hora extra nocturna de Ana (novedad 4) se aprueba despues de pagada la planilla normal.
    app(NovedadRepositoryInterface::class)->gestionar(quien('52000111'), 4, EstadoAprobacion::Aprobada);

    // El Administrador no genera correcciones: las genera el Contador que atiende la empresa.
    $e = capturar(fn () => app(PlanillaService::class)->corregir(quien('52000111'), 1));
    expect($e->getMessage())->toBe('Solo el Contador que atiende la empresa o el Super Usuario generan la correccion');

    $correccion = app(PlanillaService::class)->corregir(quien('1078901234'), 1);
    $original = app(PlanillaRepositoryInterface::class)->resumen(1);

    expect($correccion->tipo_planilla)->toBe(TipoPlanilla::Correccion)
        ->and($correccion->correccion_nro)->toBe(1)
        ->and($correccion->empresa_id)->toBe('900111222')
        ->and($correccion->lineas)->toBe(7)
        ->and($correccion->total_aportes)->toBe('2259618.24')
        ->and($original->total_aportes)->toBe('2252105.04');
})->group('mysql');

test('9. las planillas de la empresa (normal y correccion) se listan por la vista', function () {
    $lista = app(PlanillaRepositoryInterface::class)->listarPorEmpresa('900111222');

    expect($lista->total())->toBe(2)
        ->and($lista->getCollection()->pluck('correccion_nro')->all())->toBe([0, 1]);
})->group('mysql');

test('10. no se repite la planilla normal de una empresa y un periodo', function () {
    $e = capturar(fn () => app(PlanillaService::class)->generar(quien('1078901234'), '900111222', '2026-10-01', '2026-11-10'));

    expect($e)->toBeInstanceOf(ReglaDeNegocioException::class)
        ->and($e->getMessage())->toBe('Ya existe una planilla normal de esa empresa para ese periodo');
})->group('mysql');

test('11. Panaderia El Trigal (no exonerada): 2.837.093,02', function () {
    $resumen = app(PlanillaService::class)->generar(quien('1078901234'), '900333444', '2026-10-01', '2026-11-10');

    expect($resumen->empresa_id)->toBe('900333444')
        ->and($resumen->lineas)->toBe(4)
        ->and($resumen->total_aportes)->toBe('2837093.02');
})->group('mysql');

test('12. una empresa que no es cliente no tiene planilla, y solo el Contador que la atiende genera', function () {
    $servicio = app(PlanillaService::class);

    // Carlos si "atiende" a su propia contadora, pero esa empresa no es cliente: no tiene planilla.
    $e = capturar(fn () => $servicio->generar(quien('1078901234'), '900000001', '2026-10-01', '2026-11-10'));
    expect($e)->toBeInstanceOf(ReglaDeNegocioException::class)
        ->and($e->getMessage())->toBe('La planilla se genera para una empresa cliente');

    // La contadora interna de Panaderia no genera la planilla de Textiles; el Empleado y la Administradora tampoco.
    foreach (['1089012345', '1012345678', '52000111'] as $cedula) {
        $e = capturar(fn () => $servicio->generar(quien($cedula), '900111222', '2026-09-01', '2026-10-10'));
        expect($e->getMessage())->toBe('Solo el Contador que atiende la empresa o el Super Usuario generan la planilla');
    }
})->group('mysql');

test('13. refrescar y descartar una planilla en borrador (la correccion y Panaderia siguen en BORRADOR)', function () {
    $servicio = app(PlanillaService::class);
    $carlos = quien('1078901234');
    $marta = quien('1089012345');

    // Panaderia (planilla en BORRADOR): se aprueba una novedad nueva y luego se refresca.
    $idPanaderia = app(PlanillaRepositoryInterface::class)->listarPorEmpresa('900333444')->first()->id_planilla;
    $antes = app(PlanillaRepositoryInterface::class)->resumen($idPanaderia);
    $nueva = app(NovedadRepositoryInterface::class)->registrar($marta, quien('1056789012'), 'RECARGO_NOCTURNO', '2026-10-20', null, 2);
    app(NovedadRepositoryInterface::class)->gestionar(quien('79000222'), $nueva->id_novedad, EstadoAprobacion::Aprobada);

    $e = capturar(fn () => $servicio->refrescar(quien('79000222'), $idPanaderia));
    expect($e->getMessage())->toBe('Solo el Contador que atiende la empresa o el Super Usuario refrescan la planilla');

    $despues = $servicio->refrescar($marta, $idPanaderia);
    expect($despues->lineas)->toBe($antes->lineas + 1)
        ->and(bccomp($despues->total_aportes, $antes->total_aportes, 2))->toBe(1);

    // Descartar: una planilla pagada no se descarta; una en borrador si, y se puede generar de nuevo.
    expect(capturar(fn () => $servicio->descartar($carlos, 1))->getMessage())->toBe('Solo se descarta una planilla en BORRADOR');

    $nuevaPlanilla = $servicio->generar($carlos, '900111222', '2027-01-01', '2027-02-10');
    expect(capturar(fn () => $servicio->descartar(quien('1012345678'), $nuevaPlanilla->id_planilla))->getMessage())
        ->toBe('Solo el Contador que atiende la empresa o el Super Usuario descartan la planilla');

    $servicio->descartar($carlos, $nuevaPlanilla->id_planilla);
    expect(app(PlanillaRepositoryInterface::class)->resumen($nuevaPlanilla->id_planilla))->toBeNull();
})->group('mysql');
