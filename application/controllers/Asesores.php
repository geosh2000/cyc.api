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
                          e.num_colaborador as foto,
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

  public function asesoresPDV_get(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $search = $_GET['term'];
      $date = $this->uri->segment(3);



      if($list = $this->db->query("SELECT
                            a.id, a.Nombre, `N Corto` as nCorto, Usuario, vacante, f.PDV, c.nombre as Puesto, e.Puesto as PuestoCOPC, g.Ciudad
                        FROM
                            Asesores a
                                LEFT JOIN
                            dep_asesores b ON a.id = b.asesor AND b.Fecha = '$date'
                                LEFT JOIN
                            hc_codigos_Puesto c ON b.hc_puesto = c.id
                                LEFT JOIN
                            PCRCs_puestos e ON b.puesto = e.id
                                LEFT JOIN
                            asesores_plazas h ON b.vacante = h.id
                                LEFT JOIN
                            PDVs f ON h.oficina = f.id
                                LEFT JOIN
                            db_municipios g ON f.ciudad = g.id
                        WHERE
                            (a.Nombre LIKE '%$search%' OR a.Usuario LIKE '%$search%' OR a.Usuario LIKE '%$search%') AND b.dep=29 AND Egreso>='$date'
                        ORDER BY Nombre")){

        $result = $list->result_array();
      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error(),
                        'wtf'     => "que?"
                      );
      }

      return $result;

    });

    $this->response( $result );
  }

  public function PDVSelect_get(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $search = $_GET['term'];

      if($list = $this->db->query("SELECT
                                      a.id, PDV, b.Ciudad
                                  FROM
                                      PDVs a
                                          LEFT JOIN
                                      db_municipios b ON a.ciudad = b.id
                                  WHERE
                                      (a.Activo=1 OR (a.Activo=0 AND a.PDV LIKE '%General%'))
                                          AND (a.PDV LIKE 'MX%' OR a.PDV LIKE '%General%')
                                          AND PDV NOT LIKE '%YYY%'
                                          AND (PDV LIKE '%$search%' OR b.Ciudad LIKE '%$search%')
                                  ORDER BY PDV")){

        $result = $list->result_array();
      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error(),
                        'wtf'     => "que?"
                      );
      }

      return $result;

    });

    $this->response( $result );
  }

  public function actualPlacesPDV_get(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $search = $this->uri->segment(4);
      $date = $this->uri->segment(3);

      if($list = $this->db->query("SELECT
                                      a.id AS Vacante,
                                      GETVACANTE(a.id, '$date') AS asesor,
                                      NOMBREASESOR(GETVACANTE(a.id, '$date'), 2) AS Nombre,
                                      b.Puesto, PDV
                                  FROM
                                      asesores_plazas a
                                          LEFT JOIN
                                      PCRCs_puestos b ON a.puesto = b.id
                                          LEFT JOIN
                                      PDVs c ON a.oficina=c.id
                                  WHERE
                                      oficina = $search")){

        $result = array(
                        'status'  => true,
                        'data'     => $list->result_array()
                      );
      }else{
        $result = array(
                        'status'  => false,
                        'msg'     => $this->db->error(),
                        'wtf'     => "que?"
                      );
      }

      return $result;

    });

    $this->response( $result );
  }

}
