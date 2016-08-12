<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

use App\Models\Rol as Rol;
use App\Models\Usuario as Usuario;
use \Hash as Hash;

class UsuariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         DB::table('usuarios')->insert([
            [
                'id'=>'CSSSA019954',
                'password' => Hash::make('drjesusgilbertogomezmaza'),
                'nombre' => 'HOSPITAL CHIAPAS NOS UNE DR. JESUS GILBERTO GOMEZ MAZA',
                'jurisdiccion' => 'I',
                'municipio' => 'TUXTLA GUTIÉRREZ',
                'localidad' => 'TUXTLA GUTIÉRREZ',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'disur'
            ]
        ]);
    }
}