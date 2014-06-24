<?php

/**
 *	disc_router.php
 *
 *	框架应用程序路由器
 *
 */

if(!defined('IN_DISC')) {
	exit('Access Denied');
}

class disc_router
{
	var $_config = array();
	private $uri_model;

	public function __construct(&$global_config) {
		$this->_config = & $global_config['router'];
		$this->uri_model = empty($global_config['uri_model']) ? 1 : $global_config['uri_model'];
		$this->_init();
		$this->_router();
	}

	private function _init() {
		$this->_config['controller_identifier'] = empty($this->_config['controller_identifier']) ? "c" : $this->_config['controller_identifier'];
		$this->_config['action_identifier'] = empty($this->_config['action_identifier']) ? "c" : $this->_config['action_identifier'];
		$this->_config['controller_default'] = empty($this->_config['controller_default']) ? "c" : $this->_config['controller_default'];
		$this->_config['action_default'] = empty($this->_config['action_default']) ? "c" : $this->_config['action_default'];
	}

	private function _router() {
		if ($this->uri_model == 1) {
			$this->uri_querystring();
		} elseif ($this->uri_model == 2) {
			$this->uri_requesturi();
		} else {
			system_error("Oops! Invalid uri_model!");
		}

		$file = APPLICATION_PATH."./controller/".$this->_config['controller'].".php";
		if (!is_file($file)) {
			system_error("Oops! Controller file lost: ".$file);
		}
		include($file);
	}

	private function uri_querystring() {
		$this->_config['controller'] = empty($_GET[$this->_config['controller_identifier']]) ? $this->_config['controller_default'] : $_GET[$this->_config['controller_identifier']];
		$this->_config['action'] = empty($_GET[$this->_config['action_identifier']]) ? $this->_config['action_default'] : $_GET[$this->_config['action_identifier']];
	}

	private function uri_requesturi() {
		$php_file = trim($_SERVER['SCRIPT_NAME'], "/");
		$request_uri = explode("/", trim($_SERVER['REQUEST_URI'], "/"));
		if ($request_uri[0] == $php_file || empty($request_uri[0])) {
			array_shift($request_uri);
		}
		if (empty($request_uri)) {
			$this->_config['controller'] = $this->_config['controller_default'];
			$this->_config['action'] = $this->_config['action_default'];
		} else {
			$this->_config['controller'] = $request_uri[0];
			$this->_config['action'] = empty($request_uri[1]) ? $this->_config['action_default'] : $request_uri[1];
		}
	}
}