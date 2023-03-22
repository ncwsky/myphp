<?php
/**
 * Class Session
 * @package myphp
 * @method all() static
 * @method get($name) static
 * @method set($name, $value) static
 * @method delete($name) static
 * @method del($name) static 兼容处理
 * @method destroy() static 销毁
 * @method flush() static 销毁 兼容处理
 */
class Session {
    /**
     * @var null|EnvSessionInterface
     */
    public static $instance = null;
    /**
     * @var null|callable
     */
    private static $callable = null;

    /**
     * 注入指定的session处理方式
     * @param callable|string $sess
     */
    public static function on($sess)
    {
        self::$callable = $sess;
        self::$instance = null;
    }

    /**
     * @param null $opts
     * @return mixed|EnvSessionInterface
     */
    public static function init($opts = null)
    {
        if (!self::$instance) {
            if (self::$callable) {
                $class = self::$callable;
            } else {
                $class = empty($opts['class']) ? 'EnvSession' : $opts['class'];
            }
            if ($class instanceof \Closure) {
                self::$instance = call_user_func($class);
            } elseif (is_string($class)) {
                self::$instance = new $class($opts);
            } else {
                self::$instance = $class;
            }
        }
        return self::$instance;
    }

    public static function __callStatic($method, $args)
    {
        if (!static::$instance) {
            throw new \Exception('Session No Instance');
        }
        if ($method == 'del') $method = 'delete'; //兼容处理
        elseif ($method == 'flush') $method = 'destroy'; //兼容处理

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
    private $options = ['name'=>'sid'];

    public function __construct($opts)
    {
        $this->open($opts);
        register_shutdown_function([$this,'close']);
    }

    //初始会话
    public function open($opts=null){
        if ($this->isActive()) return;

        if ($opts) $this->options = $opts;
        isset($this->options['name']) && session_name($this->options['name']);
        session_set_cookie_params(
            isset($this->options['expire']) ? (int)$this->options['expire'] : 0,
            isset(\myphp::$cfg['cookie_path']) ? \myphp::$cfg['cookie_path'] : '/',
            isset(\myphp::$cfg['cookie_domain']) ? \myphp::$cfg['cookie_domain'] : '',
            isset(\myphp::$cfg['cookie_secure']) ? \myphp::$cfg['cookie_secure'] : false,
            true // HttpOnly; Yes, this is intentional and not configurable for security reasons
        );
        isset($this->options['expire']) && ini_set('session.gc_maxlifetime', $this->options['expire']);

        @session_start();
        //自定义ses类
        $session_class = isset($this->options['class']) ? $this->options['class'] : '';
        if ($session_class == 'redis') {
            require_once(__DIR__.'/session/redis.php');
            $session_class = 'session_redis';
        }
        if ($session_class) {
            $sess = new $session_class($this->options);
            session_set_save_handler($sess, true);
        } else {
            isset($this->options['path']) && session_save_path($this->options['path']);
        }
    }

    /**
     * @return bool whether the session has started
     */
    public function isActive()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function getId()
    {
        return session_id();
    }

    public function setId($id)
    {
        session_id($id);
    }

    public function getName()
    {
        return session_name();
    }

    // todo test
    public function setName($name)
    {
        $this->close();
        session_name($name);
        $this->open();
    }

    public function close()
    {
        if ($this->isActive()) @session_write_close();
    }

    //销毁 todo test
    public function destroy()
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

    public function all()
    {
        $this->open();
        return isset($_SESSION) ? $_SESSION : [];
    }

    //设置 session
    public function set($name, $val)
    {
        $this->open();
        $_SESSION[$name] = $val;
    }

    //获取 session
    public function get($name, $def = null)
    {
        $this->open();
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $def;
    }

    //删除 session
    public function delete($name)
    {
        $this->open();
        unset($_SESSION[$name]);
    }
}