<?php
// Apc缓存驱动
class cache_apc extends cache_abstract {
	//配置
    protected $options = array(
        'prefix' => 'cache',
    	'expire' => 0, //有效期
    );
	//构造函数
	public function __construct($options = array()){
		if(!function_exists('apc_cache_info')) {
            //exit('系统不支持:Apc');
			throw new myException('系统不支持:Apc',0);
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
        return apc_fetch($this->options['prefix'].$name);
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
        return apc_store($name, $data, $expire);
     }

    //删除缓存 
     public function del($name) {
         return apc_delete($this->options['prefix'].$name);
     }

    //删除所有缓存
    public function clear() {
        return apc_clear_cache();
    }
}