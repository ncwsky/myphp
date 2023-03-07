<?php
namespace myphp\db;

use PDO;
use PDOStatement;
use PDOException;
use myphp\Log;

/**
 * Class db_pdo
 * @property PDO $conn
 * @property PDOStatement $rs
 */
class db_pdo extends \myphp\DbBase{
	//连接数据库
    public function connect() {
		$cfg_db = &$this->config;
		//建立新连接 不返回已经打开的连接标识
        $options = empty($cfg_db['options']) ? array() : $cfg_db['options']; //array( PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => false);
		if(empty($cfg_db['dsn'])){//未设置dsn时
			switch($cfg_db['dbms']){
				case 'mysql':// PDO_MYSQL DSN
					$dsn = 'mysql:dbname='.$cfg_db['name'];
					if ($cfg_db['server']!='') {
						$dsn .= ';host='.$cfg_db['server'].($cfg_db['port']!=''?';port='.$cfg_db['port']:'');
					}elseif (!empty($cfg_db['socket'])) {
						$dsn .= ';unix_socket='. $cfg_db['socket'];
					}
					if($cfg_db['char']!=''){
						$dsn .= ';charset='.$cfg_db['char'];
						$options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \''. $cfg_db['char'] .'\'';
					}
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
		$cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : false;
		if($cfg_db['pconnect']) { //持久连接开启
			$options[PDO::ATTR_PERSISTENT] = TRUE;
		}
		/*if(empty($options[PDO::ATTR_TIMEOUT])){ //超时设置
            $options[PDO::ATTR_TIMEOUT] = 5;
        }*/
		/*if(version_compare(PHP_VERSION,'5.3.6','<=')){//禁用模拟预处理语句
			$options[PDO::ATTR_EMULATE_PREPARES] = false;
		}*/
		//PDO::MYSQL_ATTR_USE_BUFFERED_QUERY:false mysql非缓冲查询 查询大量数据时设置false不会出现内存不足的情况

		try {
			$this->conn = new PDO($dsn, $cfg_db['user'], $cfg_db['pwd'], $options);
		} catch(PDOException $e) {
            Log::write('dsn:' . $dsn . '|' . $e->getMessage(), 'db_connect');
			throw $e;
		}
    }

    /**
     * SQL安全过滤
     * @param $str
     * @return string
     */
    public function quote($str) {
        return $this->conn->quote($str);
    }

    /**
     * 执行sql 返回影响的行数
     * @param $sql
     * @param int $run
     * @return false|int
     */
	public function exec($sql, $run=0){
        $affected = $this->conn->exec($sql);
        if (false===$affected) { //$this->conn->errorCode() != '00000'
            $errorInfo = $this->conn->errorInfo();
            $errInfo = implode('|', $errorInfo);
            if ($run == 0 && $this->transCounter==0) { //重连1次处理 非事务时允许重连
                #MySQL server has gone away
                if ($this->config['dbms'] == 'mysql' && ($errorInfo[1] == 2006 || $errorInfo[1] == 2013)) {
                    $this->connect();
                    Log::write('重连 ' . $errInfo, 'db_connect');
                    return $this->exec($sql, 1);
                }
            }
            if (strpos($errInfo, 'Function sequence error')) { //linux下因驱动原因的错误
                $this->rs = $this->conn->prepare($sql); //PDOStatement 对象
                $this->rs->execute();
                if ($this->rs->errorCode() != '00000') {
                    throw new PDOException(implode('|', $this->rs->errorInfo())."; SQL exec: " . $sql);
                }
                $affected = $this->rs->rowCount();
            } else {
                throw new PDOException($errInfo."; SQL exec: " . $sql);
            }
        }
        return $affected;
	}

    /** 执行查询语句
     * @param $sql
     * @param int $run
     * @return false|PDOStatement
     * @throws PDOException
     */
    public function query($sql, $run = 0){
        $this->rs = $this->conn->query($sql);
        if (false===$this->rs) { //$this->conn->errorCode() != '00000'
            $errorInfo = $this->conn->errorInfo();
            $errInfo = implode('|', $errorInfo);
            if ($run == 0 && $this->transCounter==0) { //重连1次处理 非事务时允许重连
                #MySQL server has gone away
                if ($this->config['dbms'] == 'mysql' && ($errorInfo[1] == 2006 || $errorInfo[1] == 2013)) {
                    $this->connect();
                    Log::write('重连 ' . $errorInfo[2], 'db_connect');
                    return $this->query($sql, 1);
                }
            }
            throw new PDOException($errInfo . "; SQL query: " . $sql);
        }
        return $this->rs;
    }

    /** 返回所有行的数组
     * @param $sql
     * @param string $type
     * @return array|mixed
     * @throws PDOException
     */
    public function queryAll($sql, $type = 'assoc'){
        $style = PDO::FETCH_BOTH;
        if($type=='assoc') $style = PDO::FETCH_ASSOC;
        elseif($type=='num') $style = PDO::FETCH_NUM;
        //$sth = $this->conn->prepare($sql); $sth->execute();
        return $this->query($sql)->fetchAll($style);
    }

    /**
     * 从结果集中取得一行作为关联数组/数字索引数组
     * @param PDOStatement $query
     * @param string $type 默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
     * @return mixed
     */
	public function fetch_array($query, $type = 'assoc') {
        $style = PDO::FETCH_BOTH;
        if($type=='assoc') $style = PDO::FETCH_ASSOC;
        elseif($type=='num') $style = PDO::FETCH_NUM;
        /*
		if($type=='assoc') $query->setFetchMode(PDO::FETCH_ASSOC);
		elseif($type=='num') $query->setFetchMode(PDO::FETCH_NUM);
		else $query->setFetchMode(PDO::FETCH_BOTH);*/

		return $query->fetch($style);
	}

    /**
     * 结果集行数
     * @return int
     */
	public function num_rows() {
		return $this->rs->rowCount();
	}

    /**
     * 取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
     * @param string $sequenceName
     * @return string
     */
	public function insert_id($sequenceName=null) {
	    //mssql 'SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS bigint)'
		return $this->conn->lastInsertId($sequenceName);//PDO::lastInsertId(); PDO_PGSQL() 要求为 name 参数指定序列对象的名称
	}
}