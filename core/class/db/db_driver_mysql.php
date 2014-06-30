<?php

class db_driver_mysql
{
	var $tablepre;
	var $version = '';
	var $querynum = 0;
	var $curlink;
	var $config = array();
	var $sqldebug = array();

	function db_mysql($config = array()) {
		if(!empty($config)) {
			$this->set_config($config);
		}
	}

	function set_config($config) {
		$this->config = &$config;
		$this->tablepre = $config['tablepre'];
	}

	function connect() {

		if(empty($this->config)) {
			$this->halt('config_db_not_found');
		}

		$this->link = $this->_dbconnect(
			$this->config['host'],
			$this->config['user'],
			$this->config['password'],
			$this->config['charset'],
			$this->config['dbname']
		);
		$this->curlink = $this->link;

	}

	function _dbconnect($dbhost, $dbuser, $dbpw, $dbcharset, $dbname, $halt = true) {

		$link = @mysql_connect($dbhost, $dbuser, $dbpw, 1, MYSQL_CLIENT_COMPRESS);
		if(!$link) {
			$halt && $this->halt('notconnect');
		} else {
			$this->curlink = $link;
			if($this->version() > '4.1') {
				$dbcharset = $dbcharset ? $dbcharset : $this->config['dbcharset'];
				$serverset = $dbcharset ? 'character_set_connection='.$dbcharset.', character_set_results='.$dbcharset.', character_set_client=binary' : '';
				$serverset .= $this->version() > '5.0.1' ? ((empty($serverset) ? '' : ',').'sql_mode=\'\'') : '';
				$serverset && mysql_query("SET $serverset", $link);
			}
			$dbname && @mysql_select_db($dbname, $link);
		}
		return $link;
	}

	function table_name($tablename) {
		return $this->tablepre.$tablename;
	}

	function fetch_array($query, $result_type = MYSQL_ASSOC) {
		if($result_type == 'MYSQL_ASSOC') $result_type = MYSQL_ASSOC;
		return mysql_fetch_array($query, $result_type);
	}

	public function query($sql, $unbuffered) {

		if(!($query = mysql_query($sql, $this->curlink))) {
			if(in_array($this->errno(), array(2006, 2013))) {
				$this->connect();
				return $this->query($sql);
			}
			$this->halt($this->error(), $this->errno(), $sql);
		}

		$this->querynum = $this->querynum + 1;
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

	function halt($message = '', $sql = '') {
		system_error($message, "DB", $sql);
	}
}