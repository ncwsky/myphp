<?php
//redis会话类
class session_redis extends session_abstract{
	protected $handler;
	//构造函数
	public function __construct($options=null){
		parent::__construct($options);
		if(!isset($this->options['expire']))
			$this->options['expire'] = (int)ini_get('session.gc_maxlifetime');
				
		if(!isset($this->options['conf']))
			$this->options['conf'] = 'redis'; //默认配置名
		
		if(isset(Config::$cfg[$this->options['conf']]))
			$this->options = array_merge(Config::$cfg[$this->options['conf']], $this->options);

		if(!isset($this->options['host']))
			$this->options['host'] = '127.0.0.1'; //默认地址
		if(!isset($this->options['port']))
			$this->options['port'] = 6379; //默认端口
		if(!isset($this->options['prefix']))
			$this->options['prefix'] = 'sess'; //默认前缀
		if(!isset($this->options['timeout']))
			$this->options['timeout'] = 0; //超时时间
		if(!isset($this->options['password']))
			$this->options['password'] = ''; //auth pass
		if(!isset($this->options['select']))
			$this->options['select'] = 0; //选择库
	}
	/**
	 * 类似构造
	 *
	 * @param	string	$save_path	session存放目录
	 * @param	string	$name		session cookie name
	 * @return	bool
	 */
	public function open($save_path, $name){
        if ( extension_loaded('redis') ) {
            $this->handler = new Redis();
            $this->options['timeout'] == 0 ? $this->handler->connect($this->options['host'], $this->options['port']) : $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }
            if (0 != $this->options['select']) {
                $this->handler->select($this->options['select']);
            }
        }else{
            $this->options['database'] = $this->options['select'];
            $this->handler = new MyRedis($this->options);
        }

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
		$name = $this->options['prefix'].$sid;
		
		if($expire>0) {
            $result = $this->handler->setex($name, $expire, $data);
        }else{
            $result = $this->handler->set($name, $data);
        }
        return $result;
		
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
		return true;//$this->handler->flush();
    }
}