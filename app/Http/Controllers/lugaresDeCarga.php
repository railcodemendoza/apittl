<?php

namespace App\Http\Controllers;

use App\Mail\cargaAduana;
use App\Mail\cargaCargando;
use App\Mail\cargaDescarga;
use App\Mail\ubicacion;
use App\Models\pruebasModel;
use App\Models\statu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class lugaresDeCarga extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function coordenadas($patente)
    {

        $chek = new pruebasModel();
        $chek->contenido = 'Entro a la funcion coordenadas de /lugarDeCarga/{patente} con el Parametro:' . $patente;
        $chek->save();
        $coordenadas = DB::table('carga')
            ->select(
                'carga.id as idLoad',
                'cntr.id_cntr as IdTrip',
                'carga.load_place',
                'customer_load_place.lat',
                'customer_load_place.lon',
                'carga.custom_place',
                'aduanas.lat as latA',
                'aduanas.lon  as lonA',
                'carga.unload_place',
                'customer_unload_place.lat as latU',
                'customer_unload_place.lon  as lonU'
            )
            ->join('cntr', 'carga.booking', '=', 'cntr.booking')
            ->join('asign', 'cntr.cntr_number', '=', 'asign.cntr_number')
            ->join('aduanas', 'aduanas.description', '=', 'carga.custom_place')
            ->join('customer_load_place', 'customer_load_place.description', '=', 'carga.load_place')
            ->join('customer_unload_place', 'customer_unload_place.description', '=', 'carga.unload_place')
            ->where('asign.truck', '=', $patente)
            ->get();

        $chek = new pruebasModel();
        $chek->contenido = 'La api devolvio:' . $coordenadas;
        $chek->save();

        return $coordenadas;

        // SELECT * FROM `carga` INNER JOIN `cntr` INNER JOIN `asign` ON carga.booking = cntr.booking AND cntr.cntr_number = asign.cntr_number WHERE asign.truck = 'AE792WJ';
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function accionLugarDeCarga($idTrip)
    {

        $chek = new pruebasModel();
        $chek->contenido = 'Entro a la funcion accionLugarDeCarga de /accionLugarDeCarga/{idTrip}con el Parametro:' . $idTrip;
        $chek->save();

        $date = Carbon::now('-03:00');
        $qc = DB::table('cntr')->select('cntr_number', 'booking')->where('id_cntr', '=', $idTrip)->get();
        $cntr = $qc[0];

        // cual es el ultimo status.
        $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
        $description = $qd->status;

        

        if ($qd->main_status == 'CARGANDO') {

            $chek = new pruebasModel();
            $chek->contenido = 'Entro en manin status = CARGANDO';
            $chek->save();

            // si el status es igual al informado.     

            // Buscamos si se aviso o no al cliente. Si no se aviso. Avisamos.
            
            if ($qd->avisado == 0) {

                $chek = new pruebasModel();
                $chek->contenido = 'Entro en NO AVISADO';
                $chek->save();

                $insert = DB::table('status')->insert([
                    'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts del Lugar de Carga.',
                    'main_status' => 'CARGANDO',
                    'cntr_number' => $cntr->cntr_number,
                    'user_status' => 'AUTOMATICO',
                ]);


                $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
                $description = $qd->status;

                $chek = new pruebasModel();
                $chek->contenido = $description;
                $chek->save();

                $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
                $empresa = $qempresa[0]->empresa;

                $chek = new pruebasModel();
                $chek->contenido = $empresa;
                $chek->save();

                $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
                $mail = $qmail[0]->mail_logistic;

                $chek = new pruebasModel();
                $chek->contenido = $mail;
                $chek->save();

                $datos = [
                    'cntr' => $cntr->cntr_number,
                    'description' =>  $description,
                    'user' => $qd->user_status,
                    'empresa' => $empresa,
                    'booking' => $cntr->booking,
                    'date' => $date
                ];

                $mailEnviado = Mail::to($mail)->send(new cargaCargando($datos));

                $actualizarAvisado = statu::find($qd->id);
                
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                
                return 'ok, Actuliz?? Status - Envi?? mail.'  . $qd->avisado;
                
            } elseif ($qd->avisado != 0 && $qd->avisado <= 119) { // // Buscamos si se aviso o no al cliente. Si se aviso o no fue hace mucho actualizamos. 


                $chek = new pruebasModel();
                $chek->contenido = 'entro en avisado y menos de 119 veces reportado';
                $chek->save();

                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                /*   return 'ok, No actuliz?? Status - No envi?? mail.'  . $qd->avisado; */
            } elseif ($qd->avisado != 0 && $qd->avisado >= 120) {


                $chek = new pruebasModel();
                $chek->contenido = 'entro en avisado y mas de 120 veces reportado';
                $chek->save();

                DB::table('status')->insert([
                    'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts del Lugar de Carga.',
                    'main_status' => 'CARGANDO',
                    'cntr_number' => $cntr->cntr_number,
                    'user_status' => 'AUTOMATICO',
                ]);

                $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
                $description = $qd->status;

                $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
                $empresa = $qempresa[0]->empresa;

                $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
                $mail = $qmail[0]->mail_logistic;

                $datos = [
                    'cntr' => $cntr->cntr_number,
                    'description' =>  $description,
                    'user' => $qd->user_status,
                    'empresa' => $empresa,
                    'booking' => $cntr->booking,
                    'date' => $date
                ];


                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                /*  return 'ok, Actuliz?? Status - No envi?? mail.'  . $qd->avisado; */
            }
        } else {


            $chek = new pruebasModel();
            $chek->contenido = 'Entro en manin status != CARGANDO';
            $chek->save();

            DB::table('status')->insert([
                'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts del Lugar de Carga.',
                'main_status' => 'CARGANDO',
                'cntr_number' => $cntr->cntr_number,
                'user_status' => 'AUTOMATICO',
            ]);

            $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
            $description = $qd->status;

            $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
            $empresa = $qempresa[0]->empresa;

            $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
            $mail = $qmail[0]->mail_logistic;

            $datos = [
                'cntr' => $cntr->cntr_number,
                'description' =>  $description,
                'user' => $qd->user_status,
                'empresa' => $empresa,
                'booking' => $cntr->booking,
                'date' => $date
            ];

            Mail::to($mail)->send(new cargaCargando($datos));
            $actualizarAvisado = statu::find($qd->id);
            $avisadoMas = $actualizarAvisado->avisado + 1;
            $actualizarAvisado->avisado = $avisadoMas;
            $actualizarAvisado->save();
            /* return 'ok, Actuliz?? Status - Envi?? mail.'  . $qd->avisado; */
        }
    }
    public function accionLugarAduana($idTrip)
    {


        $chek = new pruebasModel();
        $chek->contenido = 'Entro a la funcion accionLugarDeCarga de /accionLugarAduana/{idTrip}con el Parametro:' . $idTrip;
        $chek->save();

        $date = Carbon::now('-03:00');
        $qc = DB::table('cntr')->select('cntr_number', 'booking')->where('id_cntr', '=', $idTrip)->get();
        $cntr = $qc[0];

        // cual es el ultimo status.
        $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
        $description = $qd->status;

        if ($qd->main_status == 'EN ADUANA') {

            $chek = new pruebasModel();
            $chek->contenido = 'entro en main status == EN aduana';
            $chek->save();

            // si el status es igual al informado.     

            // Buscamos si se aviso o no al cliente. Si no se aviso. Avisamos.

            if ($qd->avisado == 0) {

                $chek = new pruebasModel();
                $chek->contenido = 'entro en no avisado';
                $chek->save();

                DB::table('status')->insert([
                    'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts de la aduana Asignada.',
                    'main_status' => 'EN ADUANA',
                    'cntr_number' => $cntr->cntr_number,
                    'user_status' => 'AUTOMATICO',
                ]);

                $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
                $description = $qd->status;

                $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
                $empresa = $qempresa[0]->empresa;

                $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
                $mail = $qmail[0]->mail_logistic;

                $datos = [
                    'cntr' => $cntr->cntr_number,
                    'description' =>  $description,
                    'user' => $qd->user_status,
                    'empresa' => $empresa,
                    'booking' => $cntr->booking,
                    'date' => $date
                ];

                $chek = new pruebasModel();
                $chek->contenido = 'envia mail con '.$datos;
                $chek->save();

                Mail::to($mail)->send(new cargaAduana($datos));
                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                return 'ok, Actuliz?? Status - Envi?? mail.';

            } elseif ($qd->avisado != 0 && $qd->avisado <= 119) { // // Buscamos si se aviso o no al cliente. Si se aviso o no fue hace mucho actualizamos. 


                $chek = new pruebasModel();
                $chek->contenido = 'entro en avisado y menos de 119 veces reportado ';
                $chek->save();

                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                return 'ok, No actuliz?? Status - No envi?? mail.';

            } elseif ($qd->avisado != 0 && $qd->avisado >= 120) {

                $chek = new pruebasModel();
                $chek->contenido = 'entro en avisado y mas de 119 veces reportado ';
                $chek->save();

                DB::table('status')->insert([
                    'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts de la Aduana asignada.',
                    'main_status' => 'EN ADUANA',
                    'cntr_number' => $cntr->cntr_number,
                    'user_status' => 'AUTOMATICO',
                ]);

                $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
                $description = $qd->status;

                $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
                $empresa = $qempresa[0]->empresa;

                $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
                $mail = $qmail[0]->mail_logistic;

                $datos = [
                    'cntr' => $cntr->cntr_number,
                    'description' =>  $description,
                    'user' => $qd->user_status,
                    'empresa' => $empresa,
                    'booking' => $cntr->booking,
                    'date' => $date
                ];

                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                return 'ok, Actuliz?? Status - No envi?? mail.';
            }
        } else {


            $chek = new pruebasModel();
            $chek->contenido = 'Entro en manin status != ADUANA';
            $chek->save();


            DB::table('status')->insert([
                'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts de la Aduana asignada.',
                'main_status' => 'EN ADUANA',
                'cntr_number' => $cntr->cntr_number,
                'user_status' => 'AUTOMATICO',
            ]);

            $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
            $description = $qd->status;

            $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
            $empresa = $qempresa[0]->empresa;

            $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
            $mail = $qmail[0]->mail_logistic;

            $datos = [
                'cntr' => $cntr->cntr_number,
                'description' =>  $description,
                'user' => $qd->user_status,
                'empresa' => $empresa,
                'booking' => $cntr->booking,
                'date' => $date
            ];

            Mail::to($mail)->send(new cargaAduana($datos));
            $actualizarAvisado = statu::find($qd->id);
            $avisadoMas = $actualizarAvisado->avisado + 1;
            $actualizarAvisado->avisado = $avisadoMas;
            $actualizarAvisado->save();
            return 'ok, Actuliz?? Status - Envi?? mail.';
        }
    }
    public function accionLugarDescarga($idTrip)
    {
        $date = Carbon::now('-03:00');
        $qc = DB::table('cntr')->select('cntr_number', 'booking')->where('id_cntr', '=', $idTrip)->get();
        $cntr = $qc[0];

        // cual es el ultimo status.
        $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
        $description = $qd->status;

        if ($qd->main_status == 'STACKING') {

            // si el status es igual al informado.     

            // Buscamos si se aviso o no al cliente. Si no se aviso. Avisamos.

            if ($qd->avisado == 0) {

                DB::table('status')->insert([
                    'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts del Lugar de Descarga.',
                    'main_status' => 'STACKING',
                    'cntr_number' => $cntr->cntr_number,
                    'user_status' => 'AUTOMATICO',
                ]);

                $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
                $description = $qd->status;

                $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
                $empresa = $qempresa[0]->empresa;

                $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
                $mail = $qmail[0]->mail_logistic;

                $datos = [
                    'cntr' => $cntr->cntr_number,
                    'description' =>  $description,
                    'user' => $qd->user_status,
                    'empresa' => $empresa,
                    'booking' => $cntr->booking,
                    'date' => $date
                ];

                Mail::to($mail)->send(new cargaDescarga($datos));
                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                return 'ok, Actuliz?? Status - Envi?? mail.';
            } elseif ($qd->avisado != 0 && $qd->avisado <= 119) { // // Buscamos si se aviso o no al cliente. Si se aviso o no fue hace mucho actualizamos. 

                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                return 'ok, No actuliz?? Status - No envi?? mail.';
            } elseif ($qd->avisado != 0 && $qd->avisado >= 120) {


                DB::table('status')->insert([
                    'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts del Lugar de Descarga.',
                    'main_status' => 'STACKING',
                    'cntr_number' => $cntr->cntr_number,
                    'user_status' => 'AUTOMATICO',
                ]);

                $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
                $description = $qd->status;

                $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
                $empresa = $qempresa[0]->empresa;

                $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
                $mail = $qmail[0]->mail_logistic;

                $datos = [
                    'cntr' => $cntr->cntr_number,
                    'description' =>  $description,
                    'user' => $qd->user_status,
                    'empresa' => $empresa,
                    'booking' => $cntr->booking,
                    'date' => $date
                ];

                Mail::to($mail)->send(new cargaDescarga($datos));
                $actualizarAvisado = statu::find($qd->id);
                $avisadoMas = $actualizarAvisado->avisado + 1;
                $actualizarAvisado->avisado = $avisadoMas;
                $actualizarAvisado->save();
                return 'ok, Actuliz?? Status - No envi?? mail.';
            }
        } else {
            DB::table('status')->insert([
                'status' => '[AUTOMATICO] Cami??n se encuentra en un radio de 50 mts del Lugar de Descarga.',
                'main_status' => 'STACKING',
                'cntr_number' => $cntr->cntr_number,
                'user_status' => 'AUTOMATICO',
            ]);

            $qd  = DB::table('status')->where('cntr_number', '=', $cntr->cntr_number)->latest('id')->first();
            $description = $qd->status;

            $qempresa = DB::table('carga')->select('empresa')->where('booking', '=', $cntr->booking)->get();
            $empresa = $qempresa[0]->empresa;

            $qmail = DB::table('empresas')->where('razon_social', '=', $empresa)->select('mail_logistic')->get();
            $mail = $qmail[0]->mail_logistic;

            $datos = [
                'cntr' => $cntr->cntr_number,
                'description' =>  $description,
                'user' => $qd->user_status,
                'empresa' => $empresa,
                'booking' => $cntr->booking,
                'date' => $date
            ];

            Mail::to($mail)->send(new cargaDescarga($datos));
            $actualizarAvisado = statu::find($qd->id);
            $avisadoMas = $actualizarAvisado->avisado + 1;
            $actualizarAvisado->avisado = $avisadoMas;
            $actualizarAvisado->save();
            return 'ok, Actuliz?? Status - Envi?? mail.';
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
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
