<?php
/*==================================================*/
/* 文件名：db_pdo.class.php	by	ncwsky				*/
/*==================================================*/
class db_pdo extends db_abstract{
	// 连接数据库
    public function connect(&$cfg_db) {
		//建立新连接 不返回已经打开的连接标识
		$options = array();
		if(empty($cfg_db['dsn'])){//未设置dsn时
			switch($cfg_db['dbms']){
				case 'mysql':// PDO_MYSQL DSN
					$dsn = 'mysql:host='.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ';port='.$cfg_db['port']).';dbname='.$cfg_db['name'];//.(empty($cfg_db['char']) ? '' : ';charset='.strtoupper($cfg_db['char']));
					if(!empty($cfg_db['char'])) $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \''. strtoupper($cfg_db['char']) .'\'';
					break;
				case 'mssql':// PDO_SQLSRV
					$dsn = 'sqlsrv:Server='.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ','.$cfg_db['port']).';Database='.$cfg_db['name'];break;
				case 'oracle':// PDO_OCI DSN
					$dsn = 'oci:dbname=//'.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ':'.$cfg_db['port']).'/'.$cfg_db['name'].(empty($cfg_db['char']) ? '' : ';charset='.$cfg_db['char']);break;
				case 'pgsql':// PDO_PGSQL DSN
					$dsn = 'pgsql:host='.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ';port='.$cfg_db['port']).';dbname='.$cfg_db['name'];break;
				case 'sqlite':// PDO_SQLITE DSN @sqlite:/opt/databases/mydb.sq3
					$dsn = 'sqlite:'.$cfg_db['name'];break;
				default:// PDO_DBLIB DSN
					$dsn = $cfg_db['dbms'].':host='.$cfg_db['server'].';dbname='.$cfg_db['name'].(empty($cfg_db['char']) ? '' : ';charset='.$cfg_db['char']);break;
			}
		}else{
			$dsn = $cfg_db['dsn'];
		}
		$cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : FALSE;
		if($cfg_db['pconnect']) {//持久连接开启
			$options[PDO::ATTR_PERSISTENT] = TRUE;
		}
		if(version_compare(PHP_VERSION,'5.3.6','<=')){//禁用模拟预处理语句
			$options[PDO::ATTR_EMULATE_PREPARES] = FALSE;
		}

		try { 
			$this->conn = new PDO($dsn, $cfg_db['user'], $cfg_db['pwd'], $options);
		} catch(PDOException $e) {
			Log::ERROR('dsn:'.$dsn."\n".$e->getMessage());
			exit("Error!: " . $e->getMessage() . "</br>");
		}
    }
	//SQL安全过滤
    public function quote($str) {
        return $this->conn->quote($str);
    }
	
	//执行sql 返回影响的行数
	public function exec(&$sql){
		$affected = $this->conn->exec($sql);
		if($this->conn->errorCode() != '00000') {
			$error_info = implode('|', $this->conn->errorInfo());
			if(strpos($error_info,'Function sequence error')){ //linux下因驱动原因的错误
				$this->rs = $this->conn->prepare($sql); //PDOStatement 对象
				$this->rs->execute(); 
				if($this->rs->errorCode() != '00000') { 
					throw new myException("SQL exec: $sql | ". implode('|', $this->rs->errorInfo()));
					exit;
				}
				$affected = $this->rs->rowCount();
			}else{
				throw new myException("SQL exec: $sql | ". $error_info);
				exit;
			}
		}
		return $affected;
	}
	//执行查询语句
	public function query(&$sql) {
		$this->rs = $this->conn->query($sql);
		if($this->conn->errorCode() != '00000') {
			throw new myException("SQL query: $sql | ". implode('|', $this->conn->errorInfo()));
			exit;
		}
		return $this->rs;
	}
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $type : 默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
	 */
	public function fetch_array(&$query, $type = 'assoc') {
		if($type=='assoc') $query->setFetchMode(PDO::FETCH_ASSOC);
		elseif($type=='num') $query->setFetchMode(PDO::FETCH_NUM);
		else $query->setFetchMode(PDO::FETCH_BOTH);

		return $query->fetch();
	}

	//结果集行数
	public function num_rows() {
		return $this->rs->rowCount();
	}

	//取得结果集中字段的数目
	public function num_fields() {
		return $this->rs->columnCount();
	}
	//取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
	public function insert_id() {
		return $this->conn->lastInsertId();//PDO::lastInsertId();
	}
}