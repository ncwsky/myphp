<?php
/**
 * Class db_mysqli
 * @property mysqli $conn
 * @property mysqli_result $rs
 */
class db_mysqli extends DbBase{
	// 连接数据库
    public function connect() {
		$cfg_db = &$this->config;
		//建立新连接 不返回已经打开的连接标识
		$cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : false;
		$this->conn = new mysqli($cfg_db['server'].(empty($cfg_db['port']) ? '' : ':'.$cfg_db['port']), $cfg_db['user'], $cfg_db['pwd']);

		if($this->conn->connect_error) {
			throw new Exception('mysql connect error!'. $this->conn->connect_errno.':'.$this->conn->connect_error);
		}
		
		if (!empty($cfg_db['char'])) 
			$this->conn->query('SET NAMES `'.$cfg_db['char'].'`');
		
		/*if(!$this->conn->select_db($cfg_db['name'])){
			throw new Exception('Can not use '. $cfg_db['name'] .'!'. mysqli_error($this->conn));
		}*/
    }
    /** SQL安全过滤
     * @param $str
     * @return string
     */
    public function quote($str) {
        return "'". $this->conn->real_escape_string($str) ."'";
    }

    /** 执行sql
     * @param $sql
     * @return bool|int|mysqli_result
     * @throws Exception
     */
	public function exec($sql) {
		$result = $this->conn->query($sql);
		if($result===false) {
			if(IS_CLI && ($this->conn->errno=='2006' || $this->conn->errno=='2013')){ //重连 MySQL server has gone away
				$this->connect();
				Log::write('重连 '.$this->conn->error, 'db_connect');
				return $this->exec($sql);
			}
			throw new Exception("SQL exec: {$sql} | {$this->conn->errno} | {$this->conn->error}");
		}
		return $result?$this->conn->affected_rows:$result;
	}

    /** 执行查询语句
     * @param $sql
     * @return bool|mysqli_result
     * @throws Exception
     */
	public function query($sql) {
		$this->rs = $this->conn->query($sql);
		if($this->rs===false) {
			if(IS_CLI && ($this->conn->errno=='2006' || $this->conn->errno=='2013')){
				$this->connect();
				Log::write('重连 '.$this->conn->error, 'db_connect');
                return $this->query($sql);
			}
			throw new Exception("SQL query: {$sql} | {$this->conn->errno} | {$this->conn->error}");
		}
		return $this->rs;
	}

    /** 返回所有行的数组
     * @param $sql
     * @param string $type
     * @return array
     */
	public function queryAll($sql, $type = 'assoc'){
        if($type=='assoc') $type = MYSQLI_ASSOC;
        elseif($type=='num') $type = MYSQLI_NUM;
        else $type = MYSQLI_BOTH;
        return $this->query($sql)->fetch_all($type);
    }

    /**
     * 从结果集中取得一行作为关联数组/数字索引数组
     * @param mysqli_result $query
     * @param string $type 默认MYSQLI_ASSOC关联,MYSQLI_NUM 数字,MYSQLI_BOTH 两者
     * @return mixed
     */
	public function fetch_array($query, $type = 'assoc') {
		if($type=='assoc') $type = MYSQLI_ASSOC;
		elseif($type=='num') $type = MYSQLI_NUM;
		else $type = MYSQLI_BOTH;

		return $query->fetch_array($type);
	}

    /**
     * 结果集行数
     * @return int
     */
	public function num_rows() {
		return $this->rs->num_rows;
	}

    /**
     * 取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
     * @return mixed
     */
	public function insert_id() {
		return $this->conn->insert_id;
	}
}