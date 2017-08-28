<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Headcount extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->helper('mailing');
    $this->load->database();
  }

  public function codigosPuesto_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $this->temporaryPuestos();

      if($q = $this->db->query("SELECT * FROM cdPuestos")){
        $result = array(
                      "status"    => true,
                      "msg"       => $this->db->error(),
                      "rows"      => $q->num_rows(),
                      "data"     => $q->result_object()
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

    jsonPrint( $result );

  }

  public function listPuestos_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $this->temporaryPuestos();

      $params = array(
                        "UDN" => $this->uri->segment(4),
                        "Area" => $this->uri->segment(5),
                        "Departamento" => $this->uri->segment(6),
                        "Puesto" => $this->uri->segment(7),
                      );

      foreach($params as $index => $value){
        if($value != null){
          $where[$index] = $value;
        }
      }

      if(!isset($where)){
        $where[1]=1;
      }

      $search = $this->uri->segment(3);

      $relates = array(
                        "Area"          => "UDN",
                        "Departamento"  => "Area",
                        "Puesto"        => "Departamento",
                        "Alias"         => "Puesto"
                      );

      if($search == 'UDN'){
        $search = "UDN !=";
        $param = 0;
        $this->db->select("UDN as id, UDN_nombre as name")
                  ->group_by('UDN');
      }else{
        $this->db->select("$search as id, ".$search."_nombre as name")
                  ->group_by( $search )
                  ->where( $where );
      }

      if($search == 'Alias' OR $search == 'alias'){
        $this->db->select("puestoID as id, Alias as alias_id, Departamento_id as dep_id, Codigo as codigo, pcrc, ".$search."_nombre as name");
      }

      $query = $this->db->get_compiled_select("cdPuestos");

      $q = $this->db->query($query);

      $result = array(
                      "status"    => true,
                      "msg"       => $this->db->error(),
                      "data"     => $q->result_array()
                    );

      return $result;

    });

    jsonPrint( $result );

  }

  public function listPdvs_get(){
    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

        $q = $this->db->query("SELECT a.id, CONCAT('(',b.Ciudad,') ',PDV) as name, b.id as ciudad FROM PDVs a LEFT JOIN db_municipios b ON a.ciudad=b.id WHERE Activo=1 AND PDV LIKE '%MX%' AND PDV NOT LIKE '%y-outlet%' AND b.Ciudad IS NOT NULL ORDER BY b.Ciudad, PDV");

        $result = array(
                        "status"    => true,
                        "msg"       => "listado completo",
                        "data"     => $q->result_array()
                      );

        return $result;

      });

      jsonPrint( $result );

  }

  private function temporaryPuestos(){
    $this->db->query("DROP TEMPORARY TABLE IF EXISTS cdPuestos");
    $this->db->query("CREATE TEMPORARY TABLE cdPuestos SELECT
                  a.id as Alias, b.*, a.Puesto as Alias_nombre
              FROM
                  (SELECT
                      a.puesto as id, b.Puesto, hc_puesto
                  FROM
                      asesores_plazas a
                  LEFT JOIN PCRCs_puestos b ON a.puesto = b.id
                  WHERE
                      hc_puesto IS NOT NULL
                  GROUP BY a.puesto , hc_puesto) a
                      LEFT JOIN
                  (SELECT
                      a.id AS puestoID,
                          d.clave AS UDN,
                          c.clave AS Area,
                          b.clave AS Departamento,
                          a.clave AS Puesto,
                          CONCAT(d.clave, '-', c.clave, '-', b.clave, '-', a.clave) AS Codigo,
                          d.nombre AS UDN_nombre,
                          c.nombre AS Area_nombre,
                          b.nombre AS Departamento_nombre,
                          a.nombre AS Puesto_nombre,
                          d.id AS UDN_id,
                          c.id AS Area_id,
                          b.id AS Departamento_id,
                          a.id AS Puesto_id,
                          b.pcrc as pcrc
                  FROM
                      hc_codigos_Puesto a
                  LEFT JOIN hc_codigos_Departamento b ON a.departamento = b.id
                  LEFT JOIN hc_codigos_Areas c ON b.area = c.id
                  LEFT JOIN hc_codigos_UnidadDeNegocio d ON c.unidadDeNegocio = d.id) b ON a.hc_puesto = b.puestoID
                ORDER BY UDN_nombre , Area_nombre , Departamento_nombre , Puesto , Alias_nombre");
  }

  public function addVacante_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $cleanData = $this->put();

        $insert = array(
                        'main_dep'  => 1,
                        'hc_dep'    => $cleanData['alias']['dep_id'],
                        'hc_puesto' => $cleanData['alias']['id'],
                        'departamento' => $cleanData['alias']['pcrc'],
                        'puesto'    => $cleanData['alias']['alias_id'],
                        'oficina'   => $cleanData['oficina']['id'],
                        'ciudad'    => $cleanData['oficina']['ciudad'],
                        'inicio'    => $cleanData['inicio'],
                        'fin'       => $cleanData['fin'],
                        'esquema'   => $cleanData['esquema'],
                        'comentarios'  => $cleanData['comentarios'],
                        'Activo'    => 1,
                        'Status'    => 0,
                        'created_by'   => $cleanData['creador']
                      );

        if($cleanData['fin'] == null){
          $insert['fin'] = '2030-12-31';
        }

        for($i=1; $i<=$cleanData['cantidad'];$i++){
          $this->db->insert('asesores_plazas', $insert);
          $moves[] = array(
                            'vacante'     => $this->db->insert_id(),
                            'fecha_out'   => $cleanData['inicio']
                          );
          $mopers[] = $this->db->insert_id();
        }

        solicitudVacante::mail($this, array('vacante' => $mopers[0], 'cantidad' => $cleanData['cantidad'], 'status' => 0, 'applier' => $cleanData['creador']), 'ask');

        if($this->db->insert_batch('asesores_movimiento_vacantes', $moves)){
          $result = array(
                          "status"    => true,
                          "msg"       => "Inserts Listos",
                          "ids_vacantes"  => $mopers
                        );
        }else{
          $result = array(
                          "status"    => false,
                          "msg"       => $this->db->error(),
                          "ids_vacantes"  => null
                        );
        }

        return $result;

      });

      jsonPrint( $result );

  }

  public function downloadableList_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $paramTitle = array(
                            5 =>  'UDN_nombre',
                            6 =>  'Area_nombre',
                            7 =>  'Departamento_nombre',
                            8 =>  'Puesto_nombre'
                          );

      for($i=5; $i<=8; $i++){
        if($this->uri->segment($i) != null){
          $params[$paramTitle[$i]] = urldecode($this->uri->segment($i));
        }
      }

      if(!isset($params)){
        $params['UDN_nombre IS NOT NULL'] = null;
      }

      $this->temporaryPuestos();

      $this->db->select("a.id,
                          Codigo,
                          UDN_nombre AS UDN,
                          Area_nombre AS Area,
                          Departamento_nombre AS Departamento,
                          Puesto_nombre AS Puesto,
                          f.Puesto as Alias,
                          a.esquema AS Esquema,
                          d.PDV AS Oficina,
                          e.Ciudad,
                          a.inicio,
                          a.fin,
                          a.comentarios,
                          IF(a.Status = 1 AND asesor IS NULL,'Vacante',NULL) as Vacantes,
                          IF(CURDATE()<=a.fin,'Activa','Inactiva') as Activa,
                          CASE
                            WHEN a.Status=0 THEN 'Pendiente Aprobacion'
                            WHEN a.Status=1 THEN 'Aprobada'
                            WHEN a.Status=2 THEN 'Denegada'
                          END as Aprobacion,
                          NOMBREASESOR(a.created_by, 1) AS Creador,
                          NOMBREASESOR(approbed_by, 1) AS Aprobada_por,
                          CAST(date_created AS DATE) AS Fecha_creacion,
                          deactivated_by AS Desactivada_por,
                          date_deactivated AS Fecha_Desactivacion,
                          deactivation_comments as Comentarios_Desactivacion,
                          NOMBREASESOR(asesor, 2) AS Asesor_actual,
                          num_colaborador,
                          Ingreso")
                ->from('asesores_plazas a')
                ->join('(SELECT * FROM cdPuestos GROUP BY puestoID) b', 'a.hc_puesto = b.puestoID', 'LEFT')
                ->join('dep_asesores c', 'a.id = c.vacante AND c.Fecha = CURDATE()', 'LEFT')
                ->join('PDVs d', 'a.oficina = d.id', 'LEFT')
                ->join('db_municipios e', 'a.ciudad = e.id', 'LEFT')
                ->join('PCRCs_puestos f', 'a.puesto = f.id', 'LEFT')
                ->join('Asesores g', 'c.asesor = g.id', 'LEFT')
                ->where($params)
                ->order_by('Codigo');

      if($query = $this->db->get()){
        $result = array(
                        'status'  => true,
                        'msg'     => "Vacantes obtenidas",
                        'rows'    => $query->num_rows(),
                        'data'    => $query->result_object(),
                        'headers' => $query->list_fields()
                      );
      }else{
        $result = array(
                        'status'  => true,
                        'msg'     => $this->db->error(),
                        'rows'    => 0,
                        'data'    => null
                      );
      }



      return $result;

      });

      jsonPrint( $result );

  }

  public function deactivateVacante_put(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $data = $this->put();

      $update = array(
                      'fin' => $data['fecha'],
                      'deactivated_by' => $data['creador'],
                      'deactivation_comments' => $data['comments'],
                      'Activo' => 0,
                      'Status' => 2,
                    );
      $this->db->set('date_deactivated', "NOW()", FALSE)
                ->set($update)
                ->where("id = ".$data['id']);

      if($query = $this->db->update('asesores_plazas')){
        $result = array(
                        'status'  => true,
                        'msg'     => "Vacante ".$data['id']." desactivada correctamente",
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

  public function test_get(){

    $result = solicitudVacante::mail($this, array('vacante' => 685, 'cantidad' => 4, 'status' => 1, 'applier' => 177), 'set');

    jsonPrint( $result );

  }

}
