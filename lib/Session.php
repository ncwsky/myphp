<?php

declare(strict_types=1);

namespace myphp;

use myphp;
use myphp\session\Redis;

/**
 * Session设置处理
 * 默认使用系统自带session函数处理
 * @package myphp
 * @method all() static
 * @method get($name) static
 * @method set($name, $value) static
 * @method delete($name) static
 * @method del($name) static 兼容处理
 * @method destroy() static 销毁
 * @method flush() static 销毁 兼容处理
 * @method getId()
 */
class Session
{
    /**
     * @var null|EnvSessionInterface
     */
    public static $instance = null;
    /**
     * 自定义session类处理 返回的对类需要满足EnvSessionInterface接口的方法
     * @var null|callable
     */
    private static $callable = null;

    /**
     * 注入指定的session处理方式
     * @param callable|string $sess
     */
    public static function on($sess): void
    {
        self::$callable = $sess;
        self::$instance = null;
    }

    /**
     * @param array|null $opts
     * @return mixed|EnvSessionInterface
     */
    public static function init(?array $opts = null)
    {
        if (!self::$instance) {
            if (self::$callable) {
                $class = self::$callable;
            } else {
                $class = empty($opts['class']) ? '\myphp\EnvSession' : $opts['class'];
            }
            if ($class instanceof \Closure) {
                self::$instance = call_user_func($class);
            } elseif (is_object($class)) {
                self::$instance = $class;
            } else {
                self::$instance = new $class($opts);
            }
        }
        return self::$instance;
    }

    public static function __callStatic($method, $args)
    {
        if (!static::$instance) {
            throw new \Exception('Session No Instance');
        }
        if ($method == 'del') {  //兼容处理
            $method = 'delete';
        } elseif ($method == 'flush') {  //兼容处理
            $method = 'destroy';
        }

        if (method_exists(static::$instance, $method)) {
            return call_user_func_array([static::$instance, $method], $args);
        }
        return null;
    }
}

interface EnvSessionInterface
{
    public function getId();
    public function setId($id);
    public function getName();
    public function setName($name);
    public function all();
    public function set($name, $val);
    public function get($name, $default = null);
    public function delete($name);
    public function destroy();
}

class EnvSession implements EnvSessionInterface
{
    private $options = ['name' => 'sid'];

    public function __construct($opts)
    {
        $this->open($opts);
        register_shutdown_function([$this,'close']);
    }

    /**
     * 初始会话
     * @param array|null $opts
     * @return void
     */
    public function open(?array $opts = null): void
    {
        if ($this->isActive()) {
            return;
        }

        if ($opts) {
            $this->options = $opts;
        }
        isset($this->options['name']) && session_name($this->options['name']);
        session_set_cookie_params(
            isset($this->options['expire']) ? (int)$this->options['expire'] : 0,
            $this->options['cookie_path'] ?? '/',
            $this->options['cookie_domain'] ?? '',
            $this->options['cookie_secure'] ?? false,
            true // HttpOnly; Yes, this is intentional and not configurable for security reasons
        );
        isset($this->options['expire']) && ini_set('session.gc_maxlifetime', (string)$this->options['expire']);

        //默认php文件ses
        $type = $this->options['type'] ?? '';
        if ($type == 'redis') {
            $sess = new Redis($this->options);
            session_set_save_handler($sess, true);
        } else {
            isset($this->options['path']) && session_save_path($this->options['path']);
        }
        @session_start();
    }

    /**
     * @return bool whether the session has started
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function getId(): string
    {
        return session_id();
    }

    public function setId($id): void
    {
        session_id($id);
    }

    public function getName(): string
    {
        return session_name();
    }

    // todo test
    public function setName($name): void
    {
        $this->close();
        session_name($name);
        $this->open();
    }

    public function close(): void
    {
        if ($this->isActive()) {
            @session_write_close();
        }
    }

    //销毁 todo test
    public function destroy(): void
    {
        if ($this->isActive()) {
            $sessionId = $this->getId();
            $this->close();
            $this->open();
            session_unset();
            session_destroy();
            $this->setId($sessionId);
        }
        $_SESSION = [];
    }

    public function all(): array
    {
        $this->open();
        return $_SESSION ?? [];
    }

    //设置 session
    public function set($name, $val): void
    {
        $this->open();
        $_SESSION[$name] = $val;
    }

    //获取 session
    public function get($name, $default = null)
    {
        $this->open();
        return $_SESSION[$name] ?? $default;
    }

    //删除 session
    public function delete($name): void
    {
        $this->open();
        unset($_SESSION[$name]);
    }
}
