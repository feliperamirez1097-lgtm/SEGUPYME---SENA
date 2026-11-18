<?php

namespace App\Repositories\Eloquent;

use App\Models\Cargo;
use App\Repositories\Contracts\CargoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Cargo>
 */
class CargoRepository extends BaseRepository implements CargoRepositoryInterface
{
    public function __construct(Cargo $model)
    {
        parent::__construct($model);
    }

    public function porEmpresa(string $nit): Collection
    {
        return $this->query()->where('empresa_id', $nit)->orderBy('nombre_cargo')->get();
    }

    public function findByNombreEnEmpresa(string $nit, string $nombre): ?Cargo
    {
        return $this->query()->where('empresa_id', $nit)->where('nombre_cargo', $nombre)->first();
    }
}
