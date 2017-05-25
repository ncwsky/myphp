<?php
//memcached会话类
class session_memcached extends session_abstract{
	protected $handler;
	//构造函数
	public function __construct($options=null){
		parent::__construct($options);
		if(!isset($this->options['expire']))
			$this->options['expire'] = (int)ini_get('session.gc_maxlifetime');

		if(!isset($this->options['conf']))
			$this->options['conf'] = 'memcached'; //默认配置名
		/*
		$options = array(
			'options' => null, //Set Memcached options  array()
			'servers'=> array( //从服务器
							array('127.0.0.1', 11211, 33),  // ip port weight
						)
		);
		*/
		if ( !extension_loaded('memcached') ) {
            //exit('系统不支持:memcached');
			throw new myException('系统不支持:memcached',0);
        }
		
		if(isset($GLOBALS['cfg'][$this->options['conf']]))
			$this->options = array_merge($this->options, $GLOBALS['cfg'][$this->options['conf']]);

		if(!isset($this->options['host']))
			$this->options['host'] = '127.0.0.1'; //默认表名
		if(!isset($this->options['port']))
			$this->options['port'] = 11211; //默认端口
		if(!isset($this->options['weight']))
			$this->options['weight'] = 0; //默认权重
		if(!isset($this->options['prefix']))
			$this->options['prefix'] = 'sess'; //默认前缀
		
		$this->handler = new Memcached;
		$this->handler->addServer($this->options['host'], $this->options['port'], $this->options['weight']); 
		isset($this->options['servers']) && $this->handler->addServers($this->options['servers']);
        isset($this->options['options']) && $this->handler->setOptions($this->options['options']);
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
		
		return $this->handler ? true : false;
	}
	//类似析构
	public function close(){
		$this->handle = null;
		$this->options = null;
        return true;
    }
	//读取数据
	public function read($sid){
		return $this->handler->get($this->options['prefix'].$sid);
    }
	//写入数据
    public function write($sid, $data){
		//Log::write('write:'.$sid.'=='.$data, 'info');
		$expire = $this->options['expire'];
		$expire = $expire < 1 ? 0 : time() + $expire;//缓存有效期为0表示永久缓存
        $name = $this->options['prefix'].$sid;
        return $this->handler->set($name, $data, false, $expire);
		
    }
	//删除会话
	public function destroy($sid){
		//Log::trace('destroy:'.$sid);
        return $this->handler->delete( $this->options['prefix'].$sid );
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
		return true;
    }
}