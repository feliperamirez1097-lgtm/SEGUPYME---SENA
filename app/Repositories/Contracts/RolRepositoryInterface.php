<?php

namespace App\Repositories\Contracts;

use App\Models\Rol;

/**
 * @extends BaseRepositoryInterface<Rol>
 */
interface RolRepositoryInterface extends BaseRepositoryInterface
{
    public function findByNombre(string $nombre): ?Rol;
}
