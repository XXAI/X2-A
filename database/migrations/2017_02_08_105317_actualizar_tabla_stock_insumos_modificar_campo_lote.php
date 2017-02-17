<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ActualizarTablaStockInsumosModificarCampoLote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_insumos', function (Blueprint $table) {
            $table->string('lote',200)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_insumos', function (Blueprint $table) {
            $table->string('lote',200)->nullable(false)->change();
        });
    }
}
