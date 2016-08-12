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
            	'clues' => '----------',
				'clues_nombre' => '-------------'
            ]
        ]);
    }
}
