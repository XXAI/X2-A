<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ActualizarTablaActasAgregarCamposCanceladoYMotivosCancelacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->text('motivos_cancelacion')->after('fecha_validacion')->nullable();
            $table->timestamp('fecha_cancelado')->after('fecha_validacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->dropColumn('fecha_cancelado');
            $table->dropColumn('motivos_cancelacion');
        });
    }
}
