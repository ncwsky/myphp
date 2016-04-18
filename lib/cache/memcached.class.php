<?php
// Memcached 缓存类 高性能分布式的内存对象缓存系统
class cache_memcached extends cache_abstract{	
	//配置
    protected $options = array(
        'prefix' => 'cache',
		'host' => '127.0.0.1',
		'port' => 11211,
		'weight'=> 0, //权重
    	'expire' => 0, //有效期
		'options' => null, //Set Memcached options  array()
		'servers'=> null, /*array(
						array('127.0.0.1', 11211, 33),  // ip port weight
					) //从服务器
					*/
    );
	//构造函数
	public function __construct($options = array()){
		if ( !extension_loaded('memcached') ) {
            //exit('系统不支持:memcached');
			throw new myException('系统不支持:memcached',0);
        }
        if(!empty($options)) {
           $this->options = array_merge($this->options, $options);  
        }
        $this->handler = new Memcached;
		$this->handler->addServer($this->options['host'], $this->options['port'], $this->options['weight']); 
		$this->options['servers'] && $this->handler->addServers($this->options['servers']);
        $this->options['options'] && $this->handler->setOptions($this->options['options']);
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