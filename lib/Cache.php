<?php
namespace myphp;

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
    protected static $instance = array();
    /** 缓存实例
     * @param string $type
     * @param null|array $options
     * @param bool $new
     * @return \myphp\cache\File|\myphp\cache\Redis
     * @throws \Exception
     */
	public static function getInstance($type='file', $options=null, $new=false){
        if (!isset(self::$instance[$type]) || $new) {
            if ($options === null) {
                $options = \myphp::get('cache_option');
            }
            $cacheClass = '\myphp\cache\\' . ucfirst($type);
            //require_once(__DIR__.'/cache/'.$cacheClass.'.php');
            if (!class_exists($cacheClass)) throw new \Exception($cacheClass . ' not found');
            self::$instance[$type] = new $cacheClass($options);
        }
        return self::$instance[$type];
	}

    public static function __callStatic($method_name, $method_args)
    {
        return call_user_func_array([self::getInstance(), $method_name], $method_args);
    }
}
//缓存抽象类
abstract class CacheAbstract{
    protected $options = array(); //配置参数

    /**
     * CacheAbstract constructor.
     * @param null|array $options
     */
    public function __construct($options = null){
        if(is_array($options)) {
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