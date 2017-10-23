<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Horarios extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function horarios_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $fecha = $this->uri->segment(3);

      $this->preGetHorarios($fecha);

      if($horarios = $this->db->query("SELECT
          *, IF(Hora_int/2 + 0.25 BETWEEN int_j AND IF(int_je<5,int_je+24,int_je), 'j', 'x') as type
      FROM
          HoraGroup_Table a
              LEFT JOIN
          pyaMonitorBase b ON ADDTIME(Hora_time, '00:15:00') BETWEEN js AND IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je)
              OR ADDTIME(Hora_time, '00:15:00') BETWEEN x1s AND IF(x1e<'05:00:00',ADDTIME(x1e,'24:00:00'),x1e)
              OR ADDTIME(Hora_time, '00:15:00') BETWEEN x2s AND IF(x2e<'05:00:00',ADDTIME(x2e,'24:00:00'),x2e)
      ORDER BY Hora_int , Departamento , Nombre")){

          $result = array(
                          "status"    => true,
                          "msg"       => "Info Obtenida",
                          "data"      => $horarios->result_array()
                        );
          }else{
            $result = array(
                          "status"    => false,
                          "msg"       => $this->db->error(),
                          "data"      => null
                        );
    }


      return $result;

    });

    $this->response( $result );

  }

  public function preGetHorarios( $fecha ){
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS pyaAsesores");
    $this->db->query("CREATE TEMPORARY TABLE pyaAsesores SELECT
        a.*
    FROM
        dep_asesores a
    WHERE
        Fecha = '$fecha'
        AND dep !=29
            AND vacante IS NOT NULL");

    $this->db->query("DROP TEMPORARY TABLE IF EXISTS pyaHorarios");
    $this->db->query("CREATE TEMPORARY TABLE pyaHorarios SELECT
        id, asesor,
        CAST(horaCancun(Fecha,															`jornada start` 	) as TIME) as js,
        CAST(horaCancun(IF(`jornada end`	<'06:00:00',	ADDDATE(Fecha,1),Fecha),	`jornada end` 		) as TIME) as je,
        CAST(horaCancun(Fecha,															`comida start`		) as TIME) as cs,
        CAST(horaCancun(IF(`comida end`		<'06:00:00',	ADDDATE(Fecha,1),Fecha),	`comida end`		) as TIME) as ce,
        CAST(horaCancun(Fecha,															`extra1 start`		) as TIME) as x1s,
        CAST(horaCancun(IF(`extra1 end`		<'06:00:00',	ADDDATE(Fecha,1),Fecha),	`extra1 end`		) as TIME) as x1e,
        CAST(horaCancun(Fecha,															`extra2 start`		) as TIME) as x2s,
        CAST(horaCancun(IF(`extra2 end`		<'06:00:00',	ADDDATE(Fecha,1),Fecha),	`extra2 end`		) as TIME) as x2e
    FROM
        `Historial Programacion`
    WHERE
        Fecha = '$fecha'");

    $this->db->query("DROP TEMPORARY TABLE IF EXISTS pyaAusentismos");
    $this->db->query("CREATE TEMPORARY TABLE pyaAusentismos SELECT
        asesor, Ausentismo
    FROM
        Ausentismos a LEFT JOIN `Tipos Ausentismos` b ON a.tipo_ausentismo=b.id
    WHERE
        '$fecha' BETWEEN Inicio AND Fin");

    $this->db->query("DROP TEMPORARY TABLE IF EXISTS pyaMonitorBase");
    $this->db->query("CREATE TEMPORARY TABLE pyaMonitorBase SELECT
        NOMBREASESOR(a.asesor, 1) AS Nombre,
        Departamento,
        IF(Ausentismo IS NOT NULL,
            Ausentismo,
            IF(js = je, 'Descanso', NULL)) AS Excepcion,
        IF(js = je, NULL, CONCAT(DATE_FORMAT(js, '%H:%i'), ' - ', DATE_FORMAT(je, '%H:%i'))) AS Jornada,
        IF(x1s < js AND x1s!=x1e,
        CONCAT(DATE_FORMAT(x1s, '%H:%i'), ' - ', DATE_FORMAT(x1e, '%H:%i')),
            IF(x2s < js AND x2s!=x2e,
          CONCAT(DATE_FORMAT(x2s, '%H:%i'), ' - ', DATE_FORMAT(x2e, '%H:%i')), NULL)
        ) AS Pre,
        IF(x1s >= IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je) AND x1s!=x1e,
        CONCAT(DATE_FORMAT(x1s, '%H:%i'), ' - ', DATE_FORMAT(x1e, '%H:%i')),
            IF(x2s >= IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je) AND x2s!=x2e,
          CONCAT(DATE_FORMAT(x2s, '%H:%i'), ' - ', DATE_FORMAT(x2e, '%H:%i')), NULL)
        ) AS Post,
        IF(cs = ce, 'N/A', CONCAT(DATE_FORMAT(cs, '%H:%i'), ' - ', DATE_FORMAT(ce, '%H:%i'))) AS Comida,
        IF(js != je,js * 1 / 10000 + IF(MINUTE(js)>0,0.2,0),NULL) as int_j,
        IF(x1s < js AND x1s!=x1e,
        x1s * 1 / 10000 + IF(MINUTE(x1s)>0,0.2,0),
            IF(x2s < js AND x2s!=x2e,
          x2s * 1 / 10000 + IF(MINUTE(x2s)>0,0.2,0), NULL)
        ) AS int_pre,
        IF(x1s >= IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je) AND x1s!=x1e,
        x1s * 1 / 10000 + IF(MINUTE(x1s)>0,0.2,0),
            IF(x2s >= IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je) AND x2s!=x2e,
          x2s * 1 / 10000 + IF(MINUTE(x2s)>0,0.2,0), NULL)
        ) AS int_post,
      IF(js != je,je * 1 / 10000 + IF(MINUTE(je)>0,0.2,0),NULL) as int_je,
        IF(x1s < js AND x1s!=x1e,
        x1e * 1 / 10000 + IF(MINUTE(x1e)>0,0.2,0),
            IF(x2s < js AND x2s!=x2e,
          x2e * 1 / 10000 + IF(MINUTE(x2e)>0,0.2,0), NULL)
        ) AS int_pree,
        IF(x1s >= IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je) AND x1s!=x1e,
        x1e * 1 / 10000 + IF(MINUTE(x1e)>0,0.2,0),
            IF(x2s >= IF(je<'05:00:00',ADDTIME(je,'24:00:00'),je) AND x2s!=x2e,
          x2e * 1 / 10000 + IF(MINUTE(x2e)>0,0.2,0), NULL)
        ) AS int_poste,
      b.id,
        IF(js=je,NULL,js) as js,
        IF(js=je,NULL,je) as je,
        IF(cs=ce,NULL,cs) as cs,
        IF(cs=ce,NULL,ce) as ce,
        IF(x1s=x1e,NULL,x1s) as x1s,
        IF(x1s=x1e,NULL,x1e) as x1e,
        IF(x2s=x2e,NULL,x2s) as x2s,
        IF(x2s=x2e,NULL,x2e) as x2e
    FROM
        pyaAsesores a
            LEFT JOIN
        pyaAusentismos d ON a.asesor = d.asesor
            LEFT JOIN
        pyaHorarios b ON a.asesor = b.asesor
            LEFT JOIN
        PCRCs c ON a.dep = c.id
    WHERE
        dep NOT IN (1)
    HAVING Departamento NOT IN ('Otros')");
  }

}
