<?php
namespace myphp\db;

use myphp\Log;
use TDEngine\TaosRestApi;

/**
 * Class db_taos
 * composer require myphps/taos-rest
 * @property TaosRestApi $conn
 * [
    'type' => 'taos',   //数据库连接类型 TDEngine rest api
    'dbms' => '', //数据库
    'server' => '127.0.0.1',    //数据库主机
    'name' => '',   //数据库名称
    'user' => 'root',   //数据库用户
    'pwd' => 'taosdata',    //数据库密码
    'port' => '6041',   // 端口
    'ssl' => 0
]
 */
class db_taos extends \myphp\DbBase{
	// 连接数据库
    public function connect() {
        $dsn = (empty($this->config['ssl']) ? 'http://' : 'https://') . $this->config['server'] . ':' . $this->config['port'];
        $timezone = isset($this->config['timezone']) ? $this->config['timezone'] : ''; //Asia/Chongqing
        try {
            $this->conn = new TaosRestApi($dsn, $this->config['user'], $this->config['pwd'], $this->config['name'], $timezone);
        } catch (\Exception $e) {
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

    /** 执行sql
     * @param $sql
     * @return bool|int
     * @throws \Exception
     */
	public function exec($sql) {
        $result = $this->conn->exec($sql);
        if ($result === false) {
            throw new \Exception("SQL exec: {$sql} | {$this->conn->errno} | {$this->conn->error}");
        }
        return $result;
	}

    /** 执行查询语句
     * @param $sql
     * @return false|TaosRestApi
     * @throws \Exception
     */
	public function query($sql) {
		if($this->conn->exec($sql)===false) {
			throw new \Exception("SQL query: {$sql} | {$this->conn->errno} | {$this->conn->error}");
		}
		return $this->conn;
	}

    /** 返回所有行的数组
     * @param $sql
     * @param string $type
     * @return array
     */
	public function queryAll($sql, $type = 'assoc'){
        if($type=='assoc') $type = TaosRestApi::FETCH_ASSOC;
        elseif($type=='num') $type = TaosRestApi::FETCH_NUM;
        elseif($type=='column') $type = TaosRestApi::FETCH_COLUMN;
        else $type = TaosRestApi::FETCH_BOTH;

        return $this->conn->query($sql, $type);
    }

    /**
     * 从结果集中取得一行作为关联数组/数字索引数组
     * @param TaosRestApi $query
     * @param string $type 默认MYSQLI_ASSOC关联,MYSQLI_NUM 数字,MYSQLI_BOTH 两者
     * @return mixed
     */
	public function fetch(&$query, $type = 'assoc') {
		if($type=='assoc') $type = TaosRestApi::FETCH_ASSOC;
		elseif($type=='num') $type = TaosRestApi::FETCH_NUM;
		else $type = TaosRestApi::FETCH_BOTH;

		return $query->fetch($type);
	}

    /**
     * 结果集行数
     * @return int
     */
    public function num_rows() {
        return $this->conn->rowCount();
    }

    /**
     * 取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
     * @param string $sequenceName
     * @return string
     */
    public function insert_id($sequenceName=null) {
        //mssql 'SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS bigint)'
        return 0;//PDO::lastInsertId(); PDO_PGSQL() 要求为 name 参数指定序列对象的名称
    }
}