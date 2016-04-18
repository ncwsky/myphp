<?php
//mysql数据库缓存类
/**
drop table if exists `cache_table`;
CREATE TABLE `cache_table` (
`cachekey` char(32) NOT NULL,
`expire` int(10) unsigned NOT NULL default '0',
`data` blob,
PRIMARY KEY (`cachekey`)
)ENGINE=MyISAM;
 */
class cache_db extends cache_abstract{	
	//配置
    protected $options = array(
        'prefix' => 'cache',
		'table' => 'cache_table',
    	'expire' => 0, //有效期
    );
	//构造函数
	public function __construct($options = array()){
        if(!empty($options)) {
           $this->options  = array_merge($this->options, $options);  
        }/*
		if(empty($this->options['table'])){
			$this->options['table'] = 'cache_table';
		}*/
		$this->handler = M();
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
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $data  存储数据
     * @param integer $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $data, $expire = null){
        if($expire===null) $expire = $this->options['expire'];
        
        $data =  serialize($data);
        $name = $this->options['prefix'].G(0,$name,1,'');
        if(GetC('isGzipEnable') && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data,3);
        }
        $expire = $expire == 0 ? 0 : time() + $expire;//缓存有效期为0表示永久缓存
        $result = $this->handler->getOne('select top 1 `cachekey` from `'.$this->options['table'].'` where `cachekey`=\''.$name.'\'');
        if(!empty($result) ) { //更新记录
            $result = $this->handler->execute('UPDATE '.$this->options['table'].' SET data=\''.$data.'\' ,expire='.$expire.' WHERE `cachekey`=\''.$name.'\'');
        }else { //新增记录
            $result = $this->handler->execute('INSERT INTO '.$this->options['table'].' (`cachekey`,`data`,`expire`) VALUES (\''.$name.'\',\''.$data.'\','.$expire.')');
        }
        if($result) {
            return true;
        }else {
            return false;
        }
    }
    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name) {
        $name = $this->options['prefix'].G(0,$name,1,'');
        $result = $this->handler->getOne('SELECT top 1 `data` FROM `'.$this->options['table'].'` WHERE `cachekey`=\''.$name.'\' AND (`expire` =0 OR `expire`>'.time().')');
        if( $result ) {
            $content   =  $result['data'];
            if(GetC('isGzipEnable') && function_exists('gzcompress')) {
                //启用数据压缩
                $content = gzuncompress($content);
            }
            $content = unserialize($content);
            return $content;
        }
        else {
            return false;
        }
    }
	/**
	 * 判断缓存是否存在
	 * @param string $name cache_name
	 * @return boolean true 缓存存在 false 缓存不存在
	 */
	public function has($name){
		$name = $this->options['prefix'].G(0,$name,1,'');
		$result = $this->handler->getOne('SELECT top 1 `data` FROM `'.$this->options['table'].'` WHERE `cachekey`=\''.$name.'\' AND (`expire` =0 OR `expire`>'.time().')');

		if($result) return true;
		return false;
	}
    //删除缓存
    public function del($name) {
		if(empty($name)) return false;
        $name = $this->options['prefix'].G(0,$name,1,'');
        return $this->handler->execute('DELETE FROM `'.$this->options['table'].'` WHERE `cachekey`=\''.$name.'\'');
    }

    //删除所有缓存
    public function clear() {
        return $this->handler->execute('TRUNCATE TABLE `'.$this->options['table'].'`');
    }
}