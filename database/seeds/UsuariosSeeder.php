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
                'id'=>'CSSSA007540',
                'password' => Hash::make('hospitalregionaldrrafaelpascasiogamboatuxtla'),
                'nombre' => 'HOSPITAL REGIONAL DR. RAFAEL PASCASIO GAMBOA TUXTLA',
                'jurisdiccion' => 'I',
                'municipio' => 'TUXTLA GUTIÉRREZ',
                'localidad' => 'TUXTLA GUTIÉRREZ',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA019954',
                'password' => Hash::make('drjesusgilbertogomezmaza'),
                'nombre' => 'HOSPITAL CHIAPAS NOS UNE DR. JESUS GILBERTO GOMEZ MAZA',
                'jurisdiccion' => 'I',
                'municipio' => 'TUXTLA GUTIÉRREZ',
                'localidad' => 'TUXTLA GUTIÉRREZ',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'disur'
            ],
            [
                'id'=>'CSSSA009162',
                'password' => Hash::make('unidaddeatencionalasaludmentalsanagustin'),
                'nombre' => 'UNIDAD DE ATENCIÓN A LA SALUD MENTAL SAN AGUSTÍN',
                'jurisdiccion' => 'I',
                'municipio' => 'TUXTLA GUTIÉRREZ',
                'localidad' => 'EL JOBO',
                'tipologia' => 'HOSPITAL PSIQUIÁTRICO',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA005773',
                'password' => Hash::make('hospitaldelamujersancristobaldelascasas'),
                'nombre' => 'HOSPITAL DE LA MUJER SAN CRISTÓBAL DE LAS CASAS',
                'jurisdiccion' => 'II',
                'municipio' => 'SAN CRISTÓBAL DE LAS CASAS',
                'localidad' => 'SAN CRISTÓBAL DE LAS CASAS',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA018764',
                'password' => Hash::make('hospitaldelasculturassancristobaldelascasas'),
                'nombre' => 'HOSPITAL DE LAS CULTURAS SAN CRISTOBAL DE LAS CASAS',
                'jurisdiccion' => 'II',
                'municipio' => 'SAN CRISTÓBAL DE LAS CASAS',
                'localidad' => 'SAN CRISTÓBAL DE LAS CASAS',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA001030',
                'password' => Hash::make('hospitalgeneralmariaignaciagandulfocomitan'),
                'nombre' => 'HOSPITAL GENERAL MARÍA IGNACIA GANDULFO COMITAN',
                'jurisdiccion' => 'III',
                'municipio' => 'COMITÁN DE DOMÍNGUEZ',
                'localidad' => 'COMITÁN DE DOMÍNGUEZ',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA018776',
                'password' => Hash::make('hospitaldelamujercomitan'),
                'nombre' => 'HOSPITAL DE LA MUJER COMITÁN',
                'jurisdiccion' => 'III',
                'municipio' => 'COMITÁN DE DOMÍNGUEZ',
                'localidad' => 'COMITÁN DE DOMÍNGUEZ',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA018875',
                'password' => Hash::make('hospitalgeneralbicentenariovillaflores'),
                'nombre' => 'HOSPITAL GENERAL BICENTENARIO VILLAFLORES',
                'jurisdiccion' => 'IV',
                'municipio' => 'VILLAFLORES',
                'localidad' => 'VILLAFLORES',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id'=>'CSSSA004945',
                'password' => Hash::make('hospitalgeneralpichucalco'),
                'nombre' => 'HOSPITAL GENERAL PICHUCALCO',
                'jurisdiccion' => 'V',
                'municipio' => 'PICHUCALCO',
                'localidad' => 'PICHUCALCO',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'disur'
            ],
            [
                'id'=>'CSSSA008264',
                'password' => Hash::make('hospitalgeneralyajalon'),
                'nombre' => 'HOSPITAL GENERAL YAJALÓN',
                'jurisdiccion' => 'VI',
                'municipio' => 'YAJALÓN',
                'localidad' => 'YAJALÓN',
                'tipologia' => 'HOSPITAL GENERAL',
                'empresa_clave' => 'disur'
            ]
        ]);
    }
}