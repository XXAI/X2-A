<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableSalidas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('salidas', function ($table) {
            $table->renameColumn('tipo', 'tipo_salida_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('salidas', function ($table) {
            $table->renameColumn('tipo_salida_id', 'tipo');
        });
    }
}
