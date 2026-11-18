<?php

namespace App\Repositories\Eloquent;

use App\Models\Rol;
use App\Repositories\Contracts\RolRepositoryInterface;

/**
 * @extends BaseRepository<Rol>
 */
class RolRepository extends BaseRepository implements RolRepositoryInterface
{
    public function __construct(Rol $model)
    {
        parent::__construct($model);
    }

    public function findByNombre(string $nombre): ?Rol
    {
        return $this->query()->where('nombre_rol', $nombre)->first();
    }
}
