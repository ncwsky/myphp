<?php
//Session工厂
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
			isset($GLOBALS['cfg']['cookie_path'])?$GLOBALS['cfg']['cookie_path']:'/',
			isset($GLOBALS['cfg']['cookie_domain'])?$GLOBALS['cfg']['cookie_domain']:'',
			isset($GLOBALS['cfg']['cookie_secure'])?$GLOBALS['cfg']['cookie_secure']:FALSE,
			TRUE // HttpOnly; Yes, this is intentional and not configurable for security reasons
		);
		session_name($opts['id']);
		isset($opts['path']) && session_save_path($opts['path']);
		
		if(isset(self::$instance[$type])) return true;
		
		if($type!='file'){ //linux因权限原因自定义file操作类会出现无权限的情况
			if(!file_exists(MY_PATH.'/lib/session/'.$type.'.class.php')) exit($type.'会话类无法加载');
			require(MY_PATH.'/lib/session/'.$type.'.class.php');
			$session_class = 'session_'.$type;
			if(!class_exists($session_class)) exit($session_class.'会话类没有定义');

			$sess = new $session_class($opts);
			if(version_compare(PHP_VERSION,'5.4','>=')){
				session_set_save_handler($sess, true);
			}else{
				session_set_save_handler(
					array($sess, 'open'),
					array($sess, 'close'),
					array($sess, 'read'),
					array($sess, 'write'),
					array($sess, 'destroy'),
					array($sess, 'gc')
				);
			}
			register_shutdown_function('session_write_close');
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
if (!interface_exists('SessionHandlerInterface')) {
	//session接口
	interface SessionHandlerInterface{
		public function open($save_path, $name); // bool
		public function read($sid);
		public function write($sid, $data);
		
		public function close();
		public function destroy($sid); //删除 session_id
		public function gc($maxlifetime); //回收 bool
	}
}
//session抽象类
abstract class session_abstract implements SessionHandlerInterface{
	protected $handler, $options;
	
	//构造函数
	public function __construct($options=array()){
		$this->options = $options;
	}
	//关闭
	public function close(){
		return true;
	}
}