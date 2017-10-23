<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Asistencia extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();

  }

  public function pya_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $dep=$this->uri->segment(3);
      $inicio=$this->uri->segment(4);
      $fin=$this->uri->segment(5);

      $this->db->query("SET @inicio = CAST('$inicio' as DATE)");
      $this->db->query("SET @fin = CAST('$fin' as DATE)");
      $this->db->query("SET @dep = $dep");

      $this->db->query("DROP TEMPORARY TABLE IF EXISTS asistenciaAsesores");
      $this->db->query("CREATE TEMPORARY TABLE asistenciaAsesores SELECT
          a.*,
          IF(vacante IS NOT NULL, NOMBREDEP(dep), NULL) as Departamento,
          IF(vacante IS NOT NULL, NOMBREPUESTO(a.puesto), NULL) as PuestoName,
          esquema
      FROM
          dep_asesores a LEFT JOIN Asesores b ON a.asesor=b.id
      WHERE
          Fecha BETWEEN @inicio AND @fin AND vacante IS NOT NULL
              AND IF(@dep=0, dep != 29, dep = @dep)");
      $this->db->query("ALTER TABLE asistenciaAsesores ADD PRIMARY KEY (`Fecha`, `asesor`)");

      $this->db->query("DROP TEMPORARY TABLE IF EXISTS log_asesor");
      $this->db->query("CREATE TEMPORARY TABLE log_asesor (SELECT
          a.*,
      	b.id as h_id,
          `jornada start`,
          `jornada end`,
          `comida start`,
          `comida end`,
          `extra1 start`,
          `extra1 end`,
          `extra2 start`,
          `extra2 end`,
          LOGASESOR(a.Fecha, a.asesor, 'in') AS login,
          LOGASESOR(a.Fecha, a.asesor, 'out') AS logout
      FROM
          asistenciaAsesores a
              LEFT JOIN
          `Historial Programacion` b ON a.asesor = b.asesor AND a.Fecha = b.Fecha)");

      $this->db->query("DROP TEMPORARY TABLE IF EXISTS xtraTime");
      $this->db->query("CREATE TEMPORARY TABLE xtraTime SELECT
      	a.Fecha, a.asesor,
      	IF(login IS NOT NULL,
          IF(realTime(login)<=realTime(j_inicio) AND realTime(logout)>realTime(j_inicio),
            j_inicio,
            login
          ),
          NULL) as j_login,
        IF(logout IS NOT NULL,
          IF(realTime(logout)>=realTime(j_end) AND realTime(login)<=(j_end),
            j_end,
            logout),
          NULL) as j_logout,

      	IF(x1_inicio!=x1_end,IF(login<realTime(x1_end) AND realTime(logout)>=x1_inicio,IF(login<realTime(x1_inicio),x1_inicio,login),NULL),NULL) as x1_login,

      	IF(x1_inicio!=x1_end,
      		IF(login<realTime(x1_end) AND realTime(logout)>=x1_inicio,
      			IF(realTime(logout)>realTime(x1_end),
      				x1_end,
      				IF(logout>='24:00:00',ADDTIME(logout,'-24:00:00'),logout)),
      			NULL),
      		NULL) as x1_logout,

      	IF(x2_login!=x2_end,IF(login<realTime(x2_end) AND realTime(logout)>=x2_login,IF(login<realTime(x2_login),x2_login,login),NULL),NULL) as x2_login,
      	IF(x2_login!=x2_end,IF(login<realTime(x2_end) AND realTime(logout)>=x2_login,IF(realTime(logout)>realTime(x2_end),x2_end,IF(logout>='24:00:00',ADDTIME(logout,'-24:00:00'),logout)),NULL),NULL) as x2_logout
      FROM
      	(
      		SELECT Fecha, asesor, `jornada start` as j_inicio, `jornada end` as j_end, `extra1 start` as x1_inicio, `extra1 end` as x1_end, `extra2 start` as x2_login, `extra2 end` as x2_end, login, logout
      		FROM log_asesor
      	) a ");



      $this->db->query("DROP TEMPORARY TABLE IF EXISTS ausTable");
      $this->db->query("CREATE TEMPORARY TABLE ausTable SELECT
              Fecha,
                  a.asesor,
                  IF(`jornada start` = `jornada end`, 1, 0) AS Descanso,
                  CASE
                      WHEN login IS NULL THEN 0
                      WHEN login IS NOT NULL THEN 1
                  END AS Asistencia,

                  CASE
                      WHEN tipo_ausentismo IS NULL THEN 0
                      ELSE 1
                  END AS Ausentismo,
                  Caso as Aus_Caso,
                  Comments as Aus_Nota,
                  User as Aus_register,
                  `Last Update` as Aus_LU, c.Ausentismo as Aus_Nombre,
                  CASE
                      WHEN
                          tipo_ausentismo IS NOT NULL
                      THEN
                          CASE
                              WHEN Descansos = 0 THEN c.Code
                              WHEN
                                  Descansos != 0
                              THEN
                                  CASE
                                      WHEN DATEDIFF(fin, inicio) < 5 THEN IF(fin = Fecha OR inicio = Fecha, 'D', c.Code)
                                      ELSE CASE
                                          WHEN
                                              esquema = 10
                                          THEN
                                              IF(WEEKDAY(Fecha) + 1 IN (6 , 7)
                                                  AND (FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) / 7)) < Descansos, 'D', c.Code)
                                          ELSE IF(WEEKDAY(Fecha) + 1 = 7
                                              AND (FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) / 7)) < Descansos, 'D', IF((FLOOR(((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) - (FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) / 7)) - FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) - (FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) / 7)) / 5)) / 5)) < Beneficios
                                              AND WEEKDAY(Fecha) + 1 = 6, 'B', IF(Fecha = fin
                                              AND Descansos - ((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) - (FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) / 7)) - FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) - (FLOOR((DAYOFYEAR(Fecha) - DAYOFYEAR(inicio)) / 7)) / 5)) > 0, 'D', c.Code)))
                                      END
                                  END
                          END
                  END AS Code_aus,
                  IF(WEEKDAY(Fecha) + 1 = 7, 1, 0) AS Domingo
          FROM
              log_asesor a
          LEFT JOIN Ausentismos b ON a.asesor = b.asesor
              AND Fecha BETWEEN inicio AND fin
          LEFT JOIN `Tipos Ausentismos` c ON b.tipo_ausentismo = c.id");

      $this->db->query("ALTER TABLE log_asesor ADD PRIMARY KEY (`Fecha`, `asesor`)");
      $this->db->query("ALTER TABLE xtraTime ADD PRIMARY KEY (`Fecha`, `asesor`)");
      $this->db->query("ALTER TABLE ausTable ADD PRIMARY KEY (`Fecha`, `asesor`)");

      $this->db->query("DROP TEMPORARY TABLE IF EXISTS pyaTable");
      $this->db->query("CREATE TEMPORARY TABLE pyaTable SELECT
              horario_id,
                  tipo,
                  caso,
                  Nota,
                  `Last Update` as Last_Update,
                  changed_by as reg_by,
                  Excepcion,
                  Codigo
          FROM
              PyA_Exceptions a
          LEFT JOIN `Tipos Excepciones` b ON a.tipo = b.exc_type_id");
      $this->db->query("ALTER TABLE pyaTable ADD PRIMARY KEY (`horario_id`)");


      $this->db->query("DROP TEMPORARY TABLE IF EXISTS asistenciaTableResult");
      $this->db->query("CREATE TEMPORARY TABLE asistenciaTableResult SELECT
      	NOMBREASESOR(a.asesor,2) as Nombre,
          a . *,
          j_login,
          j_logout,
          x1_login,
          x1_logout,
          x2_login,
          x2_logout,
          IF(Descanso=0 AND j_logout<`jornada end`,1,0) as SalidaAnticipada,
          IF(Descanso=0,ADDTIME(realTime(j_logout),-realTime(j_login))/ADDTIME(realTime(`jornada end`),-`jornada start`)*100,null) as tiempoLaborado,
          CASE
              WHEN j_login > ADDTIME(`jornada start`, '00:13:00') THEN 'RT-B'
              WHEN j_login >= ADDTIME(`jornada start`, '00:01:00') THEN 'RT-A'
              ELSE NULL
          END as Retardo,
          CASE
              WHEN j_login >= ADDTIME(`jornada start`, '00:01:00') THEN ADDTIME(j_login, - `jornada start`)
              ELSE NULL
          END as Retardo_time,
          d.tipo as RT_tipo,
          d.caso as RT_caso,
          d.Nota as RT_Nota,
          d.Last_Update as RT_LU,
          NOMBREUSUARIO(d.reg_by,1) as RT_register,
          d.Excepcion as RT_Excepcion,
          d.Codigo as RT_Codigo,
          Descanso,
          Asistencia,
          Ausentismo,
          Code_aus,
          Aus_caso, Aus_Nota, NOMBREUSUARIO(Aus_register,1) as Aus_Register, Aus_LU, Aus_Nombre,
          Domingo
      FROM
          log_asesor a
              LEFT JOIN
          xtraTime b ON a.Fecha = b.Fecha
              AND a.asesor = b.asesor
              LEFT JOIN
          ausTable c ON a.Fecha = c.Fecha
              AND a.asesor = c.asesor
              LEFT JOIN
          pyaTable d ON h_id = horario_id
      ORDER BY
          Nombre");

          $q = $this->db->query("SELECT * FROM asistenciaTableResult");

      $result = $q->result_array();

      foreach($result as $index => $info){
        $fechas[$info['Fecha']]=1;
        $data[$info['asesor']]['Nombre']=$info['Nombre'];
        $data[$info['asesor']]['PuestoName']=$info['PuestoName'];
        $data[$info['asesor']]['Departamento']=$info['Departamento'];
        $data[$info['asesor']]['data'][$info['Fecha']]=$info;
        unset($data[$info['asesor']]['data'][$info['Fecha']]['asesor']);
        unset($data[$info['asesor']]['data'][$info['Fecha']]['Nombre']);
        unset($data[$info['asesor']]['data'][$info['Fecha']]['PuestoName']);
        unset($data[$info['asesor']]['data'][$info['Fecha']]['Departamento']);
        unset($data[$info['asesor']]['data'][$info['Fecha']]['Fecha']);
      }

      return array('Fechas' => $fechas, 'data' => $data);

    });

    $this->response($result);


  }

}
