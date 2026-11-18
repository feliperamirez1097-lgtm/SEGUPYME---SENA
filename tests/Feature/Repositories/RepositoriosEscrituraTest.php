<?php

/*
|--------------------------------------------------------------------------
| Repositorios: escritura, permisos y reglas de la base
|--------------------------------------------------------------------------
|
| Las pruebas se conectan como la cuenta de la APLICACION (segupyme_app): puede escribir directo solo
| Usuario, Empresa y Cargos; novedades, afiliaciones, valores legales y bitacora se escriben llamando
| procedimientos que validan el rol de quien actua. Aqui se prueban los dos caminos y se intenta romper
| las reglas: el error de MySQL llega a Laravel y ErroresMysql lo traduce al codigo HTTP correcto.
|
*/

use App\Enums\AccionBitacora;
use App\Enums\EstadoAprobacion;
use App\Enums\TipoEmpresa;
use App\Enums\TipoEntidad;
use App\Enums\TipoSoporte;
use App\Models\Bitacora;
use App\Models\Novedad;
use App\Models\Usuario;
use App\Repositories\Contracts\AfiliacionRepositoryInterface;
use App\Repositories\Contracts\BitacoraRepositoryInterface;
use App\Repositories\Contracts\CargoRepositoryInterface;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use App\Repositories\Contracts\ParametroLegalRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Support\ErroresMysql;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => baseNueva(__FILE__));

function idDe(string $cedula): int
{
    return Usuario::where('cedula', $cedula)->value('id_usuario');
}

test('registrar una novedad: la base valida el rol, MySQL calcula periodo, dias y valor', function () {
    $repo = app(NovedadRepositoryInterface::class);
    $carlos = idDe('1078901234');   // contador externo que atiende Textiles Andina

    // 2 horas extra diurnas de Ana: 2 x 8571.43 (hora) x 1.25 = 21428.58
    $novedad = $repo->registrar($carlos, idDe('1012345678'), 'HORA_EXTRA_DIURNA', '2026-10-21', null, 2);

    expect($novedad->periodo->toDateString())->toBe('2026-10-01')
        ->and($novedad->valor)->toBe('21428.58')
        ->and($novedad->estado)->toBe(EstadoAprobacion::Pendiente)
        ->and($novedad->dias)->toBeNull();

    // Una incapacidad: los dias salen de las fechas.
    $incapacidad = $repo->registrar($carlos, idDe('1023456789'), 'INCAPACIDAD', '2026-10-20', '2026-10-24');
    expect($incapacidad->dias)->toBe(5);

    // Quien registra queda en la bitacora (la escribe el procedimiento, no la aplicacion).
    $bitacora = app(BitacoraRepositoryInterface::class)->delRegistro('Novedad', $novedad->id_novedad);
    expect($bitacora)->toHaveCount(1)
        ->and($bitacora->first()->accion)->toBe(AccionBitacora::RegistrarNovedad)
        ->and($bitacora->first()->usuario_id)->toBe($carlos)
        ->and($bitacora->first()->fecha_hora)->not->toBeNull();
})->group('mysql');

test('permisos por rol al registrar y gestionar novedades', function () {
    $repo = app(NovedadRepositoryInterface::class);
    [$carlos, $laura, $ana, $juan, $marta] = array_map('idDe', ['1078901234', '52000111', '1012345678', '1023456789', '1089012345']);

    $propia = $repo->registrar($ana, $ana, 'HORA_EXTRA_DIURNA', '2026-11-06', null, 1);   // el Empleado registra la propia
    expect($propia->empleado_id)->toBe($ana);

    $mensaje = fn (Closure $accion) => ErroresMysql::traducir(capturar($accion))['mensaje'];

    expect($mensaje(fn () => $repo->registrar($ana, $juan, 'HORA_EXTRA_DIURNA', '2026-11-09', null, 1)))->toBe('El Empleado solo registra novedades propias')
        ->and($mensaje(fn () => $repo->registrar($laura, $ana, 'HORA_EXTRA_DIURNA', '2026-11-09', null, 1)))->toBe('El Administrador aprueba novedades, no las registra')
        ->and($mensaje(fn () => $repo->registrar($marta, $ana, 'HORA_EXTRA_DIURNA', '2026-11-09', null, 1)))->toBe('El Contador solo registra novedades de las empresas que atiende')
        ->and($mensaje(fn () => $repo->registrar($carlos, $ana, 'SALARIO', '2026-11-01')))->toBe('El salario ordinario lo genera la planilla')
        ->and($mensaje(fn () => $repo->gestionar($ana, $propia->id_novedad, EstadoAprobacion::Aprobada)))->toBe('Nadie puede gestionar su propia novedad')
        ->and($mensaje(fn () => $repo->gestionar($carlos, $propia->id_novedad, EstadoAprobacion::Aprobada)))->toContain('Solo el Administrador de la empresa de la persona');

    // El Administrador de la empresa la aprueba, y queda en la bitacora.
    expect($repo->gestionar($laura, $propia->id_novedad, EstadoAprobacion::Aprobada)->estado)->toBe(EstadoAprobacion::Aprobada);
    expect(app(BitacoraRepositoryInterface::class)->porUsuario($laura)->first()->accion)->toBe(AccionBitacora::AprobarNovedad);
})->group('mysql');

test('la aplicacion no puede escribir directo novedades ni bitacora: error de permisos -> 403', function () {
    $e = capturar(fn () => Novedad::create(['empleado_id' => idDe('1012345678'), 'concepto_id' => 6, 'fecha_inicio' => '2026-10-21', 'horas' => 1]));
    expect($e)->toBeInstanceOf(QueryException::class)
        ->and($e->errorInfo[1])->toBe(1142)
        ->and(ErroresMysql::traducir($e)['status'])->toBe(403);

    // La bitacora es de solo lectura para la aplicacion: ni insertar ni borrar.
    $antes = Bitacora::count();
    $e = capturar(fn () => DB::table('Bitacora')->insert(['usuario_id' => 1, 'accion' => 'CREAR_CUENTA', 'tabla_afectada' => 'Usuario', 'registro_id' => 1]));
    expect($e->errorInfo[1])->toBe(1142)
        ->and(Bitacora::count())->toBe($antes);
})->group('mysql');

test('las reglas de la base llegan como QueryException y se traducen al codigo HTTP', function () {
    $repo = app(NovedadRepositoryInterface::class);
    $carlos = idDe('1078901234');
    $ana = idDe('1012345678');

    // CHECK violado (mas de 24 horas), ahora dentro del procedimiento -> error 3819 -> 422
    $e = capturar(fn () => $repo->registrar($carlos, $ana, 'HORA_EXTRA_DIURNA', '2026-10-22', null, 30));
    expect($e->errorInfo[1])->toBe(3819)
        ->and(ErroresMysql::traducir($e)['status'])->toBe(422);

    // UNIQUE (la misma persona, concepto y fecha) -> error 1062 -> 409
    $e = capturar(fn () => $repo->registrar($carlos, idDe('1023456789'), 'HORA_EXTRA_DIURNA', '2026-10-10', null, 1));
    expect($e->errorInfo[1])->toBe(1062)
        ->and(ErroresMysql::traducir($e)['status'])->toBe(409);

    // Regla de un trigger (una novedad no cruza de mes) -> error 1644 -> 422 con el mensaje de la base
    $e = capturar(fn () => $repo->registrar($carlos, $ana, 'INCAPACIDAD', '2026-10-28', '2026-11-04'));
    expect(ErroresMysql::traducir($e))->toMatchArray(['status' => 422, 'mensaje' => 'Una novedad no puede cruzar de mes: registre una por cada mes']);

    // Concepto inexistente: lo dice el procedimiento (1644)
    $e = capturar(fn () => $repo->registrar($carlos, $ana, 'NO_EXISTE', '2026-10-23', null, 1));
    expect(ErroresMysql::traducir($e)['mensaje'])->toBe('El concepto no existe');
})->group('mysql');

test('soporte de una incapacidad: adjuntar, ver con bitacora y permisos', function () {
    $repo = app(NovedadRepositoryInterface::class);
    [$carlos, $laura, $juan, $sofia, $superUsuario] = array_map('idDe', ['1078901234', '52000111', '1023456789', '1056789012']) + [4 => Usuario::where('id_rol', 4)->value('id_usuario')];

    $inc = $repo->registrar($juan, $juan, 'INCAPACIDAD', '2026-11-10', '2026-11-12');
    $conSoporte = $repo->adjuntarSoporte($carlos, $inc->id_novedad, md5('inc'), hash('sha256', 'inc'), TipoSoporte::Pdf, 2000);

    expect($conSoporte->tieneSoporte)->toBeTrue()
        ->and($conSoporte->soporte_tipo)->toBe(TipoSoporte::Pdf);

    expect($repo->verSoporte($laura, $inc->id_novedad)->soporte_bytes)->toBe(2000)
        ->and(app(BitacoraRepositoryInterface::class)->delRegistro('Novedad', $inc->id_novedad)->pluck('accion')->map->value->all())
        ->toBe(['REGISTRAR_NOVEDAD', 'SUBIR_SOPORTE', 'VER_SOPORTE']);

    $mensaje = fn (Closure $a) => ErroresMysql::traducir(capturar($a))['mensaje'];
    expect($mensaje(fn () => $repo->verSoporte($sofia, $inc->id_novedad)))->toBe('No tiene permiso para ver este soporte')
        ->and($mensaje(fn () => $repo->verSoporte($superUsuario, $inc->id_novedad, 'corto')))->toBe('El Super Usuario debe indicar el motivo de la consulta')
        ->and($repo->verSoporte($superUsuario, $inc->id_novedad, 'Auditoria de la incapacidad')->soporte_tipo)->toBe('PDF');
})->group('mysql');

test('Usuario: contrasena encriptada, rol y cargo coherentes, salario minimo y cedula unica', function () {
    $repo = app(UsuarioRepositoryInterface::class);
    $cargo = app(CargoRepositoryInterface::class)->findByNombreEnEmpresa('900111222', 'Operario')->id_cargo;

    $nuevo = $repo->create([
        'cedula' => '1099000111', 'nombre' => 'Camila Rios', 'correo_acceso' => 'camila@textilesandina-prueba.co',
        'contrasena_hash' => 'Clave-Segura-1', 'id_rol' => 3, 'cargo_id' => $cargo,
        'salario' => 1900000, 'tipo_contrato' => 'TERMINO_FIJO', 'fecha_vinculacion' => '2026-10-15', 'estado_laboral' => 'ACTIVO',
    ]);

    $guardado = $repo->find($nuevo->id_usuario);
    expect(Hash::check('Clave-Segura-1', $guardado->contrasena_hash))->toBeTrue()   // cast 'hashed'
        ->and($guardado->contrasena_hash)->not->toBe('Clave-Segura-1');

    $base = ['id_rol' => 3, 'cargo_id' => $cargo, 'salario' => 1900000, 'tipo_contrato' => 'TERMINO_FIJO', 'fecha_vinculacion' => '2026-10-15', 'estado_laboral' => 'ACTIVO'];
    $mensaje = fn (Closure $a) => ErroresMysql::traducir(capturar($a))['mensaje'];

    expect($mensaje(fn () => $repo->create(['cedula' => '1099000222', 'nombre' => 'Salario Bajo', 'salario' => 1000000] + $base)))
        ->toBe('El salario no puede ser menor al salario minimo vigente')
        ->and($mensaje(fn () => $repo->create(['cedula' => '1099000333', 'nombre' => 'Sin cargo', 'cargo_id' => null] + $base)))->toBe('El cargo no existe')
        ->and($mensaje(fn () => $repo->create(['cedula' => '1099000444', 'nombre' => 'Super con cargo', 'id_rol' => 4] + $base)))->toBe('El Super Usuario no tiene cargo ni datos laborales')
        ->and($mensaje(fn () => $repo->create(['cedula' => '1099000555', 'nombre' => 'Sin estado laboral', 'estado_laboral' => null] + $base)))->toContain('Faltan los datos laborales');

    $e = capturar(fn () => $repo->create(['cedula' => '1099000111', 'nombre' => 'Repetida'] + $base));
    expect($e->errorInfo[1])->toBe(1062)
        ->and(ErroresMysql::traducir($e)['status'])->toBe(409);

    // El Administrador de la empresa deja constancia de la cuenta creada (bitacora, por procedimiento).
    $repo->registrarCreacionCuenta(idDe('52000111'), $nuevo->id_usuario);
    expect(app(BitacoraRepositoryInterface::class)->delRegistro('Usuario', $nuevo->id_usuario))->toHaveCount(1);
})->group('mysql');

test('Empresa: crear cliente, CHECK de la clase de riesgo, baja logica y no se borra', function () {
    $repo = app(EmpresaRepositoryInterface::class);
    $datos = [
        'nit' => '900555666', 'tipo_empresa' => TipoEmpresa::Cliente, 'razon_social' => 'Prueba SAS',
        'representante_legal' => 'Ana Prueba', 'correo' => 'prueba@prueba.co', 'fecha_registro' => '2026-10-01',
        'contador_id' => '900000001',
    ];

    // Un cliente debe declarar si esta exonerada y su clase de riesgo (CHECK) -> 3819 -> 422
    expect(capturar(fn () => $repo->create($datos))->errorInfo[1])->toBe(3819);

    $cliente = $repo->create($datos + ['exonerada_art_114_1' => false, 'clase_riesgo_arl' => 'III']);
    expect($repo->find('900555666')->exonerada_art_114_1)->toBeFalse();

    // Baja logica (la aplicacion no puede hacer DELETE): la empresa pasa a INACTIVA.
    $repo->update('900555666', ['estado' => 'INACTIVA']);
    expect($repo->find('900555666')->estado->value)->toBe('INACTIVA');
    $e = capturar(fn () => $repo->delete('900555666'));
    expect($e->errorInfo[1])->toBe(1142)
        ->and(ErroresMysql::traducir($e)['status'])->toBe(403);

    // Un cliente no puede tener como contador a una entidad de seguridad social (trigger)
    $e = capturar(fn () => $repo->update('900111222', ['contador_id' => '800000001']));
    expect(ErroresMysql::traducir($e)['mensaje'])->toBe('El contador externo debe ser una empresa de tipo CONTADORA');
})->group('mysql');

test('Afiliacion: se registra y se aprueba por procedimiento, con su rol', function () {
    $repo = app(AfiliacionRepositoryInterface::class);
    [$carlos, $laura, $ana, $pedro] = array_map('idDe', ['1078901234', '52000111', '1012345678', '1045678901']);
    $mensaje = fn (Closure $a) => ErroresMysql::traducir(capturar($a))['mensaje'];

    // Una EPS no puede apuntar a una AFP (800000002) -> trigger -> 422
    expect($mensaje(fn () => $repo->registrar($carlos, $ana, TipoEntidad::Eps, '800000002', '2026-11-03')))
        ->toBe('La entidad debe ser una empresa de servicios del mismo tipo que la afiliacion')
        ->and($mensaje(fn () => $repo->registrar($ana, $ana, TipoEntidad::Eps, '800000001', '2026-11-03')))->toBe('El Empleado no registra afiliaciones')
        ->and($mensaje(fn () => $repo->registrar($laura, $ana, TipoEntidad::Eps, '800000001', '2026-11-03')))->toBe('El Administrador aprueba afiliaciones, no las registra');

    $nueva = $repo->registrar($carlos, $ana, TipoEntidad::Eps, '800000001', '2026-11-03');
    expect($nueva->estado)->toBe(EstadoAprobacion::Pendiente)
        ->and($mensaje(fn () => $repo->gestionar($carlos, $nueva->id_afiliacion, EstadoAprobacion::Aprobada)))->toContain('Solo el Administrador de la empresa de la persona')
        ->and($repo->gestionar($laura, $nueva->id_afiliacion, EstadoAprobacion::Aprobada)->estado)->toBe(EstadoAprobacion::Aprobada);

    // Aprobar la ARL de Pedro lo deja con las 4 afiliaciones.
    expect($repo->estaCompleta($pedro, '2026-10-31'))->toBeFalse();
    $arl = $repo->delUsuario($pedro)->firstWhere('tipo', TipoEntidad::Arl);
    $repo->gestionar($laura, $arl->id_afiliacion, EstadoAprobacion::Aprobada);
    expect($repo->estaCompleta($pedro, '2026-10-31'))->toBeTrue()
        ->and($repo->pendientes())->toBeEmpty();
})->group('mysql');

test('ParametroLegal: solo el Super Usuario agrega una vigencia; no hacia atras ni bajando el salario minimo', function () {
    $repo = app(ParametroLegalRepositoryInterface::class);
    $super = Usuario::where('id_rol', 4)->value('id_usuario');
    $base = $repo->find(3)->only([
        'salario_minimo', 'divisor_hora', 'recargo_dominical', 'tarifa_salud_empleador', 'tarifa_salud_empleado',
        'tarifa_pension_empleador', 'tarifa_pension_empleado', 'tarifa_caja', 'tarifa_sena_icbf',
        'tarifa_arl_i', 'tarifa_arl_ii', 'tarifa_arl_iii', 'tarifa_arl_iv', 'tarifa_arl_v',
    ]);
    $mensaje = fn (Closure $a) => ErroresMysql::traducir(capturar($a))['mensaje'];

    expect($mensaje(fn () => $repo->registrar(idDe('1078901234'), $base + ['vigente_desde' => '2027-01-01'])))->toBe('Solo el Super Usuario registra los valores legales')
        ->and($mensaje(fn () => $repo->registrar($super, $base + ['vigente_desde' => '2026-03-01'])))->toBe('Solo se agregan vigencias posteriores a la ultima')
        ->and($mensaje(fn () => $repo->registrar($super, ['salario_minimo' => 1000000] + $base + ['vigente_desde' => '2027-01-01'])))->toBe('El salario minimo no puede ser menor al de la vigencia anterior');

    $nueva = $repo->registrar($super, ['salario_minimo' => 1900000] + $base + ['vigente_desde' => '2027-01-01']);
    expect($repo->vigenteEn('2027-01-15')->id_parametro)->toBe($nueva->id_parametro)
        ->and($repo->vigenteEn('2026-12-31')->id_parametro)->toBe(3);

    // La aplicacion tampoco puede modificar los valores legales directo.
    expect(capturar(fn () => $repo->update(3, ['divisor_hora' => 200]))->errorInfo[1])->toBe(1142);
})->group('mysql');
