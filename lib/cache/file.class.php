<?php
//文件缓存类
class cache_file extends cache_abstract{
	protected static $instance;
	//配置
    protected $options = array(
        'cache_dir' => "./",
        'prefix' => 'cache',
    	'mode' => '1', //mode 1 为serialize model 2为保存为可执行文件
		'expire' => 0, //有效期
    );
	//构造函数
	public function __construct($options = array()){
        if(!empty($options)) {
           $this->options  = array_merge($this->options, $options);  
        }
	}
	/**
	 * 得到本类实例
	 * @return self
	 */
	public static function getInstance($options = array()){
		if(self::$instance == NULL){
			self::$instance = new self($options);
		}
		return self::$instance;
	}
	/**
	 * 设置缓存路径
	 * @param string $path
	 */
	public function setCacheDir($path){
		$path = rtrim($path,'/') . '/';
        if (!is_dir($path)) {
            exit('file_cache: ' . $path.' 不是一个有效路径 ');
        }
        if (!is_writable($path)) {
            exit('file_cache: 路径 "'.$path.'" 不可写');
        }        
        $this->options['cache_dir'] = $path;
	}
	/**
	 * 设置缓存文件前缀
	 * @param srting $prefix
	 */
	public function setCachePrefix($prefix){
		$this->options['prefix'] = $prefix;
	}
	/**
	 * 设置缓存存储类型
	 * @param int $mode
	 * @return self
	 */
	public function setCacheMode($mode = 1){
		if($mode == 1) {
			$this->options['mode'] = 1;
		} else {
			$this->options['mode'] = 2;
		}
	}
	
	/**
	 * 设置一个缓存
	 * @param string $name 缓存name
	 * @param array  $data 缓存内容
	 * @param int    $expire 缓存生命 默认为0无限生命
	 */
	public function set($name, $data, $expire = null){
        if($expire===null) $expire = $this->options['expire'];
        
		$time = time();
		$cache = array();
		$cache['contents'] = $data;
		$cache['expire'] = $expire == 0 ? 0 : $time + $expire;
		$cache['mtime'] = $time;
		
		$file = $this->_file($name);
		return $this->_filePutContent($file, $cache);
	}
	/**
	 * 得到缓存信息
	 * @param string $name
	 * @return boolean|array
	 */
	public function get($name){
		//缓存文件不存在
		if(empty($name) || !$this->has($name)) return false;
		
		$file = $this->_file($name);
		$data = $this->_fileGetContent($file);
		if($data['expire'] == 0 || time() < $data['expire']) return $data['contents'];
		return false;
	}
	/**
	 * 判断缓存是否存在
	 * @param string $name cache_name
	 * @return boolean true 缓存存在 false 缓存不存在
	 */
	public function has($name){
		if(empty($name)) return false;
		$file = $this->_file($name);
		
		if(!is_file($file)) return false;
		return true;
	}
    /**
     * 清除一条缓存
     * @param string cache name	 
     * @return void
     */   
	public function del($name){
		if(empty($name) || !$this->has($name)) return false;
		
    	$file = $this->_file($name);
    	//删除该缓存
    	return unlink($file);
	}
	//删除所有缓存
	public function clear(){
		$glob = glob($this->options['cache_dir'] . $this->options['prefix'] . '--*');
		
		if(empty($glob)) return false;
		
		foreach ($glob as $v) {
			unlink($v);
		}
		return true;
	}
	
	/**
	 * 通过缓存name得到缓存信息路径
	 * @param string $name
	 * @return string 缓存文件路径
	 */
	protected function _file($name){
		$fileNmae  = $this->_nameToFileName($name);
		return $this->options['cache_dir'] . $fileNmae;
	}
	/**
	 * 通过name得到缓存信息存储文件名
	 * @param  $name
	 * @return string 缓存文件名
	 */
	protected function _nameToFileName($name){
		$prefix    = $this->options['prefix'];
		return $prefix . '-' . $name . '.php';
	}
	/**
	 * 把数据写入文件
	 * @param string $file 文件名称
	 * @param array  $contents 数据内容
	 * @return bool 
	 */
	protected function _filePutContent($file, $content){
		if($this->options['mode'] == 1){
			$content = '<?php exit;//' . serialize($content);
		} else {
			$time = time();	
	        $content = "<?php\n".
	                " // mktime: ". $time. "\n".
	                " return ".
	                var_export($content, true).
	                "\n?>";
		}
		file_put_contents($file,$content);
		return TRUE;				
	}
	/**
	 * 从文件得到数据
	 * @param  sring $file
	 * @return boolean|array
	 */
	protected function _fileGetContent($file){
		if(!is_file($file)) return false;
		
		if($this->options['mode'] == 1) {
			$content = file_get_contents($file);
			return unserialize(substr($content,13));
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
$cache->setCacheMode('2'); 
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