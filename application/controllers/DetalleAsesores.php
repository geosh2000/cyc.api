<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;

class DetalleAsesores extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('jwt');
    $this->load->helper('validators');
    $this->load->database();
    $this->load->model('AsesorGeneral_model');
    $this->load->model('AsesorVacantesHisto_model');
    $this->load->model('AsesorSolicitudesHisto_model');
  }

  public function detailAsesor_get(){

    $asesor_id = $this->uri->segment(3);

    // Validacion de id
    segmentSet( 3, 'Debe definir un id para buscar', $this );
    segmentType( 3, 'El id debe ser numérico', $this );

    $generales = $this->dataGeneral( $asesor_id );
    $movimientos = $this->dataMovimientos( $asesor_id );
    $solicitudes = $this->dataSolicitudes( $asesor_id );

    $dataGen = (array) $generales;
    $dataGen['solPendiente']      = $this->AsesorSolicitudesHisto_model->get_SolPendientes( $asesor_id );
    $dataGen['histo_puestos']     = (array) $movimientos;
    $dataGen['histo_solicitudes'] = (array) $solicitudes;

    okResponse("Registro cargado correctamente", "data", $dataGen, $this);

  }

  public function dataGeneral( $id, $allNULL = FALSE, $bypassVal = FALSE ){

    $asesor = $this->AsesorGeneral_model->get_asesor( $id, $allNULL );

    // Validator
    if( !$bypassVal  ){
      if( !isset($asesor) ){
        errResponse( "No existe asesor con id: $id", REST_Controller::HTTP_NOT_FOUND, $this );
        return;
      }
    }

    return $asesor;

  }

  public function dataMovimientos( $id, $allNULL = FALSE, $bypassVal = FALSE ){

    $movimientos = $this->AsesorVacantesHisto_model->get_movimientos( $id, $allNULL );

    // Validator
    if( !$bypassVal  ){
      if( !isset($movimientos) ){
        errResponse( "No existen movimientos para el asesor: $id", REST_Controller::HTTP_NOT_FOUND, $this );
        return;
      }
    }

    return $movimientos;

  }

  public function dataSolicitudes( $id, $allNULL = FALSE, $bypassVal = FALSE ){

    $solicitudes = $this->AsesorSolicitudesHisto_model->get_solicitudes( $id, $allNULL );

    // Validator
    if( !$bypassVal  ){
      if( !isset($solicitudes) ){
        errResponse( "No existen solicitudes para el asesor: $id", REST_Controller::HTTP_NOT_FOUND, $this );
        return;
      }
    }

    return $solicitudes;

  }

}
