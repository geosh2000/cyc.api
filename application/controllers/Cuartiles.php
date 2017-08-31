<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Cuartiles extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function getCuartiles_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $inicio = $this->uri->segment(3);
      $fin = $this->uri->segment(4);
      $pcrc = (int)$this->uri->segment(5);

      $puestos = array(
                        4   => 47,
                        3   => 33,
                        35  => 16,
                        5   => 19,
                        6   => 49,
                        7   => 31,
                        8   => 55,
                        9   => 52
                      );

      $hc_puesto = $puestos[$pcrc];

      $result = $this->cuartilesIN( $inicio, $fin, $pcrc, $hc_puesto );

      return $result;

    });

    $result['data']=$this->quartilize($result['data'], array(
                                                      'MontoTotal'  => 'Asc',
                                                      'AHT'         => 'Desc',
                                                      'FC'          => 'Asc'
                                                    ));


    $this->response($result);

  }

  public function quartilize($array, $fields){


    $result = $array;

    $avgSession = array_sum(array_column($result, 'TotalSesion'))/count($result)*0.7;

    foreach($result as $index => $info){
      foreach($fields as $key => $type){
        if($result[$index]['TotalSesion'] >= $avgSession){
          $data[$key][$index] = $info[$key];
        }
      }
    }

    foreach($data as $key => $info2){

      if($fields[$key] == 'Desc'){
        asort($info2);
      }else{
        arsort($info2);
      }

      $keys = array_keys($info2);

      $length=count($info2);
      $qs = intval($length/4);
      $qsx = ($length % 4)*4;

      $x=1;
      for($i=1; $i<=4; $i++){

        if($i <= $qsx){
          $q[$i] = $qs+1;
        }else{
          $q[$i]=$qs;
        }

      }

      $i=1;
      $x=1;
      foreach($info2 as $key2 => $info3){

        if($x <= $qsx){
          $max = $qs+1;
        }else{
          $max = $qs;
        }

        if($i > $max){
          $x++;
          $i=1;
        }

        $result[$key2][$key.'Q']=$x;

        $i++;
      }
    }

    return $result;

  }

  public function cuartilesIN( $inicio, $fin, $pcrc, $hc_puesto ){
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryAsesores");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryLocs");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryLocsB");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryLocsC");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryLocsOK");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryCalls");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS queryPausas");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS querySesiones");
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS cuartilOK");

    $this->db->query("CREATE TEMPORARY TABLE queryAsesores (SELECT
                          Fecha,
                          b.id as vacante, hc_dep, hc_puesto, departamento as dep, puesto,
                          GETVACANTE(id, Fecha) as asesor, NombreAsesor(GETVACANTE(id, Fecha),2) as Nombre
                      FROM
                          Fechas a
                              LEFT JOIN
                          asesores_plazas b ON a.Fecha BETWEEN inicio AND fin
                      WHERE
                          hc_puesto = $hc_puesto
                              AND Fecha BETWEEN '$inicio' AND '$fin'
                              HAVING asesor IS NOT NULL)");

    $this->db->query("ALTER TABLE queryAsesores ADD PRIMARY KEY (`Fecha`,asesor)");

    $this->db->query("CREATE TEMPORARY TABLE queryLocs SELECT
                          b.*, IF(VentaMXN!=0,Localizador,NULL) as NewLoc
                      FROM
                          (SELECT DISTINCT
                              asesor
                          FROM
                              queryAsesores) a
                              RIGHT JOIN
                          (SELECT
                              *
                          FROM
                              t_Locs
                          WHERE
                              Fecha BETWEEN '$inicio' AND '$fin' AND asesor>0 AND asesor IS NOT NULL) b ON a.asesor = b.asesor");

    $this->db->query("ALTER TABLE queryLocs ADD PRIMARY KEY (Fecha, Hora, Localizador, VentaMXN)");

    $this->db->query("CREATE TEMPORARY TABLE queryLocsB SELECT * FROM queryLocs");
    $this->db->query("ALTER TABLE queryLocsB ADD PRIMARY KEY (Fecha, Hora, Localizador, VentaMXN)");

    $this->db->query("CREATE TEMPORARY TABLE queryLocsC SELECT * FROM queryLocs");
    $this->db->query("ALTER TABLE queryLocsC ADD PRIMARY KEY (Fecha, Hora, Localizador, VentaMXN)");

    $this->db->query("CREATE TEMPORARY TABLE queryLocsOK SELECT
                          a.*,
                          FinalBalance,
                          IF(FinalBalance > 0 AND NewLoc IS NOT NULL,
                              NewLoc,
                              NULL) AS NewLocPositive,
                          IF(periodCreated IS NOT NULL,
                              a.Localizador,
                              NULL) AS periodCreated
                      FROM
                          queryLocs a
                              LEFT JOIN
                          (SELECT
                              Localizador,
                                  SUM(VentaMXN + OtrosIngresosMXN + EgresosMXN) AS FinalBalance
                          FROM
                              queryLocsB
                          GROUP BY Localizador) b ON a.Localizador = b.Localizador
                              LEFT JOIN
                          (SELECT DISTINCT
                              NewLoc AS periodCreated
                          FROM
                              queryLocsC) c ON a.Localizador = c.periodCreated");

    $this->db->query("ALTER TABLE queryLocsOK ADD PRIMARY KEY (Fecha, Hora, Localizador, VentaMXN)");

    $this->db->query("CREATE TEMPORARY TABLE queryCalls
                      SELECT
                        a.*, Skill
                      FROM
                        t_Answered_Calls a
                          LEFT JOIN
                        Cola_Skill b ON a.Cola = b.Cola
                      WHERE
                        Fecha BETWEEN '$inicio' AND '$fin'
                      HAVING Skill = $pcrc");

    $this->db->query("ALTER TABLE queryCalls ADD PRIMARY KEY (Fecha, Hora, `Llamante`(15), `AsteriskId`(25))");

    $this->db->query("CREATE TEMPORARY TABLE queryPausas SELECT
                          a.asesor,
                          SUM(IF(Productiva=0,TIME_TO_SEC(Duracion),0)) as PNP,
                          SUM(IF(Productiva=1,TIME_TO_SEC(Duracion),0)) as PP
                      FROM
                          t_pausas a
                              LEFT JOIN
                          (SELECT
                              asesor
                          FROM
                              queryAsesores
                          GROUP BY Nombre) b ON a.asesor = b.asesor
                          LEFT JOIN Tipos_pausas c ON a.codigo=c.pausa_id
                      WHERE
                          Fecha BETWEEN '$inicio' AND '$fin'
                              AND b.asesor IS NOT NULL
                              AND Skill = $pcrc
                              GROUP BY a.asesor");

    $this->db->query("ALTER TABLE queryPausas  ADD PRIMARY KEY (asesor)");

    $this->db->query("CREATE TEMPORARY TABLE querySesiones SELECT
                          a.asesor, SUM(TIME_TO_SEC(Duracion)) AS Sesion
                      FROM
                          t_Sesiones a
                              LEFT JOIN
                          (SELECT
                              asesor
                          FROM
                              queryAsesores
                          GROUP BY Nombre) b ON a.asesor = b.asesor
                      WHERE
                          Fecha_in BETWEEN '$inicio' AND '$fin'
                              AND Skill = $pcrc
                              AND b.asesor IS NOT NULL
                      GROUP BY a.asesor");

    $this->db->query("ALTER TABLE querySesiones ADD PRIMARY KEY (asesor)");

    $this->db->query("CREATE TEMPORARY TABLE cuartilOK SELECT
                          a.Nombre,
                          Usuario as user,
                          NOMBREASESOR(GETIDASESOR(FINDSUPDAY(a.asesor, '$fin'), 2),
                                  2) AS Supervisor,
                          NewLocsPositive as LocsPeriodo,
                          NewLocsPositive / Total_Llamadas_Real AS FC,
                          Sesion as TotalSesion,
                          Total_Llamadas_Real,
                          (Sesion-PNP)/Sesion as Utilizacion,
                          PNP,
                          Sesion,
                          MontoPeriodo,
                          MontoNoPeriodo,
                          MontoTotal,
                          ShortCalls as ShortCalls_Absoluto,
                          ShortCalls/Total_Llamadas as ShortCalls_Relativo,
                          AHT,
                          PP as ACW_Absoluto,
                          PP/Sesion as ACW_Relativo
                      FROM
                          (SELECT
                              *
                          FROM
                              queryAsesores
                          GROUP BY Nombre) a
                              LEFT JOIN
                          (SELECT
                              asesor,
                                  COUNT(DISTINCT Localizador) AS Locs,
                                  COUNT(DISTINCT NewLoc) AS LocsNuevos,
                                  COUNT(DISTINCT NewLocPositive) AS NewLocsPositive,
                                  SUM(IF(periodCreated IS NOT NULL, VentaMXN + OtrosIngresosMXN + EgresosMXN, 0)) AS MontoPeriodo,
                                  SUM(VentaMXN + OtrosIngresosMXN + EgresosMXN) - SUM(IF(periodCreated IS NOT NULL, VentaMXN + OtrosIngresosMXN + EgresosMXN, 0)) AS MontoNoPeriodo,
                                  SUM(VentaMXN + OtrosIngresosMXN + EgresosMXN) AS MontoTotal
                          FROM
                              queryLocsOK
                          GROUP BY asesor) b ON a.asesor = b.asesor
                              LEFT JOIN
                          (SELECT
                              asesor,
                                  COUNT(ac_id) AS Total_Llamadas,
                                  COUNT(IF(Desconexion = 'Transferida'
                                      AND Duracion_Real <= '00:02:00', ac_id, NULL)) AS ShortCalls,
                                  COUNT(ac_id) - COUNT(IF(Desconexion = 'Transferida'
                                      AND Duracion_Real <= '00:02:00', ac_id, NULL)) AS Total_Llamadas_Real,
                                  AVG(TIME_TO_SEC(Duracion_Real)) AS AHT,
                                  SUM(TIME_TO_SEC(Duracion_Real)) AS TalkingTime
                          FROM
                              queryCalls
                          GROUP BY asesor
                          HAVING asesor IS NOT NULL) c ON a.asesor = c.asesor
                              LEFT JOIN
                          querySesiones d ON a.asesor = d.asesor
                              LEFT JOIN
                          queryPausas e ON a.asesor = e.asesor
                              LEFT JOIN
                          Asesores f ON a.asesor=f.id");

    if($query = $this->db->get('cuartilOK')){
      $result = array(
                      'status'  => true,
                      'data'    => $query->result_array(),
                      'msg'     => "Cuartiles cargados correctamente"
                    );
    }else{
      $result = array(
                      'status'  => true,
                      'data'    => null,
                      'msg'     => $this->db->error()
                    );
    }

    return $result;
  }

}
