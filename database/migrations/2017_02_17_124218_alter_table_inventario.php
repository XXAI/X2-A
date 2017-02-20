<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableInventario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       
        Schema::table('inventario', function ($table) {
            $table->integer('valor')->length(10)->unsigned();
            $table->integer('mes')->length(10)->unsigned();
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salida_detalles', function ($table) {
            $table->dropColumn('valor');
            $table->dropColumn('mes');
        });
    }
}
