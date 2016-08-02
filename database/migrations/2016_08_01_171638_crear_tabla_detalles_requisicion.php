<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaDetallesRequisicion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detalles_requisicion', function (Blueprint $table) {
            $table->increments('id');
            $table->int('requisicion_id')->lenght(10)->unsigned();
            $table->int('insumo_id')->lenght(10)->unsigned();
            $table->int('lote')->lenght(10)->unsigned();
            $table->int('cantidad')->lenght(10);
            $table->decimal('total',15,2);
            $table->timestamps();
            
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('detalles_requisicion');
    }
}
