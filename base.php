<?php
#declare(strict_types=1);
//系统开始时间
define('SYS_START_TIME', microtime(TRUE));//时间戳.微秒数
define('SYS_TIME', time());//时间戳和微秒数
// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
MEMORY_LIMIT_ON && define('SYS_MEMORY', memory_get_usage());
//系统变量
define('IS_CLI', PHP_SAPI == 'cli');
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);
define('DS', '/');
//定义MY_PATH常量
define('MY_PATH', __DIR__);

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
        if ( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] )
            $_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
        else
            $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];

        if (!empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
        }
    }
}

//绝对根目录
define('ROOT', IS_CLI ? dirname($_SERVER['SCRIPT_FILENAME']) : str_replace($_SERVER['SCRIPT_NAME'], '', IS_WIN ? strtr($_SERVER['SCRIPT_FILENAME'], '\\', DS) : $_SERVER['SCRIPT_FILENAME']));
//运行临时目录
defined('RUNTIME') || define('RUNTIME', ROOT.'/runtime');
//公共目录
defined('COMMON') || define('COMMON', ROOT.'/common');
//是否引入第三方扩展 使用composer
if(defined('VENDOR') && VENDOR){
    require MY_PATH . '/vendor/autoload.php';
}else{
    require MY_PATH . '/myphp.php';
}
//初始框架
myphp::init(isset($cfg) ? $cfg : null);

/*---------- 辅助方法 ----------*/
//获取终端发送的HTTP请求头
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = array();
        $ucwords = 'Accept/Host/X-Requested-With/Cache-Control/Content-Type';
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[(strpos($ucwords, $_name)!==false? $_name : str_replace('_', '-', substr($name, 5)))] = $value;
            } elseif($name == 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif($name == 'CONTENT_LENGTH') {
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
// 统计程序运行时间 秒
function run_time() {
    return number_format(microtime(TRUE) - SYS_START_TIME, 4);
}
// 统计程序内存开销
function run_mem() {
    return MEMORY_LIMIT_ON ? toByte(memory_get_usage() - SYS_MEMORY) : 'unknown';
}
//获取配置值 支持二维数组
function GetC($name, $defVal = null){
    return Config::get($name, $defVal);
}
//动态设置配置值
function SetC($name, $val){
    Config::set($name, $val);
}
//获取语言信息 支持二维 需要先载入语言数组文件
function GetL($name){
    return myphp::lang($name);
}
//url解析 地址 [! 普通模式]admin/index/show?b=c&d=e, 附加参数 数组|null, url字符串如：/pub/index.php
function U($uri='',$vars=null, $url=''){
    return UrlRoute::forward_url($uri,$vars,$url);
}
/**
 * db实例化
 * @param string $name 数据库配置名
 * @param bool $force 是否强制生成新实例
 * @return Db
 */
function db($name='db', $force=false){
    return myphp::db($name, $force);
}
//生成json
function toJson($res, $option=0){
    return Helper::toJson($res, $option);
}
//消息输出
function out_msg($message, $url='', $info='', $time = 1){
    return Helper::outMsg($message, $url, $info, $time);
}