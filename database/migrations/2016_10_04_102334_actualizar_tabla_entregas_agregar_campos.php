<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ActualizarTablaEntregasAgregarCampos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->decimal('porcentaje_total',15,2)->after('nombre_entrega')->nullable();
            $table->decimal('porcentaje_claves',15,2)->after('nombre_entrega')->nullable();
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
            $table->dropColumn('porcentaje_total');
            $table->dropColumn('porcentaje_claves');
        });
    }
}
