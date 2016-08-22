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
            ],
            [
                'id' => 'CSSSA017353',
                'password' => Hash::make('MEDICAMENTOS'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (COMITÁN)', 
                'jurisdiccion' => 'III',
                'municipio' => 'COMITÁN',
                'localidad' => 'COMITÁN',
                'tipologia' => 'COMITÁN',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id' => 'JURISMOTO10',
                'password' => Hash::make('NUTRICION'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (MOTOZINTLA)', 
                'jurisdiccion' => 'X',
                'municipio' => 'MOTOZINTLA',
                'localidad' => 'MOTOZINTLA',
                'tipologia' => 'MOTOZINTLA',
                'empresa_clave' => 'disur'
            ],
            [
                'id' => 'CSSSA017411',
                'password' => Hash::make('HOSPITALES'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (OCOSINGO)', 
                'jurisdiccion' => 'IX',
                'municipio' => 'OCOSINGO',
                'localidad' => 'OCOSINGO',
                'tipologia' => 'OCOSINGO',
                'empresa_clave' => 'disur'
            ],
            [
                'id' => 'CSSSA017382',
                'password' => Hash::make('BIENESTAR'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (PALENQUE)', 
                'jurisdiccion' => 'VI',
                'municipio' => 'PALENQUE',
                'localidad' => 'PALENQUE',
                'tipologia' => 'PALENQUE',
                'empresa_clave' => 'disur'
            ],
            [
                'id' => 'CSSSA017370',
                'password' => Hash::make('URGENCIAS'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (PICHUCALCO)', 
                'jurisdiccion' => 'V',
                'municipio' => 'PICHUCALCO',
                'localidad' => 'PICHUCALCO',
                'tipologia' => 'PICHUCALCO',
                'empresa_clave' => 'disur'
            ],
            [
                'id' => 'CSSSA017341',
                'password' => Hash::make('VACUNACION'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (SAN CRISTÓBAL DE LAS CASAS)', 
                'jurisdiccion' => 'II',
                'municipio' => 'SAN CRISTÓBAL DE LAS CASAS',
                'localidad' => 'SAN CRISTÓBAL DE LAS CASAS',
                'tipologia' => 'SAN CRISTÓBAL DE LAS CASAS',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id' => 'CSSSA017394',
                'password' => Hash::make('DETECCION'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (TAPACHULA)', 
                'jurisdiccion' => 'VII',
                'municipio' => 'TAPACHULA',
                'localidad' => 'TAPACHULA',
                'tipologia' => 'TAPACHULA',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id' => 'CSSSA017406',
                'password' => Hash::make('SANITARIO'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (TONALÁ)', 
                'jurisdiccion' => 'VIII',
                'municipio' => 'TONALÁ',
                'localidad' => 'TONALÁ',
                'tipologia' => 'TONALÁ',
                'empresa_clave' => 'disur'
            ],
            [
                'id' => 'CSSSA017336',
                'password' => Hash::make('RESISTENCIA'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (TUXTLA GUTIÉRREZ)', 
                'jurisdiccion' => 'I',
                'municipio' => 'TUXTLA GUTIÉRREZ',
                'localidad' => 'TUXTLA GUTIÉRREZ',
                'tipologia' => 'TUXTLA GUTIÉRREZ',
                'empresa_clave' => 'exfarma'
            ],
            [
                'id' => 'CSSSA017365',
                'password' => Hash::make('RADIOLOGIA'),
                'nombre' => 'ALMACÉN JURISDICCIONAL (VILLAFLORES)', 
                'jurisdiccion' => 'IV',
                'municipio' => 'VILLAFLORES',
                'localidad' => 'VILLAFLORES',
                'tipologia' => 'VILLAFLORES',
                'empresa_clave' => 'exfarma'
            ]
        ]);
    }
}