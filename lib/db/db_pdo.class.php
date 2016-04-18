<?php
/*==================================================*/
/* 文件名：db_pdo.class.php	by	ncwsky				*/
/*==================================================*/
class db_pdo extends db_abstract{
	private $dbms = 'mysql'; //数据库名
	// 连接数据库
    public function connect($cfg_db = array()) {
		//建立新连接 不返回已经打开的连接标识
		$options = array();
		$this->dbms = $cfg_db['dbms'];
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
			unset($cfg_db);// 注销数据库连接配置信息
		} catch(PDOException $e) {
			exit("Error!: " . $e->getMessage() . "</br>");
		}
    }

	//对sql部分语句进行转换
	protected function chksql(&$sql) {
		//$sql = str_replace(array('[', ']'), '`', $sql);
		switch($this->dbms){
			case 'mssql':
			case 'oracle':
			case 'pgsql':
			case 'sqlite':
				$sql = str_replace('`', '"', $sql);break;
		}
		
		if (stripos($sql, 'select top')!==FALSE) {
			//([0-9]+(,[0-9]+)?) | ([0-9]+)
			if (preg_match('/^(select top )([0-9]+(,[0-9]+)?)/i', $sql, $regArr)) {
				$sql = str_replace($regArr[0], "SELECT ", $sql);
				$pos = strpos($regArr[2],',');
				$limit = 0;
				if($pos!==FALSE) {
					$offset = substr($regArr[2],0,$pos);
					$limit = substr($regArr[2],$pos+1);
				}else {
					$offset = $regArr[2];
				}
				if($limit==0){
					$max = $offset;$min = 0;
				}else{
					$max = $offset+$limit;$min = $offset;	
				}
				switch($this->dbms){
					case 'mysql':
						$sql .= ' LIMIT ' . $regArr[2];
						break;
					case 'mssql'://mssql2005及以上版本
						$order = '(order by .* (asc|desc))';
						if(preg_match('/(order by .* (asc|desc))/i', $sql, $order)){
							$sql = str_replace($order[0], '', $sql);
							$sql = 'SELECT T1.* FROM (SELECT myphp.*, ROW_NUMBER() OVER ('.$order[0].') AS ROW_NUMBER FROM ('.$sql.') AS myphp) AS T1 WHERE (T1.ROW_NUMBER BETWEEN '.$min.'+1 AND '.$max.')';
						}else{
							$sql = preg_replace('/^SELECT /', 'SELECT TOP '.$max, $sql);
							if($min>0) {
								$sql = 'SELECT TOP '.$min.' * FROM ('.$sql.') AS T1';
							}
						}
						break;
					case 'oracle':
						$sql = 'SELECT * FROM ( SELECT "tmp".*, ROWNUM AS "row_num" FROM ('.$sql.') "tmp" WHERE ROWNUM<='.$max.') WHERE "row_num">'.$min;
						break;
					case 'pgsql':
					case 'sqlite':
						if($min>0) {
							$sql .= ' LIMIT ' .$max.' OFFSET '.$min;
						}else{
							$sql .= ' LIMIT ' .$max;
						}
						break;
				}
			}
			unset($regArr);
		}
		if (stripos($sql, 'CONCAT')!==FALSE) {
			switch($this->dbms){
				case 'mysql':
					break;
				case 'mssql'://mssql2005及以上版本
				case 'oracle':
				case 'pgsql':
				case 'sqlite':
					$sql = preg_replace_callback('/CONCAT(\([^\)]*?\))/i',$this->concat($matches),$sql);
					break;
			}
		}
	}
	private function concat($matches){return str_replace(',','+',$matches[1]);}
	
	//执行查询语句
	public function query($sql) {
		if (empty($sql)) return FALSE;
		$this->chksql($sql);//对sql部分语句进行转换
		//if($this->rs && $this->rs!==TRUE) $this->free();
		$this->rs = $this->conn->query($sql);
		if($this->conn->errorCode() != '00000') {
			print_r($this->conn->errorInfo());
			exit("SQL语句执行错误：$sql <br />");
		}
		return $this->rs;
	}
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $type : 默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
	 */
	public function fetch_array($query, $type = 'assoc') {
		if($type=='assoc') $query->setFetchMode(PDO::FETCH_ASSOC);
		elseif($type=='num') $query->setFetchMode(PDO::FETCH_NUM);
		else $query->setFetchMode(PDO::FETCH_BOTH);

		return $query->fetch();
	}

	//取得前一次 MySQL 操作所影响的记录行数
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