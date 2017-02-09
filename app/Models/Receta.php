<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class Receta extends Model
{
    protected $table = 'recetas';
    protected $fillable = ['usuario_id', 'salida_id', 'folio_interno', 'clues', 'tipo_receta', 'medico', 'paciente', 'diagnostico'];
    //

    public function recetaDetalle(){
        return $this->hasMany('App\Models\RecetaDetalles', 'receta_id');
    }
}
