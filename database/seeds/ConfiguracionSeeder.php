<?php

use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('configuracion')->insert([
            [
            	'clues' => 'CLUES00001',
				'clues_nombre' => 'Nombre de la Unidad Médica',
				'empresa_clave' => 'clave',
				'empresa_nombre' => 'Seleccionar empresa',
				'director_unidad' => 'Nombre del Director de la unidad',
				'solicitante_nombre' => null,
				'solicitante_cargo' => null
            ]
        ]);
    }
}
