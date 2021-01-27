<?php
//文件文件类
class File {
    //配置
    public $path = '.'; //路径
    public $prefix = ''; //前缀
    public $clearExSuffix = ''; //排除指定后缀的文件 多个使用,分隔 .php,.html

	//构造函数
	public function __construct($path='./', $prefix=''){
        $this->prefix = $prefix;
        $this->setDir($path);
	}
    // 数组保存到文件
    public static function arr2file($file, $arr=''){
        if(is_array($arr)){
            $con = var_export($arr,true);
        } else{
            $con = $arr;
        }
        $data = "<?php\nreturn $con;\n?>";
        file_put_contents($file, $data, LOCK_EX);
    }
    //直接以$file文件保存
    public static function save($file, $data){
        file_put_contents($file, $data, LOCK_EX);
    }
    //递归创建目录 createDir("./up/img/ap")
    public static function createDir( $path, $mode=0755 ) {
        if(is_dir($path)) return true;
        try {
            if (!@mkdir($path, $mode, true)) {
                return false;
            }
        } catch (\Exception $e) {
            if (!is_dir($path)) {
                throw new Exception("Failed to create directory {$path}: " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        return true;
    }
    //取得文件扩展 $file 文件名
    public static function getExt($file) {
        return strtolower(strrchr($file, '.'));
    }
    //转换字节数为其他单位 $byte:字节
    public static function toByte($byte){
        $v = 'unknown';
        if($byte >= 1099511627776){
            $v = round($byte / 1099511627776  ,2) . 'TB';
        } elseif($byte >= 1073741824){
            $v = round($byte / 1073741824  ,2) . 'GB';
        } elseif($byte >= 1048576){
            $v = round($byte / 1048576 ,2) . 'MB';
        } elseif($byte >= 1024){
            $v = round($byte / 1024, 2) . 'KB';
        } else{
            $v = $byte . 'Byte';
        }
        return $v;
    }
	//设置路径
	public function setDir($path){
		if(substr($path,-1)=='/') $path = substr($path,0, -1);
        $this->createDir($path);
        $this->path = $path;
		
	}
	//设置文件前缀
	public function setPrefix($prefix){
		$this->prefix = $prefix;
	}
    //文件锁
	private $lockFile, $lockHandle;
	public function lock($name){
	    $this->lockFile[$name] = $this->path.'/'.$name.'.lock';
        $this->lockHandle[$name] = @fopen($this->lockFile[$name], 'w');
        if(!$this->lockHandle[$name]) return false;
        //LOCK_EX 获取独占锁
        //LOCK_NB 无法建立锁定时，不阻塞
        return @flock($this->lockHandle[$name], LOCK_EX | LOCK_NB);
    }
    public function unlock($name){
	    if($this->lockHandle[$name]){
            @flock($this->lockHandle[$name], LOCK_UN);
        }
        @fclose($this->lockHandle[$name]);
	    @unlink($this->lockFile[$name]); //这里会报错
    }
	//保存文件
	public function put($name, $data){
		$file = $this->_file($name);
		return file_put_contents($file, $data, LOCK_EX);
	}
    /** 获取文件
     * @param $name
     * @return bool|string
     */
	public function get($name){
		$file = $this->_file($name);
		if(!is_file($file)) return false;
        $fp = @fopen($file, 'r');
        if ($fp === false) return false;

        @flock($fp, LOCK_SH);
        $content = @stream_get_contents($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);
        //$content = file_get_contents($file);
		//substr($content,17);
		return $content;
	}
	/**
	 * 判断文件是否存在
	 * @param string $name cache_name
	 * @return bool
	 */
	public function has($name){
		if(empty($name)) return false;
		$file = $this->_file($name);
		if(!is_file($file)) return false;
		return true;
	}
    /**
     * 清除一条文件
     * @param string cache name	 
     * @return bool
     */   
	public function del($name){
    	$file = $this->_file($name);
        if(!is_file($file)) return false;
    	//删除该文件
    	return @unlink($file);
	}
    /** 删除所有文件
     * @param int $maxLifeTime 可指定过期时间 单位秒 比如删除1小时前的文件 3600
     * @param bool $isRecursive
     * @param string $exSuffix
     */
	public function clear($maxLifeTime=0, $isRecursive=false, $exSuffix=''){
	    if(!$exSuffix) $exSuffix = $this->clearExSuffix;
		$this->gcRecursive($this->path, $isRecursive, $maxLifeTime, $exSuffix);
	}
    /**
     * 通过文件name得到文件信息路径
     * @param string $name
     * @return string 文件文件路径
     */
    protected function _file($name){
        if (strpos($name, '/') !== false) return $this->path . DIRECTORY_SEPARATOR . $name;
        return $this->path . DIRECTORY_SEPARATOR . $this->prefix . $name;
    }
    protected function gcRecursive($path, $isRecursive=true, $maxLifeTime=0, $exSuffix=''){
        if (($directory = opendir($path)) === false){
            Log::WARN("Couldn't list files under directory '".$path);
            return;
        }
        $ts = time() - $maxLifeTime;
        while (($file = readdir($directory)) !== false){
            if($file[0] === '.') continue;
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                if(!$isRecursive) continue;
                $this->gcRecursive($fullPath, $isRecursive, $maxLifeTime, $exSuffix);
                if(count(scandir($fullPath))==2) @rmdir($fullPath);
            }else{
                if($exSuffix && ($pos=strrpos($file,'.'))){
                    if(strpos($exSuffix, strtolower(substr($file, $pos)))!==false) continue;
                }
                if($this->prefix && strpos($file,$this->prefix)!==0) continue; //无前缀的跳过
                if (!($mtime = @filemtime($fullPath)) || $mtime>$ts) continue;
                @unlink($fullPath);
            }
        }
        closedir($directory);
    }
}