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
		@include SOURCE_PATH.'./config/config.php';

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
	}

	private function _init_input() {
1
	}

	private function _init_output() {

	}
}