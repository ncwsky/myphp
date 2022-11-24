<?php
namespace myphp;

/**
 * Session工厂
 */
class Session {
	private static $instance = array();
	//初始会话
	public static function init($type='file', $options=null){
		$opts=array('id'=>'MYPHPSID');
		if(is_string($options)) $opts['id'] = $options; //前缀 session cookie name
		elseif(is_int($options)) $opts['expire'] = $options; //有效期 秒
		elseif(is_array($options)) {
           $opts  = array_merge($opts, $options);
		   unset($options);
        }
		isset($opts['expire']) && ini_set('session.gc_maxlifetime', $opts['expire']);
		
		session_set_cookie_params(
			isset($opts['expire'])?(int)$opts['expire']:0,
			isset(\myphp::$cfg['cookie_path'])?\myphp::$cfg['cookie_path']:'/',
			isset(\myphp::$cfg['cookie_domain'])?\myphp::$cfg['cookie_domain']:'',
			isset(\myphp::$cfg['cookie_secure'])?\myphp::$cfg['cookie_secure']:false,
            true // HttpOnly; Yes, this is intentional and not configurable for security reasons
		);
		session_name($opts['id']);

		if(isset(self::$instance[$type])) return true;
		
		if($type!='file'){ //linux因权限原因自定义file操作类会出现无权限的情况
            $session_class = '\myphp\session\\'.ucfirst($type);
			if(!class_exists($session_class)) throw new \Exception($session_class.' not found');

			$sess = new $session_class($opts);
            session_set_save_handler($sess, true);
			register_shutdown_function('session_write_close');
        } else {
            isset($opts['path']) && session_save_path($opts['path']);
        }
		self::$instance[$type] = true;
		if(!isset($_SESSION)) session_start();
	}
	public static function clear(){ //清除所有会话
		!isset($_SESSION) && self::init();
		$_SESSION = array();
	}
	public static function destroy(){ //销毁
		session_unset();
		session_destroy();
		$_SESSION = array();
	}
	public static function set($name, $val){ //设置 session
		!isset($_SESSION) && self::init();
		$_SESSION[$name] = $val;
	}
	public static function get($name=''){ //获取 session
		!isset($_SESSION) && self::init();
		if($name=='') return $_SESSION; //返回全部
		return isset($_SESSION[$name]) ? $_SESSION[$name] : array();
	}
	public static function del($name){ //删除 session
		!isset($_SESSION) && self::init();
		unset($_SESSION[$name]);
	}
}