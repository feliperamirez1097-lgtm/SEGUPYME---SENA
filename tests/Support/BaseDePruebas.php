<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Reconstruye la base de datos de pruebas a partir de los scripts de database/sql.
 *
 * Por que existe: las pruebas de escritura (generar planillas, cargas masivas) llaman procedimientos
 * almacenados que confirman su propia transaccion; no se pueden deshacer con un ROLLBACK. En lugar de
 * ensuciar la base real, cada archivo de pruebas la deja como nueva.
 *
 * Seguridad: los scripts empiezan con DROP DATABASE. Por eso el nombre de la base de pruebas DEBE terminar
 * en `_test`; si no, se aborta. Los scripts nombran la base `segupyme_v15`; aqui se reemplaza por la de pruebas.
 */
class BaseDePruebas
{
    private const ARCHIVOS = [
        '01_crear_base_de_datos.sql', '02_restricciones_check.sql', '03_funciones_y_procedimientos.sql',
        '04_triggers.sql', '05_datos_de_prueba.sql', '08_seguridad.sql',
    ];

    /** @var array<string, true> Archivos de pruebas cuya base ya se reconstruyo en esta corrida. */
    private static array $reconstruidas = [];

    /**
     * Reconstruye la base una sola vez por archivo de pruebas (la clave es la ruta del archivo).
     * Asi cada archivo parte de una base nueva y sus pruebas no dependen de otros archivos.
     */
    public static function reconstruir(string $clave): void
    {
        if (isset(self::$reconstruidas[$clave])) {
            return;
        }

        $cfg = config('database.connections.mysql');
        $nombre = (string) $cfg['database'];

        if (! str_ends_with($nombre, '_test')) {
            throw new RuntimeException("Por seguridad la base de pruebas debe terminar en _test (DB_DATABASE='{$nombre}'). Revisa .env.testing.");
        }

        // Conexion al servidor SIN base y con una cuenta administradora (DB_ADMIN_USERNAME): los scripts hacen
        // DROP/CREATE DATABASE, crean triggers y cuentas. La conexion que usan las pruebas es la de la aplicacion
        // (cuenta segupyme_app), que solo tiene los permisos reducidos de produccion.
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $cfg['host'], $cfg['port']),
            (string) env('DB_ADMIN_USERNAME', $cfg['username']),
            (string) env('DB_ADMIN_PASSWORD', $cfg['password']),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        foreach (self::ARCHIVOS as $archivo) {
            $sql = file_get_contents(base_path("database/sql/{$archivo}"));
            $sql = str_replace('segupyme_v15', $nombre, $sql);

            foreach (self::sentencias($sql) as $sentencia) {
                $pdo->exec($sentencia);
            }
        }

        // La conexion de Laravel (si ya estaba abierta) apuntaba a la base vieja: se reinicia.
        DB::purge('mysql');

        self::$reconstruidas[$clave] = true;
    }

    /**
     * Parte un script de MySQL en sentencias. Entiende DELIMITER (que es un comando del cliente de
     * MySQL, no de SQL) para poder ejecutar triggers y procedimientos por PDO.
     *
     * @return array<int, string>
     */
    public static function sentencias(string $sql): array
    {
        $sentencias = [];
        $delimitador = ';';
        $actual = '';

        foreach (preg_split('/\R/', $sql) as $linea) {
            $recortada = trim($linea);

            if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $recortada, $m)) {
                $delimitador = $m[1];

                continue;
            }

            // Se omiten lineas de comentario y vacias fuera de una sentencia.
            if ($actual === '' && ($recortada === '' || str_starts_with($recortada, '--'))) {
                continue;
            }

            $actual .= $linea."\n";

            if (str_ends_with($recortada, $delimitador)) {
                $sentencias[] = trim(substr(rtrim($actual), 0, -strlen($delimitador)));
                $actual = '';
            }
        }

        if (trim($actual) !== '') {
            $sentencias[] = trim($actual);
        }

        return $sentencias;
    }
}
