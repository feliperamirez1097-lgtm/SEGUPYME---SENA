<?php

namespace App\Repositories\Contracts;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Concepto>
 */
interface ConceptoRepositoryInterface extends BaseRepositoryInterface
{
    public function findByCodigo(string $codigo): ?Concepto;

    /** La misma busqueda escrita en SQL nativo (para comparar con Eloquent). */
    public function findByCodigoSql(string $codigo): ?Concepto;

    /** @return Collection<int, Concepto> Horas extra y recargos. */
    public function deHoras(): Collection;

    /** @return Collection<int, Concepto> Catalogo completo ordenado por id. */
    public function catalogo(): Collection;
}
