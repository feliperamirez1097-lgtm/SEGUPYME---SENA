<?php

namespace App\Repositories\Eloquent;

use App\Enums\TipoEntidad;
use App\Models\Empresa;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Empresa>
 */
class EmpresaRepository extends BaseRepository implements EmpresaRepositoryInterface
{
    public function __construct(Empresa $model)
    {
        parent::__construct($model);
    }

    public function clientes(): Collection
    {
        return $this->query()->clientes()->activas()->orderBy('razon_social')->get();
    }

    public function entidades(?TipoEntidad $tipo = null): Collection
    {
        return $this->query()->entidades($tipo)->orderBy('razon_social')->get();
    }

    public function clientesDeContadora(string $nitContadora): Collection
    {
        return $this->query()->where('contador_id', $nitContadora)->orderBy('razon_social')->get();
    }

    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        // when(): la condicion se agrega solo si el filtro viene en la peticion (patron del manual).
        return $this->query()
            ->when($filtros['tipo_empresa'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo_empresa', $tipo))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $estado) => $q->where('estado', $estado))
            ->when($filtros['texto'] ?? null, fn (Builder $q, string $t) => $q->where(
                fn (Builder $w) => $w->where('razon_social', 'like', "%{$t}%")->orWhere('nit', 'like', "%{$t}%")
            ))
            ->orderBy('razon_social')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
