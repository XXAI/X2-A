<?php

namespace App\Http\Controllers;

use JWTAuth, JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Http\Request;
use \Hash;
use App\Models\Usuario;
use App\Models\Configuracion;
use App\Models\Rol;
use App\Models\Permiso;

class AutenticacionController extends Controller
{
    public function autenticar(Request $request)
    {
        
        // grab credentials from the request
        $credentials = $request->only('id', 'password');
        /*
        $usuarios = Usuario::where('jurisdiccion',10)->get();
        foreach ($usuarios as $usuario) {
            //$usuario->password = str_replace(['á','é','í','ó','ú',' ','.','(',')'],['a','e','i','o','u'], mb_strtolower($usuario->nombre,'UTF-8'));
            $usuario->password = Hash::make($usuario->password);
            $usuario->save();
        }
        */
        //Usuario::saveMany($usuarios);

        try {
            $usuario = Usuario::where('id',$credentials['id'])->first();

            if(!$usuario) {                
                return response()->json(['error' => 'invalid_credentials'], 401); 
            }

            if(Hash::check($credentials['password'], $usuario->password)){

                $claims = [
                    "sub" => 1,
                    "id" => $usuario->id
                ];
                
                $permisos_unidad = [
                                '37DC1A627A44E', //Editar Configuracion
                                '71A3786CCEBD4', //Ver Configuracion
                                '6F5427E97863A', //Ver solicitudes
                                '44F584F6B56DE', //Agregar solicitudes
                                '439536318C63C', //Editar solicitudes
                                '77D798C33FC46', //Exportar solicitudes
                                '1D25DB28AC412' //Eliminar solicitudes
                            ];
                $permisos_hospital = [
                                '37DC1A627A44E', //Editar Configuracion
                                '71A3786CCEBD4', //Ver Configuracion
                                'AFE7E7583A18C', //Ver actas
                                '2EF18B5F2E2D7', //Agregar actas
                                'AC634E145647F', //Editar actas
                                'F4CA88791CD94', //Exportar actas
                                'FF915DEC2F235' //Eliminar actas
                            ];
                $permisos_jurisdiccion = [
                                '37DC1A627A44E', //Editar Configuracion
                                '71A3786CCEBD4', //Ver Configuracion
                                'AFE7E7583A18C', //Ver actas
                                'F4CA88791CD94', //Exportar actas
                                '4E4D8E11F6E4A', //Ver Requisiciones
                                '2438B88CD5ECC', //Guardar Requisiciones
                                'FF915DEC2F235' //Eliminar actas
                            ];
                            
                $configuracion = Configuracion::where('clues',$usuario->id)->first();
                if(!$configuracion){
                    $configuracion = new Configuracion();
                }
                

                if($configuracion->clues != $usuario->id){
                    $empresas = [
                        'disur'=> 'DISTRIBUIDORA DISUR, S.A. DE C.V.',
                        'exfarma'=> 'EXFARMA, S.A. DE C.V.'
                    ];

                    $configuracion->clues                       = $usuario->id;
                    $configuracion->clues_nombre                = $usuario->nombre;
                    $configuracion->jurisdiccion                = $usuario->jurisdiccion;
                    $configuracion->municipio                   = $usuario->municipio;
                    $configuracion->localidad                   = $usuario->localidad;
                    $configuracion->tipologia                   = $usuario->tipologia;
                    $configuracion->empresa_clave               = $usuario->empresa_clave;
                    $configuracion->empresa_nombre              = $empresas[$usuario->empresa_clave];
                    $configuracion->director_unidad             = $usuario->director_unidad;
                    $configuracion->administrador               = $usuario->administrador;
                    $configuracion->encargado_almacen           = $usuario->encargado_almacen;
                    $configuracion->coordinador_comision_abasto = $usuario->coordinador_comision_abasto;
                    $configuracion->lugar_entrega               = $usuario->lugar_entrega;

                    if(!$configuracion->save()){
                        return response()->json(['error' => 'Error al iniciar sesión, por favor intente de nuevo.'], 401); 
                    }
                }
                //$roles = $usuario->roles()->lists('id');
                //$roles = Rol::whereIn('id',$roles)->with('permisos')->get();
                /*foreach ($roles as $rol) {
                    foreach ($rol->permisos as $permiso) {
                        $permisos[] = $permiso->id;
                    }
                }*/
                if($usuario->tipo_usuario == 1){
                    $permisos = $permisos_unidad;
                }elseif($usuario->tipo_usuario == 2){
                    $permisos = $permisos_jurisdiccion;
                }else{
                    $permisos = $permisos_hospital;
                }

                $payload = JWTFactory::make($claims);
                $token = JWTAuth::encode($payload);
                return response()->json(['token' => $token->get(), 'usuario'=>$usuario, 'permisos'=>$permisos], 200);
            } else {
                return response()->json(['error' => 'invalid_credentials'], 401); 
            }

        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
    }
    public function refreshToken(Request $request){
        try{
            $token =  JWTAuth::parseToken()->refresh();
            return response()->json(['token' => $token], 200);

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'token_expirado'], 401);  
        } catch (JWTException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function verificar(Request $request)
    {   
        try{
            $obj =  JWTAuth::parseToken()->getPayload();
            return $obj;
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'no_se_pudo_validar_token'], 500);
        }
        
    }
}