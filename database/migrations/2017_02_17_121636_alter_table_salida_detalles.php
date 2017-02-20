<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableSalidaDetalles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('salida_detalles', function ($table) {
            $table->renameColumn('id_salida', 'salida_id');
            $table->renameColumn('cantidad', 'cantidad_solicitada');
            $table->renameColumn('id_stock_insumo', 'insumo_id');
            $table->integer('cantidad_surtido')->length(10)->unsigned();
            $table->integer('cantidad_no_surtido')->length(10)->unsigned();
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
            $table->renameColumn('salida_id','id_salida');
            $table->renameColumn( 'cantidad_solicitada', 'cantidad');
            $table->renameColumn('insumo_id', 'id_stock_insumo');
            $table->dropColumn('cantidad_surtido');
            $table->dropColumn('cantidad_no_surtido');
        });
        
    }
}
