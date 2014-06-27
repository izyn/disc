<?php

/**
 *	disc.php
 *
 *	框架核心入口文件
 *
 */

error_reporting(E_ALL);

define('IN_DISC', true);
define('CORE_PATH', dirname(__FILE__));

if(!defined('APPNAME')) define('APPLICATION_PATH', dirname(dirname(__FILE__)).'/application');
else define('APPLICATION_PATH', dirname(dirname(__FILE__)).'/'.APPNAME);

set_error_handler(array('core', 'handleError'));
register_shutdown_function(array('core', 'handleShutdown'));

if(function_exists('spl_autoload_register')) {
	spl_autoload_register(array('core', 'autoload'));
} else {
	function __autoload($class) {
		return core::autoload($class);
	}
}

C::creatapp();

class core
{
	private static $_tables;
	private static $_imports;
	private static $_app;
	private static $_memory;

	public static function app() {
		return self::$_app;
	}

	public static function creatapp() {
		if(!is_object(self::$_app)) {
			self::$_app = disc_application::instance();
		}
		return self::$_app;
	}

	public static function import($name, $folder = 'class') {
		$key = $folder.$name;
		if(!isset(self::$_imports[$key])) {
			$file = CORE_PATH.'/'.$folder.'/'.$name;

			if(is_file($file)) {
				include $file;
				self::$_imports[$key] = true;
				return true;
			} else {
				throw new Exception('Oops! System file lost: '.$file);
			}
		}
		return true;
	}

	public static function handleException($exception) {
		disc_error::exception_error($exception);
	}


	public static function handleError($errno, $errstr, $errfile, $errline) {
		if($errno) {
			disc_error::system_error($errstr);
		}
	}

	public static function handleShutdown() {
		if(($error = error_get_last()) && $error['type']) {
			disc_error::system_error($error);
		}
	}

	public static function autoload($class) {

		$file = $class.'.php';

		try {

			self::import($file);
			return true;

		} catch (Exception $exc) {

			$trace = $exc->getTrace();
			foreach ($trace as $log) {
				if(empty($log['class']) && $log['function'] == 'class_exists') {
					return false;
				}
			}
			disc_error::exception_error($exc);
		}
	}
}

class C extends core {}
class DB extends disc_database {}