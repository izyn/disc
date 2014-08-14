<?php

/**
 *	tinyPHP.class.php
 *
 *	框架核心文件
 *
 */

function DB() {
	static $db;
	if(!is_object($db)) {
		global $_config;
		if (!isset($_config['db'])) {
			system_error('无效的数据库配置');
		}
		$db = new db_driver_mysql($_config['db']);
	}
	return $db;
}

class tinyPHP {

	/**
     * 应用实例
     * @var SinglePHP
     */
	private static $_app;

	/**
     * 框架核心参数
     * @var array
     */
	var $var = array();

	/**
     * 创建应用实例
     * @return SinglePHP
     */
	public static function creatapp() {
		if(!is_object(self::$_app)) {
			self::$_app = new self();
		}
		return self::$_app;
	}

	/**
     * 打印错误信息
     */
	public static function handleError($errno, $errstr, $errfile, $errline) {
		if($errno) {
			system_error($errstr.' ['.$errfile.' on '.$errline.']');
		}
	}

	/**
     * 构造函数
     */
	public function __construct() {
		$this->_init_env();
		$this->_xss_check();
		$this->_init_uri();
	}

	/**
     * 初始化框架运行环境
     */
	private function _init_env() {

		global $_config;

		if(empty($_config['debug'])) {
			error_reporting(E_ALL);
		} elseif($_config['debug'] === 1 || $_config['debug'] === 2) {
			error_reporting(E_ERROR);
			if($_config['debug'] === 2) {
				error_reporting(E_ALL);
			}
		} else {
			error_reporting(0);
		}

		if(PHP_VERSION < '5.3.0') {
			set_magic_quotes_runtime(0);
		}

		if (isset($_GET['GLOBALS']) ||isset($_POST['GLOBALS']) ||  isset($_COOKIE['GLOBALS']) || isset($_FILES['GLOBALS'])) {
			system_error('request_tainting');
		}

		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			$_GET = tiny_stripslashes($_GET);
			$_POST = tiny_stripslashes($_POST);
			$_COOKIE = tiny_stripslashes($_COOKIE);
		}

		$allowgzip = is_allowgzip();

		if(!ob_start($allowgzip ? 'ob_gzhandler' : null)) {
			ob_start();
		}

		header('Content-Type: text/html; charset=utf-8');

		$this->var = & $_config;
	}

	/**
     * xss攻击检测
     */
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

	/**
     * uri路由初始化
     */
	private function _init_uri() {

		$this->var['uri_model'] = empty($this->var['uri_model']) ? 1 : $this->var['uri_model'];
		$this->var['controller_identifier'] = empty($this->var['controller_identifier']) ? "c" : $this->var['controller_identifier'];
		$this->var['action_identifier'] = empty($this->var['action_identifier']) ? "m" : $this->var['action_identifier'];
		$this->var['controller_default'] = empty($this->var['controller_default']) ? "index" : $this->var['controller_default'];
		$this->var['action_default'] = empty($this->var['action_default']) ? "index" : $this->var['action_default'];

		if ($this->var['uri_model'] == 1) {
			$this->var['controller'] = empty($_GET[$this->var['controller_identifier']]) ? $this->var['controller_default'] : $_GET[$this->var['controller_identifier']];
			$this->var['action'] = empty($_GET[$this->var['action_identifier']]) ? $this->var['action_default'] : $_GET[$this->var['action_identifier']];
		} elseif ($this->var['uri_model'] == 2) {
			$php_file = trim($_SERVER['SCRIPT_NAME'], "/");
				$request_uri = explode("/", trim($_SERVER['REQUEST_URI'], "/"));
			if ($request_uri[0] == $php_file || empty($request_uri[0])) {
				array_shift($request_uri);
			}
			if (empty($request_uri)) {
				$this->var['controller'] = $this->var['controller_default'];
				$this->var['action'] = $this->var['action_default'];
			} else {
				$this->var['controller'] = $request_uri[0];
				$this->var['action'] = empty($request_uri[1]) ? $this->var['action_default'] : $request_uri[1];
			}
		} else {
			system_error("Oops! Invalid uri_model!");
		}
	}

	/**
     * 运行实例
     */
	public function run() {
		global $_config;
		set_error_handler(array('tinyPHP', 'handleError'));
		$controller_file = dirname(__FILE__).$_config['app_path'].'/controller/'.$_config['controller'].'.php'; 
		if (file_exists($controller_file)) {
			include $controller_file;
			$_class = $_config['controller'].'_controller';
			$_obj = new $_class();
			$_obj->$_config['action']();
		} else {
			system_error("Lost file [".$controller_file."]");
		}
	}
}

class db_driver_mysql
{
	var $curlink;
	var $config = array();
	var $_lastsql;

	/**
     * 构造函数
     * @param array $config 配置数组
     */
	function __construct($config = array()) {

		$this->config = $config;

		if(empty($this->config)) {
			$this->halt('config_db_not_found');
		}

		$this->curlink = $this->_dbconnect(
			$this->config['host'],
			$this->config['user'],
			$this->config['password'],
			$this->config['charset'],
			$this->config['dbname']
		);
	}

	/**
     * 链接数据库
     * @param string $dbhost 服务器
     * @param string $dbuser 用户名
     * @param string $dbpw 密码
     * @param string $dbcharset 字符集
     * @param string $dbname 数据库名
     * @return db_link
     */
	private function _dbconnect($dbhost, $dbuser, $dbpw, $dbcharset, $dbname, $halt = true) {

		$link = @mysql_connect($dbhost, $dbuser, $dbpw, 1, MYSQL_CLIENT_COMPRESS);
		if(!$link) {
			$halt && $this->halt('数据库连接失败');
		}

		if (!@mysql_select_db($dbname, $link)) {
			$halt && $this->halt('数据库选择失败');
		}

		@mysql_set_charset($dbcharset);
		
		return $link;
	}

	/**
     * 查询
     * @param string $sql 要查询的sql
     * @return bool|objeact 查询结果
     */
    public function query($sql) {
    	$this->_lastsql = $sql;
    	if(!($query = mysql_query($sql, $this->curlink))) {
			$this->halt('errno '.$this->errno().' : '.$this->error());
		}
		return $query;
    }

    /**
     * 查询结果集
     * @param string $sql 要查询的sql
     * @return array 查询结果
     */
    public function fetch_all($sql) {

    	$data = array();

    	$res = $this->query($sql);
    	while($row = $this->fetch_array($res)) {
    		$data[] = $row;
    	}

    	$this->free_result($res);
    	return $data;
    }

    /**
     * 查询结果集, 单行
     * @param string $sql 要查询的sql
     * @return array 查询结果
     */
    public function fetch_row($sql) {
    	$res = $this->query($sql);
		$ret = $this->fetch_array($res);
		$this->free_result($res);
		return $ret ? $ret : array();
    }

    /**
     * 查询结果集, 字段
     * @param string $sql 要查询的sql
     * @return string 查询结果
     */
    public function fetch_field($sql) {
    	$res = $this->query($sql);
    	$ret = mysql_result($res, 0);
    	$this->free_result($res);
		return $ret;
    }

    /**
     * 插入
     * @param string $table 表名
     * @param array $data 插入数据(字段名=>字段值)
     * @param bool $return_id 是否返回插入ID
     * @return bool|string 查询结果
     */
    public function insert($table, $data = array(), $return_id = false) {
    	$sql = $this->implode($data);
    	$res = $this->query("INSERT INTO $table SET $sql");
    	if($res && $return_id) return mysql_insert_id($this->curlink);
    	else return $res;
    }

    /**
     * 更新
     * @param string $table 表名
     * @param array $data 待更新数据(字段名=>字段值)
     * @param string|array $condition 条件
     * @return bool 查询结果
     */
    public function update($table, $data = array(), $condition) {
    	$sql = $this->implode($data);
    	$where = '';
		if (empty($condition)) {
			$where = '1';
		} elseif (is_array($condition)) {
			$where = $this->implode($condition, ' AND ');
		} else {
			$where = $condition;
		}
    	return $this->query("UPDATE $table SET $sql WHERE $where");
    }

    /**
     * 删除
     * @param string $table 表名
     * @param string|array $condition 条件
     * @return bool 查询结果
     */
    public function delete($table, $condition) {
    	if (empty($condition)) {
    		$this->halt("危险操作：没有设置删除条件");
			return false;
		} elseif (is_array($condition)) {
			$where = $this->implode($condition, ' AND ');
		} else {
			$where = $condition;
		}
		return $this->query("DELETE FROM $table WHERE $where ");
    }

    /**
     * 获取结果集
     * @param $query 数据指针
     * @param string $result_type 结果集类型
     * @return array 结果
     */
    private function fetch_array($query, $result_type = MYSQL_ASSOC) {
		return mysql_fetch_array($query, $result_type);
	}

	/**
     * 释放结果内存
     * @param $query 数据指针
     * @return bool
     */
	private function free_result($query) {
		return mysql_free_result($query);
	}

	/**
     * 组装sql
     * @param array $array 数组
     * @return string
     */
	private function implode($array = array(), $glue = ',') {
		$sql = $comma = '';
		$glue = ' ' . trim($glue) . ' ';
		foreach ($array as $k => $v) {
			$sql .= $comma . '`'.$k.'`' . '=' . '\'' . addcslashes($v, "\n\r\\'\"\032") . '\'';
			$comma = $glue;
		}
		return $sql;
	}

	/**
     * 获取错误
     * @return string 错误信息
     */
    private function error() {
		return (($this->curlink) ? mysql_error($this->curlink) : mysql_error());
	}

	/**
     * 获取错误码
     * @return int 错误码
     */
	private function errno() {
		return intval(($this->curlink) ? mysql_errno($this->curlink) : mysql_errno());
	}

	/**
     * 打印错误信息
     * @param string 错误信息
     */
	private function halt($message) {
		system_error($message);
	}
}

class tiny_controller {

	function view($data, $tpl = "") {
		global $_config;

		$tpl = $tpl ? (file_exists($tpl) ? $tpl : dirname(__FILE__).$_config['app_path'].'/view/'.$tpl.'.php') : dirname(__FILE__).$_config['app_path'].'/view/'.$_config['action'].'.php';
		if (file_exists($tpl)) {
			include $tpl;
		} else {
			system_error('Lost file ['.$tpl.']');
		}
	}
}

function system_error($message) {
	ob_end_clean();
	$gzip = is_allowgzip();
	ob_start($gzip ? 'ob_gzhandler' : null);

	$host = $_SERVER['HTTP_HOST'];
	echo <<<EOT
<!DOCTYPE html>
<html>
<head>
<title>$host - System Error</title>
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
}
-->
</style>
</head>
<body>
<div id="body">
<h1>$message</h1>
EOT;
	if($phpmsg = debug_backtrace()) {
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
<p class="footer">tinyPHP Version 0.0.1</p>
</body>
</html>
EOT;
	exit();

}

function is_allowgzip() {
	if(!empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === false) {
		return false;
	} else 
		return true;
}

function tiny_stripslashes($string) {
	if(empty($string)) return $string;
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = tiny_stripslashes($val);
		}
	} else {
		$string = stripslashes($string);
	}
	return $string;
}

function tiny_addslashes($string, $force = 1) {
	if(is_array($string)) {
		$keys = array_keys($string);
		foreach($keys as $key) {
			$val = $string[$key];
			unset($string[$key]);
			$string[addslashes($key)] = tiny_addslashes($val, $force);
		}
	} else {
		$string = addslashes($string);
	}
	return $string;
}

function tiny_htmlspecialchars($string, $flags = null) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = tiny_htmlspecialchars($val, $flags);
		}
	} else {
		if($flags === null) {
			$string = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $string);
			if(strpos($string, '&amp;#') !== false) {
				$string = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $string);
			}
		} else {
			if(PHP_VERSION < '5.4.0') {
				$string = htmlspecialchars($string, $flags);
			} else {
				if(strtolower(CHARSET) == 'utf-8') {
					$charset = 'UTF-8';
				} else {
					$charset = 'ISO-8859-1';
				}
				$string = htmlspecialchars($string, $flags, $charset);
			}
		}
	}
	return $string;
}

function dump($var, $echo=true,$label=null, $strict=true) {
    $label = ($label===null) ? '' : rtrim($label) . ' ';
    if(!$strict) {
        if (ini_get('html_errors')) {
            $output = print_r($var, true);
            $output = "<pre>".$label.htmlspecialchars($output,ENT_QUOTES)."</pre>";
        } else {
            $output = $label . print_r($var, true);
        }
    }else {
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        if(!extension_loaded('xdebug')) {
            $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
            $output = '<pre>'. $label. htmlspecialchars($output, ENT_QUOTES). '</pre>';
        }
    }
    if ($echo) {
        echo($output);
        return null;
    }else
        return $output;
}