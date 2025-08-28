<?php

declare(strict_types=1);

namespace myphp\cache;

use myphp\Log;

/**
 * 文件缓存类
 * 操作失败返回 false
 */
class File extends \myphp\CacheAbstract
{
    public $gcProbability = 10; //100000次设置有10次机率触发垃圾回收
    public $suffix = '.php';

    public const MODE_SERIALIZE = 1;
    public const MODE_PHP = 2;

    //配置
    protected $options = [
        'path' => RUNTIME . '/cache',
        'prefix' => '_',
        'mode' => self::MODE_SERIALIZE, //mode 1 为serialize model 2为保存为可执行文件
        'dir_level' => 0, //缓存层级
    ];

    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->setCacheDir();
    }
    /**
     * 设置缓存路径
     * @param string $path
     */
    public function setCacheDir(string $path = ''): void
    {
        if ($path) {
            $this->options['path'] = $path;
        }
        if (!is_dir($this->options['path'])) {
            @mkdir($this->options['path'], 0755, true);
        }
    }
    /**
     * 设置缓存文件前缀
     * @param string $prefix
     */
    public function setCachePrefix(string $prefix): void
    {
        $this->options['prefix'] = $prefix;
    }
    /**
     * 设置缓存存储类型
     * @param int $mode
     */
    public function setCacheMode(int $mode = self::MODE_SERIALIZE): void
    {
        $this->options['mode'] = $mode == self::MODE_SERIALIZE ? self::MODE_SERIALIZE : self::MODE_PHP;
    }
    public function buildKey(string $key): string
    {
        $key = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $key);
        //ctype_alnum($key)
        return strlen($key) <= 128 ? $key : md5($key);
    }

    /**
     * 设置一个缓存
     * @param string $name 缓存name
     * @param mixed $data 缓存内容
     * @param int $expire 缓存生命 默认为0无限
     * @return bool
     */
    public function set(string $name, $data, int $expire = 0): bool
    {
        $this->gc();//触发垃圾回收

        $time = $expire > 0 ? time() + $expire : 0;
        $file = $this->_file($name, true);
        return $this->_filePutContent($file, $data, $time);
    }
    /**
     * 得到缓存信息
     * @param string $name
     * @return mixed
     */
    public function get(string $name)
    {
        $file = $this->_file($name);
        return $this->_fileGetContent($file);
    }
    /**
     * 判断缓存是否存在
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        $file = $this->_file($name);
        if (!is_file($file)) {
            return false;
        }
        if (($mTime = @filemtime($file)) && $mTime < time()) {
            return false;
        }
        return true;
    }
    public function exists(string $name): bool
    {
        return $this->has($name);
    }
    /**
     * 清除一条缓存
     * @param string $name
     * @return bool
     */
    public function del(string $name): bool
    {
        $hName = $this->buildKey($name);
        $hDir = $this->options['path'].DIRECTORY_SEPARATOR.$hName;
        if (is_dir($hDir)) {
            $this->gcRecursive($hDir, false);
            @rmdir($hDir);
        }
        $file = $this->_file($name);
        if (!is_file($file)) {
            return false;
        }
        return @unlink($file);
    }

    /**
     * 自增
     * @param string $name
     * @param int $increment 自增 数
     * @param int $expire
     * @return int
     */
    public function incr(string $name, int $increment = 1, int $expire = 0): ?int
    {
        //$file = $this->_hFile('.incr', $name, true); //自增值 固定目录.incr
        $file = $this->_file($name);
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return 0;
        }
        try {
            if (flock($fp, LOCK_EX)) {
                $mtime = filemtime($file);
                $time = time();
                if ($mtime && $mtime < $time) {
                    $num = 0;
                } else {
                    $num = (int)$this->_rContent($file, $fp);
                }
                if ($increment != 1) {
                    $num += $increment;
                } else {
                    $num++;
                }
                if ($num == $increment) { //初始值
                    $mtime = $expire > 0 ? $expire + $time : 0;//$time + 315360000
                }
                fseek($fp, 0);
                if (false !== fwrite($fp, $this->_content($num))) {
                    touch($file, $mtime);
                    clearstatcache(true, $file); //清除缓存
                }
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
        return $num ?? null;
    }

    public function incrby(string $name, int $increment): ?int
    {
        return $this->incr($name, $increment);
    }

    public function decr(string $name): ?int
    {
        return $this->incr($name, -1);
    }

    public function decrby(string $name, int $decrement): ?int
    {
        return $this->incr($name, -$decrement);
    }

    public function push(string $name, $value, bool $unshift = false): int
    {
        $file = $this->_file($name);
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return 0;
        }
        $data = [];
        try {
            if (flock($fp, LOCK_EX)) {
                $mtime = filemtime($file);
                if ($mtime && $mtime < time()) {
                    $data = [];
                } else {
                    $data = $this->_rContent($file, $fp);
                    if ($data === false) {
                        $data = [];
                    }
                }
                if (count($data) == 0) { //初始数据时
                    $mtime = 0;
                }
                if ($unshift) { //插入头部
                    if (is_array($value)) { //多个追加
                        array_unshift($data, ...$value);
                    } else {
                        array_unshift($data, $value);
                    }
                } else { //追加尾部
                    if (is_array($value)) { //多个追加
                        array_push($data, ...$value);
                    } else {
                        $data[] = $value;
                    }
                }
                fseek($fp, 0);
                if (false !== fwrite($fp, $this->_content($data))) {
                    touch($file, $mtime);
                    clearstatcache(true, $file); //清除缓存
                }
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
        return count($data);
    }

    public function rpush(string $name, ...$value)
    {
        return $this->push($name, $value);
    }

    public function lpush(string $name, ...$value)
    {
        return $this->push($name, $value, true);
    }

    public function pop(string $name, bool $unshift = false)
    {
        $file = $this->_file($name);
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return 0;
        }
        $value = null;
        try {
            if (flock($fp, LOCK_EX)) {
                $time = time();
                $mtime = filemtime($file);
                if ($mtime && $mtime < $time) {
                    $data = [];
                } else {
                    $data = $this->_rContent($file, $fp);
                    if ($data === false || !is_array($data)) {
                        $data = [];
                    }
                }
                if (count($data) == 0) { //是初始数据
                    $mtime = 0; //$time + 315360000
                }
                if ($data) {
                    if ($unshift) { //头部取出
                        $value = array_shift($data);
                    } else { //尾部取出
                        $value = array_pop($data);
                    }
                }

                if ($value !== null) { //有弹出数据
                    fseek($fp, 0);
                    if (false !== fwrite($fp, $this->_content($data))) {
                        touch($file, $mtime);
                        clearstatcache(true, $file); //清除缓存
                    }
                }
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
        return $value;
    }

    public function rpop(string $name)
    {
        return $this->pop($name);
    }

    public function lpop(string $name)
    {
        return $this->pop($name, true);
    }

    public function llen(string $name)
    {
        $file = $this->_file($name);
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return 0;
        }
        $data = [];
        try {
            if (flock($fp, LOCK_EX)) {
                $mtime = filemtime($file);
                if ($mtime && $mtime < time()) {
                    $data = [];
                } else {
                    $data = $this->_rContent($file, $fp);
                    if ($data === false || !is_array($data)) {
                        $data = [];
                    }
                }
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
        return count($data);
    }

    /**
     * 获取所有符合给定模式 pattern 的 key, key超出128字符、hset的key无法获取
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern): array
    {
        $pattern = str_replace(['\\', '/', ':', '?', '"', '<', '>', '|'], '', $pattern);
        $keys = [];
        $prefixLen = strlen($this->options['prefix']);
        $path = $this->options['path'] . DIRECTORY_SEPARATOR . $this->options['prefix'] . $pattern . $this->suffix;

        $files = glob($path);
        if ($files) {
            foreach ($files as $file) {
                $name = basename($file, $this->suffix);
                $keys[] = $prefixLen ? substr($name, $prefixLen) : $name;
            }
        }
        return $keys;
    }

    //todo 模拟  zrevrangebyscore zremrangebyscore zadd scan
    // -inf负无穷 +inf正无穷
    /** 设置过期时间
     * @param string $name
     * @param int $time 过期秒数 0不过期
     * @param bool $is_file
     * @return bool
     */
    public function expire(string $name, int $time = 0, bool $is_file = false): bool
    {
        $file = $is_file ? $name : $this->_file($name);
        if (!file_exists($file)) {
            return false;
        }
        if (($mTime = @filemtime($file)) && $mTime < time()) {
            return false;
        }
        if ($time) {
            $time = $time + time();
        }
        return @touch($file, $time);
    }

    /**
     * 获取缓存的剩余时间 -2无缓存 -1无过期时间
     * @param string $name
     * @return int
     */
    public function ttl(string $name): int
    {
        $file = $this->_file($name);
        if (!file_exists($file)) {
            return -2;
        }
        $mTime = @filemtime($file);
        if (!$mTime) { //未设置过期时间
            return -1;
        }
        $t = time();
        if ($mTime <= $t) {
            return 0;
        }
        return $mTime - $t;
    }

    /**
     * 加锁 解锁 主要用于保证并发时操作的原子性 会阻塞
     * @param string $lockKey
     * @param int $lockTimeout
     * @return bool
     */
    public function lockBlock(string $lockKey, int $lockTimeout = 10): bool
    {
        if ($lockTimeout == 0) { //释放锁
            return $this->del($lockKey);
        }
        do {
            //获得锁 加过期时间 防止意外终止锁不释放
            $num = $this->incr($lockKey, 1, $lockTimeout);
            if ($num === 1) {
            } else {
                #echo 'waiting...'.microtime(),PHP_EOL;
                usleep(100000); //睡眠，降低抢锁频率，缓解cpu压力
            }
        } while ($num > 1);
        return true;
    }

    /**
     * 加锁 解锁 主要用于判断是否重复操作
     * @param string $lockKey
     * @param int $lockTimeout
     * @return bool
     */
    public function lockOnce(string $lockKey, int $lockTimeout = 10): bool
    {
        if ($lockTimeout == 0) { //释放锁
            return $this->del($lockKey);
        }
        $num = $this->incr($lockKey, 1, $lockTimeout);
        if ($num === 1) {
            return true; //获得锁 加过期时间 防止意外终止锁不释放
        } else {
            return false;
        }
    }

    //针对h的多键 key 过期设置 暂不支持主目录 name过期设置
    public function hExpire($name, $key, int $time = 0): bool
    {
        $file = $this->_hFile($name, $key);
        return $this->expire($file, $time, true);
    }
    //多个键值设置 不支持过期时间
    public function hSet($name, $key, $val): bool
    {
        $this->gc();//触发垃圾回收

        $file = $this->_hFile($name, $key, true);
        return $this->_filePutContent($file, $val);
    }
    public function hGet($name, $key)
    {
        $file = $this->_hFile($name, $key);
        return $this->_fileGetContent($file);
    }
    public function hDel($name, $key): bool
    {
        $file = $this->_hFile($name, $key);
        if (!is_file($file)) {
            return false;
        }
        return @unlink($file);
    }
    public function hGetAll(string $name): array
    {
        $name = $this->buildKey($name);
        $keyList = [];
        $path = $this->options['path'].DIRECTORY_SEPARATOR.$name;
        if (is_dir($path) && ($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                $data = $this->_fileGetContent($fullPath);
                if (false !== $data) {
                    $keyList[basename($file, $this->suffix)] = $data;
                }
            }
            closedir($handle);
        }
        /*
        $directory  = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $fileInfo){
            if($fileInfo->isFile()){
                $key = $fileInfo->getBasename($this->suffix);
                $data = $this->_fileGetContent($fileInfo->getPathname());
                if($data) {
                    $keyList[$key] = $data;
                }
            }
        }*/
        return $keyList;
    }
    public function hLen(string $name): int
    {
        $name = $this->buildKey($name);
        $len = 0;
        $path = $this->options['path'].DIRECTORY_SEPARATOR.$name;
        /*
        $files = glob($path.DIRECTORY_SEPARATOR.'*'.$this->suffix);
        if($files) $len = count($files);*/
        if (($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $len++;
            }
            closedir($handle);
        }
        /*
        $directory  = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $fileInfo){
            if($fileInfo->isFile()){
                $len++;
            }
        }*/
        return $len;
    }
    //多键值的缓存文件路径
    protected function _hFile(string $name, $key, bool $mkdir = false): string
    {
        $name = $this->buildKey($name);
        if ($mkdir && !is_dir($this->options['path'].DIRECTORY_SEPARATOR.$name)) {
            @mkdir($this->options['path'].DIRECTORY_SEPARATOR.$name, 0755, true);
        }

        $key = $this->buildKey($key);
        return $this->options['path'] . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $key . $this->suffix;
    }
    //多个键值设置
    public function mSet($name, $key, $val): bool
    {
        $this->gc();//触发垃圾回收

        $file = $this->_file($name, true);
        $data = $this->_fileGetContent($file);
        $mtime = 0;
        if ($data === false) { //init
            $data = [];
        } elseif (!is_array($data)) {
            return false;
        } else {
            $mtime = filemtime($file);
        }
        $data[$key] = $val;
        return $this->_filePutContent($file, $data, $mtime);
    }
    public function mGet($name, $key)
    {
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        if ($data && is_array($data)) {
            return $data[$key] ?? null;
        }
        return false;
    }
    public function mGetAll($name)
    {
        return $this->get($name);
    }
    public function mLen($name): int
    {
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        return $data && is_array($data) ? count($data) : 0;
    }
    public function mDel($name, $key): bool
    {
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        if ($data) {
            unset($data[$key]);
            $mtime = filemtime($file);
            return $this->_filePutContent($file, $data, $mtime);
        }
        return false;
    }
    //删除所有缓存
    public function clear(): void
    {
        $this->gc(true, false);
    }
    public function gc($force = false, $expiredOnly = true): void
    {
        if ($force || random_int(0, 100000) < $this->gcProbability) {
            Log::INFO('cache gc:' . ($force ? 'force' : 'probability'));
            $this->gcRecursive($this->options['path'], $expiredOnly);
        }
    }
    /**
     * Recursively removing expired cache files under a directory.
     * This method is mainly used by [[gc()]].
     * @param string $path the directory under which expired cache files are removed.
     * @param bool $expiredOnly whether to only remove expired cache files. If false, all files
     * under `$path` will be removed.
     */
    protected function gcRecursive(string $path, bool $expiredOnly): void
    {
        if (($handle = opendir($path)) !== false) {
            $len = strlen($this->suffix);
            $time = time();
            while (($file = readdir($handle)) !== false) {
                if ($file[0] === '.' || substr($file, -$len) != $this->suffix) {
                    continue;
                }
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($fullPath)) {
                    $this->gcRecursive($fullPath, $expiredOnly);
                    if (!$expiredOnly) {
                        if (count(scandir($fullPath)) == 2 && !@rmdir($fullPath)) {
                            $error = error_get_last();
                            Log::WARN("Unable to remove directory '{$fullPath}': {$error['message']}");
                        }
                    }
                } elseif (!$expiredOnly || (($mTime = @filemtime($fullPath)) && $mTime < $time)) {
                    if (!@unlink($fullPath)) {
                        $error = error_get_last();
                        Log::WARN("Unable to remove file '{$fullPath}': {$error['message']}");
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * 通过缓存name得到缓存信息路径
     * @param string $name
     * @param bool $mkdir 目录检测及生成
     * @return string 缓存文件路径
     */
    protected function _file(string $name, bool $mkdir = false): string
    {
        $name = $this->buildKey($name);
        $base = DIRECTORY_SEPARATOR;
        if ($this->options['dir_level'] > 0) {
            for ($i = 0; $i < $this->options['dir_level']; ++$i) {
                $prefix = (string)substr($name, $i + $i, 2);
                if ($prefix !== '') {
                    $base .= $prefix . DIRECTORY_SEPARATOR;
                }
            }
        }
        $file = $this->options['path'] . $base . $this->options['prefix'] . $name . $this->suffix;
        if ($mkdir && $this->options['dir_level'] > 0) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $file;
    }
    //格式缓存内容
    protected function _content(&$data): string
    {
        return $this->options['mode'] == self::MODE_SERIALIZE ? '<?php exit;//' . serialize($data) : "<?php\n return " . var_export($data, true).';';
    }
    //读取缓存文件内容
    protected function _rContent(string $file, $fp = null)
    {
        try {
            if ($this->options['mode'] == self::MODE_SERIALIZE) {
                if ($fp) {
                    $data = stream_get_contents($fp, -1, 13);
                    $data = $data ? unserialize($data) : false;
                } else {
                    $fp = @fopen($file, 'r');
                    if ($fp !== false) {
                        @flock($fp, LOCK_SH);
                        $data = stream_get_contents($fp, -1, 13);
                        $data = $data ? unserialize($data) : false;
                        /*
                        //兼容未序列化数据
                        if (stream_get_contents($fp, 13, 0) == '<?php exit;//') {
                            $data = stream_get_contents($fp, -1, 13);
                            $data = $data ? unserialize($data) : false;
                        } else {
                            $data = stream_get_contents($fp, -1, 0);
                        }*/
                        @flock($fp, LOCK_UN);
                        @fclose($fp);
                    } else {
                        return false;
                    }
                }
                //兼容旧版处理
                if (is_array($data) && isset($data['contents']) && isset($data['expire'])) {
                    return $data['contents'];
                }
            } else {
                $data = require($file);
            }
        } catch (\Exception|\Error $e) {
            return false;
        }
        return $data;
    }

    /**
     * 把数据写入文件
     * @param string $file 文件
     * @param mixed $data
     * @param int $time
     * @return false|int
     */
    protected function _filePutContent(string $file, $data, int $time = 0)
    {
        if (@file_put_contents($file, $this->_content($data), LOCK_EX) !== false) {
            return @touch($file, $time);
        }
        $error = error_get_last();
        Log::WARN("Unable to write cache file '{$file}': {$error['message']}");
        return false;
    }
    /**
     * 从文件得到数据
     * @param string $file
     * @return false|array
     */
    protected function _fileGetContent(string $file)
    {
        if (!is_file($file)) {
            return false;
        }
        if (($mTime = @filemtime($file)) && $mTime < time()) {
            //@unlink($file);
            return false;
        }

        return $this->_rContent($file);
    }
}

/* 初始化设置cache的配置信息什么的 */
/*
$cache = Cache::getInstance(); //new \myphp\cache\File();
$cache->setCacheDir(RUNTIME . DS . 'cache');
$cache->setCachePrefix('.'); //设置缓存文件前缀
$cache->setCacheMode(\myphp\cache\File::MODE_PHP);
*/
