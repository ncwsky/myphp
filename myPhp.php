<?php
header("Content-type:text/html;charset=utf-8");
header('Cache-control: private');
header('X-Powered-By:MyPHP');
define('VERSION', '1.0');
//定义MY_PATH常量
define('MY_PATH', str_replace('\\', '/', dirname(__FILE__)));
defined('APP_PATH') or define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']));
//来源
define('HTTP_REFERER', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
//主机协议
define('SITE_PROTOCOL', isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://');
//当前访问的主机名
define('SITE_URL', isset($_SERVER['HTTP_X_FORWARDED_HOST'])?$_SERVER['HTTP_X_FORWARDED_HOST']:(isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:''));
//当前站点url
define('CURR_SITE_URL',SITE_PROTOCOL.SITE_URL);
//系统开始时间
define('SYS_START_TIME', microtime(TRUE));//时间戳.微秒数
define('SYS_TIME', time());//时间戳和微秒数
// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
MEMORY_LIMIT_ON && define('SYS_MEMORY', memory_get_usage());
//目录 在创建日志时自动生成
define('CACHE_DIR', 'cache');
define('CONTROL_DIR', 'control');
define('MODEL_DIR', 'model');
define('VIEW_DIR', 'view');
define('LANG_DIR', 'lang');	
define('LOG_DIR', 'logs');
//配置组合
$def_config = require MY_PATH . '/def_config.php';	//引入默认配置文件
$cfg = isset($cfg) && is_array($cfg) ? array_merge($def_config, $cfg) : $def_config; //组合参数配置
unset($def_config);
// 当前项目_相对根目录
$s_n = dirname($_SERVER['SCRIPT_NAME']);
defined('APP_ROOT') or define('APP_ROOT', (($s_n=='/' || $s_n=='\\')?'':$s_n));
//绝对根目录
if(!defined('__ROOT__')) {
	$_root = '';
	if(!isset($cfg['myphp_dir'])){
		//为便于网站根目录的获取，请将myphp框架放置在网站根目录下，如未放置根目录下请手动配置 root_dir,myphp_dir 这两项配置
		$_root = dirname(MY_PATH);
		$_s_pos = strpos($s_n,'/',1);
		if($_s_pos!==FALSE) $s_n = substr($s_n,0,$_s_pos);
		$_r = '/'.basename($_root);
		//获取根目录
		$cfg['root_dir'] = $_r==$s_n ? $_r : ''; //APP_ROOT
		if($cfg['root_dir']!='') $_root = str_replace($cfg['root_dir'], '', $_root);
	}else{
		$_root = str_replace($cfg['myphp_dir'], '', MY_PATH);
	}
	define('__ROOT__', $_root);
}
define('ROOT', __ROOT__);
//相对根目录
define('ROOT_DIR', $cfg['root_dir']);
//相对公共目录
define('PUB', ROOT_DIR.'/pub');
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
}else{
	error_reporting(E_ALL || ~E_NOTICE); //error_reporting(0);//把错误报告，全部屏蔽

	$runfile = ROOT.ROOT_DIR.'/~run.php';
	if(!is_file($runfile)) {
		$php = compile(MY_PATH . '/lib/Control.class.php');
		$php .= compile(MY_PATH . '/lib/View.class.php');
		$php .= compile(MY_PATH . '/lib/Model.class.php');
		$php .= compile(MY_PATH . '/lib/cache.class.php');
		$php .= compile(MY_PATH . '/lib/Template.class.php');
		$php .= compile(MY_PATH . '/lib/Log.class.php');
		$php .= compile(MY_PATH . '/inc/global.func.php');
		$php .= compile(MY_PATH . '/inc/ext.func.php');
		
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
register_shutdown_function('Log::Err'); //定义PHP程序执行完成后执行的函数
set_error_handler('Log::UserErr'); // 设置一个用户定义的错误处理函数
set_exception_handler('Log::Exception'); //自定义异常处理。 

//自动加载对象
function __autoload($class_name) {
	static $_file  = array();
	$class_dir = GetC('class_dir');
	$class_array= empty($class_dir) ? array() : explode(',', ROOT.ROOT_DIR. str_replace(',', ','.ROOT.ROOT_DIR, $class_dir));//获取自行添加的class目录
	defined('CONTROL_PATH') && $class_array[]= CONTROL_PATH;//当前项目类目录
	$class_array[]= MY_PATH.'/ext/';//扩展类目录
	//循环判断
	foreach($class_array as $file) {
		$file .= $class_name.'.class.php';
		if(is_file($file)) {
			if (!isset($_file[$class_name])){
				$_file[$file] = $file;
				require $file; 
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
//获取指定配置信息 支持二维数组
function GetC($name, $defVal = NULL){
	$pos = strpos($name, '.');
	if ($pos===FALSE) 
    	return isset($GLOBALS['cfg'][$name]) ? $GLOBALS['cfg'][$name] : $defVal;
	// 二维数组设置和获取支持
	$name1 = substr($name,0,$pos);
	$name2 = substr($name,$pos+1);
	//$name = explode('.', $name);
	return isset($GLOBALS['cfg'][$name1][$name2]) ? $GLOBALS['cfg'][$name1][$name2] : $defVal;
}
//获取指定的语言信息 支持二维 需要先载入语言数组文件
function GetL($name){
	if (empty($name)) return NULL;
	global $lang;
	if (!strpos($name, '.')) {
    	return isset($lang[$name]) ? $lang[$name] : NULL;
	}
	// 二维数组设置和获取支持
	$name = explode('.', $name);
	return isset($lang[$name[0]][$name[1]]) ? $lang[$name[0]][$name[1]] : NULL;
}
//获取执行页面Url　方法　控制　参数数组  扩展设置 常规url,url指定
function GetU($a='',$c='',$para=array(),$ext=array('normal'=>FALSE,'url'=>'')){
	$url_mode = GetC('url_mode');
	$p1 = $p2 = GetC('url_para_str');
	if($ext['url']!='') $url = $ext['url'];
	else $url = $c=='' ? URL : APP;
	$a = !empty($a) ? $a : GetC('default_action');
	if($url_mode !=1 && $url_mode !=2 || $ext['normal']){//0
		$c = $c==''?'':'?c='.$c;
		$a = 'a='.$a;
		$p1 = '&';$p2 = '=';
	}
	$url .= $c.$p1.$a;
	if(MODULE!='') $para['m'] = MODULE;
	foreach($para as $k=>$v){
		if($v=='') continue;
		$url .=$p1.$k.$p2.$v;
	}
	return $url;
}
//url解析 地址 [!]admin/index/show?b=c&d=e, 附加参数 数组|null, url字符串如：/pub/index.php 
function U($uri,$vars=null,$url=''){
	$uri = trim($uri); $normal = false;
	if(substr($uri,0,1)=='!'){ //普通url模式
		$normal = true;
		$uri = substr($uri,1);
	}
	//url映射
	if(is_array($GLOBALS['cfg']['url_maps']) && !empty($GLOBALS['cfg']['url_maps'])){
		static $url_maps;
		if(!isset($url_maps)){
			foreach ($GLOBALS['cfg']['url_maps'] as $k => $v) {
				$url_maps[$v]=$k;
			}
		}
		$maps_para = '';
		if(is_array($vars)) $maps_para = http_build_query($vars);
	}
	//分析mac及参数
	$m = $c = $a = $mac = $para = '';
	$pos = strpos($uri,'?');
	if($pos!==false) {// 参数处理
		$mac = substr($uri,0,$pos);
		if(is_array($vars)){
			parse_str(substr($uri,$pos+1),$get);
			$vars = array_merge($vars,$get);
		}else{
			parse_str(substr($uri,$pos+1),$vars);
		} 
	}else{
		$mac = $uri;
	}

	if($mac!=''){//分解m a c
		if($pos = strpos($mac,'/')){
			$path = explode('/',$mac);
			$a = array_pop($path);
			$c = array_pop($path);
			if(!empty($path)) $m = array_pop($path);
		}else{
			$a = $mac;
		}
	}
	if($c=='' && $a=='' && !is_array($vars)){
		return $url==''?__URI__:ROOT_DIR.$url;
	}
	//
	static $url_mode,$ups;
	if(!isset($url_mode)) {
		$url_mode = GetC('url_mode');
		$ups = GetC('url_para_str');
	}
	$c = $c==''?GetC('default_control'):$c;
	$a = $a==''?GetC('default_action'):$a;
	//url映射处理
	if(isset($url_maps)){
		$_url = $url==''?substr(__URI__,strlen(ROOT_DIR)):$url;

		$para = 'c='.$c.'&a='.$a;
		if($m!='') $para .= '&m='.$m;
		if(is_array($vars)) $para .= '&'.urldecode(http_build_query($vars));
		$_url .='?'.$para;

		//先完整比配 再mac?附加参数比配 再 mac比配 
		if(isset($url_maps[$_url])){
			return ROOT_DIR.$url_maps[$_url];
		}elseif(isset($url_maps[$mac.'?'.$maps_para])){
			return ROOT_DIR.UrlRoute::reverse_url($url_maps[$mac.'?'.$maps_para],$vars);
		}elseif(isset($url_maps[$mac])){
			return ROOT_DIR.UrlRoute::reverse_url($url_maps[$mac],$vars);
		}
	}

	//直接解析
	if($url_mode==0 || $normal){//普通模式
		$url = $url==''?__URI__:ROOT_DIR.$url;
		$para = 'c='.$c.'&a='.$a;
		if($m!='') $para .= '&m='.$m;
		if(is_array($vars)) $para .= '&'.urldecode(http_build_query($vars));
		return $url.'?'.$para;
	}
	//其他模式
	if($url!=''){
		$url = ROOT_DIR.$url;
		if($url_mode == 1){
			$url .= '?do=';
		}elseif($url_mode == 2){
			$url .= '/';
		}
	}else{
		$url = APP;
	}
	$url .= $c.$ups.$a;
	if($m!='') $url .= $ups.'m'.$ups.$m;
	if(is_array($vars)){
		foreach($vars as $k=>$v){
			if($v=='') continue;
			$url .=$ups.$k.$ups.$v;
		}
	}
	return $url;
}
/**
 * 设置和获取统计数据
 * 使用方法:
 * <code>
 * N('sql',1); // 记录sql执行次数
 * echo N('sql'); // 获取当前sql执行次数
 * </code>
 * @param string $key 标识位置
 * @param integer $step 步进值
 * @param boolean $save 是否保存结果
 * @return mixed
 */
function N($key, $step=0,$save=false) {
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
//跳转提示信息输出: ([0,1]:)信息标题, url, 辅助信息, 等待时间（秒）
function out_msg($message, $url='', $info='', $time = 2) {
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
	if($status=='1') $success = substr($message,2); //成功提示
	elseif($status=='0') $error = substr($message,2); //错误提示
	
	if(ob_get_length() !== false) ob_clean();//清除页面
	$out_html = '<!doctype html><html><head><meta charset="utf-8"><title>'.($url!='err'?'跳转提示':'错误提示').'</title><style type="text/css">*{padding:0;margin:0}body{background:#fff;font-family:"Microsoft YaHei";color:#333;font-size:100%}.system-message{padding:1.5em 3em}.system-message h1{font-size:6.25em;font-weight:400;line-height:120%;margin-bottom:.12em}.system-message .jump{padding-top:.625em}.system-message .jump a{color:#333}.system-message .success{color:#207E05}.system-message .error{color:#da0404}.system-message .normal,.system-message .success,.system-message .error{line-height:1.8em;font-size:2.25em}.system-message .detail{font-size:1.2em;line-height:160%;margin-top:.8em}</style></head><body><div class="system-message">';
	
	if(isset($success)) {
		$out_html .= '<h1>:)</h1><p class="success">'.$success.'</p>'; //操作成功！
	}elseif(isset($error)){
		$out_html .= '<h1>:(</h1><p class="error">'.$error.'</p>'; //操作失败！
	}else{
		$out_html .= '<h1>:)</h1><p class="normal">'.$message.'</p>'; //普通提示！
	}
	
    $out_html .= $info!=''?'<p class="detail">'.$info.'</p>':'';
	if($url!='err') //错误提示不跳转
		$out_html .= '<p class="jump">页面自动 <a id="href" href="'.$jumpUrl.'">跳转</a>  等待时间： <b id="time">'.$time.'</b><!--<br><a href="'.$jumpUrl.'">如未跳转请点击此处手工跳转</a>--></p></div><script type="text/javascript">var pgo=0,t=setInterval(function(){var time=document.getElementById("time");var val=parseInt(time.innerHTML)-1;time.innerHTML=val;if(val<=0){clearInterval(t);if(pgo==0){pgo=1;'.$js.';}}},1000);</script></body></html>';

	exit($out_html);
}
/**
 * 加载函数库
 * @param string $func 函数库名
 * @param string $path 地址
 */
function load_func($func, $path = '') {
	static $funcs = array();
	if (empty($path)) $path = MY_PATH.'/inc/';
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