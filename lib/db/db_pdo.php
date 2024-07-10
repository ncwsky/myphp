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
		//运行参数
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //以异常的方式报错
            PDO::ATTR_STRINGIFY_FETCHES => false, //提取的时候不将数值转换为字符串
            PDO::ATTR_EMULATE_PREPARES => false //禁用预处理语句的模拟 启用pdo在查询时会预分配内存
        ];
        if (!empty($cfg_db['options'])) {
            $options = array_merge($options, $cfg_db['options']);
        }
        $cfg_db['pconnect'] = isset($cfg_db['pconnect']) ? $cfg_db['pconnect'] : false;
        if($cfg_db['pconnect']) { //持久连接开启
            $options[PDO::ATTR_PERSISTENT] = TRUE;
        }
        //PDO::ATTR_TIMEOUT:30 设置连接数据库的超时秒数。
        //PDO::MYSQL_ATTR_USE_BUFFERED_QUERY:false mysql非缓冲查询 查询大量数据时设置false不会出现内存不足的情况

        $initSql = '';
        if(empty($cfg_db['dsn'])){//未设置dsn时
            switch($cfg_db['dbms']){
                case 'mysql':// PDO_MYSQL DSN
                    $dsn = 'mysql:dbname='.$cfg_db['name'];
                    if ($cfg_db['server']!='') {
                        $dsn .= ';host='.$cfg_db['server'].($cfg_db['port']!=''?';port='.$cfg_db['port']:'');
                    }elseif (!empty($cfg_db['socket'])) {
                        $dsn .= ';unix_socket='. $cfg_db['socket'];
                    }

                    $initCommand = '';
                    if ($cfg_db['char'] != '') {
                        $dsn .= ';charset=' . $cfg_db['char'];
                        $initCommand .= "SET names '" . $cfg_db['char'] . "';";
                        //$initSql .= "SET names '" . $cfg_db['char'] . "';";
                    }
                    if (!empty($cfg_db['timezone'])) {
                        $initCommand .= "set time_zone='" . $cfg_db['timezone'] . "';";
                        //$initSql .= "set time_zone='" . $cfg_db['timezone'] . "';";
                    }
                    if ($initCommand) $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $initCommand;
                    //Log::write($initCommand, 'init_command');
                    break;
                case 'mssql':// PDO_SQLSRV
                    $dsn = 'sqlsrv:Server='.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ','.$cfg_db['port']).';Database='.$cfg_db['name'];break;
                case 'oracle':// PDO_OCI DSN
                    $dsn = 'oci:dbname=//'.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ':'.$cfg_db['port']).'/'.$cfg_db['name'].(empty($cfg_db['char']) ? '' : ';charset='.$cfg_db['char']);break;
                case 'pgsql':// PDO_PGSQL DSN
                    $dsn = 'pgsql:host='.$cfg_db['server'].(empty($cfg_db['port']) ? '' : ';port='.$cfg_db['port']).';dbname='.$cfg_db['name'];
                    //if ($cfg_db['char'] != '') $initSql .= "SET names '" . $cfg_db['char'] . "';";
                    //if (!empty($cfg_db['timezone'])) $initSql .= "set time zone='" . $cfg_db['timezone'] . "';";
                    break;
                case 'sqlite':// PDO_SQLITE DSN @sqlite:/opt/databases/mydb.sq3
                    $dsn = 'sqlite:'.$cfg_db['name'];break;
                default:// PDO_DBLIB DSN
                    $dsn = $cfg_db['dbms'].':host='.$cfg_db['server'].';dbname='.$cfg_db['name'].(empty($cfg_db['char']) ? '' : ';charset='.$cfg_db['char']);break;
            }
        }else{
            $dsn = $cfg_db['dsn'];
        }

        try {
            $this->conn = new PDO($dsn, $cfg_db['user'], $cfg_db['pwd'], $options);
        } catch(PDOException $e) {
            Log::write('dsn:' . $dsn . '|' . $e->getMessage(), 'db_connect');
            throw $e;
        }
        //if ($initSql) $this->conn->exec($initSql);
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
        try {
            $affected = $this->conn->exec($sql);
        } catch (PDOException $e) { //兼容>=8.0处理
            $errorInfo = $this->conn->errorInfo();
            if (empty($errorInfo[1]) && is_int($e->getCode())) {
                $errorInfo[1] = $e->getCode();
                $errorInfo[2] = $e->getMessage();
            }
            if ($errorInfo[1]) {
                $errInfo = implode('|', $errorInfo);
                if ($run == 0 && $this->transCounter==0 && IS_CLI) { //重连1次处理 非事务时允许重连
                    #MySQL server has gone away
                    if ($this->config['dbms'] == 'mysql' && ($errorInfo[1] == 2006 || $errorInfo[1] == 2013)) {
                        $this->connect();
                        Log::write($errInfo . ' 重连', 'db_exec');
                        return $this->exec($sql, 1);
                    }
                }
                if (strpos($errInfo, 'Function sequence error')) { //linux下因驱动原因的错误
                    $this->rs = $this->conn->prepare($sql); //PDOStatement 对象
                    $this->rs->execute();
                    if ($this->rs->errorCode() != '00000') {
                        throw new PDOException(implode('|', $this->rs->errorInfo())."; SQL exec: " . $sql);
                    }
                    return $this->rs->rowCount();
                }
            } else {
                $errInfo = $e->getCode() . ':' . $e->getMessage();
            }
            throw new PDOException($errInfo . "; SQL exec: " . $sql);
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
        try {
            $this->rs = $this->conn->query($sql); //预处理并执行没有占位符的 SQL 语句
        } catch (PDOException $e) {
            $errorInfo = $this->conn->errorInfo();
            if (empty($errorInfo[1]) && is_int($e->getCode())) {
                $errorInfo[1] = $e->getCode();
                $errorInfo[2] = $e->getMessage();
            }
            if ($errorInfo[1]) {
                $errInfo = implode('|', $errorInfo);
                if ($run == 0 && $this->transCounter == 0 && IS_CLI) { //重连1次处理 非事务时允许重连
                    #MySQL server has gone away
                    if ($this->config['dbms'] == 'mysql' && ($errorInfo[1] == 2006 || $errorInfo[1] == 2013)) {
                        $this->connect();
                        Log::write($errInfo. ' 重连', 'db_query');
                        return $this->query($sql, 1);
                    }
                }
            } else {
                $errInfo = $e->getCode() . ':' . $e->getMessage();
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
        $mode = PDO::FETCH_BOTH;
        if($type=='assoc') $mode = PDO::FETCH_ASSOC;
        elseif($type=='num') $mode = PDO::FETCH_NUM;
        //$sth = $this->conn->prepare($sql); $sth->execute(); return $sth->fetchAll($mode);
        return $this->query($sql)->fetchAll($mode);
    }

    /**
     * 从结果集中取得一行作为关联数组/数字索引数组
     * @param PDOStatement $query
     * @param string $type 默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
     * @return mixed
     */
	public function fetch(&$query, $type = 'assoc') {
        $mode = PDO::FETCH_BOTH;
        if($type=='assoc') $mode = PDO::FETCH_ASSOC;
        elseif($type=='num') $mode = PDO::FETCH_NUM;

		return $query->fetch($mode);
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