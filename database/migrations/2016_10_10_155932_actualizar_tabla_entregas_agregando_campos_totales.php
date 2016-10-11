<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ActualizarTablaEntregasAgregandoCamposTotales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->renameColumn('porcentaje_total', 'porcentaje_cantidad');

            $table->integer('total_cantidad_recibida')->after('nombre_entrega')->nullable();
            $table->integer('total_cantidad_validada')->after('nombre_entrega')->nullable();
            $table->integer('total_claves_recibidas')->after('nombre_entrega')->nullable();
            $table->integer('total_claves_validadas')->after('nombre_entrega')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->renameColumn('porcentaje_cantidad', 'porcentaje_total');
            $table->dropColumn('total_cantidad_recibida');
            $table->dropColumn('total_cantidad_validada');
            $table->dropColumn('total_claves_recibidas');
            $table->dropColumn('total_claves_validadas');
        });
    }
}
