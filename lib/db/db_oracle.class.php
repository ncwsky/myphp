<?php
/*==================================================*/
/* 文件名：db_oracle.class.php	by	ncwsky			*/
/*==================================================*/
class db_oracle extends db_abstract{
	// 连接数据库
    public function connect($cfg_db = array()) {
		//建立新连接 不返回已经打开的连接标识
		$cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : FALSE;
		$conn = $cfg_db['pconnect'] ? 'oci_pconnect':'oci_new_connect';
		if (!function_exists($conn)) exit('oracle扩展可能未开启！');
		$db = '//'.$cfg_db['server'].':'.$cfg_db['port'].'/'.$cfg_db['name'];
		$this->conn = $conn($cfg_db['user'], $cfg_db['pwd'], $db, $cfg_db['char']);
		if(!$this->conn) exit('数据库连接出错！<br>'. oci_error());
		
		unset($cfg_db);// 注销数据库连接配置信息
    }
	//
    public function escape_string($str) {
        return str_replace("'", "''", $str);
    }
	//对sql部分语句进行转换
	protected function chksql(&$sql) {
		$sql = str_replace('`', '"', $sql);
		$this->sql = $sql;
		
		if (stripos($sql, 'select top')!==FALSE) {
			//([0-9]+(,[0-9]+)?) | ([0-9]+)
			if (preg_match ('/^(select top )([0-9]+(,[0-9]+)?)/i', $sql, $regArr)) {
				$sql = str_replace($regArr[0], "select ", $sql);
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
				$sql = 'SELECT * FROM ( SELECT "tmp".*, ROWNUM AS "row_num" FROM ('.$sql.') "tmp" WHERE ROWNUM<='.$max.') WHERE "row_num">'.$min.'';//;
			}
			unset($regArr);
		}

	}
	//执行查询语句
	public function query($sql) {
		if (empty($sql)) return FALSE;
		$this->chksql($sql);//对sql部分语句进行转换
		if($this->rs && $this->rs!==TRUE) $this->free();
		$this->rs = oci_parse($this->conn, $sql);
		oci_execute($this->rs) or exit("SQL语句执行错误：$sql <br />" . oci_error());//;//$this->rs = 
		return $this->rs;
	}
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $type : 默认OCI_ASSOC 关联，OCI_NUM  数字，OCI_BOTH 两者
	 */
	public function fetch_array($query, $type = 'assoc') {
		/*
		if($type=='assoc') $type = OCI_ASSOC;
		elseif($type=='num') $type = OCI_NUM;
		else $type = OCI_BOTH;
		return oci_fetch_array($query, $type);
		*/
		if(oci_fetch($query)){
			$row = array();
			$ncols = oci_num_fields($query);
			for ($i = 1; $i <= $ncols; $i++) {
				$column_name  = oci_field_name($query, $i);
				$column_value = rtrim(oci_result($query, $i),' ');
				if($type=='assoc') {
					$row[$column_name] = $column_value;
				}elseif($type=='num') {
					$row[$i-1] = $column_value;
				}else {
					$row[$column_name] = $column_value;
					$row[$i-1] = $column_value;
				};
			}
		}else{
			$row = FALSE;
		}
		return $row;
	}
	// 取操作所影响的记录行数
	public function num_rows() {
		return oci_num_rows($this->rs);
	}
	//取得结果集中字段的数目
	public function num_fields() {
		return oci_num_fields($this->rs);
	}
	//获取最后插入id ,仅适用于采用序列+触发器结合生成ID的方式
	public function insert_id() {
		$flag = FALSE;$sequenceName='';

		if(preg_match("/^\s*(INSERT\s+INTO)\s+\"(\w+)\"\s+/i", $this->sql, $match)) {
			$sequenceName = 'seq_'.$match[2].'_id';//注意序列的命名方式
			$flag = (boolean)$this->query("SELECT * FROM user_sequences WHERE sequence_name='" . strtoupper($sequenceName) . "'");
        }
        if(!flag) return 0;

        $vo = $this->fetch_array($this->query("SELECT {$sequenceName}.currval currval FROM dual"));
        return $vo ? $vo[0]["currval"] : 0;
	}
}