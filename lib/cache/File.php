<?php
namespace myphp\cache;

//文件缓存类
use myphp\Log;

class File extends \myphp\CacheAbstract{
    public $gcProbability = 10; //100000次设置有10次机率触发垃圾回收
    public $suffix = '.php';

    const MODE_SERIALIZE = 1;
    const MODE_PHP = 2;

	//配置
    protected $options = [
        'path' => "./",
        'prefix' => '_',
    	'mode' => self::MODE_SERIALIZE, //mode 1 为serialize model 2为保存为可执行文件
		'expire' => 0, //有效期
        'dir_level'=>0 //缓存层级
    ];

	public function __construct($options = array()){
        parent::__construct($options);
		$this->setCacheDir();
	}
	/**
	 * 设置缓存路径
	 * @param string $path
	 */
	public function setCacheDir($path=''){
        if($path) $this->options['path'] = $path;
        if (!is_dir($this->options['path'])) {
            @mkdir($this->options['path'], 0755, true);
        }
	}
	/**
	 * 设置缓存文件前缀
	 * @param string $prefix
	 */
	public function setCachePrefix($prefix){
		$this->options['prefix'] = $prefix;
	}
	/**
	 * 设置缓存存储类型
	 * @param int $mode
	 * @return self
	 */
	public function setCacheMode($mode = self::MODE_SERIALIZE){
        $this->options['mode'] = $mode == self::MODE_SERIALIZE ? self::MODE_SERIALIZE : self::MODE_PHP;
	}
    public function buildKey($key){
        if (is_scalar($key)) {
            $key = str_replace(['\\','/',':','*','?','"','<','>','|'],'',$key);
            $key = strlen($key) <= 128 ? $key : md5($key); //ctype_alnum($key)
        } else {
            $key = md5(json_encode($key));
        }
        return $key;
    }
    /**
     * 设置一个缓存
     * @param string $name 缓存name
     * @param mixed  $data 缓存内容
     * @param int    $expire 缓存生命 默认为0无限生命
     * @return bool
     */
	public function set($name, $data, $expire = null){
        if($expire===null) $expire = $this->options['expire'];

        $this->gc();//触发垃圾回收

		$time = time();
		$cache = array();
		$cache['contents'] = $data;
		$cache['expire'] = $expire == 0 ? 0 : $time + $expire;
		$cache['mtime'] = $time;

        $file = $this->_file($name, true);
        if(false!==$this->_filePutContent($file, $cache)){
            return @touch($file, $expire ? $time+$expire : 0); //修改访问时间 用于垃圾回收 $time+($expire == 0 ? 31536000 : $expire)
        }
        $error = error_get_last();
        Log::WARN("Unable to write cache file '{$file}': {$error['message']}");
		return false;
	}
	/**
	 * 得到缓存信息
	 * @param string $name
	 * @return mixed
	 */
	public function get($name){
		$file = $this->_file($name);
		$data = $this->_fileGetContent($file);
		if($data && ($data['expire'] == 0 || time() < $data['expire'])) return $data['contents'];
		return false;
	}
	/**
	 * 判断缓存是否存在
	 * @param string $name cache_name
	 * @return bool
	 */
	public function has($name){
		$file = $this->_file($name);
		if(!is_file($file)) return false;
		return true;
	}
    /**
     * 清除一条缓存
     * @param string name
     * @return bool
     */   
	public function del($name){
        $hName = $this->buildKey($name);
        $hDir = $this->options['path'].DIRECTORY_SEPARATOR.$hName;
	    if(is_dir($hDir)){
            $this->gcRecursive($hDir, false);
            @rmdir($hDir);
        }
    	$file = $this->_file($name);
        if(!is_file($file)) return false;
    	return @unlink($file);
	}

    /** 设置过期时间
     * @param $name
     * @param int $time  过期秒数 0不过期
     * @return bool
     */
	public function expire($name, $time=0){
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        if(!$data) return false;

        if($time) $time = $time+time();
        $data['expire'] = $time;
        $data['mtime'] = time();
        if(false!==$this->_filePutContent($file, $data)){
            return @touch($file, $data['expire']);
        }
        $error = error_get_last();
        Log::WARN("Unable to write expire file '{$file}': {$error['message']}");
        return false;
    }
    //针对h的多键 key 过期设置 暂不支持主目录 name过期设置
    public function hExpire($name, $key, $time=0){
        $file = $this->_hFile($name, $key);
        $data = $this->_fileGetContent($file);
        if(!$data) return false;

        if($time) $time = $time+time();
        $data['expire'] = $time;
        $data['mtime'] = time();

        if(false!==$this->_filePutContent($file, $data)){
            return @touch($file, $data['expire']);
        }
        $error = error_get_last();
        Log::WARN("Unable to write expire file '{$file}': {$error['message']}");
        return false;
    }
    //多个键值设置 不支持过期时间
    public function hSet($name, $key, $val){
        $this->gc();//触发垃圾回收

        $time = time();
        $cache = array();
        $cache['contents'] = $val;
        $cache['expire'] = 0;
        $cache['mtime'] = $time;

        $file = $this->_hFile($name, $key, true);
        if(false!==$this->_filePutContent($file, $cache)){
            return @touch($file, 0);
        }
        $error = error_get_last();
        Log::WARN("Unable to write cache file '{$file}': {$error['message']}");
    }
    public function hGet($name, $key){
        $file = $this->_hFile($name, $key);
        $data = $this->_fileGetContent($file);
        if($data && ($data['expire'] == 0 || time() < $data['expire'])) return $data['contents'];
        return false;
    }
    public function hDel($name, $key){
        $file = $this->_hFile($name, $key);
        if(!is_file($file)) return false;
        return @unlink($file);
    }
    public function hGetAll($name){
        $name = $this->buildKey($name);
        $keyList = [];
        $path = $this->options['path'].DIRECTORY_SEPARATOR.$name;
/*
        $files = glob($path.DIRECTORY_SEPARATOR.'*'.$this->suffix);
        if($files) {
            foreach ($files as $file){
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                $data = $this->_fileGetContent($fullPath);
                if($data && ($data['expire'] == 0 || time() < $data['expire'])) {
                    $keyList[basename($file, $this->suffix)] = $data['contents'];
                }
            }
        }*/

        if (is_dir($path) && ($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') continue;

                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                $data = $this->_fileGetContent($fullPath);
                if($data && ($data['expire'] == 0 || time() < $data['expire'])) {
                    $keyList[basename($file, $this->suffix)] = $data['contents'];
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
                if($data && ($data['expire'] == 0 || time() < $data['expire'])) {
                    $keyList[$key] = $data['contents'];
                }
            }
        }*/
        return $keyList;
    }
    public function hLen($name){
        $name = $this->buildKey($name);
        $len = 0;
        $path = $this->options['path'].DIRECTORY_SEPARATOR.$name;
/*
        $files = glob($path.DIRECTORY_SEPARATOR.'*'.$this->suffix);
        if($files) $len = count($files);*/

        if (($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') continue;
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
    protected function _hFile($name, $key, $mkdir=false){
        $name = $this->buildKey($name);
        if ($mkdir && !is_dir($this->options['path'].DIRECTORY_SEPARATOR.$name)) {
            @mkdir($this->options['path'].DIRECTORY_SEPARATOR.$name, 0755, true);
        }

        $key = $this->buildKey($key);
        $file = $this->options['path'] . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $key . $this->suffix;
        return $file;
    }
    //多个键值设置
    public function mSet($name, $key, $val){
        $this->gc();//触发垃圾回收

        $file = $this->_file($name, true);
        $data = $this->_fileGetContent($file);
        if($data && ($data['expire'] == 0 || time() < $data['expire'])){

        }else{
            $data = array();
            $data['contents'] = [];
            $data['expire'] = 0;
            $data['mtime'] = time();
        }
        $data['contents'][$key] = $val;

        if(false!==$this->_filePutContent($file, $data)){
            return @touch($file, $data['expire']);
        }
        $error = error_get_last();
        Log::WARN("Unable to write cache file '{$file}': {$error['message']}");
        return false;
    }
    public function mGet($name, $key){
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        if($data && ($data['expire'] == 0 || time() < $data['expire'])) {
            return isset($data['contents'][$key]) ? $data['contents'][$key] : null;
        }
        return false;
    }
    public function mGetAll($name){
        return $this->get($name);
    }
    public function mLen($name){
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        if($data && ($data['expire'] == 0 || time() < $data['expire'])) {
            return count($data['contents']);
        }
        return 0;
    }
	public function mDel($name, $key){
        $file = $this->_file($name);
        $data = $this->_fileGetContent($file);
        if($data && ($data['expire'] == 0 || time() < $data['expire'])) {
            unset($data['contents'][$key]);
            if(false!==$this->_filePutContent($file, $data)){
                return @touch($file, $data['expire']);
            }
        }
        return false;
    }
	//删除所有缓存
	public function clear(){
        $this->gc(true, false);
        return true;
	}
    public function gc($force = false, $expiredOnly = true)
    {
        if ($force || mt_rand(0, 100000) < $this->gcProbability) {
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
    protected function gcRecursive($path, $expiredOnly)
    {
        if (($handle = opendir($path)) !== false) {
            $len = strlen($this->suffix);
            $time = time();
            while (($file = readdir($handle)) !== false) {
                if ($file[0] === '.' || substr($file, -$len)!=$this->suffix) {
                    continue;
                }
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($fullPath)) {
                    $this->gcRecursive($fullPath, $expiredOnly);
                    if (!$expiredOnly) {
                        if (count(scandir($fullPath))==2 && !@rmdir($fullPath)) {
                            $error = error_get_last();
                            Log::WARN("Unable to remove directory '{$fullPath}': {$error['message']}");
                        }
                    }
                } elseif (!$expiredOnly || $expiredOnly && (($mTime=@filemtime($fullPath)) && $mTime < $time)) {
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
	protected function _file($name, $mkdir=false){
	    $name = $this->buildKey($name);
        $base = DIRECTORY_SEPARATOR;
        if ($this->options['dir_level'] > 0) {
            for ($i = 0; $i < $this->options['dir_level']; ++$i) {
                if (($prefix = substr($name, $i + $i, 2)) !== false) {
                    $base .= $prefix . DIRECTORY_SEPARATOR;
                }
            }
        }
        $file = $this->options['path'] . $base . $this->options['prefix'] . $name . $this->suffix;
        if ($mkdir && $this->options['dir_level'] > 0) {
            $dir = dirname($file);
            if(!is_dir($dir)) @mkdir($dir, 0755, true);
        }
        return $file;
	}
	/**
	 * 把数据写入文件
	 * @param string $file 文件
	 * @param $data
	 * @return bool
	 */
	protected function _filePutContent($file, $data){
        $content = $this->options['mode'] == self::MODE_SERIALIZE ? '<?php exit;//' . serialize($data) : "<?php\n return " . var_export($data, true).';';
        return @file_put_contents($file, $content, LOCK_EX);
	}
	/**
	 * 从文件得到数据
	 * @param  string $file
	 * @return bool|array
	 */
	protected function _fileGetContent($file){
        if(!is_file($file)) return false; // || ($mTime=@filemtime($file)) && $mTime < time())

        if($this->options['mode'] == self::MODE_SERIALIZE) {
            $content = file_get_contents($file, false, null, 13);
            return unserialize($content);
        } else {
            return include $file;
        }
	}
}

/* 初始化设置cache的配置信息什么的 */
/*
$cache = Cache::getInstance();
$cache->setCachePrefix('core'); //设置缓存文件前缀
$cache->setCacheDir('./app/cache/'); //设置存放缓存文件夹路径
$cache->setCacheMode(2); 
*/
//模式1 缓存存储方式
//a:3:{s:8:"contents";a:7:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:34;i:4;i:5;i:5;i:6;i:6;i:6;}s:6:"expire";i:0;s:5:"mtime";i:1318218422;}
//模式2 缓存存储方式
/*
 <?php
 // mktime: 1318224645
 return array (
  'contents' => 
  array (
    0 => 1,
    1 => 2,
    2 => 3,
    3 => 34,
    4 => 5,
    5 => 6,
    6 => 6,
  ),
  'expire' => 0,
  'mtime' => 1318224645,
)
?>
 */
/*
if(!$row = $cache->get('zj2'))
{
	
	$array = array(1,2,3,34,5,6,6);
	$row = $cache->set('zj2',$array);
}
// $cache->clear(); 清空所有缓存

print_r($row);
*/