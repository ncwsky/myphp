<?php
//构子类 行为 插件
class Hook{
	private static $_hooks = array();
	/**
     * 增加行为处理
     * @param string $name 行为名称
     * @param string $hook 执行插件 闭包匿名函数|实例类 方法|实例类|静态类方法|hook类->run方法
     * @return void
     */
	public static function add($name, $hook){
		if(!isset(self::$_hooks[$name])){
            self::$_hooks[$name] = array();
        }
        if(is_array($hook)){
            self::$_hooks[$name] = array_merge(self::$_hooks[$name],$hook);
			self::$_hooks[$name] = array_unique(self::$_hooks[$name]); //不允许重复
        }else{
			if(array_search($hook, self::$_hooks[$name])===false)
				self::$_hooks[$name][] = $hook;
        }
	}
    /**
     * 监听行为并处理
     * @param string $name 行为名称
     * @param mixed $params 传入参数
     * @return void
     */
	public static function listen($name, &$params=null){
		if(isset(self::$_hooks[$name])){
			//日志记录
			foreach(self::$_hooks[$name] as $hook){
				self::run($hook, '', $params);
			}
		}
	}
    /**
     * 直接执行
     * @param string $hook 行为名称
     * @param string $method 方法
     * @param mixed $params 传入参数
     * @return mixed
     */
	public static function run($hook, $method='', &$params=null){
		//echo $hook;
        //记录构子执行前时间

        if(!is_array($params)) $params = [&$params];
        if ($hook instanceof \Closure) { //闭包 匿名函数
            $result = call_user_func_array($hook, $params);
            $hook  = '\Closure';
        } elseif (is_array($hook)) { //实例类 方法
            list($hook, $method) = $hook;
            $result = (new $hook())->$method($params);
            $hook  = $hook . '->' . $method;
        } elseif (is_object($hook)) { //实例类
            $result = $hook->$method($params);
            $hook  = get_class($hook) . '->' . $method;
        } elseif (strpos($hook, '::')) { //静态类方法
            $result = call_user_func_array($hook, $params);
        } else {
            $obj    = new $hook();
            $method = 'run';
            $result = $obj->$method($params);
            $hook  = $hook . '->' . $method;
        }

        //记录构子执行后时间
        //Log::trace('hook run:'.$hook.' runtime:xxx');
        return $result;
	}
}