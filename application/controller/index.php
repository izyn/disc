<?php

if(!defined('IN_DISC')) {
	exit('Access Denied');
}

class index_controller extends disc_controller {

	function index() {

		$data['name'] = "David";
		$this->view($data);
	}
}