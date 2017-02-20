<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SalidaDetalleDesglose extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salida_detalles_desglose', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('salida_detalle_id')->length(10)->unsigned();
            $table->integer('id_stock_insumo')->length(10);
            $table->integer('cantidad')->length(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('salida_detalles_desglose');
    }
}
