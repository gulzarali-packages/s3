<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	function __construct()
 	{
 		parent::__construct();
 		$this->load->library('s3');
 	}
	public function index()
	{
		$params=[
			'content_type'=>'image/jpg', //put any time like zip
			'content_acl'=>'public-read',
			'content'=>file_get_contents($object_path), //path at local storage including extension
			'content_title'=>'' //content name to be saved at aws server  
		];

		$this->s3->put_object($params);
		$this->load->view('welcome_message');
	}
}
