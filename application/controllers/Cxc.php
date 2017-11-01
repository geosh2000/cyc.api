<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Cxc extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function addcxc_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $data['firmado']=(int)$data['firmado'];
      if($this->db->insert('asesores_cxc', $data)){
        $result = array(
                      "status"    => true,
                      "msg"       => "Cxc guardado correctamente",
                      "folio"      => $this->db->insert_id()
                    );
      }else{
        $result = array(
                      "status"    => false,
                      "msg"       => $this->db->error(),
                      "folio"      => null
                    );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  public function obtener_saldos_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $asesor = $this->uri->segment(3);

      $this->db->select("a.id, pago as quincena, a.monto, asesor, localizador")
              ->from('rrhh_pagoCxC as a')
              ->join('asesores_cxc as b', 'a.cxc=b.id', 'LEFT')
              ->join('rrhh_calendarioNomina as c', 'a.quincena = c.id', 'LEFT')
              ->where(array('activo' => 1, 'cobrado' => 0, 'asesor' => $asesor))
              ->order_by('pago');

      if($query = $this->db->get()){

        foreach($query->result() as $row){
            $data[]=$row;
        }

        $result = array(
                      "status"    => true,
                      "msg"       => "Información obtenida",
                      "rows"      => $query->num_rows()
                    );

        if($query->num_rows()>0){
          $result['data'] = $data;
        }else{
          $result['data'] = null;
        }

      }else{
        $result = array(
                      "status"    => false,
                      "msg"       => $this->db->error(),
                      "rows"      => 0,
                      "data"      => null
                    );
      }

      return $result;

    });

    jsonPrint($result);

  }

  public function saldar_cxc_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      foreach($data as $item => $flag){

        if( $item == 'saldado_por' ){
          $applier = $flag;
        }else{
          if($flag == 'true'){
            $where[] = $item;
          }
        }

      }

      if(isset($where)){
        if($this->db->where_in('id', $where)->update('rrhh_pagoCxC', array( 'cobrado' => 1, "saldado_por" => $applier ))){
          $result = array(
                        "status"    => true,
                        "msg"       => "Items Saldados Correctamente",
                        "saldados"  => $where
                      );
        }else{
          $result = array(
                        "status"    => false,
                        "msg"       => $this->db->error()
                      );
        }
      }else{
        $result = array(
                      "status"    => true,
                      "msg"       => "No existen items seleccionados para saldar"
                    );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  function getToApply_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $asesor = $this->uri->segment(3);
      $filter = $this->uri->segment(4);

      if($asesor == 0){
        $where = array('a.status' => 1 );
        switch ($filter) {
          case 'pdv':
            $where['dep'] = 29;
            break;
          case 'cc':
            $where['dep !='] = 29;
            break;
        }
      }else{
        $where = array('a.asesor' => $asesor, 'status !=' => 2);
      }

      $this->db->select('a.*, nombreAsesor(a.created_by, 1) as creador, nombreAsesor(a.asesor, 2) as nombreAsesor')
                ->from('asesores_cxc a')
                ->join('dep_asesores b', 'a.asesor=b.asesor AND CURDATE()=b.Fecha', 'LEFT')
                ->where($where);
      if($query = $this->db->get()){

        foreach($query->result() as $row){
          $data[] = $row;
        }

        $result = array(
                        'status'  => true,
                        'msg'     => 'Información obtenida correctamente',
                        'rows'    => $query->num_rows()
                        );

        if($query->num_rows()>0){
          $result['data']=$data;
        }else{
          $result['data']=null;
        }

      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error()
                        );
      }

      return $result;

    });

    $this->response( $result );

  }

  public function getCalendario_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $limit = $this->uri->segment(3);

      if( !isset($limit) ){
        $limit = 5;
      }

      $this->db->where('CURDATE() <', 'fin', FALSE)
                ->order_by('inicio')
                ->limit($limit);
      if($query = $this->db->get('rrhh_calendarioNomina')){

        foreach($query->result() as $row){
          $data[] = $row;
        }

        $result = array(
                        'status'  => true,
                        'msg'     => 'Información obtenida correctamente',
                        'rows'    => $query->num_rows()
                        );

        if($query->num_rows()>0){
          $result['data']=$data;
        }else{
          $result['data']=null;
        }

      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error()
                        );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  public function applyCxc_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();
      $quincena = $data['inicio'];

      for($i=1; $i<=$data['quincenas']; $i++){

        $inserts[] = array(
                        'cxc'        => $data['id'],
                        'n_pago'    => $i,
                        'quincena'  => $quincena,
                        'monto'     => $data['monto']/$data['quincenas'],
                        'activo'    => 1,
                        'cobrado'   => 0,
                        'created_by'=> $data['created_by']
                      );
        $quincena++;
      }

      if($query = $this->db->insert_batch('rrhh_pagoCxC',$inserts)){

        $this->db->update('asesores_cxc', array('status' => 2), "id = ".$data['id']);

        $result = array(
                        'status'  => true,
                        'msg'     => 'Cxc aplicado correctamente'
                        );

      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error()
                        );
      }

      return $result;

    });

    jsonPrint( $result );


  }

  public function getAllCxc_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $inicio = $this->uri->segment(3);
      $fin    = $this->uri->segment(4);

      if($query = $this->db->select("a.*, NOMBREASESOR(asesor,2) as NombreAsesor, NOMBREASESOR(created_by,1) as NombreCreador, NOMBREASESOR(updated_by,1) as NombreAplicador")
                        ->select("CASE WHEN status = 0 THEN 'Pendiente de Envío' WHEN status = 1 THEN 'Esperando RRHH' WHEN status = 2 THEN 'Aplicado' END as statusOK")
                        ->select("CASE WHEN tipo = 0 THEN 'Responsabilidad' WHEN tipo = 1 THEN 'Colaborador' END as tipoOK")
                        ->get_where('asesores_cxc a',"fecha_aplicacion BETWEEN '$inicio' AND '$fin'")){

        $result = array(
                        'status'  => true,
                        'rows'    => $query->num_rows(),
                        'data'    => $query->result_array()
                        );

      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error()
                        );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  public function edit_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $update = array(
                      'comments'    => $data['comments'],
                      'firmado'     => (int)$data['firmado'],
                      'monto'       => $data['monto'],
                      'updated_by'  => $data['applier']
                    );

      if($this->db->set($update)->where(array('id' => $data['id'] ))->update('asesores_cxc')){
        $result = array(
                          'status'  => true,
                          'msg'     => "Registro Actualizado"
                          );
      }else{
        $result = array(
                          'status'  => true,
                          'msg'     => $this->db->error()
                          );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  public function statusChange_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $update = array('status' => 1, 'updated_by' => $data['applier'] );

      if($this->db->set($update)->where(array('id' => $data['id'] ))->update('asesores_cxc')){
        $result = array(
                          'status'  => true,
                          'msg'     => "Registro Actualizado"
                          );
      }else{
        $result = array(
                          'status'  => true,
                          'msg'     => $this->db->error()
                          );
      }

      return $result;

    });

    jsonPrint( $result );

  }

}
