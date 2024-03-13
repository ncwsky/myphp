<?php

/**
 * 循环日志写入 并发写入时日志记录时间可能顺序错乱
 * 示例
function cLog($name='clog'){
    static $log;
    if (!isset($log[$name])) {
        $log[$name] = new RotateLog(RUNTIME . '/' . $name . '.log'); //,RotateLog::MODE_FIXED, 6*1024*1024
    }
    return $log[$name];
}
cLog()->write('[test]',microtime(),true);
cLog('ab')->write(['test'],microtime(),true);
 */
class RotateLog
{
    public static $isLog = true;

    const MODE_DEF = 0; //达到大小自动生成新文件
    const MODE_YMD = 1; //按年月日生成
    const MODE_FIXED = 2; //固定大小 超出保留ratio分之一最新日志

    private $keepSize; //截断保留x分之一
    private $logSize; //4M
    private $logFile;
    private $mode;
    private $fp;
    private $tplYmdFile = '';
    private $ymd = '';

    public function __construct($logFile, $mode=self::MODE_DEF, $logSize=4194304, $ratio=3)
    {
        $this->mode = $mode;
        $this->logSize = $logSize;
        if ($this->mode == self::MODE_YMD) {
            $fileInfo = pathinfo($logFile);
            $fileName = $fileInfo['filename'];
            $fileExtension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            // 构建新的文件路径
            $this->ymd = date('Ymd');
            $this->tplYmdFile = $fileInfo['dirname'] . '/' . $fileName . '_{Ymd}' . $fileExtension;
            $this->logFile = $fileInfo['dirname'] . '/' . $fileName . '_' . $this->ymd . $fileExtension;
            $logDir = $fileInfo['dirname'];
        } else {
            $this->logFile = $logFile;
            $logDir = dirname($logFile);
        }
        !is_dir($logDir) && @mkdir($logDir, 0755, true);

        $this->keepSize = intval($this->logSize / $ratio);

        $this->fp = fopen($this->logFile, 'ab');
    }
    public function __destruct()
    {
        fclose($this->fp);
    }

    public function json($content)
    {
        return json_encode($content, defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0);
    }

    //仅记录指定大小的日志 超出大小重置重新记录
    public function write($content)
    {
        if (!self::$isLog) return;
        $time = time();
        $this->truncate($time);

        $msg = '[' . date($this->mode == self::MODE_YMD ? 'H:i:s':'Y-m-d H:i:s', $time) . '.' . substr(microtime(), 2, 3) . ']';
        if (func_num_args() > 1) {
            $args = func_get_args();
            foreach ($args as $v) {
                $msg .= (is_scalar($v) && !is_bool($v) ? $v : $this->json($v)) . ' ';
            }
        } else {
            $msg .= (is_scalar($content) && !is_bool($content) ? $content : $this->json($content));
        }

        if (flock($this->fp, LOCK_EX)) {
            fwrite($this->fp, $msg . PHP_EOL);
            flock($this->fp, LOCK_UN);
        } else {
            error_log($msg . ' write ' . $this->logFile . ' lock fail'.PHP_EOL);
        }
    }

    private function truncate($time)
    {
        if ($this->mode == self::MODE_YMD) { //按年月日生成
            $logYmd = date('Ymd', $time);
            if ($logYmd != $this->ymd) {
                fclose($this->fp);
                $this->ymd = $logYmd;
                $this->logFile = str_replace('{Ymd}', $logYmd, $this->tplYmdFile);
                $this->fp = fopen($this->logFile, 'ab');
            }
            return;
        }

        $fileSize = fstat($this->fp)['size'];
        //$fileSize = filesize($this->logFile); //测试有缓存大小读取不对
        //global $i; if($i>99990) var_dump($fileSize.'-'. $this->logSize);
        if ($this->logSize > $fileSize) return;

        //读写方式
        $fp = fopen($this->logFile, 'r+b');
        if (flock($fp, LOCK_EX)) {
            $fileSize = fstat($fp)['size'];
            if ($fileSize < $this->logSize) { //文件可能已截断需要重新判断
                #fclose($this->fp);
                #$this->fp = fopen($this->logFile, 'ab');
                flock($fp, LOCK_UN);
                fclose($fp);
                return;
            }
            $microtime = microtime(true);

            if ($this->mode == self::MODE_FIXED) {
                $size = $this->keepSize;

                fseek($fp, -$size, SEEK_END); //移动到保留偏移量
                //第一行可能数据不全，再读一行
                $offset = ftell($fp);
                if (fgets($fp) !== false) {
                    $size -= (ftell($fp) - $offset);
                }

                $buffer = 524288; //块读取大小512K
                $w_offset = 0; //覆写偏移量
                // 逐块读取和写入
                while (true) {
                    $chunk = fread($fp, $buffer);
                    $eof = feof($fp);
                    $pos = ftell($fp); //当前位置
                    //移动到覆写偏移量 覆写
                    fseek($fp, $w_offset);
                    fwrite($fp, $chunk);
                    $w_offset = ftell($fp);

                    if ($eof) break;
                    fseek($fp, $pos); //移动回原来读取位置
                }
                // 截断文件，删除多余的内容
                ftruncate($fp, $w_offset);
                //stat
                fwrite($fp, '['.date('Y-m-d H:i:s').'.'.substr(microtime(), 2,3) . ']'. sprintf('use %s truncate, %s -> %s', run_time($microtime), $fileSize, $size) . PHP_EOL);
            } else {
                $new_fp = fopen(dirname($this->logFile).'/'.date('YmdHis').'.log', 'ab');
                stream_copy_to_stream($fp, $new_fp);
                //copy($this->logFile, dirname($this->logFile).'/'.date('YmdHis').'.log');
                ftruncate($fp, 0); // 截断文件
            }
            //clearstatcache(true, $this->logFile);
            flock($fp, LOCK_UN);
        } else {
            error_log(date('Y-m-d H:i:s') . ' truncate, ' . $this->logFile . ' lock fail'.PHP_EOL);
        }
        fclose($fp);
    }
}