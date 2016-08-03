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
    	$usuario = new Usuario();
        $usuario->id = 'administrador';
        $usuario->nombre = 'Administrador';
        $usuario->apellidos = 'del Sistema';
        $usuario->password = Hash::make('desabasto-2016');
        $usuario->save();

        $usuario->roles()->attach('ADMIN');
    }
}
