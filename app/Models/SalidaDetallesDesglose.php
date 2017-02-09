<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use \DB;

class SalidaDetallesDesglose extends Model {
    protected $table = 'salida_detalle_desglose';

    protected $fillable = ['salida_detalle_id', 'id_stock_insumo', 'cantidad'];

}