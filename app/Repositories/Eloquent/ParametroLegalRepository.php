<?php

namespace App\Repositories\Eloquent;

use App\Models\ParametroLegal;
use App\Repositories\Contracts\ParametroLegalRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<ParametroLegal>
 */
class ParametroLegalRepository extends BaseRepository implements ParametroLegalRepositoryInterface
{
    public function __construct(ParametroLegal $model)
    {
        parent::__construct($model);
    }

    public function vigenteEn(string $fecha): ?ParametroLegal
    {
        return $this->query()->vigenteEn($fecha)->first();
    }

    public function registrar(int $idActor, array $valores): ParametroLegal
    {
        DB::statement('CALL sp_registrar_parametro_legal(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @id_parametro)', [
            $idActor, $valores['vigente_desde'], $valores['salario_minimo'], $valores['divisor_hora'], $valores['recargo_dominical'],
            $valores['tarifa_salud_empleador'], $valores['tarifa_salud_empleado'], $valores['tarifa_pension_empleador'], $valores['tarifa_pension_empleado'],
            $valores['tarifa_caja'], $valores['tarifa_sena_icbf'],
            $valores['tarifa_arl_i'], $valores['tarifa_arl_ii'], $valores['tarifa_arl_iii'], $valores['tarifa_arl_iv'], $valores['tarifa_arl_v'],
        ]);

        return $this->findOrFail((int) DB::selectOne('SELECT @id_parametro AS id')->id);
    }

    public function vigenteEnSql(string $fecha): ?ParametroLegal
    {
        $fila = DB::selectOne(
            'SELECT * FROM Parametro_Legal WHERE vigente_desde <= ? ORDER BY vigente_desde DESC LIMIT 1',
            [$fecha]
        );

        return $fila ? $this->model->newFromBuilder($fila) : null;
    }
}
