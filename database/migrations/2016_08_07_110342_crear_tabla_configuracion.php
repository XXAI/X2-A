<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablaConfiguracion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configuracion', function (Blueprint $table) {
            $table->increments('id');
            $table->string('clues',15);
            $table->string('clues_nombre',255);
            $table->string('empresa_clave',14);
            $table->string('empresa_nombre',255);
            $table->string('director_unidad',255);
            $table->string('solicitante_nombre',255)->nullable();
            $table->string('solicitante_cargo',255)->nullable();
            $table->string('ciudad',255)->nullable();
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
        Schema::drop('configuracion');
    }
}
