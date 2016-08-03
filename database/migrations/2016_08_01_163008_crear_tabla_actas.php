<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaActas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('actas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('folio',100);
            $table->string('ciudad',255);
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_termino');
            $table->string('lugar_reunion',255);
            $table->string('empresa',45);
            $table->integer('estatus')->length(1);
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
        Schema::drop('actas');
    }
}
