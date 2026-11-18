<?php

namespace App\Providers;

use App\Models\Afiliacion;
use App\Models\Novedad;
use App\Models\Planilla;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Evita el problema N+1 (manual, seccion 6): en desarrollo, leer una relacion sin with() lanza error.
        Model::preventLazyLoading(! app()->isProduction());

        // Relacion polimorfica de Bitacora (manual, fila 59): `tabla_afectada` guarda el nombre corto
        // y no la clase de PHP. enforceMorphMap obliga a que todo nombre este registrado aqui.
        Relation::enforceMorphMap([
            'Usuario' => Usuario::class,
            'Novedad' => Novedad::class,
            'Planilla' => Planilla::class,
            'Afiliacion' => Afiliacion::class,
        ]);
    }
}
