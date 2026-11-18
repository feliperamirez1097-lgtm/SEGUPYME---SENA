<?php

namespace App\Providers;

use App\Repositories\Contracts\AfiliacionRepositoryInterface;
use App\Repositories\Contracts\BitacoraRepositoryInterface;
use App\Repositories\Contracts\CargoRepositoryInterface;
use App\Repositories\Contracts\ConceptoRepositoryInterface;
use App\Repositories\Contracts\DetallePlanillaRepositoryInterface;
use App\Repositories\Contracts\EmpresaRepositoryInterface;
use App\Repositories\Contracts\NovedadRepositoryInterface;
use App\Repositories\Contracts\ParametroLegalRepositoryInterface;
use App\Repositories\Contracts\PlanillaRepositoryInterface;
use App\Repositories\Contracts\RolRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Repositories\Eloquent\AfiliacionRepository;
use App\Repositories\Eloquent\BitacoraRepository;
use App\Repositories\Eloquent\CargoRepository;
use App\Repositories\Eloquent\ConceptoRepository;
use App\Repositories\Eloquent\DetallePlanillaRepository;
use App\Repositories\Eloquent\EmpresaRepository;
use App\Repositories\Eloquent\NovedadRepository;
use App\Repositories\Eloquent\ParametroLegalRepository;
use App\Repositories\Eloquent\PlanillaRepository;
use App\Repositories\Eloquent\RolRepository;
use App\Repositories\Eloquent\UsuarioRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Une cada interfaz de repositorio con su implementacion.
 * Al pedir la interfaz (inyeccion en controladores/servicios),
 * Laravel entrega la implementacion Eloquent.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        RolRepositoryInterface::class => RolRepository::class,
        EmpresaRepositoryInterface::class => EmpresaRepository::class,
        CargoRepositoryInterface::class => CargoRepository::class,
        UsuarioRepositoryInterface::class => UsuarioRepository::class,
        BitacoraRepositoryInterface::class => BitacoraRepository::class,
        AfiliacionRepositoryInterface::class => AfiliacionRepository::class,
        ConceptoRepositoryInterface::class => ConceptoRepository::class,
        NovedadRepositoryInterface::class => NovedadRepository::class,
        ParametroLegalRepositoryInterface::class => ParametroLegalRepository::class,
        PlanillaRepositoryInterface::class => PlanillaRepository::class,
        DetallePlanillaRepositoryInterface::class => DetallePlanillaRepository::class,
    ];
}
