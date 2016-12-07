<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class Salida extends Model {
    protected $fillable = ['clues', 'realiza', 'autoriza', 'recibe'];

    public function acta(){
        return $this->hasOne('App\Models\Acta', 'id', 'acta_id');
    }

    public function tipoSalida(){
        return $this->hasOne('App\Models\TipoSalida', 'id', 'tipo_salida_id');
    }

    public function salidaDetalle(){
        return $this->hasMany('App\Models\SalidaDetalles', 'salida_id');
    }

}