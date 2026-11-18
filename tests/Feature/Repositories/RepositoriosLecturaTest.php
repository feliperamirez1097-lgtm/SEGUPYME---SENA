<?php

/*
|--------------------------------------------------------------------------
| Repositorios: consultas de lectura
|--------------------------------------------------------------------------
|
| Cada repositorio se pide por su INTERFAZ (app(Interfaz::class)): el RepositoryServiceProvider
| entrega la implementacion Eloquent. Con eso se prueba, de paso, que los 11 enlaces funcionan.
| Solo lee: no modifica la base.
|
*/

use App\Enums\EstadoAprobacion;
use App\Enums\TipoEmpresa;
use App\Enums\TipoEntidad;
use App\Models\Concepto;
use App\Models\Usuario;
use App\Repositories\Contracts\AfiliacionRepositoryInterface;
use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Repositories\Contracts\CargoRepositoryInterface;
use App\Repositories\Contracts\ConceptoRepositoryInterface;
use App\Repositories\Contracts\DetallePlanillaRepositoryInterface;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use App\Repositories\Contracts\ParametroLegalRepositoryInterface;
use App\Repositories\Contracts\PlanillaRepositoryInterface;
use App\Repositories\Contracts\RolRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

beforeEach(fn () => baseNueva(__FILE__));

test('el provider entrega una implementacion para cada contrato de repositorio', function (string $interfaz) {
    $repo = app($interfaz);

    expect($repo)->toBeInstanceOf($interfaz)
        ->toBeInstanceOf(BaseRepositoryInterface::class);
})->with([
    RolRepositoryInterface::class,
    EmpresaRepositoryInterface::class,
    CargoRepositoryInterface::class,
    UsuarioRepositoryInterface::class,
    AfiliacionRepositoryInterface::class,
    ConceptoRepositoryInterface::class,
    NovedadRepositoryInterface::class,
    ParametroLegalRepositoryInterface::class,
    PlanillaRepositoryInterface::class,
    DetallePlanillaRepositoryInterface::class,
])->group('mysql');

test('BaseRepository: all, find, findOrFail y paginate con llave numerica y con NIT de texto', function () {
    $roles = app(RolRepositoryInterface::class);
    $empresas = app(EmpresaRepositoryInterface::class);

    expect($roles->all())->toHaveCount(4)
        ->and($roles->find(2)->nombre_rol)->toBe('CONTADOR')
        ->and($roles->find(99))->toBeNull()
        ->and($empresas->find('900111222')->razon_social)->toBe('Textiles Andina SAS')
        ->and($empresas->findOrFail('900333444', ['contadora'])->contadora->nit)->toBe('900000001')
        ->and($empresas->paginate(3))->toBeInstanceOf(LengthAwarePaginator::class);

    expect(fn () => $empresas->findOrFail('000'))->toThrow(ModelNotFoundException::class);
})->group('mysql');

test('RolRepository y CargoRepository', function () {
    $roles = app(RolRepositoryInterface::class);
    $cargos = app(CargoRepositoryInterface::class);

    expect($roles->findByNombre('SUPER_USUARIO')->id_rol)->toBe(4)
        ->and($cargos->porEmpresa('900111222')->pluck('nombre_cargo')->all())->toBe(['Gerente', 'Operario'])
        ->and($cargos->findByNombreEnEmpresa('900333444', 'Panadero'))->not->toBeNull()
        ->and($cargos->findByNombreEnEmpresa('900111222', 'Panadero'))->toBeNull();
})->group('mysql');

test('EmpresaRepository: clientes, entidades, clientes de una contadora y buscador con filtros opcionales', function () {
    $repo = app(EmpresaRepositoryInterface::class);

    expect($repo->clientes()->pluck('nit')->all())->toBe(['900333444', '900111222'])  // por razon social
        ->and($repo->entidades())->toHaveCount(4)
        ->and($repo->entidades(TipoEntidad::Eps)->first()->razon_social)->toBe('EPS Sanitas (prueba)')
        ->and($repo->clientesDeContadora('900000001'))->toHaveCount(2);

    $buscar = fn (array $f) => $repo->buscar($f)->total();

    expect($buscar([]))->toBe(7)
        ->and($buscar(['texto' => 'Textiles']))->toBe(1)
        ->and($buscar(['tipo_empresa' => TipoEmpresa::Servicios->value]))->toBe(4)
        ->and($buscar(['texto' => '9001112']))->toBe(1);
})->group('mysql');

test('UsuarioRepository: busquedas por cedula y correo, por empresa y con filtros', function () {
    $repo = app(UsuarioRepositoryInterface::class);

    expect($repo->findByCedula('1012345678')->nombre)->toBe('Ana Perez')
        ->and($repo->findByCedula('000'))->toBeNull()
        ->and($repo->findByCorreo('  ANA@textilesandina-prueba.co ')->nombre)->toBe('Ana Perez')
        ->and($repo->porEmpresa('900111222')->pluck('nombre')->all())->toBe(['Ana Perez', 'Juan Torres', 'Laura Gomez', 'Pedro Diaz'])
        ->and($repo->buscar(['empresa' => '900333444'])->total())->toBe(3)
        ->and($repo->buscar(['nombre' => 'Perez', 'id_rol' => 3])->total())->toBe(1);
})->group('mysql');

test('UsuarioRepository: quienes bloquean la planilla por afiliaciones incompletas (funcion de MySQL)', function () {
    $repo = app(UsuarioRepositoryInterface::class);

    $pedro = $repo->conAfiliacionesIncompletas('900111222', '2026-10-31');

    expect($pedro->pluck('nombre')->all())->toBe(['Pedro Diaz'])
        ->and($repo->conAfiliacionesIncompletas('900333444', '2026-10-31'))->toBeEmpty();
})->group('mysql');

test('AfiliacionRepository: por usuario, vigentes, pendientes y si esta completa', function () {
    $repo = app(AfiliacionRepositoryInterface::class);
    $ana = Usuario::where('cedula', '1012345678')->first();
    $pedro = Usuario::where('cedula', '1045678901')->first();

    expect($repo->delUsuario($pedro->id_usuario))->toHaveCount(4)
        ->and($repo->vigentesDe($pedro->id_usuario, '2026-10-31'))->toHaveCount(3)
        ->and($repo->vigentesDe($ana->id_usuario, '2026-10-31'))->toHaveCount(4)
        ->and($repo->vigentesDe($ana->id_usuario, '2026-08-01'))->toBeEmpty()   // aun no estaba vinculada
        ->and($repo->estaCompleta($ana->id_usuario, '2026-10-31'))->toBeTrue()
        ->and($repo->estaCompleta($pedro->id_usuario, '2026-10-31'))->toBeFalse();

    $pendientes = $repo->pendientes();
    expect($pendientes)->toHaveCount(1)
        ->and($pendientes->first()->usuario->nombre)->toBe('Pedro Diaz')
        ->and($pendientes->first()->estado)->toBe(EstadoAprobacion::Pendiente);
})->group('mysql');

test('ConceptoRepository: Eloquent y SQL nativo devuelven lo mismo', function () {
    $repo = app(ConceptoRepositoryInterface::class);

    $eloquent = $repo->findByCodigo('RECARGO_DOMINICAL_FESTIVO_DIURNO');
    $nativo = $repo->findByCodigoSql('RECARGO_DOMINICAL_FESTIVO_DIURNO');

    expect($nativo)->toBeInstanceOf(Concepto::class)
        ->and($nativo->id_concepto)->toBe($eloquent->id_concepto)
        ->and($nativo->suma_dominical)->toBeTrue()          // el cast de booleano tambien se aplica
        ->and($nativo->incluye_hora_base)->toBeFalse()      // los recargos pagan solo el porcentaje
        ->and($repo->findByCodigoSql('NO_EXISTE'))->toBeNull()
        ->and($repo->deHoras())->toHaveCount(7)
        ->and($repo->catalogo())->toHaveCount(12);
})->group('mysql');

test('NovedadRepository: por persona, pendientes y aprobadas de una empresa, y buscador', function () {
    $repo = app(NovedadRepositoryInterface::class);
    $juan = Usuario::where('cedula', '1023456789')->first();

    expect($repo->delEmpleado($juan->id_usuario, '2026-10-20'))->toHaveCount(1)
        ->and($repo->delEmpleado($juan->id_usuario, '2026-11-01'))->toBeEmpty()
        ->and($repo->pendientesDeEmpresa('900111222')->pluck('concepto.codigo')->all())->toBe(['HORA_EXTRA_NOCTURNA'])
        ->and($repo->aprobadasDelPeriodo('900111222', '2026-10-20'))->toHaveCount(2)
        ->and($repo->aprobadasDelPeriodo('900333444', '2026-10-20')->pluck('concepto.codigo')->all())->toBe(['RECARGO_DOMINICAL_FESTIVO_DIURNO']);

    $buscar = fn (array $f) => $repo->buscar($f)->total();

    expect($buscar([]))->toBe(4)
        ->and($buscar(['empresa' => '900111222']))->toBe(3)
        ->and($buscar(['empresa' => '900111222', 'estado' => 'APROBADA']))->toBe(2)
        ->and($buscar(['periodo' => '2026-11-15']))->toBe(0);
})->group('mysql');

test('ParametroLegalRepository: la vigencia de una fecha, con Eloquent y con SQL nativo', function () {
    $repo = app(ParametroLegalRepositoryInterface::class);

    expect($repo->vigenteEn('2026-10-31')->divisor_hora)->toBe(210)
        ->and($repo->vigenteEnSql('2026-10-31')->id_parametro)->toBe($repo->vigenteEn('2026-10-31')->id_parametro)
        ->and($repo->vigenteEn('2026-03-15')->divisor_hora)->toBe(220)
        ->and($repo->vigenteEn('2026-07-20')->divisor_hora)->toBe(210)     // el divisor cambia el 15 de julio
        ->and($repo->vigenteEn('2025-01-01'))->toBeNull()
        ->and($repo->vigenteEnSql('2025-01-01'))->toBeNull();
})->group('mysql');

test('PlanillaRepository y DetallePlanillaRepository sin planillas todavia', function () {
    $planillas = app(PlanillaRepositoryInterface::class);
    $detalle = app(DetallePlanillaRepositoryInterface::class);

    expect($planillas->resumen(1))->toBeNull()
        ->and($planillas->listarPorEmpresa('900111222')->total())->toBe(0)
        ->and($detalle->lineas(1))->toBeEmpty()
        ->and($detalle->totalesPorPersona(1))->toBe([]);
})->group('mysql');
