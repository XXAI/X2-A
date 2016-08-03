<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaPivoteRequisicionInsumo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('requisicion_insumo', function (Blueprint $table) {
            $table->int('requisicion_id')->lenght(10)->unsigned();
            $table->int('insumo_id')->lenght(10)->unsigned();
            
            $table->int('cantidad')->lenght(10);
            $table->decimal('total',15,2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('requisicion_insumo');
    }
}
