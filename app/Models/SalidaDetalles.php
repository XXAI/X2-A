<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class SalidaDetalles extends Model {
    protected $table = 'salida_detalles';

    protected $fillable = ['salida_id', 'clues', 'cantidad_solicitada', 'cantidad_surtido', 'cantidad_no_surtido', 'insumo_id'];

}