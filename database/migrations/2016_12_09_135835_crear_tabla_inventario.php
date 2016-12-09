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
            $table->integer('lote')->nullable();
            $table->string('pedido',15)->nullable();
            $table->string('proveedor',50)->nullable();
            $table->integer('1');
            $table->integer('2');
            $table->integer('3');
            $table->integer('4');
            $table->integer('5');
            $table->integer('6');
            $table->integer('7');
            $table->integer('8');
            $table->integer('9');
            $table->integer('10');
            $table->integer('11');
            $table->integer('12');
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
