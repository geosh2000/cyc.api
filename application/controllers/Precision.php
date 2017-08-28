<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Precision extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function getPrecision_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data['inicio'] = $this->uri->segment(3);
      $data['fin'] = $this->uri->segment(4);

      $this->detalleTelefonia( $data );
      $this->detalleBO( $data );

      $flag = true;

      if($tel = $this->db->query("SELECT Departamento, SUM(flag) / COUNT(*) AS prec
                                FROM precCalls GROUP BY skill HAVING prec!=0")){
                                  $telefonia = $tel->result_array();
                                }else{
                                  $flag=false;
                                  $error[]=$this->db->error();
                                }

      if($bo = $this->db->query("SELECT area as Departamento, SUM(flag) / COUNT(*) AS prec
                                FROM precBO GROUP BY Departamento HAVING prec!=0")){
                                  $boffice = $bo->result_array();
                                }else{
                                  $flag=false;
                                  $error[]=$this->db->error();
                                }

      if($flag){

        $datos = $telefonia;
        foreach($boffice as $index => $info){
          $datos[]=$info;
        }

        $result = array(
                        'status'  => true,
                        'data'    => $datos,
                        'msg'     => 'Resultados Obtenidos'
                      );
      }else{
        $result = array(
                        'status'  => false,
                        'data'    => null,
                        'msg'     => $error
                      );
      }

      return $result;

    });

    jsonPrint( $result );

  }

  public function detalleTelefonia( $data ){

    $this->db->query("DROP TEMPORARY TABLE IF EXISTS precCalls");
    $this->db->query("CREATE TEMPORARY TABLE precCalls SELECT
                          a.Fecha,
                              a.skill,
                              Departamento,
                              calls / volumen * 100 AS prec,
                              IF(calls / volumen >= 0.85
                                  AND calls / volumen <= 1.15, 1, 0) AS flag
                      FROM
                          (SELECT
                          *
                      FROM
                          forecast_volume
                      WHERE
                          Fecha BETWEEN '".$data['inicio']."' AND '".$data['fin']."') a
                      LEFT JOIN (SELECT
                          Fecha, Skill, COUNT(*) AS calls
                      FROM
                          t_Answered_Calls a
                      LEFT JOIN Cola_Skill b ON a.Cola = b.Cola
                      WHERE
                          Fecha BETWEEN '".$data['inicio']."' AND '".$data['fin']."'
                      GROUP BY Fecha , Skill) b ON a.Fecha = b.Fecha AND a.skill = b.Skill
                      LEFT JOIN
                      PCRCs c ON a.skill = c.id");

  }

  public function detalleBO( $data ){

    $this->db->query("DROP TEMPORARY TABLE IF EXISTS precBO");
    $this->db->query("CREATE TEMPORARY TABLE precBO SELECT
                                    CAST(a.fecha_recepcion AS DATE) AS Fecha,
                                        b.area, bo_skill as id,
                                        COUNT(*) AS regs,
                                        c.volumen,
                                        IF(COUNT(*) / volumen >= 0.85
                                            AND COUNT(*) / volumen <= 1.15, 1, 0) AS flag
                                FROM
                                    bo_tipificacion a
                                LEFT JOIN bo_areas b ON a.area = b.bo_area_id
                                LEFT JOIN forecast_volume c ON CAST(a.fecha_recepcion AS DATE) = c.Fecha
                                    AND b.bo_skill = c.skill
                                WHERE
                                    CAST(a.fecha_recepcion AS DATE) BETWEEN '".$data['inicio']."' AND '".$data['fin']."'
                                        AND a.`status` != 8
                                GROUP BY c.Fecha , a.area");

  }

}
