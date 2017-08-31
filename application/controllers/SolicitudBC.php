<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class SolicitudBC extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->helper('mailing');
    $this->load->database();
    $this->load->model('Cliente_model');
  }

  public function baja_solicitud_put(  ){

      $data = $this->put();

      $token = JWT::validateToken( $_GET['token'], $_GET['usn'], 'cAlbertyCome' );

      if( !$token['status'] ){
          $result = array(
                        "status"    => false,
                        "msg"       => $token['msg'],
                        "folio"      => null
                      );
      }else{
        $result = $this->bajaSolicitud( $data, $_GET['usn'] );
      }

      jsonPrint( $result );

  }

  public function cambio_solicitud_put(  ){

      $data = $this->put();

      $token = JWT::validateToken( $_GET['token'], $_GET['usn'], 'cAlbertyCome' );

      if( !$token['status'] ){
          $result = array(
                        "status"    => false,
                        "msg"       => $token['msg'],
                        "folio"      => null
                      );
      }else{
        $result = $this->cambioSolicitud( $data );
      }

      jsonPrint( $result );

  }

  public function baja_set_put(  ){

      $data = $this->put();
      $flag = true;

      $token = JWT::validateToken( $_GET['token'], $_GET['usn'], 'cAlbertyCome' );

      if( !$token['status'] ){
          $result = array(
                        "status"    => false,
                        "msg"       => $token['msg'],
                        "folio"      => null
                      );
      }else{

        $createSol = $this->bajaSolicitud( $data, $_GET['usn'] );

        if($createSol['status']){
          $result = $this->bajaSet( $data );
        }else{
          $result = array(
                        "status"    => false,
                        "msg"       => $createSol['msg'],
                        "tabla"     => "Crear Solicitud"
                      );
        }
      }

      if($result['status']){
        $this->db->query("SELECT depAsesores(".$data['id'].",ADDDATE(CURDATE(),180))");
      }

      jsonPrint( $result );

  }

  public function bajaSet_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $vac_off = $this->getVacOff($data['id']);

      if($data['approbe']){
        $result = $this->bajaSet( $data );

        if($result['status']){
          $this->db->query("SELECT depAsesores(".$data['id'].",ADDDATE(CURDATE(),180))");
          $result = $this->changeSolicitudStatus($data, 1);

          $q = $this->db->get_where('rrhh_solicitudesCambioBaja', 'id = '.$data['solicitud']);
          $mailParams = $q->row_array();
          mailSolicitudBaja::mail( $this, $mailParams, $vac_off['vac_off'], 'set' );

          return $result;
        }else{
          return $result;
        }

      }else{
        $result = $this->changeSolicitudStatus($data, 2);

        return $result;
      }

    });

    jsonPrint( $result );

  }

  public function changeSolicitudStatus( $data, $status ){
    $update = array(
                      'status'         => $status,
                      'aprobado_por'   => $data['applier'],
                      'comentariosRRHH'=> $data['comentariosRRHH']);

    if($this->db->set('fecha_aprobacion = NOW()', false)->set($update)->where('id = '.$data['solicitud'])->update('rrhh_solicitudesCambioBaja')){
      $result = array(
                        'status'    => true,
                        'msg'       => 'Aprobación correctamente cargada. Cambios realizados');
    }else{
      $result = array(
                        'status'    => true,
                        'msg'       => $this->db->error());
    }

    return $result;
  }

  public function cxl_solicitud_delete(){

      $solicitud = $this->uri->segment(3);

      $token = JWT::validateToken( $_GET['token'], $_GET['usn'], 'cAlbertyCome' );

      if( !$token['status'] ){
          $result = array(
                        "status"    => false,
                        "msg"       => $token['msg']
                      );
      }else{

        $update = array(
                        "status" => 4 ,
                      );
        $this->db->set('aprobado_por', "GETIDASESOR('".str_replace("."," ",$_GET['usn'])."',2)", FALSE);
        $this->db->set('fecha_aprobacion', 'NOW()', FALSE);

        if($this->db->update('rrhh_solicitudesCambioBaja', $update, "id = ".$solicitud)){
          $result = array(
                        "status"    => true,
                        "msg"       => 'Solicitud Cancelada'
                      );
        }else{
          $result = array(
                        "status"    => false,
                        "msg"       => $this->db->error()
                      );
        }

      }

      jsonPrint( $result );

  }

  public function delete_solicitud_delete(){

      $solicitud = $this->uri->segment(3);

      $token = JWT::validateToken( $_GET['token'], $_GET['usn'], 'cAlbertyCome' );

      if( !$token['status'] ){
          $result = array(
                        "status"    => false,
                        "msg"       => $token['msg']
                      );
      }else{
        if($this->db->delete('rrhh_solicitudesCambioBaja', array('id' => $solicitud ))){
          $result = array(
                        "status"    => true,
                        "msg"       => 'Solicitud Eliminada'
                      );
        }else{
          $result = array(
                        "status"    => false,
                        "msg"       => $this->db->error()
                      );
        }

      }

      jsonPrint( $result );

  }

  function getVacOff( $asesor ){
    $query = $this->db->query("SELECT getLastVacante(".$asesor.",1) as Last");
    $result = $query->row();
    $lastMove = $result->Last;

    $query = $this->db->select("vacante, fecha_in")->get_where('asesores_movimiento_vacantes', array( "id" => $lastMove ));
    $result = $query->row();
    $vac_off = $result->vacante;
    $last_fecha_in = $result->fecha_in;

    return array("vac_off" => $vac_off, "last_fecha_in" => $last_fecha_in);
  }

  function setOut( $data, $usr ){

    $vo = $this->getVacOff( $data['id'] );

    $vac_off = $vo['vac_off'];
    $last_fecha_in = $vo['last_fecha_in'];

    if( (int)$data['reemplazable'] == 1 ){
      if(date('Y-m-d',strtotime($last_fecha_in))>=date('Y-m-d',strtotime($data['fechaLiberacion']))){

        $result = array(
                        'status'  => false,
                        'msg'     => "No es posible fijar la ficha final de una vacante que cuenta con cambios posteriores a la fecha de liberacion -> $last_fecha_in || ".$input['fecha_out']
                      );

        return $result;
      }
    }else{

      $this->notReplace($data, $usr, $vac_off);
    }

    if(date('Y-m-d', strtotime($last_fecha_in))>=date('Y-m-d', strtotime($data['fechaBaja']))){
      $result = array(
                      'status'  => false,
                      'msg'     => "No es posible asignar cambios con fechas anteriores al ultimo registrado"
                    );

      return $result;
    }

    if( (int)$data['reemplazable'] == 1 ){
      $fout = $data['fechaLiberacion'];
    }else{
      $fout = $data['fechaBaja'];
    }
    $insert = array(
                    'vacante' => $vac_off,
                    'fecha_out' => $fout,
                    'asesor_out' => $data['id']
                  );
    $this->db->set('userupdate', "GETIDASESOR('".str_replace("."," ",$usr)."',2)", FALSE);
    $this->db->set( $insert );

    if($this->db->insert('asesores_movimiento_vacantes', $insert)){
      $result = array(
                      'status'  => true,
                      'msg'     => "Movimiento de salida registrado correctamente",
                      'vac_off' => $vac_off
                    );
    }else{
      $result = array(
                      'status'  => false,
                      'msg'     => $this->db->error()
                    );
    }

    return $result;

  }

  public function setIn($data, $usr){

    $update = array(
                    'fecha_in'    => $data['fecha'],
                    'asesor_in'   => $data['asesor']
                  );
    if($this->db->set('userupdate', "GETIDASESOR('".str_replace("."," ",$usr)."',2)", FALSE)
                ->set($update)
                ->where("id=".$data['movimientoID'])
                ->update("asesores_movimiento_vacantes")){
          $result = array(
                          'status' => true,
                          'msg'    => 'Asesor correctamente ingresado a la vacante');
        }else{
          $result = array(
                          'status' => false,
                          'msg'    => $this->db->error());
        }

    return $result;
  }

  public function notReplace($data, $usr, $vac_off){
    $update = array(
                    'fin' => $data['fechaBaja'],
                    'deactivation_comments' => "Desactivación automática por baja o cambio no reemplazable",
                    'Activo' => 0,
                    'Status' => 2,
                  );
    $this->db->set('deactivated_by', "GETIDASESOR('".str_replace("."," ",$usr)."',2)", FALSE)
              ->set('date_deactivated', "NOW()", FALSE)
              ->set($update)
              ->where("id = ".$vac_off);

    $query = $this->db->update('asesores_plazas');
  }

  public function bajaSolicitud( $data, $usr  ){

        $vo = $this->getVacOff( $data['id'] );

        if($data['tipo'] == 'ask' ){
          $insert = array(
                      "asesor"        => $data['id'],
                      "tipo"          => 2,
                      "fecha"         => $data['fechaBaja'],
                      "reemplazable"  => (int)$data['reemplazable'],
                      "recontratable" => (int)$data['recontratable'],
                      "fecha_replace" => $data['fechaLiberacion'],
                      "comentarios"   => $data['comentarios'],
                      "vac_off"       => $vo['vac_off'],
                      "solicitado_por"=> $data['applier']
                    );
        }else{
          $insert = array(
                      "asesor"        => $data['id'],
                      "tipo"          => 2,
                      "fecha"         => $data['fechaBaja'],
                      "reemplazable"  => (int)$data['reemplazable'],
                      "recontratable" => (int)$data['recontratable'],
                      "fecha_replace" => $data['fechaLiberacion'],
                      "comentariosRRHH" => $data['comentarios'],
                      "vac_off"       => $vo['vac_off'],
                      "comentarios" => "Solicitud creada automáticamente por baja directa en RRHH",
                      "solicitado_por"=> $data['applier'],
                      "status"        => 1
                    );
          $this->db->set('aprobado_por', "GETIDASESOR('".str_replace("."," ",$usr)."',2)", FALSE);
          $this->db->set('fecha_aprobacion', 'NOW()', FALSE);
        }


        $this->db->set('fecha_solicitud', 'NOW()', FALSE);
        $this->db->set( $insert );

        if($this->db->insert('rrhh_solicitudesCambioBaja', $insert)){
          $result = array(
                        "status"    => true,
                        "msg"       => 'Solicitud Guardada Correctamente',
                        "folio"     => $this->db->insert_id()
                      );

          if($data['tipo'] == 'ask'){
            mailSolicitudBaja::mail( $this, $insert, $vo['vac_off'], 'ask' );
          }else{
            mailSolicitudBaja::mail( $this, $insert, $vo['vac_off'], 'ask' );
            mailSolicitudBaja::mail( $this, $insert, $vo['vac_off'], 'set' );
          }


        }else{
          $result = array(
                        "status"    => false,
                        "msg"       => $this->db->error(),
                        "folio"     => null
                      );
        }



      return $result;

  }

  public function bajaSet( $data ){

      // SET OUT
      $out = $this->setOut( $data, $_GET['usn'] );

      if($out['status']){

          $recontra = onDuplicateUpdate($this, array('asesor'=>$data['id'], 'recontratable' => (int)$data['recontratable']), 'asesores_recontratable');


          $query = $this->db->select('Egreso')->get_where('Asesores', array( "id" => $data['id'] ) );
          $row = $query->row();
          $old_egreso = $row->Egreso;

          $update = array(
                      "Egreso" => $data['fechaBaja']
                    );

          // UPDATE TABLA ASESORES
          if($this->db->update('Asesores', $update, "id = ".$data['id'])){


            // UPDATE HISTORIAL ASESORES
            $insert = array(
                      "asesor"  => $data['id'],
                      "campo"   => 'Egreso',
                      "old_val" => $old_egreso,
                      "new_val" => $data['fechaBaja']
                    );
            $this->db->set('changed_by', "GETIDASESOR('".str_replace("."," ",$_GET['usn'])."',2)", FALSE);
            $this->db->set( $insert );
            if($this->db->insert('historial_asesores', $insert)){
              $result = array(
                            "status"    => true,
                            "msg"       => "Baja registrada en todas las tablas correctamente",
                            "tabla"     => null
                          );

            }else{
              $result = array(
                            "status"    => false,
                            "msg"       => $this->db->error(),
                            "tabla"     => "historial_asesores"
                          );
            }
        }else{
          $result = array(
            "status"    => false,
            "msg"       => $this->db->error(),
            "tabla"     => "Asesores"
          );
        }

      }else{
        $result = array(
                      "status"    => false,
                      "msg"       => $out['msg'],
                      "tabla"     => "movimiento_asesores"
                    );$result = array(
                      "status"    => false,
                      "msg"       => $this->db->error(),
                      "tabla"     => "Asesores"
                    );
      }

      return $result;
  }


  public function cambioSolicitud( $data ){

        $insert = array(
                    "asesor"        => $data['asesor'],
                    "tipo"          => 1,
                    "fecha"         => $data['fechaCambio'],
                    "reemplazable"  => (int)$data['reemplazable'],
                    "fecha_replace" => $data['fechaLiberacion'],
                    "comentarios"   => $data['comentarios'],
                    "status"        => 0,
                    "solicitado_por"=> $data['applier'],
                    "vacante"       => $data['puesto']['vacante'],
                    "movimientoID"  => $data['puesto']['movimientoID']
                  );
        $this->db->set('fecha_solicitud', 'NOW()', FALSE);
        $this->db->set( $insert );

        if($this->db->insert('rrhh_solicitudesCambioBaja', $insert)){
          $vo = $this->getVacOff( $data['asesor'] );

          $result = array(
                        "status"    => true,
                        "msg"       => 'Solicitud Guardada Correctamente',
                        "vo"        => $vo,
                        "folio"     => $this->db->insert_id()
                      );

          mailSolicitudPuesto::mail( $this, $data, $vo['vac_off'], 'ask' );

        }else{
          $result = array(
                        "status"    => false,
                        "msg"       => $this->db->error(),
                        "folio"     => null
                      );
        }

        // $result = array( "status" => true, "query" => $this->db->get_compiled_insert('rrhh_solicitudesCambioBaja', $insert));

      return $result;

  }

  public function addAsesor_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();
      $flag = true;

      //DB Asesores
      $asesores = array(
                        'num_colaborador'     => $data['num_colaborador'],
                        'Nombre'              => $data['nombre']." ".$data['apellido'],
                        'Nombre_Separado'     => $data['nombre'],
                        'Apellidos_Separado'   => $data['apellido'],
                        'puesto'              => $data['puesto']['puestoid'],
                        'Activo'              => 1,
                        'on_training'         => 0,
                        'Ingreso'             => $data['fechaCambio'],
                        'Egreso'              => '2030-12-31',
                        'Usuario'             => str_replace(" ",".",strtolower($data['nombre_corto'])),
                        'Esquema'             => $data['puesto']['esquema'],
                        'plaza'               => $data['puesto']['vacante']
                      );
      $this->db->set( '`N Corto`', "'".$data['nombre_corto']."'", FALSE )
                ->set( '`id Departamento`', "'".$data['puesto']['depid']."'", FALSE );
      if($this->db->set($asesores)->insert('Asesores')){
          $inserted_asesor=$this->db->insert_id();
      }else{
        $flag = false;
        $error['Asesores']=$this->db->error();
      }


      // userDB
      if($flag){
        $user     = array(
                          'username'            => str_replace(" ",".",strtolower($data['nombre_corto'])),
                          'profile'             => $data['profile'],
                          'asesor_id'           => $inserted_asesor,
                          'active'              => 1,
                          'noAD'                => 0
                        );

        if($this->db->set($user)->insert('userDB')){
          $inserted_userDB=$this->db->insert_id();
        }else{
          $flag = false;
          $error['userDB']=$this->db->error();
        }
      }


      // Supervisores
      if($flag){
        $super    = array(
                          'Fecha'               => $data['fechaCambio'],
                          'asesor'              => $inserted_asesor,
                          'pcrc'                => 0
                        );

        if($this->db->set($super)->insert('Supervisores' )){
          $inserted_super=$this->db->insert_id();
        }else{
          $flag = false;
          $error['super']=$this->db->error();
        }
      }

      // Contrato
      $contrato = array(
                        'asesor'              => $inserted_asesor,
                        'tipo'                => $data['tipo_contrato'],
                        'inicio'              => $data['fechaCambio'],
                        'fin'                 => $data['fin_contrato']
                      );
      $this->db->set($contrato)->insert('asesores_contratos');

      //HISTORIAL
      $historial = array(
                        'asesor'              => $inserted_asesor,
                        'campo'               => 'Nuevo Asesor',
                        'old_val'             => '',
                        'new_val'             => '',
                        'changed_by'          => $data['applier']
                      );

      if($this->db->set($historial)->insert('historial_asesores')){
        $inserted_histo=$this->db->insert_id();
      }else{
        $flag = false;
        $error['historial']=$this->db->error();
      }

      // Movimiento vacantes
      $move     = array(
                        'fecha_in'            => $data['fechaCambio'],
                        'asesor_in'           => $inserted_asesor,
                        'userupdate'          => $data['applier']
                      );

      if($this->db->set($move)->where("id = ".$data['puesto']['movimientoID'])->update('asesores_movimiento_vacantes')){

      }else{
        $flag = false;
        $error['move']=$this->db->error();
      }

      //Factor Salario
      $salario  = array(
                        'asesor'              => $inserted_asesor,
                        'Fecha'               => $data['fechaCambio'],
                        'factor'              => $data['factor']
                      );

      $this->db->set($salario)->insert('asesores_fcSalario');

      // DepTable
      $this->db->query("SELECT depAsesores($inserted_asesor, ADDDATE(CURDATE(),365))");

      if($flag){
        $result = array(
                        'status'  => true,
                        'new_id'  => $inserted_asesor
                      );
      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $error
                      );
      }
      return $result;

    });

    jsonPrint( $result );

  }

  public function solicitudAjuste_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $this->db->set($data)
              ->set("fecha_solicitud", "NOW()", false )
              ->set(array('status' => 0))
              ->set(array('solicitudActiva' => 1));
      if($this->db->insert('rrhh_solicitudAjusteSalarial')){
        $result = array(
                      'status'  => true,
                      'folio'   => $this->db->insert_id()
                    );
      }else{
        $result = array(
                      'status'  => false,
                      'msg'   => $this->db->error()
                    );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  public function approbeSalario_put(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $q = $this->db->get_where('rrhh_solicitudAjusteSalarial', 'id = '.$data['id']);
      $solicitud = $q->row_array();

      $q = $this->db->query("SELECT salarioPuesto(".$data['puesto'].", CURDATE()) as salarioPuesto");
      $salario = $q->row_array();

      $factor = floatval($solicitud['nuevo_salario'])/floatval($salario['salarioPuesto']);

      if($data['accept']){
        $update   = array(
                          'status'            => 1,
                          'solicitudActiva'   => $data['id']."-1",
                          'aprobador'         => $data['applier']
                        );
        if($this->db->set($update)
                  ->set("fecha_aprobacion", "NOW()", false )
                  ->where("id = ".$data['id'])
                  ->update('rrhh_solicitudAjusteSalarial')){

                    $fcSalario = array(
                                      'asesor'  => $solicitud['asesor'],
                                      'Fecha'   => $solicitud['fecha_cambio'],
                                      'factor'  => $factor
                                      );
                    if($this->db->set($fcSalario)->insert('asesores_fcSalario')){
                      $result = array('status' => true, 'msg' => "Solicitud Aprobada");

                    }else{
                      $result = array('status' => false, 'msg' => $this->db->error());
                    }



                  }else{
                    $result = array('status' => false, 'msg' => $this->db->error());
                  }

      }else{
        $update   = array(
                          'status'            => 2,
                          'solicitudActiva'   => $data['id']."-4",
                          'aprobador'         => $data['applier']
                        );
        if($this->db->set($update)
                  ->set("fecha_aprobacion", "NOW()", false )
                  ->where("id = ".$data['id'])
                  ->update('rrhh_solicitudAjusteSalarial')){
                    $result = array('status' => true, 'msg' => "Solicitud Declinada");
                  }else{
                    $result = array('status' => false, 'msg' => $this->db->error());
                  }

      }

      return $result;

    });

    jsonPrint( $result );
  }

  public function cxlSalario_put(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $update   = array(
                        'status'            => 4,
                        'solicitudActiva'   => $data['id']."-4",
                        'aprobador'         => $data['applier']
                      );
      if($this->db->set($update)
                ->set("fecha_aprobacion", "NOW()", false )
                ->where("id = ".$data['id'])
                ->update('rrhh_solicitudAjusteSalarial')){

        $result = array('status' => true, 'msg' => "Solicitud Aprobada");

      }else{
        $result = array('status' => false, 'msg' => $this->db->error());
      }

      return $result;

    });

    jsonPrint( $result );
  }

  public function addContrato_put(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      if($this->db->set($data)->insert('asesores_contratos')){
        $result       = array(
                              'status'  => true,
                              'msg'     => "Contrato Agregado Correctamente"
                            );
      }else{
        $result       = array(
                              'status'  => false,
                              'msg'     => $this->db->error()
                            );
      }

      return $result;

    });

    jsonPrint( $result );
  }

  public function test_get(){

    echo "HOLA";

    $result = array('status' => 'hola como estas');

    echo "Adios";

    return $result;



  }

  public function approbeChange_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();
      $flag = true;

      $q = $this->db->get_where('rrhh_solicitudesCambioBaja', 'id = '.$data['solicitud']);
      $solicitud = $q->row_array();

      $q = $this->getVacOff($solicitud['asesor']);
      $vac_off = $q['vac_off'];

      $mailData   =   array(
                            'asesor'          => $solicitud['asesor'],
                            'fechaCambio'     => $solicitud['fecha'],
                            'reemplazable'    => $solicitud['reemplazable'],
                            'fechaLiberacion' => $solicitud['fecha_replace'],
                            'applier'         => $solicitud['solicitado_por'],
                            'approber'        => $data['applier'],
                            'action'          => $data['accion'],
                            'puesto'          => array('vacante' => $solicitud['vacante'])
                          );

      if($data['accion']){


        $dataOut = array(
                          'id'                => $solicitud['asesor'],
                          'reemplazable'      => $solicitud['reemplazable'],
                          'fechaLiberacion'   => $solicitud['fecha_replace'],
                          'fechaBaja'         => $solicitud['fecha'],
                        );

        $out = $this->setOut($dataOut, $_GET['usn']);

          if($out['status']){

            if($solicitud['reemplazable'] == 0){
              $replace = $this->notReplace($dataOut, $_GET['usn'], $vac_off);
            }

            $in = $this->setIn($solicitud, $_GET['usn']);
              if($in['status']){
                $result = array (
                                  'status'  => true,
                                  'msg'     => "Cambio aplicado correctamente"
                                );
              }else{
                $errors[] = $this->db->error();
                $flag = false;
              }


          }else{
            $errors[] = $out['msg'];
            $flag = false;
          }

        if($flag){

          // DepTable
          $this->db->query("SELECT depAsesores(".$solicitud['asesor'].", ADDDATE(CURDATE(),365))");
          //Update solicitud
          $this->db->set(array('status' => 1))
                    ->set('aprobado_por', "GETIDASESOR('".str_replace("."," ",$_GET['usn'])."',2)", FALSE)
                    ->set('fecha_aprobacion', 'NOW()', FALSE)
                    ->set('comentariosRRHH', $data['comentarios'])
                    ->where('id='.$data['solicitud'])
                    ->update('rrhh_solicitudesCambioBaja');

          // Mail
          mailSolicitudPuesto::mail( $this, $mailData, $vac_off, 'set' );

          return $result;
        }else{
          $result = array('status' => false, 'msg' => $errors);
          return $result;
        }

        return $result;
      }else{

        //Update solicitud
        $this->db->set(array('status' => 3))
                  ->set('aprobado_por', "GETIDASESOR('".str_replace("."," ",$_GET['usn'])."',2)", FALSE)
                  ->set('fecha_aprobacion', 'NOW()', FALSE)
                  ->where('id='.$data['solicitud'])
                  ->update('rrhh_solicitudesCambioBaja');

        mailSolicitudPuesto::mail( $this, $mailData, $vac_off, 'set' );

        return $result = array('status' => true, 'msg' => "Solicitud Declinada");

      }



    });

    jsonPrint( $result );

  }



}
