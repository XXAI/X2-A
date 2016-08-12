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
                
                $permisos = [
                                '2EA4582FC8A19',
                                '542C64323FC18',
                                'DFBB15D35AF9F',
                                'A99BCC6596321',
                                '37DC1A627A44E',
                                '71A3786CCEBD4',
                                'D7A9BAC54EF15',
                                'AFE7E7583A18C',
                                '2EF18B5F2E2D7',
                                'AC634E145647F',
                                'FF915DEC2F235'
                            ];

                $configuracion = Configuracion::find(1);

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