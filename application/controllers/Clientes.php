<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Clientes extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->database();
    $this->load->model('Cliente_model');
  }

  public function index_get(){

    $clientes = array('hola','como','estas');

    jsonPrint( $clientes );

  }

  public function cliente_get(){

    $cliente_id = $this->uri->segment(3);

    $cliente = $this->Cliente_model->get_cliente( $cliente_id );

    jsonPrint( $cliente );

  }

}
