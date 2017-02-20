<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class HistorialInventario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hisitorial_inventario', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('anio')->length(11);
            $table->string('clues',15);
            $table->string('llave',50);
            $table->integer('valor')->length(11);
            $table->date('fecha_actualizo',254)->nullable();
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
        Schema::drop('hisitorial_inventario');
    }
}
