<?php
namespace myphp;

//日志类
class Log{
    /**
     * @var resource[]
     */
	private $handler = [];
	private static $level = 0; //日志级别 0-5
	private static $size = 2097152; //日志大小 2M 
	private static $instance = null;
	private static $file = null;
	private static $logs = null;
	private static $errs = null;
	private static $errflag = false;
	private static $dir = '_def'; //当前日志目录
	private static $logDir = null; //日志目录

	private function __construct(){}
	public function __destruct(){
		self::free();
	}
	public static function free(){
        if (!self::$instance) return;
        if (self::$instance->handler) {
            foreach (self::$instance->handler as $handler)
                $handler && @fclose($handler);
        }
        self::$instance = null;
    }
    //注册异常处理
    public static function register(){
		set_error_handler('\myphp\Log::UserErr'); // 自定义用户错误处理函数
		set_exception_handler('\myphp\Log::Exception'); //自定义异常处理
		register_shutdown_function('\myphp\Log::Err'); //定义PHP程序执行完成后执行的函数
    }
	//初始日志目录
	public static function Init($logDir=null, $level=0, $size=2097152){
		if(!self::$instance) self::$instance = new self();
		self::$logDir = $logDir ? (substr($logDir,-1)==DS?$logDir:$logDir.DS) : ROOT.'/log/';
        //set_error_handler(function(){});
        !is_dir(self::$logDir) && @mkdir(self::$logDir, 0755, true);
        //restore_error_handler();
		self::$file = self::$logDir.'log.log';
		self::$instance->handler[self::$dir] = fopen(self::$file,'ab');
		self::$level = $level;
		self::$size = $size;
		//PHP_EOL 当前平台中对于换行符的定义
		return self::$instance;
	}
	//切换日志子目录
	public static function Dir($dir='_def'){
		if(!self::$instance) self::Init();
		if(self::$logs){ //切换日志时记录上个辅助日志
			$logs = implode(PHP_EOL, self::$logs);
			self::write($logs, null);
			self::$logs = null;
		}
		self::$dir = $dir;
		if(!isset(self::$instance->handler[$dir])){
            !is_dir(self::$logDir.$dir) && @mkdir(self::$logDir.$dir, 0755, true);
			self::$file = self::$logDir.$dir.'/log.log';
			self::$instance->handler[$dir] = fopen(self::$file,'ab');
		}
	}
	public static function DEBUG($msg){
		self::write($msg, 'debug');
	}
	public static function INFO($msg){
		self::write($msg, 'info');
	}
	public static function NOTICE($msg){
		self::write($msg, 'notice');
	}
	public static function WARN($msg){
		self::write($msg, 'warn');
	}
	public static function SQL($msg){
		self::write($msg, 'sql');
	}
	public static function ERROR($msg){
		self::write($msg, 'error');
	}
	/*******************分隔***************************/
	//错误日志记录 用于 register_shutdown_function
	public static function Err(){
		$stack = '';
		if ($e = error_get_last()) {
			self::$errflag=true;
			self::$errs[] = $stack = date('[Y-m-d H:i:s]').'[error] type:'.$e['type'].', line:'.$e['line'].', file:'.$e['file'].', message:'.$e['message'];
		}
		if(self::$errflag){
			!IS_CLI && self::$errs[] = Log::REQ();
			$logs = implode( PHP_EOL, self::$errs);
			self::write($logs, '_def'); //错误信息记录到主日志
			self::$errs = null;
		}
		if(self::$logs){ //辅助日志记录
			$logs = implode(PHP_EOL, self::$logs);
			self::write($logs, null);
			self::$logs = null;
		}
		if(!IS_CLI && GetC('debug') && $e){
			ob_end_clean();
			exit('<pre style="color:#c10;">'.$stack.'</pre>');
		}
	}
	//自定义错误记录 用于 set_error_handler
	public static function UserErr($errno, $errstr, $errfile, $errline){
		$level = 'info'; $debug=true; $stack = '';
		switch ($errno){
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
            case E_USER_ERROR:
                $level = 'error'; //Fatal Error
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $level = 'warn';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $level = 'notice'; //$debug = false;
                break;
            default:
                $debug = false;
        }
        if($debug){
            $debugInfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if(count($debugInfo)>1){
                array_pop($debugInfo); // 删除最后一个跟踪: Log::UserErr
                $stack = PHP_EOL."[".PHP_EOL;
                foreach($debugInfo as $key => $val){
                    if(array_key_exists("file", $val)){
                        $stack .= ",file:" . $val["file"];
                    }
                    if(array_key_exists("line", $val)){
                        $stack .= ",line:" . $val["line"];
                    }
                    if(array_key_exists("function", $val)){
                        $stack .= "function:" . $val["function"];
                    }
                    $stack .= PHP_EOL;
                }
                $stack .= "]";
            }
        }
        self::write('errno:'.$errno.', line:'.$errline.', file:'.$errfile.', message:'.$errstr.$stack.PHP_EOL.self::miniREQ(), $level);
	}

    /** 自定义异常记录 用于 set_exception_handler
     * @param \Exception $e
     * @param bool $out
     */
	public static function Exception($e, $out=true){
		$err = $e->getMessage().PHP_EOL.'line:'.$e->getLine().', file:'.$e->getFile().PHP_EOL.$e->getTraceAsString();
		if(IS_CLI || !$out){
		    self::WARN($err.PHP_EOL.self::miniREQ());
		    return;
        }
        self::$errflag=true;
        self::$errs[] = date('[Y-m-d H:i:s]').'[error] '.$err;
		if(GetC('debug')) echo '<pre>'.$err.'</pre>';
	}
	public static function miniREQ($raw_full=false){
        if (!isset($_SERVER['REQUEST_METHOD'])) return '';
        $postStr = \myphp::rawBody();
        $_srv = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'],'?')===false && isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . urldecode($_SERVER['QUERY_STRING']) : '') . (isset($_SERVER['SERVER_PROTOCOL']) ? ' ' . $_SERVER['SERVER_PROTOCOL'] : '') . PHP_EOL . (isset($_SERVER['HTTP_HOST']) ? 'HOST:' . $_SERVER['HTTP_HOST'] . PHP_EOL : ''). 'Remote: ' . $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'] . (empty($_SERVER['HTTP_X_REAL_IP']) ? '' : '(' . $_SERVER['HTTP_X_REAL_IP'] . ')');

		return $_srv.(isset($_POST)?PHP_EOL."Form-Data: ".rawurldecode(http_build_query($_POST, "", "&", PHP_QUERY_RFC3986)):'').($postStr?PHP_EOL."Raw: ".($raw_full?$postStr:substr($postStr,0,255)):'');
	}
	//返回请求信息
	public static function REQ($raw_full=false){
        $_srv = self::miniREQ($raw_full);
        if ($_srv) {
            $_srv .= PHP_EOL.(isset($_SERVER['HTTP_ACCEPT']) ? 'Http_Accept: ' . $_SERVER['HTTP_ACCEPT'] . PHP_EOL : '')
                . (isset($_SERVER['HTTP_REFERER']) ? 'Http_Referer: ' . $_SERVER['HTTP_REFERER'] . PHP_EOL : '')
                . (isset($_SERVER['HTTP_USER_AGENT']) ? 'Http_User_Agent: ' . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL : '')
                . (isset($_SERVER['HTTP_COOKIE']) ? 'Http_Cookie: ' . $_SERVER['HTTP_COOKIE'] . PHP_EOL : '') . PHP_EOL;
        }
        return $_srv;
	}
	//日志记录等级判断
	private static function _level($level){
		$val = 2; //日志记录等级值
		switch($level){ //strtolower($level)
			case 'trace':
				$val = 0;break; //追踪
			case 'debug':
				$val = 1;break; //调试
			case 'info':
				$val = 2;break; //信息
			case 'notice':
				$val = 3;break; //通知
			case 'warn':
				$val = 4;break; //警告
            case '_def':
			case 'error':
				$val = 5;break; //错误
			case 'sql':
				$val = 10;break; //sql语句
		}
		return self::$level > $val ? false : true;
	}
	//记录日志 建议优先使用
	public static function trace($msg,$level='trace'){
        if (IS_CLI) {
            self::write($msg, $level);
        } else {
            if (!self::_level($level)) return;
            if (!is_scalar($msg) || is_bool($msg)) $msg = toJson($msg);
            self::$logs[] = date('[Y-m-d H:i:s]') . '[' . $level . '] ' . $msg;
        }
	}
	//写入日志
	public static function write($msg,$level='info')
    {
        if (!self::_level($level)) return;
        if (!is_scalar($msg) || is_bool($msg)) {
            $msg = toJson($msg);
            if (false === $msg) {
                $msg = '->json fail<-' . json_last_error_msg();
            }
        }

        if (!self::$instance) self::Init(); //自动初始化

        $dir = $level == '_def' ? '_def' : self::$dir;
        $file = $level == '_def' ? self::$logDir . 'log.log' : self::$file;
        self::truncate(self::$instance->handler[$dir], $file);

        if ($level && $level != '_def') $msg = '['.date('Y-m-d H:i:s').']['.$level.'] '.$msg;
        if (flock(self::$instance->handler[$dir], LOCK_EX)) {
            fwrite(self::$instance->handler[$dir], $msg.PHP_EOL);
            flock(self::$instance->handler[$dir], LOCK_UN);
        } else {
            error_log($msg . ' write ' . $file . " lock fail".PHP_EOL);
        }
    }
    //写入日志 多个内容输入
    public static function echo($content)
    {
        $msg = '[' . date('Y-m-d H:i:s') . '.' . substr(microtime(), 2, 3) . ']';
        if (func_num_args() > 1) {
            $args = func_get_args();
            foreach ($args as $v) {
                $msg .= (is_scalar($v) && !is_bool($v) ? $v : toJson($v)) . ' ';
            }
        } else {
            $msg .= (is_scalar($content) && !is_bool($content) ? $content : toJson($content));
        }

        if (!self::$instance) self::Init(); //自动初始化

        self::truncate(self::$instance->handler[self::$dir], self::$file);

        if (flock(self::$instance->handler[self::$dir], LOCK_EX)) {
            fwrite(self::$instance->handler[self::$dir], $msg.PHP_EOL);
            flock(self::$instance->handler[self::$dir], LOCK_UN);
        } else {
            error_log($msg . ' write ' . self::$file . " lock fail".PHP_EOL);
        }
    }

    /**
     * 日志超过配置大小则备份并重新生成
     * @param resource $fp
     * @param string $file
     */
    public static function truncate($fp, $file){
        $fileSize = fstat($fp)['size'];
        if (self::$size > $fileSize) return;

        $lockFp = fopen($file, 'r+b'); //读写方式
        if (flock($lockFp, LOCK_EX)) {
            $fileSize = fstat($lockFp)['size'];
            if ($fileSize < self::$size) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
                return;
            }
            #$new_fp = fopen(dirname($file) . '/' . date('YmdHis') . '.log', 'ab');
            #stream_copy_to_stream($lockFp, $new_fp);
            copy($file, dirname($file).'/'.date('YmdHis').'.log');
            ftruncate($lockFp, 0); // 截断文件

            //clearstatcache(true, $file);
            flock($lockFp, LOCK_UN);
        } else {
            error_log(date('Y-m-d H:i:s') . ' truncate, ' . $file . ' lock fail' . PHP_EOL);
        }
        fclose($lockFp);
    }
/*

DROP DATABASE IF EXISTS `log`;
CREATE DATABASE `log`;
-- 日志表 sys_log
DROP TABLE IF EXISTS `sys_log`;
CREATE TABLE `sys_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ctime` int(10) unsigned DEFAULT '0',
  `log` varchar(30) NOT NULL,
  `url` varchar(255) DEFAULT '',
  `ip` varchar(20) DEFAULT '',
  `des` text,
  PRIMARY KEY (`id`),
  KEY `ctime` (`ctime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
*/
	//以数据库方式记录 2
	public static function sys_log($log,$des){
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-';
		$url = Request::url();
		$post = array('ctime'=>time(),'log'=>$log,'des'=>$des,'url'=>substr("referer: $referer\nurl: $url",0,250),'ip'=>GetIP());
        db()->add($post, 'sys_log');
	}
}