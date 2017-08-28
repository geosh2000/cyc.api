<?php
//defined('BASEPATH') OR exit('No direct script access allowed');
require( APPPATH.'/libraries/REST_Controller.php');
// use REST_Controller;


class Navbar extends REST_Controller {

  public function __construct(){

    parent::__construct();
    $this->load->helper('json_utilities');
    $this->load->helper('jwt');
    $this->load->database();
  }

  public function getMenu_get(){

    $result = validateToken( $_GET['token'], $_GET['usn'], $func = function(){

      $select = "titulo as title, IF(v2Active = 1, v2link, liga) as v2link, permiso as credential, id, level, parent";

      if($q = $this->db->select($select)->get('menu')){
        $menu = $q->result_array();

        foreach($menu as $index => $data){
          $navbar[$data['level']][$data['parent']][] = array(
                                                      'title'         => str_replace("<br>", " ", $data['title']),
                                                      'href'          => $data['v2link'],
                                                      'credential'    => $data['credential'],
                                                      'id'            => $data['id'],
                                                      'v2link'          => $data['v2link']
                                                    );

        }

        $result = $navbar;
      }else{
        $result = false;
      }

      return $result;

    });

    jsonPrint( $result );

  }

}
