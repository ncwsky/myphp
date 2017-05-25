<?php
/*
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
`id` varchar(40) NOT NULL,
`expire` int(10) unsigned DEFAULT 0 NOT NULL,
`data` blob NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `sessions_mem`; -- ?
CREATE TABLE `sessions_mem` (
`id` varchar(40) NOT NULL,
`expire` int(10) unsigned DEFAULT 0 NOT NULL,
`data` varchar(2500) DEFAULT '' NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;
*/
//mysql会话类
class session_mysql extends session_abstract{
	protected $_db;
	//构造函数
	public function __construct($options=null){
		parent::__construct($options);
		if(!isset($this->options['expire']))
			$this->options['expire'] = (int)ini_get('session.gc_maxlifetime');

		if(!isset($this->options['conf']))
			$this->options['conf'] = 'db'; //默认数据库配置名
		
		if(!isset($GLOBALS['cfg'][$this->options['conf']]))
			throw new Exception('Session mysql: '.$this->options['conf'].'数据库连接数组配置不存在');
		
		if(isset($GLOBALS['cfg'][$this->options['conf']]))
			$this->options = array_merge($this->options, $GLOBALS['cfg'][$this->options['conf']]);
		
		if(!isset($this->options['table']))
			$this->options['table'] = 'sessions'; //默认表名
		if(!isset($this->options['port']))
			$this->options['port'] = 3306; //默认端口
		/*
		$this->options = array(
			'server' => 'localhost',	//数据库主机
			'name' => 'lh_jiaoban',	//数据库名称
			'user' => 'root',	//数据库用户
			'pwd' => '123456',	//数据库密码
			'port' => 3306,     // 端口
			'table' => 'sessions',	//session表
		);*/
	}
	/**
	 * 类似构造
	 *
	 * @param	string	$save_path	session存放目录
	 * @param	string	$name		session cookie name
	 * @return	bool
	 */
	public function open($save_path, $name){
		//var_dump($this->options);
		$this->_db = mysqli_connect($this->options['server'], $this->options['user'], $this->options['pwd'], $this->options['name'], $this->options['port']);
		if(!$this->_db){
			//throw new Exception('Session mysql: '.json_encode($this->options));
			exit("Session mysql: 数据库连接失败");
		}
		return $this->_db ? true : false;
	}
	//类似析构
	public function close(){
		mysqli_close($this->_db);
		$this->options = null;
        return true;
    }
	//读取数据
	public function read($sid){
		//Log::trace('read:'. $sid .'==='.time()); 
		$expire = time() - $this->options['expire'];
		
		$res = mysqli_query($this->_db,"SELECT `data` FROM ".$this->options['table']." WHERE `id` = '".$sid."' AND `expire` > ". $expire);
        if($row = mysqli_fetch_assoc($res)){
            return $row['data'];
        }else{
            return '';
        }
    }
	//写入数据
    public function write($sid, $data){
		//Log::write('write:'.$sid.'=='.$data, 'info');
		
		$expire = time();// + $this->options['expire'];
		
        $r = mysqli_query($this->_db, "REPLACE INTO ".$this->options['table']." SET `id` = '".$sid."', `expire` = ".$expire.", `data` = '".$data."'");
        return $r ? true : false;
    }
	//删除会话
	public function destroy($sid){
		//Log::trace('destroy:'.$sid);

		$r = mysqli_query($this->_db, 'DELETE FROM '.$this->options['table'].' WHERE `id`='.$sid);
		return $r ? true : false;
	}
	/**
	 * 垃圾回收 删除过期session
	 *
	 * Deletes expired sessions
	 *
	 * @param	int 	$maxlifetime	Maximum lifetime of sessions
	 * @return	bool
	 */
    public function gc($maxlifetime){
		Log::trace('gc:'.time());
		
		$r = mysqli_query($this->_db, 'DELETE FROM '.$this->options['table'].' WHERE `expire` < '. time());
        return $r ? true : false;
    }
}