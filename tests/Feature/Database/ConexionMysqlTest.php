<?php

/*
|--------------------------------------------------------------------------
| Prueba de conexion a la base de datos MySQL
|--------------------------------------------------------------------------
|
| Verifica que Laravel se conecta a MySQL con los datos de .env.testing y que la
| base de pruebas se reconstruye con los scripts de database/sql (11 tablas y la vista).
|
| Grupo "mysql":
|   php artisan test --group=mysql
|
*/

use Illuminate\Support\Facades\DB;

beforeEach(fn () => baseNueva(__FILE__));

test('se conecta a la base de datos MySQL', function () {
    $nombre = config('database.connections.mysql.database');

    DB::connection('mysql')->getPdo();
    fwrite(STDOUT, "\n  Conexion exitosa a la base de datos '{$nombre}'.\n");

    expect(DB::connection('mysql')->selectOne('SELECT DATABASE() AS base')->base)->toBe($nombre);
})->group('mysql');

test('la base tiene las 11 tablas del modelo y la vista de planilla', function () {
    $tablas = collect(DB::select('SHOW FULL TABLES'))
        ->mapWithKeys(fn ($f) => [array_values((array) $f)[0] => array_values((array) $f)[1]]);

    expect($tablas->filter(fn ($tipo) => $tipo === 'BASE TABLE'))->toHaveCount(11)
        ->and($tablas->filter(fn ($tipo) => $tipo === 'VIEW')->keys()->all())->toBe(['v_planilla_total']);
})->group('mysql');

test('Laravel no creo tablas propias: no hay users, sessions, cache ni jobs', function () {
    $nombres = collect(DB::select('SHOW TABLES'))->map(fn ($f) => strtolower(array_values((array) $f)[0]));

    expect($nombres->intersect(['users', 'sessions', 'cache', 'jobs', 'migrations']))->toBeEmpty();
})->group('mysql');
