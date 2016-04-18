<?php
// Memcache缓存类
class cache_memcache extends cache_abstract{	
	//配置
    protected $options = array(
        'prefix' => 'cache',
		'host' => '127.0.0.1',
		'port' => 11211,
		'timeout'=> 0, 
		'pconnect' => false, //持续连接
    	'expire' => 0, //有效期
		'server' => array() //从服务器
    );
	//构造函数
	public function __construct($options = array()){
		if ( !extension_loaded('memcache') ) {
            //exit('系统不支持:memcache');
			throw new myException('系统不支持:memcache',0);
        }
        if(!empty($options)) {
           $this->options = array_merge($this->options, $options);  
        }
		$func = $this->options['pconnect'] ? 'pconnect' : 'connect';
        $this->handler = new Memcache;
        $this->options['timeout'] == 0 ? $this->handler->$func($this->options['host'], $this->options['port']) : $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);
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
	
    //读取缓存
    public function get($name) {
        return $this->handler->get($this->options['prefix'].$name);
    }

    //写入缓存
    public function set($name, $data, $expire = null){
        if($expire===null) $expire = $this->options['expire'];

		$expire = $expire == 0 ? 0 : time() + $expire;//缓存有效期为0表示永久缓存
        $name = $this->options['prefix'].$name;
        return $this->handler->set($name, $data, false, $expire);
    }

    //删除缓存 
    public function del($name, $timeout = 0) {
        $name = $this->options['prefix'].$name;
        return $this->handler->delete($name, $timeout);
    }

    //删除所有缓存
    public function clear() {
        return $this->handler->flush();
    }
}