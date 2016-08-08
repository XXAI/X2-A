<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {        
        $this->call(ServidoresSeeder::class);
        $this->call(PermisosSeeder::class);
        $this->call(CatalogosSeeder::class);
        $this->call(RolesSeeder::class);
        $this->call(UsuariosSeeder::class);
        $this->call(InsumosSeeder::class);
        $this->call(ConfiguracionSeeder::class);
    }
}