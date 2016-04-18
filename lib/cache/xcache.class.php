<?php
//Xcache缓存驱动
class cache_xcache extends cache_abstract {
	//配置
    protected $options = array(
        'prefix' => 'cache',
    	'expire' => 0, //有效期
    );
	//构造函数
	public function __construct($options = array()){
		if(!function_exists('xcache_info')) {
            //exit('系统不支持:Xcache');
			throw new myException('系统不支持:Xcache',0);
        }
        if(!empty($options)) {
           $this->options = array_merge($this->options, $options);  
        }
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
        $name = $this->options['prefix'].$name;
        if (xcache_isset($name)) {
            return xcache_get($name);
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

        $name = $this->options['prefix'].$name;
        if(xcache_set($name, $data, $expire)) {
            return true;
        }
        return false;
    }

    //删除缓存 
    public function del($name) {
        return xcache_unset($this->options['prefix'].$name);
    }

    //删除所有缓存
    public function clear() {
        return xcache_clear_cache(1, -1);
    }
}