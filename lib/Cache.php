<?php

declare(strict_types=1);

namespace myphp;

/**
 * 缓存
 *
 * @property array $options
 * @method mixed setOption($option, $val=null)
 * @method mixed set(string $name, $data, $expire=null)
 * @method mixed get(string $name)
 * @method mixed del(string $name)
 * @method mixed clear()
 * @method mixed gc($force = false, $expiredOnly = true) only for file cache
 */
class Cache
{
    protected static $instance = [];
    /** 缓存实例
     * @param string $type
     * @param array|null $options
     * @param bool $force
     * @return \myphp\cache\File|\myphp\cache\Redis
     */
    public static function getInstance(string $type = 'file', ?array $options = null, bool $force = false)
    {
        if (!isset(self::$instance[$type]) || $force) {
            if ($options === null) {
                $options = \myphp::get('cache_option');
            }
            $cacheClass = '\myphp\cache\\' . ucfirst($type);
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
abstract class CacheAbstract
{
    protected $options = []; //配置参数

    /**
     * CacheAbstract constructor.
     * @param array|null $options
     */
    public function __construct(?array $options = null)
    {
        if (is_array($options)) {
            $this->options  = array_merge($this->options, $options);
        }
    }

    /**
     * 设置配置参数
     * @param array|string $option
     * @param mixed $val
     * @return void
     */
    public function setOption($option, $val = null): void
    {
        if (is_array($option)) {
            $this->options  = array_merge($this->options, $option);
        } elseif (is_string($option) && !empty($val)) {
            $this->options[$option] = $val;
        }
    }
    //设置缓存
    abstract public function set(string $name, $data, int $expire = 0);
    //获取缓存
    abstract public function get(string $name);
    //判断缓存
    //abstract public function has(string $name);
    //删除缓存
    abstract public function del(string $name);
    //清除所有缓存
    abstract public function clear();
}
