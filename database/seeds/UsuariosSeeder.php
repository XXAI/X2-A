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
        $csv = storage_path().'/app/seeds/usuarios-clues.csv';
        $query = sprintf("
            LOAD DATA local INFILE '%s' 
            INTO TABLE usuarios 
            FIELDS TERMINATED BY ',' 
            OPTIONALLY ENCLOSED BY '\"' 
            ESCAPED BY '\"' 
            LINES TERMINATED BY '\\n' 
            IGNORE 1 LINES
            (id,password,nombre,jurisdiccion,municipio,localidad,tipologia,empresa_clave,tipo_usuario)", addslashes($csv));
        DB::connection()->getpdo()->exec($query);
    }
}