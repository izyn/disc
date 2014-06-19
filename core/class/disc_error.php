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

		$title = lang('error', 'db_'.$message);
		$title_msg = lang('error', 'db_error_message');
		$title_sql = lang('error', 'db_query_sql');
		$title_backtrace = lang('error', 'backtrace');
		$title_help = lang('error', 'db_help_link');

		$db = &DB::object();
		$dberrno = $db->errno();
		$dberror = str_replace($db->tablepre,  '', $db->error());
		$sql = dhtmlspecialchars(str_replace($db->tablepre,  '', $sql));

		$msg = '<li>[Type] '.$title.'</li>';
		$msg .= $dberrno ? '<li>['.$dberrno.'] '.$dberror.'</li>' : '';
		$msg .= $sql ? '<li>[Query] '.$sql.'</li>' : '';

		disc_error::show_error('db', $msg, $showtrace, false);
		unset($msg, $phperror);

		$errormsg = '<b>'.$title.'</b>';
		$errormsg .= "[$dberrno]<br /><b>ERR:</b> $dberror<br />";
		if($sql) {
			$errormsg .= '<b>SQL:</b> '.$sql;
		}
		$errormsg .= "<br />";
		$errormsg .= '<b>PHP:</b> '.$logtrace;

		disc_error::write_error_log($errormsg);
		exit();

	}

	public static function exception_error($exception) {

		if($exception instanceof DbException) {
			$type = 'db';
		} else {
			$type = 'system';
		}

		if($type == 'db') {
			$errormsg = '('.$exception->getCode().') ';
			$errormsg .= self::sql_clear($exception->getMessage());
			if($exception->getSql()) {
				$errormsg .= '<div class="sql">';
				$errormsg .= self::sql_clear($exception->getSql());
				$errormsg .= '</div>';
			}
		} else {
			$errormsg = $exception->getMessage();
		}

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

		self::show_error($type, $errormsg, $phpmsg);
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
	::selection { background-color: #E13300; color: white; }
    ::-moz-selection { background-color: #E13300; color: white; }
    ::-webkit-selection { background-color: #E13300; color: white; }

    body {
        background-color: #fff;
        margin: 40px;
        font: 13px/20px normal Helvetica, Arial, sans-serif;
        color: #4F5155;
    }

    a {
        color: #003399;
        background-color: transparent;
        font-weight: normal;
    }

    h1 {
        color: #444;
        background-color: transparent;
        border-bottom: 1px solid #D0D0D0;
        font-size: 19px;
        font-weight: normal;
        margin: 0 0 14px 0;
        padding: 14px 15px 10px 15px;
    }

    code {
        font-family: Consolas, Monaco, Courier New, Courier, monospace;
        font-size: 14px;
        background-color: #f9f9f9;
        border: 1px solid #D0D0D0;
        color: #002166;
        display: block;
        margin: 14px 0 14px 0;
        padding: 12px 10px 12px 10px;
    }

    #message {color:red;}

    #body {
        margin: 0 15px 0 15px;
    }

    p.footer {
        text-align: right;
        font-size: 11px;
        border-top: 1px solid #D0D0D0;
        line-height: 32px;
        padding: 0 10px 0 10px;
        margin: 20px 0 0 0;
    }

    #container {
        margin: 10px;
        border: 1px solid #D0D0D0;
        box-shadow: 0 0 8px #D0D0D0;
    }
	-->
	</style>
</head>
<body>
<div id="container">
<h1>DiscPHP! $title Error</h1>
<div id="body">
<p id="message"><strong>$errormsg</strong></p>
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
</div>
<p class="footer">Page rendered in <strong>{elapsed_time}</strong> seconds. DiscPHP Version 0.0.1</p>
</body>
</html>
EOT;
		exit();

	}

	public static function clear($message) {
		return str_replace(array("\t", "\r", "\n"), " ", $message);
	}

	public static function sql_clear($message) {
		$message = self::clear($message);
		$message = str_replace(DB::object()->tablepre, '', $message);
		$message = dhtmlspecialchars($message);
		return $message;
	}
}