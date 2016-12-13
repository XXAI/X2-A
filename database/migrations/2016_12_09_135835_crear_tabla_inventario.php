<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaInventario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('anio')->length(4); //mes y año
            $table->string('clues',15);
            $table->string('llave',50);
            $table->integer('1')->nullable();
            $table->integer('2')->nullable();
            $table->integer('3')->nullable();
            $table->integer('4')->nullable();
            $table->integer('5')->nullable();
            $table->integer('6')->nullable();
            $table->integer('7')->nullable();
            $table->integer('8')->nullable();
            $table->integer('9')->nullable();
            $table->integer('10')->nullable();
            $table->integer('11')->nullable();
            $table->integer('12')->nullable();
            $table->string('usuario_actualizo',255);
            $table->timestamp('fecha_actualizo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('inventario');
    }
}
