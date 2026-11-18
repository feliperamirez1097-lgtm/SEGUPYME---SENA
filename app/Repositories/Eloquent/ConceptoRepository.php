<?php

namespace App\Repositories\Eloquent;

use App\Models\Concepto;
use App\Repositories\Contracts\ConceptoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Concepto>
 */
class ConceptoRepository extends BaseRepository implements ConceptoRepositoryInterface
{
    public function __construct(Concepto $model)
    {
        parent::__construct($model);
    }

    public function findByCodigo(string $codigo): ?Concepto
    {
        return $this->query()->where('codigo', $codigo)->first();
    }

    public function findByCodigoSql(string $codigo): ?Concepto
    {
        $fila = DB::selectOne('SELECT * FROM Concepto WHERE codigo = ? LIMIT 1', [$codigo]);

        // newFromBuilder convierte la fila en modelo y aplica los casts (afecta_ibc como booleano...).
        return $fila ? $this->model->newFromBuilder($fila) : null;
    }

    public function deHoras(): Collection
    {
        return $this->query()->deHoras()->orderBy('id_concepto')->get();
    }

    public function catalogo(): Collection
    {
        return $this->query()->orderBy('id_concepto')->get();
    }
}
