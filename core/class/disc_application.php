<?php

/**
 *	disc_application.php
 *
 *	框架应用程序初始化文件
 *
 */

if(!defined('IN_DISC')) {
	exit('Access Denied');
}

class disc_application
{

	var $var = array();

	static function &instance() {
		static $object;
		if(empty($object)) {
			$object = new self();
		}
		return $object;
	}

	public function __construct() {
		$this->_init_env();
		$this->_init_config();
		$this->_init_input();
		$this->_init_uri();
		$this->_init_output();
	}

	private function _init_env() {

		error_reporting(E_ERROR);
		if(PHP_VERSION < '5.3.0') {
			set_magic_quotes_runtime(0);
		}

		define('MAGIC_QUOTES_GPC', function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc());
		define('ICONV_ENABLE', function_exists('iconv'));
		define('MB_ENABLE', function_exists('mb_convert_encoding'));
		define('EXT_OBGZIP', function_exists('ob_gzhandler'));

		define('TIMESTAMP', time());

		if(!@include(CORE_PATH.'/function/function_core.php')) {
			exit('function_core.php is missing');
		}
	}

	private function _init_config() {

		$_config = array();

		@include(APPLICATION_PATH.'./conf/config.php');

		if(empty($_config['debug'])) {
			error_reporting(0);
		} elseif($_config['debug'] === 1 || $_config['debug'] === 2) {
			error_reporting(E_ERROR);
			if($_config['debug'] === 2) {
				error_reporting(E_ALL);
			}
		} else {
			error_reporting(0);
		}

		if (empty($_config)) {
			system_error('Oops! Invalid Config!');
		}

		$this->var = & $_config;
	}

	private function _init_input() {
		if (isset($_GET['GLOBALS']) ||isset($_POST['GLOBALS']) ||  isset($_COOKIE['GLOBALS']) || isset($_FILES['GLOBALS'])) {
			system_error('request_tainting');
		}

		if(MAGIC_QUOTES_GPC) {
			$_GET = dstripslashes($_GET);
			$_POST = dstripslashes($_POST);
			$_COOKIE = dstripslashes($_COOKIE);
		}

		$this->_xss_check();
	}

	private function _init_output() {

		$allowgzip = is_allowgzip();

		if(!ob_start($allowgzip ? 'ob_gzhandler' : null)) {
			ob_start();
		}

		@header('Content-Type: text/html; charset=utf-8');
	}

	private function _xss_check() {

		static $check = array('"', '>', '<', '\'', '(', ')', 'CONTENT-TRANSFER-ENCODING');

		if($_SERVER['REQUEST_METHOD'] == 'GET' ) {
			$temp = $_SERVER['REQUEST_URI'];
		} else {
			//$temp = $_SERVER['REQUEST_URI'].file_get_contents('php://input');
			$temp = '';
		}

		$temp = strtoupper(urldecode(urldecode($temp)));
		foreach ($check as $str) {
			if(strpos($temp, $str) !== false) {
				system_error('request_tainting');
			}
		}

		return true;
	}

	private function _init_uri() {

		$router = new disc_router($this->var);
		var_dump($this->var);exit();
	}
}