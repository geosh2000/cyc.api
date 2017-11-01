<?php

function segmentSet( $segment, $msg, $class ){

  $segVal = $class->uri->segment($segment);

  if( !isset($segVal) ){
    $respuesta = array(
                      'ERR'       => TRUE,
                      'msg'       => $msg,
                      'segmento'  => $segment);

    $class->response( $respuesta, REST_Controller::HTTP_BAD_REQUEST );
    return;
  }

}

function segmentType( $segment, $msg, $class ){

  $segVal = $class->uri->segment($segment);

  if( !is_numeric($segVal) ){
    $respuesta = array(
                      'ERR'       => TRUE,
                      'msg'       => $msg,
                      'segmento'  => $segment);

    $class->response( $respuesta, REST_Controller::HTTP_BAD_REQUEST );
    return;
  }

}

function errResponse( $msg, $status, $class, $addData = 'Data Adicional', $data = null ){

  $respuesta = array(
                    'ERR'     => TRUE,
                    'msg'     => $msg,
                    $addData  => $data );

  $class->response( $respuesta, $status );

}

function okResponse( $msg, $title, $result, $class ){

  $respuesta = array(
                    'ERR'     => FALSE,
                    'msg'     => $msg,
                    $title    => $result);

  $class->response( $respuesta );

}

?>
