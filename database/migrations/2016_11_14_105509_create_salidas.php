<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSalidas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salidas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('acta_id')->length(10);
            $table->integer('tipo')->length(2);
            $table->string('clues',12);
            $table->string('realiza',254)->nullable();
            $table->string('autoriza',254)->nullable();
            $table->string('recibe',254)->nullable();
            $table->integer('estatus')->length(2);
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
        Schema::drop('salidas');
    }
}
