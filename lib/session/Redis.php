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
        'pconnect' => true, //持续连接
        'expire' => 1440 //有效期 为0使用php默认配置
    ];

    /**
     * Redis constructor.
     * @param null $options = [
     *
     * ]
     */
	public function __construct($options=null){
        if (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        }
        if (empty($this->options['expire'])) {
            $this->options['expire'] = (int)ini_get('session.gc_maxlifetime');
        }

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
            $this->options['retries'] = 1;
            $this->handler = new \myphp\driver\Redis($this->options);
        }
	}
	/**
	 * {@inheritdoc}
	 */
	public function open($save_path, $name){
		return $this->handler ? true : false;
	}
    /**
     * {@inheritdoc}
     */
	public function close(){
        return true;
    }
    /**
     * {@inheritdoc}
     */
	public function read($sid){
        return (string)$this->handler->get($this->options['prefix'] . $sid);
    }
    /**
     * {@inheritdoc}
     */
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
    /**
     * {@inheritdoc}
     */
	public function destroy($sid){
		//\myphp\Log::trace('destroy:'.$sid);
        return (bool)$this->handler->del($this->options['prefix'].$sid)>0;
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

    /**
     * Update sesstion modify time.
     *
     * @see https://www.php.net/manual/en/class.sessionupdatetimestamphandlerinterface.php
     *
     * @param string $id Session id.
     * @param string $data Session Data.
     *
     * @return bool
     */
    public function updateTimestamp($id, $data = ""){
        return (bool)$this->handler->expire($this->options['prefix'].$id, $this->options['expire']);
    }
}