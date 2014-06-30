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

	public static function init($config) {
		self::$db = new db_driver_mysql;
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
		$limit = intval($limit);
		$sql = "DELETE FROM " . self::table($table) . " WHERE $where " . ($limit > 0 ? "LIMIT $limit" : '');
		return self::query($sql, ($unbuffered ? 'UNBUFFERED' : ''));
	}

	public static function insert($table, $data, $return_insert_id = false) {

		$sql = self::implode($data);

		$table = self::table($table);

		return self::query("INSERT INTO $table SET $sql", null, !$return_insert_id);
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

	public static function fetch_first($sql, $arg = array()) {
		$res = self::query($sql, $arg);
		$ret = self::$db->fetch_array($res);
		self::$db->free_result($res);
		return $ret ? $ret : array();
	}

	public static function fetch_all($sql, $arg = array(), $keyfield = '') {

		$data = array();
		$query = self::query($sql, $arg, false);
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

	public static function result_first($sql, $arg = array()) {
		$res = self::query($sql, $arg, false);
		$ret = self::$db->result($res);
		self::$db->free_result($res);
		return $ret;
	}

	public static function query($sql, $arg = array(), $unbuffered = false) {
		if (!empty($arg)) {
			if (is_array($arg)) {
				$sql = self::format($sql, $arg);
			} elseif ($arg === 'UNBUFFERED') {
				$unbuffered = true;
			}
		}
		self::checkquery($sql);
		$ret = self::$db->query($sql, $unbuffered);
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
			self::$db->halt('SQL string format error! This SQL need "' . $count . '" vars to replace into.', $sql);
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
			$querysafe_config = array();
			$querysafe_config['status'] = 1;
			$querysafe_config['dfunction']['0'] = 'load_file';
			$querysafe_config['dfunction']['1'] = 'hex';
			$querysafe_config['dfunction']['2'] = 'substring';
			$querysafe_config['dfunction']['3'] = 'if';
			$querysafe_config['dfunction']['4'] = 'ord';
			$querysafe_config['dfunction']['5'] = 'char';
			$querysafe_config['daction']['0'] = '@';
			$querysafe_config['daction']['1'] = 'intooutfile';
			$querysafe_config['daction']['2'] = 'intodumpfile';
			$querysafe_config['daction']['3'] = 'unionselect';
			$querysafe_config['daction']['4'] = '(select';
			$querysafe_config['daction']['5'] = 'unionall';
			$querysafe_config['daction']['6'] = 'uniondistinct';
			$querysafe_config['dnote']['0'] = '/*';
			$querysafe_config['dnote']['1'] = '*/';
			$querysafe_config['dnote']['2'] = '#';
			$querysafe_config['dnote']['3'] = '--';
			$querysafe_config['dnote']['4'] = '"';
			$querysafe_config['dlikehex'] = 1;
			$querysafe_config['afullnote'] = '0';
			self::$config = $querysafe_config;
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
}