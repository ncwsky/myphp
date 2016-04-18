<?php
/*==================================================*/
/* 文件名：db_mysql.class.php	by	ncwsky			*/
/*==================================================*/
class db_mysql extends db_abstract{
	// 连接数据库
    public function connect($cfg_db = array()) {
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
		mysql_select_db($cfg_db['name'], $this->conn) or exit('未找到指定的数据库（'. $cfg_db['name'] .'）');
		unset($cfg_db);// 注销数据库连接配置信息
    }
	//SQL安全过滤
    public function escape_string($str) {
        return str_replace("'", "\'", $str);
    }
	//对sql部分语句进行转换
	protected function chksql(&$sql) {
		//$sql = str_replace(array('[', ']'), '`', $sql);
		if (stripos($sql, 'select top')!==FALSE) {
			//([0-9]+(,[0-9]+)?) | ([0-9]+)
			if (preg_match ('/^(select top )([0-9]+(,[0-9]+)?)/i', $sql, $regArr)) {
				$sql = str_replace($regArr[0], "select ", $sql) . ' LIMIT ' . $regArr[2];
			}
			unset($regArr);
		}
		//$sql .= ';';
	}
	//执行查询语句
	public function query($sql) {
		if (empty($sql)) return FALSE;
		set_time_limit (60 * 60);
		$this->chksql($sql);//对sql部分语句进行转换
		//if($this->rs && $this->rs!==TRUE) $this->free();
		$this->rs = mysql_query($sql, $this->conn) or exit("SQL语句执行错误：$sql <br />" . mysql_error());
		return $this->rs;
	}
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $type : 默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
	 */
	public function fetch_array($query, $type = 'assoc') {
		if($type=='assoc') $type = MYSQL_ASSOC;
		elseif($type=='num') $type = MYSQL_NUM;
		else $type = MYSQL_BOTH;

		return mysql_fetch_array($query, $type);
	}
	//取得前一次 MySQL 操作所影响的记录行数
	public function num_rows() {
		return mysql_affected_rows($this->conn);
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