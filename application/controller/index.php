<?php

class index_controller extends tiny_controller {

	function index() {

		$data['name'] = "David";
		$this->view($data);
	}
}