<?php
//文件会话类
class session_file extends session_abstract{
	protected $_path;
	//构造函数
	public function __construct($options=null){
		parent::__construct($options);

		if (isset($this->options['save_path'])){
			$this->options['save_path'] = rtrim($this->options['save_path'], '/\\');
			if(isset($this->options['id'])) 
				$this->options['save_path'] .= DIRECTORY_SEPARATOR . $this->options['id'];

			ini_set('session.save_path', $this->options['save_path']);
		}else{
			$this->options['save_path'] = rtrim(ini_get('session.save_path'), '/\\');
			if(isset($this->options['id'])) {
				$this->options['save_path'] .= DIRECTORY_SEPARATOR . $this->options['id'];
				ini_set('session.save_path', $this->options['save_path']);
			}	
		}
	}
	/**
	 * 类似构造 设置 save_path 目录
	 *
	 * @param	string	$save_path	session存放目录
	 * @param	string	$name		session cookie name
	 * @return	bool
	 */
	public function open($save_path, $name){
		if ( ! is_dir($save_path)){ //如果这里设置的目录无效可设为sess前缀
			if ( ! mkdir($save_path, 0700, TRUE)){
				throw new Exception("Session: 设置存储路径 '".$this->options['save_path']."' 不是一个目录, 不存在或不能创建");
			}
		} elseif ( ! is_writable($save_path)) {
			throw new Exception("Session: 设置存储路径 '".$this->options['save_path']."' 不可写.");
		}
		//Log::trace(__FUNCTION__.':'.$save_path.'--'.$name);
		$this->options['save_path'] = $save_path;
		//$this->_path = $this->options['save_path']. DIRECTORY_SEPARATOR .$name.dechex(crc32($_SERVER['REMOTE_ADDR'])); // we'll use the session cookie name as a prefix to avoid collisions
		$this->_path = $save_path . DIRECTORY_SEPARATOR;

		return true;
	}
	//类似析构
	public function close(){
		//Log::trace(__FUNCTION__.':');
		$this->options = null;
        return true;
    }
	
	public function read($sid){
		//Log::trace(__FUNCTION__.':'.$this->_path.$sid);
		//$sess_path = substr($sid,0,2);
		//$save_path = $this->_path . $sess_path . DIRECTORY_SEPARATOR;
		return file_exists($this->_path.$sid) ? file_get_contents($this->_path.$sid) : '';
    }

    public function write($sid, $data){
		//Log::write(__FUNCTION__.':'.$sid.'=='.$data, 'info');
		/*
		$sess_path = substr($sid,0,2);
		$save_path = $this->_path . $sess_path;
		if ( ! is_dir($save_path)){
			if ( ! mkdir($save_path, 0700, TRUE)){
				throw new Exception("Session: 设置存储路径 '".$save_path."' 不是一个目录, 不存在或不能创建");
			}
		}*/
        return file_put_contents($this->_path.$sid, $data, LOCK_EX) === false ? false : true; //$save_path. DIRECTORY_SEPARATOR 
    }
	/**
	 * 注销当前会话
	 *
	 * @param	string	$sid	Session ID
	 * @return	bool
	 */
	public function destroy($sid){
		//$sess_path = substr($sid,0,2);
		//$save_path = $this->_path . $sess_path . DIRECTORY_SEPARATOR;
		//Log::trace(__FUNCTION__.':'.$this->_path.'=='.$sid);
		if (file_exists($this->_path.$sid)) {
			return unlink($this->_path.$sid) ? true : false;
		}
		return true;
	}
	/**
	 * 垃圾回收 删除过期session
	 *
	 * Deletes expired sessions
	 *
	 * @param	int 	$maxlifetime	Maximum lifetime of sessions
	 * @return	bool
	 */
    public function gc($maxlifetime){
		if (!is_dir($this->options['save_path']) OR ($directory = opendir($this->options['save_path'])) === FALSE){
			throw new Exception("Session: Garbage collector couldn't list files under directory '".$this->options['save_path']);
			return false;
		}
		$ts = time() - $maxlifetime;
		//$pattern = sprintf('/^%s[0-9a-f]{%d}$/', preg_quote($this->options['cookie_name'], '/'), ($this->options['match_ip'] === TRUE ? 72 : 40) );
		while (($file = readdir($directory)) !== FALSE){
			if($file=='.' || $file=='..') continue;
			// If the filename doesn't match this pattern, it's either not a session file or is not ours
			//! preg_match($pattern, $file)
			$file = $this->options['save_path'].DIRECTORY_SEPARATOR.$file;
			if (!is_file($file) OR ($mtime = filemtime($file))===FALSE OR $mtime>$ts){
				continue;
			}
			$r = unlink($file);
			//Log::trace('gc:'.$file.'='.$r);
		}
		closedir($directory);
        return true;
    }
}