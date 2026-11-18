<?php

namespace App\Repositories\Contracts;

use App\Models\ParametroLegal;

/**
 * @extends BaseRepositoryInterface<ParametroLegal>
 */
interface ParametroLegalRepositoryInterface extends BaseRepositoryInterface
{
    /** La vigencia que rige en una fecha (version Eloquent). */
    public function vigenteEn(string $fecha): ?ParametroLegal;

    /**
     * Agrega una nueva vigencia legal (sp_registrar_parametro_legal: solo el Super Usuario).
     *
     * @param  array<string, mixed>  $valores  vigente_desde, salario_minimo, divisor_hora, recargo_dominical y las tarifas.
     */
    public function registrar(int $idActor, array $valores): ParametroLegal;

    /** Lo mismo con la funcion fn_parametro_vigente de MySQL (SQL nativo). */
    public function vigenteEnSql(string $fecha): ?ParametroLegal;
}
