<?php
// redis缓存类
class CacheRedis extends CacheAbstract{
    private $handler;
	//配置
    protected $options = array(
        'prefix' => 'cache',
		'host' => '127.0.0.1',
		'port' => 6379,
		'password'=>'',
		'select'=>0, //选择库 
		'timeout'=> 0, 
		'pconnect' => false, //持续连接
    	'expire' => 0, //有效期
		'server' => array() //从服务器 待实现
    );
	//构造函数
	public function __construct($options = array()){
        parent::__construct($options);

	    if ( extension_loaded('redis') ) {
            $func = $this->options['pconnect'] ? 'pconnect' : 'connect';
            $this->handler = new Redis();
            $this->options['timeout'] == 0 ? $this->handler->$func($this->options['host'], $this->options['port']) : $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);
            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }
            $this->handler->select($this->options['select']);
        }else{
            $this->options['database'] = $this->options['select'];
            $this->handler = new MyRedis($this->options);
        }
	}

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name) {
        $value = $this->handler->get($this->options['prefix'].$name);
        $jsonData = json_decode( $value, true );
        return ($jsonData === null) ? $value : $jsonData;	//检测是否为JSON数据 true 返回JSON解析数组, false返回源数据
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
        
        //$expire = $expire == 0 ? 0 : time() + $expire;//缓存有效期为0表示永久缓存
        $name = $this->options['prefix'].$name;
        //对数组/对象数据进行缓存处理，保证数据完整性
        $data  =  (is_object($data) || is_array($data)) ? json_encode($data) : $data;
        if(is_int($expire) && $expire) {
            $result = $this->handler->setex($name, $expire, $data);
        }else{
            $result = $this->handler->set($name, $data);
        }
        return $result;
    }

    //删除缓存
    public function del($name) {
        return $this->handler->delete($this->options['prefix'].$name);
    }

    //清除缓存
    public function clear() {
        return $this->handler->flushDB();
    }
}