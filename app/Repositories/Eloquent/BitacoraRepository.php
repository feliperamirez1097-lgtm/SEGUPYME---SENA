<?php

namespace App\Repositories\Eloquent;

use App\Models\Bitacora;
use App\Repositories\Contracts\BitacoraRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Bitacora>
 */
class BitacoraRepository extends BaseRepository implements BitacoraRepositoryInterface
{
    public function __construct(Bitacora $model)
    {
        parent::__construct($model);
    }

    public function porUsuario(int $idUsuario): Collection
    {
        return $this->query()->where('usuario_id', $idUsuario)->orderByDesc('id_bitacora')->get();
    }

    public function delRegistro(string $tabla, int $idRegistro): Collection
    {
        return $this->query()->where('tabla_afectada', $tabla)->where('registro_id', $idRegistro)->orderBy('id_bitacora')->get();
    }
}
