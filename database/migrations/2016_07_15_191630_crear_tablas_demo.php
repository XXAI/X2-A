<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CrearTablasDemo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tabla_a', function (Blueprint $table) {
            $table->string('id'); 
            $table->string('servidor_id',4); 
            $table->integer('incremento');           
            $table->string('campo_1');
            $table->string('campo_2');
            $table->string('usuario_id');
            $table->timestamps();
            $table->softDeletes();

            $table->primary('id');
            $table->foreign('servidor_id')->references('id')->on('servidores')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onUpdate('cascade')->onDelete('cascade');
        });
        Schema::create('tabla_b', function (Blueprint $table) {
            $table->string('id');      
            $table->string('servidor_id',4);   
            $table->integer('incremento');        
            $table->string('tabla_a_id');
            $table->string('campo_1');
            $table->string('usuario_id');
            $table->timestamps();
            $table->softDeletes();

            $table->primary('id');
            $table->foreign('servidor_id')->references('id')->on('servidores')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('tabla_a_id')->references('id')->on('tabla_a')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tabla_b');
        Schema::drop('tabla_a');
    }
}
