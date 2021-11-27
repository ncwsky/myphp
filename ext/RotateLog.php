<?php

class RotateLog
{
    public static $logFile = './log.log';
    public static $logSize = 4194304; //4M
    public static $isLog = true;
    public static $ratio = 10;

    public function logInit($logFile, $logSize=4194304, $log=true)
    {
        self::$logFile = $logFile;
        self::$isLog = $log;
        self::$logSize = $logSize;
    }

    public static function json($content)
    {
        return json_encode($content, defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0);
    }

    //仅记录指定大小的日志 超出大小重置重新记录
    public function log($content)
    {
        if (!self::$isLog) return;

        if (is_file(self::$logFile) && self::$logSize <= filesize(self::$logFile)) {
            $size = intval(self::$logSize / self::$ratio);
            $fp = fopen(self::$logFile, 'r+');
            flock($fp, LOCK_EX | LOCK_NB);
            fseek($fp, $size, SEEK_END);
            fwrite($fp, fgets($fp, $size));
            flock($fp, LOCK_UN);
            fclose($fp);
            //file_put_contents(self::$logFile, '', LOCK_EX); clearstatcache(true, self::$logFile);
        }
        if (func_num_args() > 1) {
            $args = func_get_args();
            $content = '';
            foreach ($args as $v) {
                $content .= (is_scalar($v) ? $v : self::json($v)) . ' ';
            }
        }
        file_put_contents(self::$logFile, "[" . date("Y-m-d H:i:s").'.'.substr(microtime(), 2,3) . "]" . (is_scalar($content) ? $content : self::json($content)) . "\n", FILE_APPEND);
    }
}