<?php
//Sqlite缓存
/**
DROP TABLE "cache_table";
CREATE TABLE "cache_table" (
"cachekey" TEXT(32) NOT NULL,
"expire" INTEGER NOT NULL DEFAULT 0,
"data" BLOB NOT NULL,
PRIMARY KEY ("cachekey")
);
 */
class cache_sqlite3 extends cache_abstract {
	//配置
    protected $options = array(
        'prefix' => 'cache',
		'db' => ':memory:', //
        'table' => 'cache_table',
		'timeout'=> 0, 
		'pconnect' => false, //持续连接
    	'expire' => 0, //有效期
		'server' => array() //从服务器
    );
	//构造函数
	public function __construct($options = array()){
		if ( !extension_loaded('sqlite3') ) {
            //exit('系统不支持:sqlite');
			throw new myException('系统不支持:sqlite',0);
        }
        if(!empty($options)) {
           $this->options = array_merge($this->options, $options);  
        }
		$func = $this->options['pconnect'] ? 'sqlite3_popen' : 'sqlite3_open';
        $this->handler = new SQLite3($this->options['db']);
		//$this->handler->exec('CREATE TABLE IF NOT EXISTS "'.$this->options['table'].'" ("cachekey" TEXT(32) NOT NULL, "expire" INTEGER NOT NULL DEFAULT 0, "data"  BLOB NOT NULL, PRIMARY KEY ("cachekey"));');
	}
	
	/**
	 * 得到本类实例
	 * @return self
	 */
	public static function getInstance($options = array()){
		if(self::$instance == NULL){
			self::$instance = new self($options);
		}
		return self::$instance;
	}

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name) {
		$name = $this->options['prefix'].SQLite3::escapeString($name);
        $sql = 'SELECT data FROM '.$this->options['table'].' WHERE cachekey=\''.$name.'\' AND (expire=0 OR expire >'.time().') LIMIT 1';
        $result = $this->handler->querySingle($sql);
        if ($result) {
            if(GetC('isGzipEnable') && function_exists('gzcompress')) {
                //启用数据压缩
                $result = gzuncompress($result);
            }
            return unserialize($result);
        }
        return false;
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $data  存储数据
     * @param integer $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $data, $expire = null){
        if($expire===null) $expire = $this->options['expire'];
        
        $name  = $this->options['prefix'].SQLite3::escapeString($name);
        $data = SQLite3::escapeString(serialize($data));
        $expire = $expire == 0 ? 0 : time() + $expire;//缓存有效期为0表示永久缓存
        if(GetC('isGzipEnable') && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data,3);
        }
        $sql  = 'REPLACE INTO '.$this->options['table'].' (cachekey, expire, data) VALUES (\''.$name.'\', \''.$expire.'\', \''.$data.'\')';
        if($this->handler->exec($sql)){
            return true;
        }
        return false;
    }

    //删除缓存
    public function del($name) {
        $name = $this->options['prefix'].SQLite3::escapeString($name);
        $sql = 'DELETE FROM '.$this->options['table'].' WHERE cachekey=\''.$name.'\'';
        $this->handler->exec($sql);
        return true;
    }

    //清除缓存
    public function clear() {
        $sql = 'DELETE FROM '.$this->options['table'];
        $this->handler->exec($sql);
        return true;
    }
}
