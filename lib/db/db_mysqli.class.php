<?php
/*==================================================*/
/* 文件名：db_mysqli.class.php	by	ncwsky			*/
/*==================================================*/
class db_mysqli extends db_abstract{
	// 连接数据库
    public function connect(&$cfg_db) {
		//建立新连接 不返回已经打开的连接标识
		$cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : FALSE;
		$this->conn = new mysqli($cfg_db['server'].(empty($cfg_db['port']) ? '' : ':'.$cfg_db['port']), $cfg_db['user'], $cfg_db['pwd']);

		if($this->conn->connect_error) exit('DB Connect Error ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
		
		if (!empty($cfg_db['char'])) 
			$this->conn->query('SET NAMES `'.$cfg_db['char'].'`');
		
		$this->conn->select_db($cfg_db['name']) or exit('Can not find DB ('. $cfg_db['name'] .')');
    }
	//SQL安全过滤
    public function quote($str) {
        return "'". $this->conn->real_escape_string($str) ."'";
    }
	//执行查询语句
	public function exec(&$sql) {
		$result = $this->conn->query($sql);
		if($result===false) {
			throw new myException("SQL exec: $sql | " . $this->conn->error);
			exit;
		}
		return $result?$this->conn->affected_rows:$result;
	}
	//执行查询语句
	public function query(&$sql) {
		set_time_limit (60 * 60);
		$this->rs = $this->conn->query($sql);
		if($this->rs===false) {
			throw new myException("SQL query: $sql | " . $this->conn->error);
			exit;
		}
		return $this->rs;
	}
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $type : 默认MYSQLI_ASSOC关联,MYSQLI_NUM 数字,MYSQLI_BOTH 两者
	 */
	public function fetch_array(&$query, $type = 'assoc') {
		if($type=='assoc') $type = MYSQLI_ASSOC;
		elseif($type=='num') $type = MYSQLI_NUM;
		else $type = MYSQLI_BOTH;

		return $query->fetch_array($type);
	}
	//结果集行数
	public function num_rows() {
		return $this->rs->num_rows;
	}
	//取得结果集中字段的数目
	public function num_fields() {
		return $this->conn->field_count;
	}
	//取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
	public function insert_id() {
		return $this->conn->insert_id;
	}
}