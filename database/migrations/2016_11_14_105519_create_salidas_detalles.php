<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSalidasDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salida_detalles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_salida')->length(10)->unsigned();
            $table->string('clues',12);
            $table->integer('cantidad')->length(10);
            $table->integer('id_stock_insumo')->length(10);
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
        Schema::drop('salida_detalles');
    }
}
