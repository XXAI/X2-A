<?php

use Illuminate\Database\Seeder;

class TipoSalidaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $total = DB::table('tipo_salida')->count();
        if($total == 0){
            DB::table('tipo_salida')->insert([
                [
                    'descripcion' => "CUADRO DE DISTRIBUCIÓN",
                    'created_at' => Null,
                    'updated_at' => Null,
                ],
                [
                    'descripcion' => "CARRITO ROJO",
                    'created_at' => Null,
                    'updated_at' => Null,
                ],
                [
                    'descripcion' => "COLECTIVO",
                    'created_at' => Null,
                    'updated_at' => Null,
                ],
                [
                    'descripcion' => "RECETA MÉDICA",
                    'created_at' => Null,
                    'updated_at' => Null,
                ]
            ]);
        }
    }
}
