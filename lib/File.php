<?php
//文件文件类
class File {
    //配置
    public $path = '.'; //路径
    public $prefix = ''; //前缀
    public $clearExSuffix = ''; //排除指定后缀的文件 多个使用,分隔 .php,.html
    public $listLimit = 2048; //显示列表限制
    private $dirLen;

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
                throw new \Exception("Failed to create directory {$path}: " . $e->getMessage(), $e->getCode(), $e);
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
		//if(substr($path,-1)=='/') $path = substr($path,0, -1);
		$path = realpath($path);
        $this->createDir($path);
        $this->path = $path;
        $this->dirLen = strlen($this->path);
		
	}
	//设置文件前缀
	public function setPrefix($prefix){
		$this->prefix = $prefix;
	}
    //文件锁
	private $lockFile, $lockHandle;

    /**
     * @param $name
     * @param bool $block 默认阻塞
     * @return bool
     */
	public function lock($name, $block=true){
	    $this->lockFile[$name] = $this->path.'/'.$name.'.lock';
        $this->lockHandle[$name] = @fopen($this->lockFile[$name], 'w');
        if(!$this->lockHandle[$name]) {
            unset($this->lockHandle[$name], $this->lockFile[$name]);
            return false;
        }
        //LOCK_EX 获取独占锁
        //LOCK_NB 无法建立锁定时，不阻塞
        if(@flock($this->lockHandle[$name], $block ? LOCK_EX : LOCK_EX | LOCK_NB)){
            return true;
        }
        unset($this->lockHandle[$name], $this->lockFile[$name]);
        return false;
    }
    public function unlock($name){
        if (!isset($this->lockHandle[$name])) {
            return;
        }
        @flock($this->lockHandle[$name], LOCK_UN);
        @fclose($this->lockHandle[$name]);
        @unlink($this->lockFile[$name]);
        unset($this->lockHandle[$name], $this->lockFile[$name]);
    }
    //保存文件
    public function put($name, $data, $append = false){
        $file = $this->_file($name);
        return file_put_contents($file, $data, $append ? FILE_APPEND | LOCK_EX : LOCK_EX);
    }
    /** 获取文件
     * @param $name
     * @return bool|string
     */
	public function get($name){
		$file = $this->_file($name);
		if(!is_file($file)) return false;
        return file_get_contents($file);
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

    /**
     * 读取目录
     * @param string $reqPath 相对$this->path下的 /a/b/c
     * @param string $sortBy 排序方式 name_asc,name_desc | mtime_asc,mtime_desc | size_asc,size_desc
     * @param string $search 搜索
     * @param array $list 文件及目录列表
     * @param array $pathList 目录路径列表
     */
	public function readPath($reqPath, $sortBy='name_asc', $search='', &$list=[], &$pathList=[]){
        // name_asc,name_desc | mtime_asc,mtime_desc | size_asc,size_desc
        //$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'name_asc';
        //$search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $list = $this->depth($reqPath, $search!=='', $search); //有搜索递归
        $path = $reqPath;
        $pathList = []; //排除根目录 目录路径列表
        if ($reqPath !== '/') {
            $pathList = [$path];
            while ($path = dirname($path)) {
                if ($path == DIRECTORY_SEPARATOR) break;
                $pathList[] = $path;
            }
            sort($pathList);
        }

        array_shift($list);
        if(empty($list)) return;

        foreach ($list as $k => $v) {
            $list[$k]['name'] = basename($v['path']);
            if($list[$k]['name']=='$RECYCLE.BIN' || $list[$k]['name']=='System Volume Information'){
                unset($list[$k]);
            }
        }

        $array_column = function ($array, $column) {
            if (function_exists('array_column')) {
                return array_column($array, $column);
            } else {
                $columns = [];
                foreach ($array as $element) {
                    $columns[] = $element[$column];
                }
                return $columns;
            }
        };

        switch ($sortBy) {
            case 'name_desc':
                $keys = ['is_dir', 'name'];
                $direction = [SORT_ASC, SORT_DESC];
                $sortFlag = [SORT_REGULAR, SORT_STRING | SORT_FLAG_CASE];
                break;
            case 'mtime_asc':
                $keys = ['is_dir', 'mtime', 'name'];
                $direction = [SORT_DESC, SORT_ASC, SORT_ASC];
                $sortFlag = [SORT_REGULAR, SORT_REGULAR, SORT_STRING | SORT_FLAG_CASE];
                break;
            case 'mtime_desc':
                $keys = ['is_dir', 'mtime', 'name'];
                $direction = [SORT_ASC, SORT_DESC, SORT_DESC];
                $sortFlag = [SORT_REGULAR, SORT_REGULAR, SORT_STRING | SORT_FLAG_CASE];
                break;
            case 'size_asc':
                $keys = ['is_dir', 'size', 'name'];
                $direction = [SORT_DESC, SORT_ASC, SORT_ASC];
                $sortFlag = [SORT_REGULAR, SORT_NUMERIC, SORT_STRING | SORT_FLAG_CASE];
                break;
            case 'size_desc':
                $keys = ['is_dir', 'size', 'name'];
                $direction = [SORT_ASC, SORT_DESC, SORT_ASC];
                $sortFlag = [SORT_REGULAR, SORT_NUMERIC, SORT_STRING | SORT_FLAG_CASE];
                break;
            default: //默认文件名升序
                $keys = ['is_dir', 'name'];
                $direction = [SORT_DESC, SORT_ASC];
                $sortFlag = [SORT_REGULAR, SORT_STRING | SORT_FLAG_CASE];

        }
        $args = [];
        foreach ($keys as $i => $key) {
            $args[] = $array_column($list, $key);
            $args[] = $direction[$i];
            $args[] = $sortFlag[$i];
        }
        $args[] = range(1, count($list));
        $args[] = SORT_ASC;
        $args[] = SORT_NUMERIC;
        $args[] = &$list;
        call_user_func_array('array_multisort', $args);
    }
    /**
     * 递归读取目录
     * @param string $path  /a/b/c
     * @param bool $infinity 递归
     * @param string $search
     * @return array
     */
    public function depth($path, $infinity = false, $search = '')
    {
        $fullPath = $this->path . rtrim($path, '/');
        $stat = stat($fullPath);
        $list = [];//new SplFixedArray($this->listLimit);
        $list[0] = ['path' => $path, 'is_dir' => true, 'size' => $stat['size'], 'mtime' => $stat['mtime'], 'ctime' => $stat['ctime'], 'type' => ''];
        $num = 1;

        $this->depthRecursive($list, $num, $fullPath, $infinity, $search);
        return $list;
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

    /**
     * 递归处理目录
     * @param $list
     * @param $num
     * @param $path
     * @param bool $infinity
     * @param string $search
     */
    protected function depthRecursive(&$list, &$num, $path, $infinity = false, $search = '')
    {
        if (($directory = opendir($path)) === false) {
            return;
        }

        while (($file = readdir($directory)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $path . '/' . $file;
            $stat = stat($fullPath);
            $isDir = is_dir($fullPath);

            if ($search === '' || stripos($file, $search) !== false) {
                $list[$num] = ['path' => substr($fullPath, $this->dirLen), 'is_dir' => $isDir, 'size' => $stat['size'], 'mtime' => $stat['mtime'], 'ctime' => $stat['ctime'], 'type' => $isDir ? '' : mime_content_type($fullPath)];
                $num++;
            }

            if ($num >= $this->listLimit) break;
            if ($isDir && $infinity) {
                $this->depthRecursive($list, $num, $fullPath, $infinity, $search);
            }
        }
        closedir($directory);
    }
}