<?php
//以下为日志
class CLogFileHandler{
	private $handle = null;
	
	public function __construct($file = ''){
		$this->handle = fopen($file,'a');
	}
	
	public function write($msg){
		fwrite($this->handle, $msg, 4096);
	}
	
	public function __destruct(){
		fclose($this->handle);
	}
}
//单实例日志
class Log{
    // 日志级别
    const ERR     = 'ERR';  // 一般错误: 一般性错误
    const WARN    = 'WARN';  // 警告性错误: 需要发出警告的错误
	const NOTICE  = 'NOTIC';  // 通知: 程序可以运行但是还不够完美的错误
    const INFO    = 'INFO';  // 信息: 程序输出信息
    const DEBUG   = 'DEBUG';  // 调试: 调试信息
    const SQL     = 'SQL';  // SQL：SQL语句 注意只在调试模式开启时有效
	
	private $handler = null;
	private $level = 15; //最高级别15
	private static $instance = null;
	private static $file = null;
	private static $logs = array();

	private function __construct(){}
	public function __destruct(){
		self::$instance=self::$logs=null;
	}
	//初始实例
	public static function Init($file=null,$level=15){
		if(!self::$instance instanceof self){
			self::$file = $file ? $file : MY_PATH.'/../log.log'; //未指定日志 框架同级log.log记录
			self::$instance = new self();
			self::$instance->handler = new CLogFileHandler(self::$file);
			self::$instance->level = $level;
		}
		return self::$instance;
	}
	public static function DEBUG($msg){
		self::$instance->_write(1, $msg);
	}
	public static function WARN($msg){
		self::$instance->_write(4, $msg);
	}
	public static function INFO($msg){
		self::$instance->_write(2, $msg);
	}
	public static function ERROR($msg=''){
		$debugInfo = debug_backtrace();
		$stack = "[\n";
		foreach($debugInfo as $key => $val){
			if(array_key_exists("file", $val)){
				$stack .= ",file:" . $val["file"];
			}
			if(array_key_exists("line", $val)){
				$stack .= ",line:" . $val["line"];
			}
			if(array_key_exists("function", $val)){
				$stack .= ",function:" . $val["function"];
			}
			$stack .= "\n";
		}
		$stack .= "]";
		if($msg == 'debug_backtrace') return $stack;
		else self::$instance->_write(8, $stack . $msg);
	}
	private function _write($level,$msg){
		if(($level & $this->level) == $level){
			$msg = '['.date('Y-m-d H:i:s').']['.$this->_getLevelStr($level).'] '.$msg."\n";
			$this->handler->write($msg);
		}
	}
	private function _getLevelStr($level){
		switch ($level){
		case 1:
			return 'debug';
		break;
		case 2:
			return 'info';	
		break;
		case 4:
			return 'warn';
		break;
		case 8:
			return 'error';
		break;
		default:
			return 'unknown';	
		}
	}
	/*******************分隔***************************/
	//自定义错误日志记录 用于 register_shutdown_function
	public static function Err(){
		$stack = "";
		if(!empty(self::$logs)){
			$trace = '';
			foreach(self::$logs as $log) $trace .= $log;
			self::write($trace,'trace');
			self::$logs = array();
		}
		if ($e = error_get_last()) {
			//错误类型 http://php.net/manual/zh/errorfunc.constants.php
			$stack .= 'type:'.$e['type'].', line:'.$e['line'].', file:'.$e['file'].', message:'.$e['message'];
		}else return;
		self::write($stack);
		if(isset($GLOBALS['cfg']['debug']) && $GLOBALS['cfg']['debug']){
		}else{
			ob_end_clean();
			exit('<pre style="color:#c10;">'.$stack.'</pre>');
		}
	}
	//自定义错误日志记录 用于 set_error_handler
	public static function UserErr($errno, $errstr, $errfile, $errline){
		self::write('type:'.$errno.', line:'.$errline.', file:'.$errfile.', message:'.$errstr."\n".self::ERROR('debug_backtrace'));
	}
	//自定义异常记录 用于 set_exception_handler
	public static function Exception($e){
		$error = array();
        $error['message'] = $e->getMessage();
		$error['file']    = $e->getFile();
		$error['line']    = $e->getLine();
        //$error['trace']   = $e->getTraceAsString();
        Log::trace('line:'.$error['line'].', file:'.$error['file'].', message:'.$error['message']."\n".$e->getTraceAsString());
        // 发送404信息
        header('HTTP/1.1 404 Not Found');
        header('Status:404 Not Found');
		echo '<pre>'.json_encode($error).'</pre>';
	}
	
	//追踪记录日志 建议优先使用
	public static function trace($msg,$level='trace'){
		self::$logs[] = date('[Y-m-d H:i:s]')."[{$level}] {$msg}\n";//$msg;
	}
	// 直接写入日志
	public static function write($message,$level='error',$file=null){
		$isHandler = false;
		if(!$file){
			if(self::$file){
				$file = self::$file;
				$isHandler = true;
			}else $file = MY_PATH.'/../log.log';
			//$file = self::$file?self::$file:MY_PATH.'/../log.log';
		}
		//检测日志大小，超过配置大小则备份日志文件重新生成
		if(is_file($file) && floor(GetC('log_size')) <= filesize($file) )
			rename($file,dirname($file).'/'.time().'-'.basename($file));
			
		if($level!='trace') {
			$message = date('[Y-m-d H:i:s]')."[{$level}] {$message}\n";	
		}
		$isHandler ? self::$instance->handler->write($message) : error_log($message, 3, $file);
    }
/*
-- 日志表 sys_log 
drop table if exists `sys_log`;
CREATE TABLE `sys_log` (
`id` int unsigned auto_increment NOT NULL primary key,
`log` char(30) DEFAULT '' NULL , -- 日志类型
`des` varchar(255) DEFAULT '' NULL , -- 日志描述
`url` varchar(255) DEFAULT '' NOT NULL , -- 日志处理url
`ip` varchar(20) DEFAULT '' NULL ,
`ctime` int(10) unsigned DEFAULT '0' NOT NULL 
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
Create Index `log` ON `sys_log`(`log`);	
*/
	//以数据库方式记录 2
	public static function sys_log($log,$des){
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '浏览器直接输入';
		$url = get_url();
		$post = array('log'=>$log,'des'=>substr($des,0,250),'url'=>substr("referer: $referer\nurl: $url",0,250),'ip'=>GetIP(),'ctime'=>SYS_TIME);
		$db = M();
		$db->add($post, '{db_prefix}sys_log');
	}
}