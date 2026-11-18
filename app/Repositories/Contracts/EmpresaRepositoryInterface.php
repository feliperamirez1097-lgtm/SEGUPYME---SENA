<?php

namespace App\Repositories\Contracts;

use App\Enums\TipoEntidad;
use App\Models\Empresa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Empresa>
 */
interface EmpresaRepositoryInterface extends BaseRepositoryInterface
{
    /** @return Collection<int, Empresa> Empresas cliente activas, ordenadas por razon social. */
    public function clientes(): Collection;

    /** @return Collection<int, Empresa> Entidades de seguridad social, opcionalmente de un tipo. */
    public function entidades(?TipoEntidad $tipo = null): Collection;

    /** @return Collection<int, Empresa> Clientes que atiende una contadora. */
    public function clientesDeContadora(string $nitContadora): Collection;

    /**
     * Buscador con filtros opcionales: tipo_empresa, estado y texto en razon social o NIT.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Empresa>
     */
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator;
}
