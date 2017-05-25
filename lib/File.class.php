<?php
//文件文件类
class File {
	protected static $instance;
	//配置
    protected $options = array(
        'path' => "./", //文件目录
        'prefix' => '', //前缀
    );
	//构造函数
	public function __construct($options = array()){
        if(!empty($options)) {
			$this->options  = array_merge($this->options, $options);
			if($this->options['path']!='./')
			   $this->createDir($this->options['path']); //创建目录
        }
	}
	public function __destruct(){
		self::$instance=null;
	}
	//得到本类实例
	public static function Init($path='./', $prefix=''){
		if(substr($path,-1)!='/') $path .='/';
		$md5 = md5($path.$prefix);
		if(!isset(self::$instance[$md5])){
			$options = array('path'=>$path, 'prefix'=>$prefix);
			self::$instance[$md5] = new self($options);
		}
		return self::$instance[$md5];
	}
	//设置路径
	public function setDir($path){
		if(substr($path,-1)!='/') $path .='/';
        $this->createDir($path); //创建目录     
        $this->options['path'] = $path;
		
	}
	//设置文件前缀
	public function setPrefix($prefix){
		$this->options['prefix'] = $prefix;
	}
	// 数组保存到文件
	public function arr2file($file, $arr=''){
		if(is_array($arr)){
			$con = var_export($arr,true);
		} else{
			$con = $arr;
		}
		$data = "<?php\nreturn $con;\n?>";
		file_put_contents($file, $data, LOCK_EX);
	}
	//直接以$file文件保存
	public function save($file, $data){
		file_put_contents($file, $data, LOCK_EX);
	}
	//递归创建目录 createPath ("./up/img/ap")
	public function createDir( $folderPath ) {
		$sParent = dirname( $folderPath );
		//Check if the parent exists, or create it.
		if ( !is_dir($sParent)) $this->createDir( $sParent );
		if ( !is_dir($folderPath)) 
			if(!mkdir($folderPath)) throw new myException('创建目录（'. $folderPath .'）失败！');
	}
	//取得文件扩展 $file 文件名
	public function fileExt($file) {
		return strtolower(strrchr($file, '.'));
	}
	//转换字节数为其他单位 $byte:字节
	public function toByte($byte){
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

	//保存文件
	public function put($name, $data){
		$file = $this->_file($name);
		return file_put_contents($file, $data, LOCK_EX);
	}
	//获取文件
	public function get($name){
		if(!$this->has($name)) return false;
		
		$file = $this->_file($name);
		if(!is_file($file)) return false;
		$data = file_get_contents($file);
		//substr($content,17);
		return $data;
	}
	/**
	 * 判断文件是否存在
	 * @param string $name cache_name
	 * @return boolean true 文件存在 false 文件不存在
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
     * @return void
     */   
	public function del($name){
		if(!$this->has($name)) return false;
		
    	$file = $this->_file($name);
    	//删除该文件
    	return unlink($file);
	}
	//删除所有文件 可指定过期时间 单位秒 比如删除1小时前的文件 3600
	public function clear($maxlifetime=0){	
		if (!is_dir($this->options['path']) OR ($directory = opendir($this->options['path'])) === FALSE){
			throw new Exception("couldn't list files under directory '".$this->options['path']);
			return false;
		}
		$ts = time() - $maxlifetime;
		while (($file = readdir($directory)) !== FALSE){
			if($file=='.' || $file=='..') continue;
			if($this->options['prefix']!=''){
				$has_prefix = strpos($file,$this->options['prefix']);
				if($has_prefix===false || $has_prefix!==0) continue; //无前缀的跳过
			}
			
			$file = $this->options['path'].$file;
			if (!is_file($file) OR ($mtime = filemtime($file))===FALSE OR $mtime>$ts){
				continue;
			}
			$r = unlink($file);
		}
		closedir($directory);
        return true;
	}
	
	/**
	 * 通过文件name得到文件信息路径
	 * @param string $name
	 * @return string 文件文件路径
	 */
	protected function _file($name){
		if(strpos($name,'/')!==false) return $name;
		$fileNmae  = $this->options['prefix'] . $name;
		return $this->options['path'] . $fileNmae;
	}
}