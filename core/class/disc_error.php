<?php

/**
 *	disc_error.php
 *
 *	错误日志文件
 *
 */

if(!defined('IN_DISC')) {
	exit('Access Denied');
}

class disc_error
{

	public static function system_error($error) {

		list($showtrace, $logtrace) = disc_error::debug_backtrace();

		if(is_array($error))
			$message = ucfirst(substr($error['message'], 0, stripos($error['message'], ',') ? stripos($error['message'], ',') : 100)).': '.$error['message'].' in '.$error['file'].' on line '.$error['line'];
		else 
			$message = $error;

		disc_error::show_error('system', $message, $showtrace, 0);
	}

	public static function template_error($message, $tplname) {
		$message = lang('error', $message);
		$tplname = $tplname;
		$message = $message.': '.$tplname;
		disc_error::system_error($message);
	}

	public static function debug_backtrace() {

		$show = $log = '';
		$debug_backtrace = debug_backtrace();
		krsort($debug_backtrace);
		foreach ($debug_backtrace as $k => $error) {
			$file = isset($error['file']) ? $error['file'] : '';
			$func = isset($error['class']) ? $error['class'] : '';
			$func .= isset($error['type']) ? $error['type'] : '';
			$func .= isset($error['function']) ? $error['function'] : '';
			if (empty($error['line'])) {
				$error['line'] = 0;
			}
			$error['line'] = sprintf('%04d', $error['line']);
			$show .= "<code>[Line: $error[line]]".$file."($func)</code>";
			$log .= !empty($log) ? ' -> ' : '';$file.':'.$error['line'];
			$log .= $file.':'.$error['line'];
		}
		return array($show, $log);
	}

	public static function db_error($message, $sql) {

		list($showtrace, $logtrace) = disc_error::debug_backtrace();

		$db = DB::object();
		$dberrno = $db->errno();
		$sql = dhtmlspecialchars(str_replace($db->tablepre,  '', $sql));

		$msg = '[code:'.$dberrno.'] '.$message.' ('.$sql.')';

		disc_error::show_error('db', $msg, $showtrace, false);
		exit();

	}

	public static function exception_error($exception) {

		$errormsg = $exception->getMessage();

		$trace = $exception->getTrace();
		krsort($trace);

		$trace[] = array('file'=>$exception->getFile(), 'line'=>$exception->getLine(), 'function'=> 'break');
		$phpmsg = array();
		foreach ($trace as $error) {
			if (empty($error['file'])) {
				continue;
			}
			if(!empty($error['function'])) {
				$fun = '';
				if(!empty($error['class'])) {
					$fun .= $error['class'].$error['type'];
				}
				$error['function'] = $fun.$error['function'];
			}

			$phpmsg[] = array(
			    'file' => $error['file'],
			    'line' => $error['line'],
			    'function' => $error['function'],
			);
		}

		self::show_error('system', $errormsg, $phpmsg);
		exit();

	}

	public static function show_error($type, $errormsg, $phpmsg = '', $typemsg = '') {

		ob_end_clean();
		$gzip = is_allowgzip();
		ob_start($gzip ? 'ob_gzhandler' : null);

		$host = $_SERVER['HTTP_HOST'];
		$title = $type == 'db' ? 'Database' : 'System';
		echo <<<EOT
<!DOCTYPE html>
<html>
<head>
	<title>$host - $title Error</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="ROBOTS" content="NOINDEX,NOFOLLOW,NOARCHIVE" />
	<style type="text/css">
	<!--
    body {
        background-color: #fff;
        margin: 40px;
        font: 13px/20px normal Helvetica, Arial, sans-serif;
        color: #4F5155;
    }

    h1 {
        color: #000;
        font-weight: 800;
        font-size: 36px;
    	line-height: 40px;
    }

    code {
        font-family: Consolas, Monaco, Courier New, Courier, monospace;
        font-size: 14px;
        color: #333;
        display: block;
    }

    #body {
        margin: 10% 15px;
    }

    p.footer {
        font-size: 11px;
        border-top: 1px solid #D0D0D0;
        line-height: 32px;
        margin: 20px 0 0 0;
    }
	-->
	</style>
</head>
<body>
<div id="body">
<h1>$errormsg</h1>
EOT;
		if(!empty($phpmsg)) {
			if(is_array($phpmsg)) {
				foreach($phpmsg as $k => $msg) {
					$k++;
					echo '<code>'.$k.'. ';
					if ($msg['file']) {
						echo '[Line: '.$msg['line'].']'.$msg['file'];
					}
					echo '('.$msg['function'].')</code>';
				}
			} else {
				echo $phpmsg;
			}
		}

		echo <<<EOT
<p class="footer">Page rendered in <strong>{elapsed_time}</strong> seconds. DiscPHP Version 0.0.1</p>
</body>
</html>
EOT;
		exit();

	}
}