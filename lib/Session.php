<?php
namespace myphp;

/**
 * Class Session
 * @package myphp
 * @method flush() static
 * @method all() static
 * @method get($name) static
 * @method set($name, $value) static
 * @method delete($name) static
 * @method del($name) static 兼容处理
 */
class Session {
    /**
     * @var null|EnvSessionInterface
     */
    private static $instance = null;

    /**
     * 默认的session处理
     * @param null $opts
     * @return EnvSession|null
     */
    public static function init($opts = null)
    {
        return self::load('\myphp\EnvSession', $opts);
    }

    /**
     * 载入其他定义的session
     * @param EnvSessionInterface|\Closure$sess
     * @param null $opts
     * @return mixed|null
     */
    public static function load($sess, $opts = null)
    {
        if (!self::$instance) {
            if ($sess instanceof \Closure) {
                self::$instance = call_user_func($sess);
            } elseif (is_string($sess)) {
                self::$instance = new $sess($opts);
            } else {
                self::$instance = $sess;
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
    public function flush();
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
        if ($session_class == 'redis') $session_class = '\myphp\session\Redis';

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
        session_unset();
        session_destroy();
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

    //清除所有会话
    public function flush()
    {
        $this->open();
        $_SESSION = [];
    }
}