<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaRequisiciones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('requisiciones', function (Blueprint $table) {
            $table->increments('id');
            $table->int('acta_id')->lenght(10)->unsigned();
            $table->int('numero')->lenght(10)->unsigned();
            $table->string('pedido',10);
            $table->string('lotes',255);
            $table->string('empresa',45);
            $table->int('tipo_requisicion')->lenght(1);
            $table->int('dias_surtimiento')->lenght(10);
            $table->decimal('sub_total',15,2);
            $table->decimal('gran_total',15,2);
            $table->decimal('iva',5,2);
            $table->string('firma_solicita',255);
            $table->string('firma_director',255);
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
        Schema::drop('requisiciones');
    }
}
