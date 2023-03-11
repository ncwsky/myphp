<?php
namespace myphp;

/**
 * Session工厂
 */
class Session {
	private static $options = ['name'=>'sid'];
	//初始会话
	public static function init($opts=null){
        if (self::isActive()) return;

        if ($opts) self::$options = $opts;
        isset(self::$options['name']) && session_name(self::$options['name']);
        session_set_cookie_params(
            isset(self::$options['expire']) ? (int)self::$options['expire'] : 0,
            isset(\myphp::$cfg['cookie_path']) ? \myphp::$cfg['cookie_path'] : '/',
            isset(\myphp::$cfg['cookie_domain']) ? \myphp::$cfg['cookie_domain'] : '',
            isset(\myphp::$cfg['cookie_secure']) ? \myphp::$cfg['cookie_secure'] : false,
            true // HttpOnly; Yes, this is intentional and not configurable for security reasons
        );
        isset(self::$options['expire']) && ini_set('session.gc_maxlifetime', self::$options['expire']);

        @session_start();
        //自定义ses类
        $session_class = isset(self::$options['class']) ? self::$options['class'] : '';
        if ($session_class == 'redis') $session_class = '\myphp\session\Redis';

        if ($session_class) {
            $sess = new $session_class(self::$options);
            session_set_save_handler($sess, true);
            register_shutdown_function('session_write_close');
        } else {
            isset(self::$options['path']) && session_save_path(self::$options['path']);
        }
	}
    /**
     * @return bool whether the session has started
     */
    public static function isActive()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function close()
    {
        if (self::isActive()) @session_write_close();
    }
	public static function clear(){ //清除所有会话
		self::init();
        $_SESSION = [];
	}
    //销毁
    public static function destroy()
    {
        session_unset();
        session_destroy();
        $_SESSION = [];
    }
    //设置 session
    public static function set($name, $val)
    {
        self::init();
        $_SESSION[$name] = $val;
    }
    //获取 session
    public static function get($name = '', $def = null)
    {
        self::init();
        if ($name === '') return $_SESSION; //返回全部
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $def;
    }
    //删除 session
    public static function del($name)
    {
        self::init();
        unset($_SESSION[$name]);
    }
}