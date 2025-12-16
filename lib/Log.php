<?php

declare(strict_types=1);

namespace myphp;

//日志类
class Log
{
    private static $level = 0; //日志级别 0-5
    private static $size = 2097152; //日志大小 2M
    private static $file = '';
    private static $logs = [];
    private static $errs = null;
    private static $errFlag = false;
    private static $logDir = null; //日志目录

    //注册异常处理
    public static function register(): void
    {
        set_error_handler('\myphp\Log::UserErr'); // 自定义用户错误处理函数
        set_exception_handler('\myphp\Log::Exception'); //自定义异常处理
        register_shutdown_function('\myphp\Log::Err'); //定义PHP程序执行完成后执行的函数
    }

    //初始日志目录
    public static function Init(?string $logDir = null, int $level = 0, int $size = 2097152): void
    {
        if (self::$logDir) {
            return;
        }
        self::$logDir = $logDir ? (substr($logDir, -1) == DS ? $logDir : $logDir . DS) : ROOT . '/log/';
        self::$level = $level;
        self::$size = $size;

        self::$file = self::$logDir . 'log.log';
        if (!is_file(self::$file)) {
            //set_error_handler(function(){});
            !is_dir(self::$logDir) && @mkdir(self::$logDir, 0755, true);
            //restore_error_handler();

            $fp = fopen(self::$file, 'a');
            $fp && fclose($fp);
        }
        //self::$logFiles[self::$dir] = self::$file;
    }

    /**
     * 切换日志子目录
     * @param string $dir '_def' 当前日志主目录
     * @return void
     */
    public static function Dir(string $dir = '_def'): void
    {
        self::Init();
        if (self::$logs) { //切换日志时记录上个辅助日志
            $logs = implode(PHP_EOL, self::$logs);
            self::write($logs, '');
            self::$logs = [];
        }
        self::$file = self::$logDir . $dir . '/log.log';
        if (!is_file(self::$file)) {
            !is_dir(self::$logDir . $dir) && @mkdir(self::$logDir . $dir, 0755, true);
            $fp = fopen(self::$file, 'a');
            $fp && fclose($fp);
        }
    }

    public static function DEBUG($msg): void
    {
        self::write($msg, 'debug');
    }

    public static function INFO($msg): void
    {
        self::write($msg);
    }

    public static function NOTICE($msg): void
    {
        self::write($msg, 'notice');
    }

    public static function WARN($msg): void
    {
        self::write($msg, 'warn');
    }

    public static function SQL($msg): void
    {
        self::write($msg, 'sql');
    }

    public static function ERROR($msg): void
    {
        self::write($msg, 'error');
    }
    /*******************分隔***************************/
    //错误日志记录 用于 register_shutdown_function
    public static function Err(): void
    {
        $stack = '';
        if ($e = error_get_last()) {
            self::$errFlag = true;
            self::$errs[] = $stack = date('[Y-m-d H:i:s]') . '[error] line:' . $e['line'] . ', file:' . $e['file'] . ', err:' . $e['message'];
        }
        if (self::$errFlag) {
            !IS_CLI && array_unshift(self::$errs, Log::REQ()); //在开头记录请求信息
            $logs = implode(PHP_EOL, self::$errs);
            self::write($logs, '_def'); //错误信息记录到主日志
            self::$errs = null;
        }
        if (self::$logs) { //辅助日志记录
            $logs = implode(PHP_EOL, self::$logs);
            self::write($logs, '');
            self::$logs = [];
        }
        if (!IS_CLI && GetC('debug') && $e) {
            ob_end_clean();
            echo '<pre style="color:#c10;">' . $stack . '</pre>';
        }
    }

    //自定义错误记录 用于 set_error_handler
    public static function UserErr($errno, $err, $eFile, $eLine): bool
    {
        $level = 'info';
        $debug = true;
        $stack = '';
        switch ($errno) {
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
        if ($debug) {
            $debugInfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if (count($debugInfo) > 1) {
                array_shift($debugInfo); // 删除最前一个跟踪: Log::UserErr
                $stack = PHP_EOL;
                foreach ($debugInfo as $val) {
                    if (isset($val['type'])) {
                        $val['function'] = $val['class'] . $val['type'] . $val['function'];
                        if (!isset($val['line'])) {
                            $val['line'] = '';
                        }
                        if (!isset($val['file'])) {
                            $val['file'] = '';
                        }
                    }
                    /*foreach ($val as $k => $v) {
                        if (in_array($k, ['line', 'file', 'function'])) {
                            $stack .= $k . ':' . $v . ' ';
                        }
                    }*/
                    //$stack .= PHP_EOL;
                    $stack .= 'line:' . $val['line'] . ', file:' . $val['file'] . ', func:' . $val['function'] . PHP_EOL;
                }
            }
        }
        self::write(self::miniREQ() . PHP_EOL . 'errno:' . $errno . ', line:' . $eLine . ', file:' . $eFile . ', err:' . $err . $stack, $level);
        return true;
    }

    /** 自定义异常记录 用于 set_exception_handler
     * @param \Throwable $e
     * @param bool $out
     */
    public static function Exception(\Throwable $e, bool $out = true): void
    {
        $err = 'line:' . $e->getLine() . ', file:' . $e->getFile() . ', err:' . $e->getMessage() . PHP_EOL . $e->getTraceAsString();
        if (IS_CLI || !$out) {
            self::WARN(self::miniREQ() . PHP_EOL . $err);
            return;
        } elseif (GetC('debug')) {
            echo '<pre>' . $err . '</pre>';
        }
        self::$errFlag = true;
        self::$errs[] = date('[Y-m-d H:i:s]') . '[error] ' . $err;
    }

    public static function miniREQ(bool $raw_full = false): string
    {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return '';
        }
        $postStr = \myphp::rawBody();
        $_srv = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false && isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . urldecode($_SERVER['QUERY_STRING']) : '') . (isset($_SERVER['SERVER_PROTOCOL']) ? ' ' . $_SERVER['SERVER_PROTOCOL'] : '') . PHP_EOL . (isset($_SERVER['HTTP_HOST']) ? 'HOST:' . $_SERVER['HTTP_HOST'] . PHP_EOL : '') . 'Remote: ' . $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'] . (empty($_SERVER['HTTP_X_REAL_IP']) ? '' : '(' . $_SERVER['HTTP_X_REAL_IP'] . ')');

        return $_srv . (empty($_POST) ? '' : PHP_EOL . "Form-Data: " . rawurldecode(http_build_query($_POST, "", "&", PHP_QUERY_RFC3986))) . ($postStr ? PHP_EOL . "Raw: " . ($raw_full ? $postStr : substr($postStr, 0, 255)) : '');
    }

    //返回请求信息
    public static function REQ(bool $raw_full = false): string
    {
        $_srv = self::miniREQ($raw_full);
        if ($_srv) {
            $_srv .= PHP_EOL . (isset($_SERVER['HTTP_ACCEPT']) ? 'Http_Accept: ' . $_SERVER['HTTP_ACCEPT'] . PHP_EOL : '')
                . (isset($_SERVER['HTTP_REFERER']) ? 'Http_Referer: ' . $_SERVER['HTTP_REFERER'] . PHP_EOL : '')
                . (isset($_SERVER['HTTP_USER_AGENT']) ? 'Http_User_Agent: ' . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL : '')
                . (isset($_SERVER['HTTP_COOKIE']) ? 'Http_Cookie: ' . $_SERVER['HTTP_COOKIE'] . PHP_EOL : '') . PHP_EOL;
        }
        return $_srv;
    }

    //日志记录等级判断
    private static function _level(string $level): bool
    {
        $val = 2; //日志记录等级值
        switch ($level) { //strtolower($level)
            case 'trace':
                $val = 0;
                break; //追踪
            case 'debug':
                $val = 1;
                break; //调试
            case 'info':
                $val = 2;
                break; //信息
            case 'notice':
                $val = 3;
                break; //通知
            case 'warn':
                $val = 4;
                break; //警告
            case '_def':
            case 'error':
                $val = 5;
                break; //错误
            case 'sql':
                $val = 10;
                break; //sql语句
        }
        return $val >= self::$level;
    }

    //记录日志 建议优先使用
    public static function trace($msg, string $level = 'trace'): void
    {
        if (IS_CLI) {
            self::write($msg, $level);
        } else {
            if (!self::_level($level)) {
                return;
            }
            if (!is_scalar($msg) || is_bool($msg)) {
                $msg = toJson($msg);
            }
            self::$logs[] = date('[Y-m-d H:i:s]') . '[' . $level . '] ' . $msg;
        }
    }

    //写入日志
    public static function write($msg, string $level = 'info'): void
    {
        if (!self::_level($level)) {
            return;
        }
        if (!is_scalar($msg) || is_bool($msg)) {
            $msg = toJson($msg);
            if (false === $msg) {
                $msg = '->json fail<-' . json_last_error_msg();
            }
        }
        if ($level && $level != '_def') {
            $msg = '[' . date('Y-m-d H:i:s') . '][' . $level . '] ' . $msg;
        }

        self::Init();
        $file = $level == '_def' ? self::$logDir . 'log.log' : self::$file;
        self::truncate($file, $msg);
    }

    //写入日志 多个内容输入
    public static function echo($content): void
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

        self::Init();
        self::truncate(self::$file, $msg);
    }

    /**
     * 日志超过配置大小则备份并重新生成
     * 在锁定状态下直接在锁定代码外fwrite会失败(Permission denied)
     * @param string $file
     * @param string $msg
     */
    public static function truncate(string $file, string $msg): void
    {
        if (filesize($file) < self::$size) {
            if (!file_put_contents($file, $msg . PHP_EOL, FILE_APPEND)) {
                error_log($msg . ' write ' . $file . " fail" . PHP_EOL);
            }
            return;
        }
        //日志超出大小 截断日志
        $fp = fopen($file, 'a');
        if (!$fp) {
            error_log('Failed to open: ' . $file);
            return;
        }
        if (flock($fp, LOCK_EX)) { //并发阻塞
            clearstatcache(true, $file);
            if (!fwrite($fp, $msg . PHP_EOL)) {
                error_log($msg . ' write ' . $file . " fail" . PHP_EOL);
            }
            if (filesize($file) >= self::$size) { //并发后这里的大小可能已改变
                fflush($fp);  // 确保写入物理存储
                if (rename($file, dirname($file) . '/' . date('YmdHis') . '.log')) {
                    $new = fopen($file, 'a');
                    $new && fclose($new);
                }
            }
            flock($fp, LOCK_UN);
        } else {
            error_log(date('Y-m-d H:i:s') . $msg . ' truncate, ' . $file . ' lock fail' . PHP_EOL);
        }
        fclose($fp);
    }
}
