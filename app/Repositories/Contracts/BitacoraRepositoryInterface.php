<?php

namespace App\Repositories\Contracts;

use App\Models\Bitacora;
use Illuminate\Database\Eloquent\Collection;

/**
 * La bitacora es de SOLO LECTURA para la aplicacion: la escriben los procedimientos (registrar una novedad,
 * aprobarla, adjuntar o ver un soporte, crear una cuenta). La base impide modificarla o borrarla, y la cuenta
 * de la aplicacion ni siquiera puede insertar en ella.
 *
 * @extends BaseRepositoryInterface<Bitacora>
 */
interface BitacoraRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Bitacora> Lo que hizo una persona, lo mas reciente primero. */
    public function porUsuario(int $idUsuario): Collection;

    /** @return Collection<int, Bitacora> Historia de un registro (p. ej. la novedad 12). */
    public function delRegistro(string $tabla, int $idRegistro): Collection;
}
