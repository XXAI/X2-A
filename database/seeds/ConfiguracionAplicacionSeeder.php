<?php

use Illuminate\Database\Seeder;

class ConfiguracionAplicacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('configuracion_aplicacion')->insert([
			[
				'variable'			=> 'habilitar_captura',
				'valor'				=> '1'
			]
        ]);
    }
}
