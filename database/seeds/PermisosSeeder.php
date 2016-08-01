<?php

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('permisos')->insert([
            [
                'id' => str_random(32),
                'descripcion' => "Ver usuarios",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Agregar usuarios",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Editar usuarios",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Eliminar usuarios",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Ver roles",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Agregar roles",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Editar roles",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Eliminar roles",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Ver permisos",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Agregar permisos",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Editar permisos",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => str_random(32),
                'descripcion' => "Eliminar permisos",
                'grupo' => "Administrador",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
