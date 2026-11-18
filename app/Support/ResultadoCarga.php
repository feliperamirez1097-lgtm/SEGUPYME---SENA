<?php

namespace App\Support;

/**
 * Resultado de una carga masiva (novedades o empleados).
 *
 * La carga no deja registro en una tabla: este objeto se devuelve al momento y de ahi se muestra
 * al usuario cuantas filas entraron y el motivo de cada fila rechazada.
 */
final readonly class ResultadoCarga
{
    /**
     * @param  array<int, array{fila: int, motivo: string}>  $rechazadas
     */
    public function __construct(
        public int $ok,
        public int $errores,
        public array $rechazadas,
    ) {}

    /**
     * @param  array{ok: int, errores: int, detalle: string}  $crudo  Lo que devuelve el procedimiento.
     */
    public static function desdeProcedimiento(array $crudo): self
    {
        $rechazadas = [];
        foreach (preg_split('/\R/', trim($crudo['detalle'])) ?: [] as $linea) {
            // El procedimiento escribe una linea por fila rechazada: "Fila 3: motivo".
            if (preg_match('/^Fila (\d+): (.+)$/u', $linea, $m)) {
                $rechazadas[] = ['fila' => (int) $m[1], 'motivo' => $m[2]];
            }
        }

        return new self($crudo['ok'], $crudo['errores'], $rechazadas);
    }

    public function hayErrores(): bool
    {
        return $this->errores > 0;
    }
}
