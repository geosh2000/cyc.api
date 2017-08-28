<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Asesores extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function listAsesores_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $dep = $this->uri->segment(3);

      $this->db->select("e.id,
                          e.num_colaborador,
                          e.Nombre,
                          IF(a.dep = 29,
                              FINDSUPPDVDAY(b.oficina, CURDATE(), 2),
                              FINDSUPERDAY(DAY(CURDATE()),
                                      MONTH(CURDATE()),
                                      YEAR(CURDATE()),
                                      e.id)) AS Jefe_Directo,
                          e.Ingreso,
                          IF(Egreso >= '2030-01-01', NULL, Egreso) AS Egreso,
                          IF(CURDATE() <= Egreso,
                              'Activo',
                              'Inactivo') AS Status,
                          CONCAT(e.Usuario, '@pricetravel.com') AS Correo,
                          g.nombre AS Puesto,
                          f.Puesto AS Alias_Puesto,
                          c.PDV AS Oficina,
                          d.Ciudad,
                          e.Fecha_Nacimiento,
                          e.RFC,
                          e.Telefono1,
                          e.Telefono2,
                          e.correo_personal,
                          e.Vigencia_Pasaporte,
                          e.Vigencia_Visa", FALSE)
                ->from("dep_asesores a")
                ->join("asesores_plazas b",   'a.vacante    = b.id', 'LEFT')
                ->join("PDVs c",              'b.oficina    = c.id', 'LEFT')
                ->join("db_municipios d",     'b.ciudad     = d.id', 'LEFT')
                ->join("Asesores e",          'a.asesor     = e.id', 'LEFT')
                ->join("PCRCs_puestos f",     'b.puesto     = f.id', 'LEFT')
                ->join("hc_codigos_Puesto g", 'b.hc_puesto  = g.id', 'LEFT')
                ->where(array( 'Fecha = CURDATE()' => NULL, 'a.hc_dep' => $dep))
                ->order_by('Nombre');

      if($query = $this->db->get()){
        $result = array(
                        'status'  => true,
                        'data'    => array(
                                            'data'      => $query->result_array(),
                                            'headers'   => $query->field_data()
                                          )
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

}
