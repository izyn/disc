<?php

/**
 *  disc_database.php
 *  
 *  数据库类  
 *
 */

if(!defined('IN_DISC')) {
	exit('Access Denied');
}
class disc_database {

	public static $db;

	public static $driver;

	public static function init($config) {
		self::$db = new db_driver;
		self::$db->set_config($config);
		self::$db->connect();
	}

	public static function object() {
		return self::$db;
	}

	public static function table($table) {
		return self::$db->table_name($table);
	}

	public static function delete($table, $condition, $limit = 0, $unbuffered = true) {
		if (empty($condition)) {
			return false;
		} elseif (is_array($condition)) {
			if (count($condition) == 2 && isset($condition['where']) && isset($condition['arg'])) {
				$where = self::format($condition['where'], $condition['arg']);
			} else {
				$where = self::implode_field_value($condition, ' AND ');
			}
		} else {
			$where = $condition;
		}
		$limit = dintval($limit);
		$sql = "DELETE FROM " . self::table($table) . " WHERE $where " . ($limit > 0 ? "LIMIT $limit" : '');
		return self::query($sql, ($unbuffered ? 'UNBUFFERED' : ''));
	}

	public static function insert($table, $data, $return_insert_id = false, $replace = false, $silent = false) {

		$sql = self::implode($data);

		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';

		$table = self::table($table);
		$silent = $silent ? 'SILENT' : '';

		return self::query("$cmd $table SET $sql", null, $silent, !$return_insert_id);
	}

	public static function update($table, $data, $condition, $unbuffered = false, $low_priority = false) {
		$sql = self::implode($data);
		if(empty($sql)) {
			return false;
		}
		$cmd = "UPDATE " . ($low_priority ? 'LOW_PRIORITY' : '');
		$table = self::table($table);
		$where = '';
		if (empty($condition)) {
			$where = '1';
		} elseif (is_array($condition)) {
			$where = self::implode($condition, ' AND ');
		} else {
			$where = $condition;
		}
		$res = self::query("$cmd $table SET $sql WHERE $where", $unbuffered ? 'UNBUFFERED' : '');
		return $res;
	}

	public static function insert_id() {
		return self::$db->insert_id();
	}

	public static function fetch($resourceid, $type = 'MYSQL_ASSOC') {
		return self::$db->fetch_array($resourceid, $type);
	}

	public static function fetch_first($sql, $arg = array(), $silent = false) {
		$res = self::query($sql, $arg, $silent, false);
		$ret = self::$db->fetch_array($res);
		self::$db->free_result($res);
		return $ret ? $ret : array();
	}

	public static function fetch_all($sql, $arg = array(), $keyfield = '', $silent=false) {

		$data = array();
		$query = self::query($sql, $arg, $silent, false);
		while ($row = self::$db->fetch_array($query)) {
			if ($keyfield && isset($row[$keyfield])) {
				$data[$row[$keyfield]] = $row;
			} else {
				$data[] = $row;
			}
		}
		self::$db->free_result($query);
		return $data;
	}

	public static function result($resourceid, $row = 0) {
		return self::$db->result($resourceid, $row);
	}

	public static function result_first($sql, $arg = array(), $silent = false) {
		$res = self::query($sql, $arg, $silent, false);
		$ret = self::$db->result($res, 0);
		self::$db->free_result($res);
		return $ret;
	}

	public static function query($sql, $arg = array(), $silent = false, $unbuffered = false) {
		if (!empty($arg)) {
			if (is_array($arg)) {
				$sql = self::format($sql, $arg);
			} elseif ($arg === 'SILENT') {
				$silent = true;

			} elseif ($arg === 'UNBUFFERED') {
				$unbuffered = true;
			}
		}
		self::checkquery($sql);
		$ret = self::$db->query($sql, $silent, $unbuffered);
		if (!$unbuffered && $ret) {
			$cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if ($cmd === 'SELECT') {

			} elseif ($cmd === 'UPDATE' || $cmd === 'DELETE') {
				$ret = self::$db->affected_rows();
			} elseif ($cmd === 'INSERT') {
				$ret = self::$db->insert_id();
			}
		}
		return $ret;
	}

	public static function num_rows($resourceid) {
		return self::$db->num_rows($resourceid);
	}

	public static function affected_rows() {
		return self::$db->affected_rows();
	}

	public static function free_result($query) {
		return self::$db->free_result($query);
	}

	public static function error() {
		return self::$db->error();
	}

	public static function errno() {
		return self::$db->errno();
	}

	public static function checkquery($sql) {
		return discuz_database_safecheck::checkquery($sql);
	}

	public static function quote($str, $noarray = false) {

		if (is_string($str))
			return '\'' . addcslashes($str, "\n\r\\'\"\032") . '\'';

		if (is_int($str) or is_float($str))
			return '\'' . $str . '\'';

		if (is_array($str)) {
			if($noarray === false) {
				foreach ($str as &$v) {
					$v = self::quote($v, true);
				}
				return $str;
			} else {
				return '\'\'';
			}
		}

		if (is_bool($str))
			return $str ? '1' : '0';

		return '\'\'';
	}

	public static function quote_field($field) {
		if (is_array($field)) {
			foreach ($field as $k => $v) {
				$field[$k] = self::quote_field($v);
			}
		} else {
			if (strpos($field, '`') !== false)
				$field = str_replace('`', '', $field);
			$field = '`' . $field . '`';
		}
		return $field;
	}

	public static function limit($start, $limit = 0) {
		$limit = intval($limit > 0 ? $limit : 0);
		$start = intval($start > 0 ? $start : 0);
		if ($start > 0 && $limit > 0) {
			return " LIMIT $start, $limit";
		} elseif ($limit) {
			return " LIMIT $limit";
		} elseif ($start) {
			return " LIMIT $start";
		} else {
			return '';
		}
	}

	public static function order($field, $order = 'ASC') {
		if(empty($field)) {
			return '';
		}
		$order = strtoupper($order) == 'ASC' || empty($order) ? 'ASC' : 'DESC';
		return self::quote_field($field) . ' ' . $order;
	}

	public static function field($field, $val, $glue = '=') {

		$field = self::quote_field($field);

		if (is_array($val)) {
			$glue = $glue == 'notin' ? 'notin' : 'in';
		} elseif ($glue == 'in') {
			$glue = '=';
		}

		switch ($glue) {
			case '=':
				return $field . $glue . self::quote($val);
				break;
			case '-':
			case '+':
				return $field . '=' . $field . $glue . self::quote((string) $val);
				break;
			case '|':
			case '&':
			case '^':
				return $field . '=' . $field . $glue . self::quote($val);
				break;
			case '>':
			case '<':
			case '<>':
			case '<=':
			case '>=':
				return $field . $glue . self::quote($val);
				break;

			case 'like':
				return $field . ' LIKE(' . self::quote($val) . ')';
				break;

			case 'in':
			case 'notin':
				$val = $val ? implode(',', self::quote($val)) : '\'\'';
				return $field . ($glue == 'notin' ? ' NOT' : '') . ' IN(' . $val . ')';
				break;

			default:
				throw new DbException('Not allow this glue between field and value: "' . $glue . '"');
		}
	}

	public static function implode($array, $glue = ',') {
		$sql = $comma = '';
		$glue = ' ' . trim($glue) . ' ';
		foreach ($array as $k => $v) {
			$sql .= $comma . self::quote_field($k) . '=' . self::quote($v);
			$comma = $glue;
		}
		return $sql;
	}

	public static function implode_field_value($array, $glue = ',') {
		return self::implode($array, $glue);
	}

	public static function format($sql, $arg) {
		$count = substr_count($sql, '%');
		if (!$count) {
			return $sql;
		} elseif ($count > count($arg)) {
			throw new DbException('SQL string format error! This SQL need "' . $count . '" vars to replace into.', 0, $sql);
		}

		$len = strlen($sql);
		$i = $find = 0;
		$ret = '';
		while ($i <= $len && $find < $count) {
			if ($sql{$i} == '%') {
				$next = $sql{$i + 1};
				if ($next == 't') {
					$ret .= self::table($arg[$find]);
				} elseif ($next == 's') {
					$ret .= self::quote(is_array($arg[$find]) ? serialize($arg[$find]) : (string) $arg[$find]);
				} elseif ($next == 'f') {
					$ret .= sprintf('%F', $arg[$find]);
				} elseif ($next == 'd') {
					$ret .= dintval($arg[$find]);
				} elseif ($next == 'i') {
					$ret .= $arg[$find];
				} elseif ($next == 'n') {
					if (!empty($arg[$find])) {
						$ret .= is_array($arg[$find]) ? implode(',', self::quote($arg[$find])) : self::quote($arg[$find]);
					} else {
						$ret .= '0';
					}
				} else {
					$ret .= self::quote($arg[$find]);
				}
				$i++;
				$find++;
			} else {
				$ret .= $sql{$i};
			}
			$i++;
		}
		if ($i < $len) {
			$ret .= substr($sql, $i);
		}
		return $ret;
	}

}

class discuz_database_safecheck {

	protected static $checkcmd = array('SEL'=>1, 'UPD'=>1, 'INS'=>1, 'REP'=>1, 'DEL'=>1);
	protected static $config;

	public static function checkquery($sql) {
		if (self::$config === null) {
			self::$config = getglobal('config/security/querysafe');
		}
		if (self::$config['status']) {
			$check = 1;
			$cmd = strtoupper(substr(trim($sql), 0, 3));
			if(isset(self::$checkcmd[$cmd])) {
				$check = self::_do_query_safe($sql);
			} elseif(substr($cmd, 0, 2) === '/*') {
				$check = -1;
			}

			if ($check < 1) {
				throw new DbException('It is not safe to do this query', 0, $sql);
			}
		}
		return true;
	}

	private static function _do_query_safe($sql) {
		$sql = str_replace(array('\\\\', '\\\'', '\\"', '\'\''), '', $sql);
		$mark = $clean = '';
		if (strpos($sql, '/') === false && strpos($sql, '#') === false && strpos($sql, '-- ') === false && strpos($sql, '@') === false && strpos($sql, '`') === false) {
			$clean = preg_replace("/'(.+?)'/s", '', $sql);
		} else {
			$len = strlen($sql);
			$mark = $clean = '';
			for ($i = 0; $i < $len; $i++) {
				$str = $sql[$i];
				switch ($str) {
					case '`':
						if(!$mark) {
							$mark = '`';
							$clean .= $str;
						} elseif ($mark == '`') {
							$mark = '';
						}
						break;
					case '\'':
						if (!$mark) {
							$mark = '\'';
							$clean .= $str;
						} elseif ($mark == '\'') {
							$mark = '';
						}
						break;
					case '/':
						if (empty($mark) && $sql[$i + 1] == '*') {
							$mark = '/*';
							$clean .= $mark;
							$i++;
						} elseif ($mark == '/*' && $sql[$i - 1] == '*') {
							$mark = '';
							$clean .= '*';
						}
						break;
					case '#':
						if (empty($mark)) {
							$mark = $str;
							$clean .= $str;
						}
						break;
					case "\n":
						if ($mark == '#' || $mark == '--') {
							$mark = '';
						}
						break;
					case '-':
						if (empty($mark) && substr($sql, $i, 3) == '-- ') {
							$mark = '-- ';
							$clean .= $mark;
						}
						break;

					default:

						break;
				}
				$clean .= $mark ? '' : $str;
			}
		}

		if(strpos($clean, '@') !== false) {
			return '-3';
		}

		$clean = preg_replace("/[^a-z0-9_\-\(\)#\*\/\"]+/is", "", strtolower($clean));

		if (self::$config['afullnote']) {
			$clean = str_replace('/**/', '', $clean);
		}

		if (is_array(self::$config['dfunction'])) {
			foreach (self::$config['dfunction'] as $fun) {
				if (strpos($clean, $fun . '(') !== false)
					return '-1';
			}
		}

		if (is_array(self::$config['daction'])) {
			foreach (self::$config['daction'] as $action) {
				if (strpos($clean, $action) !== false)
					return '-3';
			}
		}

		if (self::$config['dlikehex'] && strpos($clean, 'like0x')) {
			return '-2';
		}

		if (is_array(self::$config['dnote'])) {
			foreach (self::$config['dnote'] as $note) {
				if (strpos($clean, $note) !== false)
					return '-4';
			}
		}

		return 1;
	}

	public static function setconfigstatus($data) {
		self::$config['status'] = $data ? 1 : 0;
	}

}

class db_driver
{
	var $tablepre;
	var $version = '';
	var $drivertype = 'mysql';
	var $querynum = 0;
	var $slaveid = 0;
	var $curlink;
	var $link = array();
	var $config = array();
	var $sqldebug = array();
	var $map = array();

	function db_mysql($config = array()) {
		if(!empty($config)) {
			$this->set_config($config);
		}
	}

	function set_config($config) {
		$this->config = &$config;
		$this->tablepre = $config['1']['tablepre'];
	}

	function connect($serverid = 1) {

		if(empty($this->config) || empty($this->config[$serverid])) {
			$this->halt('config_db_not_found');
		}

		$this->link[$serverid] = $this->_dbconnect(
			$this->config[$serverid]['dbhost'],
			$this->config[$serverid]['dbuser'],
			$this->config[$serverid]['dbpw'],
			$this->config[$serverid]['dbcharset'],
			$this->config[$serverid]['dbname'],
			$this->config[$serverid]['pconnect']
			);
		$this->curlink = $this->link[$serverid];

	}

	function _dbconnect($dbhost, $dbuser, $dbpw, $dbcharset, $dbname, $pconnect, $halt = true) {

		if($pconnect) {
			$link = @mysql_pconnect($dbhost, $dbuser, $dbpw, MYSQL_CLIENT_COMPRESS);
		} else {
			$link = @mysql_connect($dbhost, $dbuser, $dbpw, 1, MYSQL_CLIENT_COMPRESS);
		}
		if(!$link) {
			$halt && $this->halt('notconnect', $this->errno());
		} else {
			$this->curlink = $link;
			if($this->version() > '4.1') {
				$dbcharset = $dbcharset ? $dbcharset : $this->config[1]['dbcharset'];
				$serverset = $dbcharset ? 'character_set_connection='.$dbcharset.', character_set_results='.$dbcharset.', character_set_client=binary' : '';
				$serverset .= $this->version() > '5.0.1' ? ((empty($serverset) ? '' : ',').'sql_mode=\'\'') : '';
				$serverset && mysql_query("SET $serverset", $link);
			}
			$dbname && @mysql_select_db($dbname, $link);
		}
		return $link;
	}

	function table_name($tablename) {
		if(!empty($this->map) && !empty($this->map[$tablename])) {
			$id = $this->map[$tablename];
			if(!$this->link[$id]) {
				$this->connect($id);
			}
			$this->curlink = $this->link[$id];
		} else {
			$this->curlink = $this->link[1];
		}
		return $this->tablepre.$tablename;
	}

	function select_db($dbname) {
		return mysql_select_db($dbname, $this->curlink);
	}

	function fetch_array($query, $result_type = MYSQL_ASSOC) {
		if($result_type == 'MYSQL_ASSOC') $result_type = MYSQL_ASSOC;
		return mysql_fetch_array($query, $result_type);
	}

	function fetch_first($sql) {
		return $this->fetch_array($this->query($sql));
	}

	function result_first($sql) {
		return $this->result($this->query($sql), 0);
	}

	public function query($sql, $silent = false, $unbuffered = false) {
		if(defined('DISCUZ_DEBUG') && DISCUZ_DEBUG) {
			$starttime = microtime(true);
		}

		if('UNBUFFERED' === $silent) {
			$silent = false;
			$unbuffered = true;
		} elseif('SILENT' === $silent) {
			$silent = true;
			$unbuffered = false;
		}

		$func = $unbuffered ? 'mysql_unbuffered_query' : 'mysql_query';

		if(!($query = $func($sql, $this->curlink))) {
			if(in_array($this->errno(), array(2006, 2013)) && substr($silent, 0, 5) != 'RETRY') {
				$this->connect();
				return $this->query($sql, 'RETRY'.$silent);
			}
			if(!$silent) {
				$this->halt($this->error(), $this->errno(), $sql);
			}
		}

		if(defined('DISCUZ_DEBUG') && DISCUZ_DEBUG) {
			$this->sqldebug[] = array($sql, number_format((microtime(true) - $starttime), 6), debug_backtrace(), $this->curlink);
		}

		$this->querynum++;
		return $query;
	}

	function affected_rows() {
		return mysql_affected_rows($this->curlink);
	}

	function error() {
		return (($this->curlink) ? mysql_error($this->curlink) : mysql_error());
	}

	function errno() {
		return intval(($this->curlink) ? mysql_errno($this->curlink) : mysql_errno());
	}

	function result($query, $row = 0) {
		$query = @mysql_result($query, $row);
		return $query;
	}

	function num_rows($query) {
		$query = mysql_num_rows($query);
		return $query;
	}

	function num_fields($query) {
		return mysql_num_fields($query);
	}

	function free_result($query) {
		return mysql_free_result($query);
	}

	function insert_id() {
		return ($id = mysql_insert_id($this->curlink)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	function fetch_row($query) {
		$query = mysql_fetch_row($query);
		return $query;
	}

	function fetch_fields($query) {
		return mysql_fetch_field($query);
	}

	function version() {
		if(empty($this->version)) {
			$this->version = mysql_get_server_info($this->curlink);
		}
		return $this->version;
	}

	function escape_string($str) {
		return mysql_escape_string($str);
	}

	function close() {
		return mysql_close($this->curlink);
	}

	function halt($message = '', $code = 0, $sql = '') {
		throw new DbException($message, $code, $sql);
	}

}

?>