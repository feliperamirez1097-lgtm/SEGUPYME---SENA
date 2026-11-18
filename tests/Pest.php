<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BaseDePruebas;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Deja la base de pruebas como nueva (esquema, reglas, procedimientos y datos del ejemplo).
 * Cada archivo de pruebas la llama en su beforeEach, pasando __FILE__ (se reconstruye solo la primera vez): asi las pruebas no dependen unas de otras.
 */
function baseNueva(string $archivo): void
{
    BaseDePruebas::reconstruir($archivo);
}

/**
 * Ejecuta algo que se espera que falle y devuelve la excepcion (o null si no fallo).
 * Sirve para revisar el codigo de error de MySQL de un QueryException.
 */
function capturar(Closure $accion): ?Throwable
{
    try {
        $accion();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}
