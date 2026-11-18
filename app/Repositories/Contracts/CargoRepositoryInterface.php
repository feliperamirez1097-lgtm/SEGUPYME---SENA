<?php

namespace App\Repositories\Contracts;

use App\Models\Cargo;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Cargo>
 */
interface CargoRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Cargo> */
    public function porEmpresa(string $nit): Collection;

    public function findByNombreEnEmpresa(string $nit, string $nombre): ?Cargo;
}
