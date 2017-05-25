<?php
//日志类
class Log{
    // 日志级别
    const ERR     = 'ERR';  // 一般错误: 一般性错误
    const WARN    = 'WARN';  // 警告性错误: 需要发出警告的错误
	const NOTICE  = 'NOTIC';  // 通知: 程序可以运行但是还不够完美的错误
    const INFO    = 'INFO';  // 信息: 程序输出信息
    const DEBUG   = 'DEBUG';  // 调试: 调试信息
    const SQL     = 'SQL';  // SQL：SQL语句 注意只在调试模式开启时有效
	const LOG_SIZE= 2097152; //2M
	
	private $handler = null;
	private static $instance = null;
	private static $file = null;
	private static $logs = null;
	private static $errs = null;
	private static $errflag = false;
	private static $dir = '_def'; //当前日志目录 记录辅助信息日志 如订单
	private static $logDir = null; //日志主目录 记录程序运行日志

	private function __construct(){}
	public function __destruct(){
		if(self::$instance->handler){
			foreach(self::$instance->handler as $handler)
				fclose($handler);
		}
		self::$instance=null;
	}
    //注册异常处理
    public static function register(){
		set_error_handler('Log::UserErr'); // 自定义用户错误处理函数
		set_exception_handler('Log::Exception'); //自定义异常处理
		register_shutdown_function('Log::Err'); //定义PHP程序执行完成后执行的函数
    }
	//初始日志目录
	public static function Init($logDir=null){
		if(!self::$instance instanceof self){
			self::$logDir = $logDir ? $logDir : ROOT.ROOT_DIR.DS;
			self::$file = self::$logDir.'log.log';
			self::$instance = new self();
			self::$instance->handler[self::$dir] = fopen(self::$file,'a');
		}
		return self::$instance;
	}
	public static function Dir($dir='_def'){
		if(!self::$instance) self::Init();
		if(self::$logs){ //切换日志时记录上个辅助日志
			$logs = implode('', self::$logs);
			self::write($logs, null);
			self::$logs = null;
		}
		self::$dir = $dir;
		if(!isset(self::$instance->handler[$dir])){
			if (!is_dir(self::$logDir.$dir)) mkdir(self::$logDir.$dir);
			self::$file = self::$logDir.$dir.'/log.log';
			self::$instance->handler[$dir] = fopen(self::$file,'a');
		}
	}
	
	public static function DEBUG($msg){
		self::write($msg, 'debug');
	}
	public static function WARN($msg){
		self::write($msg, 'warn');
	}
	public static function INFO($msg){
		self::write($msg, 'info');
	}
	public static function ERROR($msg){
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
		else self::write($stack . $msg, 'error');
	}
	/*******************分隔***************************/
	//错误日志记录 用于 register_shutdown_function
	public static function Err(){
		$stack = '';
		if ($e = error_get_last()) {
			self::$errflag=true;
			self::$errs[] = $stack = date('[Y-m-d H:i:s]').'[error] type:'.$e['type'].', line:'.$e['line'].', file:'.$e['file'].', message:'.$e['message']."\n";
		}
		if(self::$errflag){ //主日志记录错误信息
			PHP_SAPI != 'cli' && self::$errs[] = Log::REQ();
			$logs = implode('', self::$errs);
			self::write($logs, '_def');
			self::$errs = null;
		}
		if(self::$logs){ //辅助日志记录
			$logs = implode('', self::$logs);
			self::write($logs, null);
			self::$logs = null;
		}
		if($e && isset($GLOBALS['cfg']['debug']) && $GLOBALS['cfg']['debug']){
			ob_end_clean();
			exit('<pre style="color:#c10;">'.$stack.'</pre>');
		}
	}
	//自定义错误记录 用于 set_error_handler
	public static function UserErr($errno, $errstr, $errfile, $errline){
		self::$errflag=true;
		self::$errs[] =  date('[Y-m-d H:i:s]').'[error] type:'.$errno.', line:'.$errline.', file:'.$errfile.', message:'.$errstr."\n".self::ERROR('debug_backtrace')."\n";
	}
	//自定义异常记录 用于 set_exception_handler
	public static function Exception($e,$out=true){
        self::$errflag=true;
        self::$errs[] = date('[Y-m-d H:i:s]').'[error] '.$e->getMessage()."\n".', line:'.$e->getLine().', file:'.$e->getFile()."\n".$e->getTraceAsString()."\n";
        // 发送404信息
        //header('HTTP/1.1 404 Not Found');
        //header('Status:404 Not Found');
		if($out) echo '<pre>'.$e->getMessage().'</pre>';
	}
	public static function miniREQ(){
		$_srv = 'Request: '.$_SERVER['SERVER_PROTOCOL'].' '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' '.date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME'])."\n"
		.($_SERVER['QUERY_STRING']!=''?'Query_String: '. urldecode($_SERVER['QUERY_STRING']) ."\n":'')
		.'Remote: '.$_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT']."\n";
		$post = isset($_POST)?"POST: ".json($_POST)."\n":'';

		return date('[Y-m-d H:i:s]').'[MiniREQ] '.$_srv."\n".$post."\n";
	}
	//返回请求信息
	public static function REQ(){
		$postStr = isset($GLOBALS["HTTP_RAW_POST_DATA"])?$GLOBALS["HTTP_RAW_POST_DATA"]:file_get_contents("php://input");
		$_srv = 'Request: '.$_SERVER['SERVER_PROTOCOL'].' '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' '.date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME'])."\n"
		.($_SERVER['QUERY_STRING']!=''?'Query_String: '. urldecode($_SERVER['QUERY_STRING']) ."\n":'')
		.(isset($_SERVER['HTTP_ACCEPT'])?'Http_Accept: '.$_SERVER['HTTP_ACCEPT']."\n":'')
		.(isset($_SERVER['HTTP_REFERER'])?'Http_Referer: '.$_SERVER['HTTP_REFERER']."\n":'')
		.'Http_User_Agent: '.$_SERVER['HTTP_USER_AGENT']."\n"
		.(isset($_SERVER['HTTP_COOKIE'])?'Http_Cookie: '.$_SERVER['HTTP_COOKIE']."\n":'')
		.'Remote: '.$_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT']."\n";

		$get = isset($_GET)?"GET: ".json($_GET)."\n":'';
		$post = isset($_POST)?"POST: ".json($_POST)."\n":'';
		return $_srv."\n".$get.$post."HTTP_RAW_POST_DATA: ".$postStr."\n";
	}
	//记录日志 建议优先使用
	public static function trace($msg,$level='trace'){
		self::$logs[] = date('[Y-m-d H:i:s]')."[{$level}] {$msg}\n";
	}
	//写入日志
	public static function write($str,$level='trace',$file=null){
		$isHandler = false;
		if(!$file){
			$file = ROOT.ROOT_DIR.'/log.log';
			if(self::$file){
				$file = $level=='_def'?self::$logDir.'log.log':self::$file;
				$isHandler = true;
			}
		}
		//日志超过配置大小则备份并重新生成
		if(is_file($file) && floor(GetC('log_size')) <= filesize($file) )
			rename($file,dirname($file).'/'.date('YmdHis').'-'.basename($file));
		if($level && $level!='_def') 
			$str = date('[Y-m-d H:i:s]')."[{$level}] {$str}\n";
		$isHandler ? fwrite(self::$instance->handler[$level=='_def'?'_def':self::$dir], $str, strlen($str)+1) : error_log($str, 3, $file);
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