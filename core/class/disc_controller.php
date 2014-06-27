<?php

/**
 *	disc_controller.php
 *
 *	框架控制器基类
 *
 */

if(!defined('IN_DISC')) {
	exit('Access Denied');
}

class disc_controller
{

	function __call($func, $arguments) {
		
	}

	public function view(array $data = null, $template_name = '') {
		if (empty($template_name)) {
			$template_name = getconfig('router/action');
		}
		$template_file = APPLICATION_PATH."./view/".$template_name.".php";
		if (!is_file($template_file)) {
			system_error("模板文件不存在[".$template_file."]");
		}

		if (!empty($data)) {
			foreach ($data as $key => $value) {
				$$key = $value;
			}
		}
		include $template_file;
	}
}