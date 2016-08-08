<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use App\Http\Requests;
use App\Models\Usuario;
use Illuminate\Support\Facades\Input;
use \Validator,\Hash, \Response;

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        try{
            //DB::enableQueryLog();
            $elementos_por_pagina = 50;
            $pagina = Input::get('pagina');
            if(!$pagina){
                $pagina = 1;
            }

            $query = Input::get('query');

            $recurso = Usuario::getModel();

            if($query){
                $recurso = $recurso->where(function($condition)use($query){
                    $condition->where('id','LIKE','%'.$query.'%')
                            ->orWhere('nombre','LIKE','%'.$query.'%')
                            ->orWhere('apellidos','LIKE','%'.$query.'%');
                });
            }

            $totales = $recurso->count();
            
            $recurso = $recurso->select('id','nombre','apellidos')
                                ->skip(($pagina-1)*$elementos_por_pagina)->take($elementos_por_pagina)
                                ->orderBy('created_at','desc')->get();

            //$queries = DB::getQueryLog();
            //$last_query = end($queries);
            //return Response::json(['query'=>$last_query,'data'=>$recurso,'totales'=>$totales,'con_errores'=>$con_errores,'clues_no_encontradas'=>$clues_no_encontradas],200);
            return Response::json(['data'=>$recurso,'totales'=>$totales],200);
        }catch(Exception $ex){
            return Response::json(['error'=>$e->getMessage()],500);
        }
        return Response::json([ 'data' => $usuario ],200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $mensajes = [
            
            'required'      => "required",
            'email'         => "email",
            'unique'        => "unique"
        ];

        $reglas = [
            'id'            => 'required|email|unique:usuarios',
            'password'      => 'required',
            'nombre'        => 'required',
            'apellidos'     => 'required'
        ];

        $inputs = Input::only('id','servidor_id','password','nombre', 'apellidos');
        $roles = Input::only('roles');

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $inputs['servidor_id'] = env("SERVIDOR_ID");
            $inputs['password'] = Hash::make($inputs['password']);
            $usuario = Usuario::create($inputs);

            $roles_usuario = [];
            foreach ($roles['roles'] as $rol) {
                $roles_usuario[] = ['rol_id'=>$rol['id']];
            }

            if($usuario){
                $usuario->roles()->sync($roles_usuario);
            }

            return Response::json([ 'data' => $usuario ],200);

        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(),'line'=>$e->getLine(),'asdf'=>$roles], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $usuario = Usuario::with('roles')->find($id);

            return Response::json([ 'data' => $usuario ],200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $mensajes = [
            'required'      => "required",
            'email'         => "email",
            'unique'        => "unique"
        ];

        $reglas = [
            'id'            => 'required|email',
            'nombre'        => 'required',
            'apellidos'     => 'required'
        ];

        $inputs = Input::only('id','password','nombre', 'apellidos');
        $roles = Input::only('roles');

        $v = Validator::make($inputs, $reglas, $mensajes);

        if ($v->fails()) {
            return Response::json(['error' => $v->errors()], HttpResponse::HTTP_CONFLICT);
        }

        try {
            $usuario = Usuario::find($id);

            if(!$usuario){
                throw new \Exception("Usuario no encontrado");
            }

            if($inputs['password']){
                $inputs['password'] = Hash::make($inputs['password']);
            }else{
                $inputs['password'] = $usuario->password;
            }
            $usuario->update($inputs);

            $roles_usuario = [];
            foreach ($roles['roles'] as $rol) {
                $roles_usuario[] = ['rol_id'=>$rol['id']];
            }
            
            if($usuario){
                $usuario->roles()->sync($roles_usuario);
            }

            return Response::json([ 'data' => $usuario,'asf'=>$ch],200);

        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage(),'line'=>$e->getLine()], HttpResponse::HTTP_CONFLICT);
        } 
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
