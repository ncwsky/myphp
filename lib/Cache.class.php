<?php
/**
 * 缓存工厂
 *
 * @property array $options
 * @method mixed setOption($option, $val=null)
 * @method mixed set($name, $data, $expire=null)
 * @method mixed get($name)
 * @method mixed del($name)
 * @method mixed clear()
 * @method mixed gc($force = false, $expiredOnly = true) only for file cache
 */
class Cache {
	private $_instance = NULL;
	private static $instance = array();
	//构造函数
	public function __construct($type='file',$options=array()){
		$this->_instance = $this->getInstance($type,$options);
	}

    /** 生成新的缓存实例
     * @param string $type
     * @param array $options
     * @return CacheFile|CacheRedis
     * @throws Exception
     */
    public static function newInstance($type='file',$options=array()){
        $cacheClass = 'Cache'.ucfirst($type);
        require_once(MY_PATH.'/lib/cache/'.$cacheClass.'.class.php');
        if(!class_exists($cacheClass)) throw new Exception($cacheClass.' not found');

        return new $cacheClass($options);
    }
    /** 缓存实例
     * @param string $type
     * @param array $options
     * @return CacheFile|CacheRedis
     * @throws Exception
     */
	public static function getInstance($type='file',$options=array()){
		$md5 = $type.md5(serialize($options));
		if(!isset(self::$instance[$md5])){
            self::$instance[$md5] = self::newInstance($type, $options);
		}
		return self::$instance[$md5];
	}
	public function __call($method_name, $method_args) {
		if (method_exists($this, $method_name))
			return call_user_func_array(array(& $this, $method_name), $method_args);
		elseif (!empty($this->_instance) && method_exists($this->_instance, $method_name)
		) return call_user_func_array(array(& $this->_instance, $method_name), $method_args); // && ($this->_instance instanceof CacheAbstract)
	}
}
//缓存抽象类
abstract class CacheAbstract{
	protected $options = array(); //配置参数
	//构造函数
	public function __construct($options = array()){
        if(!empty($options)) {
           $this->options  = array_merge($this->options, $options);  
        }
	}
	//设置配置参数
	public function setOption($option, $val = null){
        if(is_array($option)) {
           $this->options  = array_merge($this->options, $option);  
        }elseif(is_string($option) && !empty($val)){
			$this->options[$option] = $val;
		}
	} 
	//设置参数 可数组方式 或 键值方式  $option:array/string
	//abstract public function setOption($option, $val = null);
	//设置缓存
	abstract public function set($name, $data, $expire=null);
	//获取缓存
	abstract public function get($name);
	//判断缓存
	//abstract public function has($name);
	//删除缓存
	abstract public function del($name);
	//清除所有缓存
	abstract public function clear();
}