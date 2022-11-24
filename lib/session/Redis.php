<?php
namespace myphp\session;

//redis会话类
class Redis implements \SessionHandlerInterface{
    private $handler;
    //配置
    private $options = [
        'prefix' => 'ses:', //前缀
        'host' => '127.0.0.1',
        'port' => 6379,
        'password'=>'',
        'select'=>0, //选择库
        'timeout'=> 0, //连接超时时间（秒）
        'pconnect' => false, //持续连接
        'expire' => 1440, //有效期
        'server' => array() //从服务器 待实现
    ];
	//构造函数
	public function __construct($options=null){
        if (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        }
        if (empty($this->options['expire'])) {
            $this->options['expire'] = (int)ini_get('session.gc_maxlifetime');
        }
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
            $func = $this->options['pconnect'] ? 'pconnect' : 'connect';
            $this->handler = new \Redis();
            $this->options['timeout'] == 0 ? $this->handler->$func($this->options['host'], $this->options['port']) : $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);
            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }
            $this->handler->select($this->options['select']);
        }else{
            $this->options['database'] = $this->options['select'];
            $this->handler = new \myphp\MyRedis($this->options);
        }

		return $this->handler ? true : false;
	}
	//类似析构
	public function close(){
        //$this->handle->close();
		$this->handle = null;
        return true;
    }
	//读取数据
	public function read($sid){
        return (string)$this->handler->get($this->options['prefix'] . $sid);
    }
	//写入数据
    public function write($sid, $data){
		$expire = $this->options['expire'];
		$name = $this->options['prefix'].$sid;
		
		if($expire>0) {
            $result = $this->handler->setex($name, $expire, $data);
        }else{
            $result = $this->handler->set($name, $data);
        }
        return $result ? true : false;
		
    }
	//删除会话
	public function destroy($sid){
		//\myphp\Log::trace('destroy:'.$sid);
        return $this->handler->del($this->options['prefix'].$sid)>0;
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