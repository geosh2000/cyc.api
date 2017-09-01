<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Config extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function addExternal_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $data = $this->put();
      $flag = true;

      //DB Asesores
      $asesores = array(
                        'Nombre'              => $data['nombre']." ".$data['apellido'],
                        'Nombre_Separado'     => $data['nombre'],
                        'Apellidos_Separado'  => $data['apellido'],
                        'Egreso'              => '2030-12-31',
                        'Usuario'             => str_replace(" ",".",strtolower($data['nombre_corto'])),
                        'Esquema'             => 8,
                        'plaza'               => ""
                      );
      $this->db->set( '`N Corto`', "'".$data['nombre_corto']."'", FALSE )
                ->set( '`Ingreso`', "CURDATE()", FALSE )
                ->set( '`id Departamento`', "47", FALSE );
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
                          'noAD'                => $data['validation']
                        );
        if($data['validation'] == 1){
          $this->db->set('hassed_pswd', "$2y$10$2He4.0svP7aCUsrLjjZxLuxOJ1dh1hRPF5IzXIWvnfLH603HH2yMC");
        }

        if($this->db->set($user)->insert('userDB')){
          $inserted_userDB=$this->db->insert_id();
        }else{
          $flag = false;
          $error['userDB']=$this->db->error();
        }
      }

      if($flag){
        $result =   array(
                          'status' => true,
                          'msg' => "Usuario cargado correctamente",
                        );
      }else{
        $result =   array(
                          'status' => false,
                          'msg' => $error,
                        );
      }

      return $result;

    });

    $this->response($result);

  }

}
