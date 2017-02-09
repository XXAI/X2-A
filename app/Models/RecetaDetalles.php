<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;


class RecetaDetalles extends Model
{
    protected $table = 'recetas_detalles';
    protected $fillable = ['receta_id', 'insumo_id', 'cantidad', 'cantidad_gotas', 'duplicar_dosis', 'frecuencia', 'duracion', 'observaciones'];

    public function insumo(){
        return $this->hasOne('App\Models\insumo', 'id', 'insumo_id');
    }
}
