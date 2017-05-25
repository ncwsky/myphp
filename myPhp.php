<?php
header("Content-type:text/html;charset=utf-8");
header('Cache-control: private');
header('X-Powered-By:MyPHP');
define('VERSION', '1.0');
//系统开始时间
define('SYS_START_TIME', microtime(TRUE));//时间戳.微秒数
define('SYS_TIME', time());//时间戳和微秒数
// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
MEMORY_LIMIT_ON && define('SYS_MEMORY', memory_get_usage());
//来源
define('HTTP_REFERER', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
//主机协议
define('SITE_PROTOCOL', isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://');
//当前访问的主机名
define('SITE_URL', isset($_SERVER['HTTP_X_FORWARDED_HOST'])?$_SERVER['HTTP_X_FORWARDED_HOST']:(isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:''));
//当前站点url
define('CURR_SITE_URL',SITE_PROTOCOL.SITE_URL);
//系统变量
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('DS', '/');
//定义MY_PATH常量
define('MY_PATH', str_replace('\\', '/', dirname(__FILE__)));
defined('APP_PATH') or define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']));
//目录 在创建日志时自动生成
define('CACHE_DIR', 'cache');
define('CONTROL_DIR', 'control');
define('MODEL_DIR', 'model');
define('VIEW_DIR', 'view');
define('LANG_DIR', 'lang');	
define('LOG_DIR', 'logs');
//当前项目相对根目录
$s_n = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
($s_n=='.' || $s_n=='/' || IS_CLI) && $s_n='';
defined('APP_ROOT') or define('APP_ROOT', $s_n);
//绝对根目录 ROOT_PATH手动指定项目的绝对目录
define('__ROOT__', IS_CLI ? dirname($_SERVER['SCRIPT_FILENAME']) : str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['SCRIPT_FILENAME']));
define('ROOT', __ROOT__); //站点的绝对目录 
//相对根目录
if(!isset($cfg['root_dir'])){ //仅支持识别1级目录 如/www 不支持/www/web 需要支持请手动设置此配置
	$cfg['root_dir'] = '';
	if(APP_ROOT!=''){
		if($_s_pos=strpos($s_n,'/',1)) $cfg['root_dir'] = substr($s_n,0,$_s_pos);
		else $cfg['root_dir'] = APP_ROOT;
	}
}
define('ROOT_DIR', $cfg['root_dir']);

//相对公共目录
define('PUB', ROOT_DIR.'/pub');
//配置组合
$def_config = require MY_PATH . '/def_config.php';	//引入默认配置文件
$cfg = isset($cfg) && is_array($cfg) ? array_merge($def_config, $cfg) : $def_config; //组合参数配置
unset($def_config);
//设置本地时差
function_exists('date_default_timezone_set') && date_default_timezone_set($cfg['timezone']);
//PATHINFO处理
if(!isset($_SERVER['PATH_INFO'])) {
	$types = array('ORIG_PATH_INFO','REDIRECT_PATH_INFO','REDIRECT_URL');
	foreach ($types as $type){
		if(isset($_SERVER[$type])) {
			$_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type],$_SERVER['SCRIPT_NAME']))?
				substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME'])) : $_SERVER[$type];
			break;
		}
	}
	$_SERVER['PATH_INFO'] = isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'';
}
//REQUEST_URI 处理
if(!isset($_SERVER['REQUEST_URI'])){
    if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
    } else {
        if ( isset($_SERVER['PATH_INFO']) ) {
            if ( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] )
                $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
            else
                $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
        }
        if (!empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
        }
    }
}
//开启错误提示
if($cfg['debug']){
	error_reporting(E_ALL);// 报错级别设定,一般在开发环境中用E_ALL,这样能够看到所有错误提示
	function_exists('ini_set') && ini_set('display_errors', 'On');// 有些环境关闭了错误显示

	//加载基本类
	include MY_PATH . '/lib/Control.class.php';	//引入控制器类
	include MY_PATH . '/lib/View.class.php';	//引入视图类
	include MY_PATH . '/lib/Model.class.php';	//引入模型类
	include MY_PATH . '/lib/Cache.class.php'; //引入缓存类
	include MY_PATH . '/lib/Template.class.php';//引入模板类
	include MY_PATH . '/lib/Log.class.php'; //引入缓存类
	//引入公共及扩展函数文件
	include MY_PATH . '/inc/global.func.php';
	include MY_PATH . '/inc/ext.func.php';
	include MY_PATH . '/inc/comm.func.php';
}else{
	error_reporting(E_ALL || ~E_NOTICE); //error_reporting(0);//把错误报告，全部屏蔽

	$runfile = ROOT.ROOT_DIR.'/~run.php';
	if(!is_file($runfile)) {
		$php = compile(MY_PATH . '/lib/Control.class.php');
		$php .= compile(MY_PATH . '/lib/View.class.php');
		$php .= compile(MY_PATH . '/lib/Model.class.php');
		$php .= compile(MY_PATH . '/lib/Cache.class.php');
		$php .= compile(MY_PATH . '/lib/Template.class.php');
		$php .= compile(MY_PATH . '/lib/Log.class.php');
		$php .= compile(MY_PATH . '/inc/global.func.php');
		$php .= compile(MY_PATH . '/inc/ext.func.php');
		$php .= compile(MY_PATH . '/inc/comm.func.php');
		
		file_put_contents($runfile, '<?php '.$php);
		unset($php);
	}
	include $runfile;
}
//获取终端发送的HTTP请求头 
if (!function_exists('getallheaders')) { 
    function getallheaders() { 
		$headers = array(); 
		$ucwords = 'Accept/Host/X-Requested-With/Cache-Control/Content-Type';
		foreach ($_SERVER as $name => $value) { 
			if (substr($name, 0, 5) == 'HTTP_') { 
				$_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
				$headers[(strpos($ucwords, $_name)!==false? $_name : str_replace('_', '-', substr($name, 5)))] = $value;
			}else if ($name == 'CONTENT_TYPE') { 
				$headers['Content-Type'] = $value; 
			} else if ($name == 'CONTENT_LENGTH') { 
				$headers['Content-Length'] = $value; 
			}   
		} 
		if (isset($_SERVER['PHP_AUTH_DIGEST'])) { 
			$headers['AUTHORIZATION'] = $_SERVER['PHP_AUTH_DIGEST']; 
		} elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) { 
			$headers['AUTHORIZATION'] = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']); 
		}
		return $headers; 
    }
}
//注册类的自动加载
if(function_exists('spl_autoload_register'))
	spl_autoload_register('__autoload');
// 设定错误和异常处理
Log::register();

//自动加载对象
function __autoload($class_name) {
	static $_file  = array();
	$class_name = trim(str_replace(array('\\','/'), '', $class_name));
	if($class_name=='') return false;
	if (isset($_file[$class_name])) return true;
	$class_dir = GetC('class_dir');
	$class_array= empty($class_dir) ? array() : explode(',', ROOT.ROOT_DIR. str_replace(',', ','.ROOT.ROOT_DIR, $class_dir));//获取自行添加的class目录
	defined('CONTROL_PATH') && $class_array[]= CONTROL_PATH;//当前项目类目录
	defined('MODEL_PATH') && $class_array[]= MODEL_PATH;//当前项目模型目录
	$class_array[]= MY_PATH.'/lib/';
	$class_array[]= MY_PATH.'/ext/';//扩展类目录
	//循环判断
	foreach($class_array as $file) {
		$file .= $class_name.'.class.php';
		if(is_file($file)) {
			if (!isset($_file[$class_name])){
				$_file[$file] = $file;
				include $file; 
			}
			return true;
		} 
	}
	return false;
}
//anti_reflesh(5);//GetC('refresh_time',10)
//浏览器防刷新检测
function anti_reflesh($time=3){
	if($_SERVER['REQUEST_METHOD'] == 'GET') {
		//	启用页面防刷新机制
		$id = md5($_SERVER['PHP_SELF']);
		// 浏览器防刷新的时间间隔（秒） 默认为3
		$refleshTime = $time;;
		// 检查页面刷新间隔
		if(cookie('_last_visit_time_'.$id) && cookie('_last_visit_time_'.$id)>time()-$refleshTime) {
			// 页面刷新读取浏览器缓存
			header('HTTP/1.1 304 Not Modified');
			exit;
		}else{
			// 缓存当前地址访问时间
			cookie('_last_visit_time_'.$id, $_SERVER['REQUEST_TIME']);
			//header('Last-Modified:'.(date('D,d M Y H:i:s',$_SERVER['REQUEST_TIME']-C('refresh_time'))).' GMT');
		}
	}
}
//php代码格式化
function compile($filename) {
    $content = php_strip_whitespace($filename);
    $content = substr(trim($content), 5);
    if ('?>' == substr($content, -2)) $content = substr($content, 0, -2);
    return $content;
}
// 统计程序运行时间 秒
function run_time() {
	return number_format(microtime(TRUE) - SYS_START_TIME, 4);
}
// 统计程序内存开销
function run_mem() {
	return MEMORY_LIMIT_ON ? toByte(memory_get_usage() - SYS_MEMORY) : 'unknown';
}
//获取配置值 支持二维数组
function GetC($name, $defVal = NULL){
	$pos = strpos($name, '.');
	if ($pos===FALSE) 
    	return isset($GLOBALS['cfg'][$name]) ? $GLOBALS['cfg'][$name] : $defVal;
	// 二维数组支持
	$name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
	//$name = explode('.', $name);
	return isset($GLOBALS['cfg'][$name1][$name2]) ? $GLOBALS['cfg'][$name1][$name2] : $defVal;
}
//动态设置配置值
function SetC($name, $val){
	$pos = strpos($name, '.');
	if ($pos===FALSE) 
    	$GLOBALS['cfg'][$name]=$val;
	// 二维数组支持
	$name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
	//$name = explode('.', $name);
	$GLOBALS['cfg'][$name1][$name2]=$val;
}
//获取语言信息 支持二维 需要先载入语言数组文件
function GetL($name){
	if (!strpos($name, '.')) {
    	return isset($GLOBALS['lang'][$name]) ? $GLOBALS['lang'][$name] : $name;
	}
	// 二维数组支持
	$name = explode('.', $name);
	return isset($GLOBALS['lang'][$name[0]][$name[1]]) ? $GLOBALS['lang'][$name[0]][$name[1]] : $name;
}
//url解析 地址 [! 普通模式]admin/index/show?b=c&d=e, 附加参数 数组|null, url字符串如：/pub/index.php 
function U($uri='',$vars=null,$url=''){
	return UrlRoute::forward_url($uri,$vars,$url);
}
/*
设置和获取统计数据
$key string 标识位置, $step integer 步进值, $save boolean 是否保存结果
return mixed
example:
N('sql',1); // 记录sql执行次数
echo N('sql'); // 获取当前sql执行次数
 */
function N($key, $step=0, $save=false) {
    static $_num = array();
    if (!isset($_num[$key])) {
        $_num[$key] = 0;//(false !== $save)? cache('N_'.$key) :  0;
    }
    if (empty($step)){
        return $_num[$key];
    }else{
        $_num[$key] = $_num[$key] + (int)$step;
    }
    if(false !== $save){ // 保存结果
        //cache('N_'.$key,$_num[$key],$save);
    }
    return null;
}
/**
 * 模型实例化
 * @param string $name 数据库配置名(.表名)?
 * @return object
 */
function M($name='db'){
	static $_model  = array();
	$id = 'M_'. $name;
	if (!isset($_model[$id])){
		$_model[$id] = new Model($name);	//实例化模型类
	}
	return $_model[$id]; //返回实例
}
if (!function_exists('out_msg')) { //用于前端自动定义信息输出模板
//跳转提示信息输出: ([0,1]:)信息标题, url, 辅助信息, 等待时间（秒）
	function out_msg($message, $url='', $info='', $time = 1) {
		if ($url=='') {
			$jumpUrl = 'javascript:window.history.back()';
			$js = 'window.history.back()';
		} elseif (substr($url,0,11)=='javascript:') {
			$jumpUrl = $url;
			$js = substr($url,11);
		} else {
			$jumpUrl = $url;
			$js = "window.location='$jumpUrl'";
		}
		$status = substr($message,0,1); //提示状态 默认为普通
		$flag = 'normal'; //普通提示
		if($status=='1' || $status=='0'){
			$message=substr($message,2);
			$flag = $status=='1'?'success':'error'; //成功提示 | 错误提示
		}else{
			$status = -1;
		}

		if(ob_get_length() !== false) ob_clean();//清除页面
		if(is_ajax()){ //ajax输出
			$json = array('status'=>(int)$status, 'msg'=>$message, 'url'=>$jumpUrl, 'info'=>$info, 'time'=>$time);
			exit(json($json));
		}
		
		$out_html = '<!doctype html><html><head><meta charset="utf-8"><title>'.($url!='err'?'跳转提示':'错误提示').'</title><style type="text/css">*{padding:0;margin:0}body{background:#fff;font-family:"Microsoft YaHei";color:#333;font-size:100%}.system-message{padding:1.5em 3em}.system-message h1{font-size:6.25em;font-weight:400;line-height:120%;margin-bottom:.12em}.system-message .jump{padding-top:.625em}.system-message .jump a{color:#333}.system-message .success{color:#207E05}.system-message .error{color:#da0404}.system-message .normal,.system-message .success,.system-message .error{line-height:1.8em;font-size:2.25em}.system-message .detail{font-size:1.2em;line-height:160%;margin-top:.8em}</style></head><body><div class="system-message">';
		
		$out_html .= '<h1>:)</h1><p class="'.$flag.'">'.$message.'</p>'; //输出
		
		$out_html .= $info!=''?'<p class="detail">'.$info.'</p>':'';
		if($url!='err') //错误提示不跳转
			$out_html .= '<p class="jump">页面自动 <a id="href" href="'.$jumpUrl.'">跳转</a>  等待时间： <b id="time">'.$time.'</b><!--<br><a href="'.$jumpUrl.'">如未跳转请点击此处手工跳转</a>--></p></div><script type="text/javascript">var pgo=0,t=setInterval(function(){var time=document.getElementById("time");var val=parseInt(time.innerHTML)-1;time.innerHTML=val;if(val<=0){clearInterval(t);if(pgo==0){pgo=1;'.$js.';}}},1000);</script></body></html>';

		exit($out_html);
	}
}
if (!function_exists('json')) {
	//json_encode 缩写
	function json($res, $option=0){
		if($option==0 && defined('JSON_UNESCAPED_UNICODE'))
			$option = JSON_UNESCAPED_UNICODE;
		return json_encode($res, $option);
	}
}
/**
 * 加载函数库
 * @param string $func 函数库名
 * @param string $path 地址
 */
function load_func($func, $path = '') {
	static $funcs = array();
	if ($path=='') $path = MY_PATH.'/inc/';
	$path .= $func.'.func.php';
	$key = md5($path);
	if (isset($funcs[$key])) return true;
	if (file_exists($path)) {
		include $path;
	} else {
		$funcs[$key] = false;
		return false;
	}
	$funcs[$key] = true;
	return true;
}
/**
 * 加载类文件函数
 * @param string $classname 类名
 * @param string $path 扩展地址
 * @param intger $initialize 是否初始化
 */
function load_class($classname, $path = '', $initialize = 1) {
	static $classes = array();
	if (empty($path)) $path = MY_PATH.'/lib/';
	$path .=$classname.'.class.php';

	$key = md5($path);
	if (isset($classes[$key])) {
		if (!empty($classes[$key])) {
			return $classes[$key];
		} else {
			return true;
		}
	}
	
	if (file_exists($path)) {
		include $path;
		$name = $classname;
		if ($initialize) {
			$classes[$key] = new $name;
		} else {
			$classes[$key] = true;
		}
		return $classes[$key];
	} else {
		return false;
	}
}
/**
 * 加载其他类文件函数
 * @param string $path 类地址
 * @param string $classname 指定类名并初始化
 */
function load_other_class($path, $classname='') {
	static $classes = array();
	$key = md5($path);
	if (isset($classes[$key])) {
		if (!empty($classes[$key])) {
			return $classes[$key];
		} else {
			return true;
		}
	}
	
	if (file_exists($path)) {
		include $path;
		if ($classname!='') {
			$classes[$key] = new $classname;
		} else {
			$classes[$key] = true;
		}
		return $classes[$key];
	} else {
		return false;
	}
}

/**
 * 当前的请求类型
 * @return string
 */
function get_method()
{
	//static $_method;
	//if (!isset($_method) {
		// 如果指定 $_POST['_method'] ，表示使用POST请求来模拟其他方法的请求。
		// 此时 $_POST['_method'] 即为所模拟的请求类型。
		if (isset($_POST['_method'])) {
			$_method = strtoupper($_POST['_method']);
		} elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
			$_method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
		} else {
			$_method = IS_CLI ? 'GET' : (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
		}
	//}
	return $_method;
}
// 当前是否Ajax请求
function is_ajax()
{
	//跨域情况  // javascript 或 JSONP 格式    //  JSON 格式  
	//isset($_SERVER['HTTP_ACCEPT']) && ( $_SERVER['HTTP_ACCEPT']=='text/javascript, application/javascript, */*' || $_SERVER['HTTP_ACCEPT']=='application/json, text/javascript, */*')
	return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ? true : false;
}