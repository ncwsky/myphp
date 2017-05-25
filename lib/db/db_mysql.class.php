<?php
/*==================================================*/
/* 文件名：db_mysql.class.php	by	ncwsky			*/
/*==================================================*/
class db_mysql extends db_abstract{
	// 连接数据库
    public function connect(&$cfg_db) {
		//建立新连接 不返回已经打开的连接标识
		$cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : FALSE;
		if($cfg_db['pconnect']){//
			$this->conn = mysql_pconnect($cfg_db['server'].(empty($cfg_db['port']) ? '' : ':'.$cfg_db['port']), $cfg_db['user'], $cfg_db['pwd'], 131072);
		}else{
			$this->conn = mysql_connect($cfg_db['server'].(empty($cfg_db['port']) ? '' : ':'.$cfg_db['port']), $cfg_db['user'], $cfg_db['pwd'], TRUE, 131072);
		}
		if(!$this->conn) exit('数据库连接出错！<br>'. mysql_error());
		
		if (!empty($cfg_db['char'])) 
			mysql_query('SET NAMES `'.$cfg_db['char'].'`', $this->conn);
		mysql_select_db($cfg_db['name'], $this->conn) or exit('Can not find DB ('. $cfg_db['name'] .')');
    }
	//SQL安全过滤
    public function quote($str) {
        return "'". mysql_escape_string($str) ."'";
    }
	//执行查询语句
	public function exec(&$sql) {
		$result = mysql_query($sql, $this->conn);
		if($result===false) {
			throw new myException("SQL exec: $sql | " . mysql_error());
			exit;
		}
		return $result?mysql_affected_rows():$result;
	}
	//执行查询语句
	public function query(&$sql) {
		set_time_limit (60 * 60);
		$this->rs = mysql_query($sql, $this->conn);
		if($this->rs===false) {
			throw new myException("SQL query: $sql | " . mysql_error());
			exit;
		}
		return $this->rs;
	}
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $type : 默认MYSQL_ASSOC 关联,MYSQL_NUM 数字,MYSQL_BOTH 两者
	 */
	public function fetch_array(&$query, $type = 'assoc') {
		if($type=='assoc') $type = MYSQL_ASSOC;
		elseif($type=='num') $type = MYSQL_NUM;
		else $type = MYSQL_BOTH;

		return mysql_fetch_array($query, $type);
	}
	//结果集行数
	public function num_rows() {
		return mysql_num_rows($this->rs);
	}
	//取得结果集中字段的数目
	public function num_fields() {
		return mysql_num_fields($this->rs);
	}
	//取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
	public function insert_id() {
        //如果 AUTO_INCREMENT 的列的类型是 BIGINT，则 mysql_insert_id() 返回的值将不正确。
        //可以在 SQL 查询中用 MySQL 内部的 SQL 函数 LAST_INSERT_ID() 来替代。
        //$rs = mysql_query("Select LAST_INSERT_ID() as lid",$this->linkID);
        //$row = mysql_fetch_array($rs);
        //return $row["lid"];
		return mysql_insert_id($this->conn);
	}
}