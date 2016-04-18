<?php
//缓存工厂
class Cache {
	private $_instance = NULL;
	private static $instance = array();
	//构造函数
	public function __construct($type='file',$options=array()){
		$this->_instance = $this->getInstance($type,$options);
	}
	//得到缓存实例
	public static function getInstance($type='file',$options=array()){
		$md5 = $type.md5(serialize($options));
		if(!isset(self::$instance[$md5])){
			if(!file_exists(MY_PATH.'/lib/cache/'.$type.'.class.php')) exit($type.'缓存类型无法加载');
			require_once(MY_PATH.'/lib/cache/'.$type.'.class.php');
			$cache_class = 'cache_'.$type;
			if(class_exists($cache_class)) {
				self::$instance[$md5] = new $cache_class($options);
			}else {
				exit($cache_class.'缓存类没有定义');
			}
		}
		return self::$instance[$md5];
	}
	public function __call($method_name, $method_args) {
		if (method_exists($this, $method_name))
			return call_user_func_array(array(& $this, $method_name), $method_args);
		elseif (!empty($this->_instance) && method_exists($this->_instance, $method_name)
		) return call_user_func_array(array(& $this->_instance, $method_name), $method_args); // && ($this->_instance instanceof cache_abstract)
	}
}

//缓存接口
interface cache_interface{
	public function setOption($option, $val = NULL); ////设置参数 可数组方式 或 键值方式  $option:array/string
	public function set($name, $data, $expire=0); //设置缓存
	public function get($name); //获取缓存
	public function has($name); //判断缓存
	public function del($name); //删除缓存
    public function clear(); //清除所有缓存
}
//缓存抽象类
abstract class cache_abstract{
	protected static $instance = NULL;
	protected $handler;
	protected $options = array(); //配置参数
	
	//构造函数
	public function __construct($options = array()){
        if(!empty($options)) {
           $this->options  = array_merge($this->options, $options);  
        }
	}
	//设置配置参数
	public function setOption($option, $val = NULL){
        if(is_array($option)) {
           $this->options  = array_merge($this->options, $option);  
        }elseif(is_string($option) && !empty($val)){
			$this->options[$option] = $val;
		}
	} 
	//设置参数 可数组方式 或 键值方式  $option:array/string
	//abstract public function setOption($option, $val = NULL);
	//设置缓存
	abstract public function set($name, $data, $expire=0);
	//获取缓存
	abstract public function get($name);
	//判断缓存
	//abstract public function has($name);
	//删除缓存
	abstract public function del($name);
	//清除所有缓存
	abstract public function clear();

	//析构方法
    public function __destruct() {
		//$this->options = NULL;
    }
}