<?php
//构子类 行为 插件
class Hook{
	private static $_hooks = array();
	/**
     * 增加行为处理
     * @param string $tag 行为名称
     * @param string $params 执行插件 类 或 函数
     * @return void
     */
	public static function add($name, $addon){
		if(!isset(self::$_hooks[$name])){
            self::$_hooks[$name] = array();
        }
        if(is_array($addon)){
            self::$_hooks[$name] = array_merge(self::$_hooks[$name],$addon);
			self::$_hooks[$name] = array_unique(self::$_hooks[$name]); //不允许重复
        }else{
			if(array_search($addon, self::$_hooks[$name])===false)
				self::$_hooks[$name][] = $addon;
        }
	}
    /**
     * 监听行为并处理
     * @param string $tag 行为名称
     * @param mixed $params 传入参数
     * @return void
     */
	public static function listen($name, &$params=null){
		if(isset(self::$_hooks[$name])){
			//日志记录
			foreach(self::$_hooks[$name] as $addon){
				self::run($addon, $params);
			}
		}
	}
	/**
     * 直接执行
     * @param string $tag 行为名称
     * @param mixed $params 传入参数
     * @return void
     */
	public static function run($addon, &$params=null){
		echo $addon;
		Log::trace($addon);
	}

	public function __destruct(){
		self::$_hooks=null;
	}
}