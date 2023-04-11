<?php
#declare(strict_types=1);

//系统开始时间
define('SYS_START_TIME', microtime(true));//时间戳.微秒数
define('SYS_TIME', time());//时间戳
// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
MEMORY_LIMIT_ON && define('SYS_MEMORY', memory_get_usage());
//系统变量
define('IS_CLI', PHP_SAPI == 'cli');
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);
define('DS', '/');
//定义MY_PATH常量
define('MY_PATH', __DIR__);

if (!class_exists('Error')) { //兼容7.0
    class Error extends \Exception{}
}

//REQUEST_URI 处理 ORIG_PATH_INFO REDIRECT_PATH_INFO REDIRECT_URL
if(!isset($_SERVER['REQUEST_URI'])){
    if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
    } else {
        $_SERVER['REQUEST_URI'] = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')) . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    }
}

//绝对根目录
define('ROOT', IS_CLI ? strtr(dirname(realpath($_SERVER['SCRIPT_FILENAME'])), '\\', DS) : str_replace($_SERVER['SCRIPT_NAME'], '', strtr($_SERVER['SCRIPT_FILENAME'], '\\', DS)));

//运行临时目录
defined('RUNTIME') || define('RUNTIME', ROOT.'/runtime');
//公共目录
defined('COMMON') || define('COMMON', ROOT.'/common');
require __DIR__ . '/myphp.php';
require __DIR__ . '/inc/comm.func.php';
require __DIR__ . '/lib/Db.php';

myphp::$rootPath = ROOT;
myphp::$namespaceMap = [
    'myphp\\' => __DIR__ . '/lib',
    'common\\' => COMMON,
];
//类映射
myphp::$classMap = [
    'myphp\Cache' => __DIR__ . '/lib/Cache.php',
    'myphp\CacheAbstract' => __DIR__ . '/lib/Cache.php',
    'myphp\Value' => __DIR__ . '/lib/Value.php',
    'myphp\Control' => __DIR__ . '/lib/Control.php',
    //'myphp\Db' => __DIR__ . '/lib/Db.php',
    //'myphp\DbBase' => __DIR__ . '/lib/Db.php',
    //'myphp\Expr' => __DIR__ . '/lib/Db.php',
    //'myphp\TbBase' => __DIR__ . '/lib/Db.php',
    'myphp\db\db_mysqli' => __DIR__ . '/lib/db/db_mysqli.php',
    'myphp\db\db_pdo' => __DIR__ . '/lib/db/db_pdo.php',
    'myphp\db\tb_mysql' => __DIR__ . '/lib/db/tb_mysql.php',
    'myphp\db\tb_sqlite' => __DIR__ . '/lib/db/tb_sqlite.php',

    'myphp\File' => __DIR__ . '/lib/File.php',
    'myphp\Helper' => __DIR__ . '/lib/Helper.php',
    'myphp\Hook' => __DIR__ . '/lib/Hook.php',
    'myphp\Log' => __DIR__ . '/lib/Log.php',
    'myphp\Model' => __DIR__ . '/lib/Model.php',
    'myphp\Pipeline' => __DIR__ . '/lib/Pipeline.php',
    'myphp\Session' => __DIR__ . '/lib/Session.php',
    'myphp\Template' => __DIR__ . '/lib/Template.php',
    'myphp\View' => __DIR__ . '/lib/View.php',

    //'myphp\cache\File' => __DIR__ . '/lib/cache/File.php',
    //'myphp\cache\Redis' => __DIR__ . '/lib/cache/Redis.php',

    'myphp\driver\Redis' => __DIR__ . '/lib/driver/Redis.php',

    //'myphp\session\Redis' => __DIR__ . '/lib/session/Redis.php',

    'AES' => __DIR__ . '/ext/AES.php',
    'BitMap' => __DIR__ . '/ext/BitMap.php',
    'BitmapFile' => __DIR__ . '/ext/BitmapFile.php',
    'DecConvert' => __DIR__ . '/ext/DecConvert.php',
    'DesSecurity' => __DIR__ . '/ext/DesSecurity.php',
    'Endian' => __DIR__ . '/ext/Endian.php',
    'Http' => __DIR__ . '/ext/Http.php',
    'HttpAuth' => __DIR__ . '/ext/HttpAuth.php',
    'HttpCode' => __DIR__ . '/ext/HttpCode.php',
    'HttpReqInfo' => __DIR__ . '/ext/HttpReqInfo.php',
    'Image' => __DIR__ . '/ext/Image.php',
    'lib_redis' => __DIR__ . '/ext/lib_redis.php',
    'Py' => __DIR__ . '/ext/Py.php',
    'ReplyAck' => __DIR__ . '/ext/ReplyAck.php',
    'RotateLog' => __DIR__ . '/ext/RotateLog.php',
    'Upload' => __DIR__ . '/ext/Upload.php',
    'Zip' => __DIR__ . '/ext/Zip.php'
];
//初始框架
myphp::init(isset($cfg) ? $cfg : null);
/*---------- 辅助方法 ----------*/
/**
 * 统计程序运行时间 秒
 * @return string
 */
function run_time() {
    return number_format(microtime(TRUE) - SYS_START_TIME, 4);
}

/**
 * 统计程序内存开销
 * @return string
 */
function run_mem() {
    return MEMORY_LIMIT_ON ? toByte(memory_get_usage() - SYS_MEMORY) : 'unknown';
}

/**
 * 获取配置值 支持二维数组
 * @param $name
 * @param null $defVal
 * @return mixed|null
 */
function GetC($name, $defVal = null){
    return myphp::get($name, $defVal);
}

/**
 * 动态设置配置值
 * @param $name
 * @param $val
 */
function SetC($name, $val){
    myphp::set($name, $val);
}

/**
 * 获取语言信息 支持二维 需要先载入语言数组文件
 * @param $name
 * @return mixed|null
 */
function GetL($name){
    return myphp::lang($name);
}

/**
 * url解析 地址 [! 普通模式]admin/index/show?b=c&d=e, 附加参数 数组|null, url字符串如：/pub/index.php
 * @param string $uri
 * @param null $vars
 * @param string $url
 * @return mixed|string
 */
function U($uri='',$vars=null, $url=''){
    return myphp::forward_url($uri,$vars,$url);
}

/**
 * db实例化
 * @param string $name 数据库配置名
 * @param bool $force 是否强制生成新实例
 * @return \myphp\Db
 * @throws Exception
 */
function db($name='db', $force=false){
    return myphp::db($name, $force);
}

/**
 * @param string $name
 * @return lib_redis
 */
/*
function redis($name = 'redis')
{
    return myphp::redis($name);
}*/

/**
 * 生成json
 * @param $res
 * @param int $option
 * @return false|string
 */
function toJson($res, $option=0){
    return \myphp\Helper::toJson($res, $option);
}

/**
 * 消息输出
 * @param $message
 * @param string $url
 * @param string $info
 * @param int $time
 * @return false|string
 */
function out_msg($message, $url='', $info='', $time = 1){
    return \myphp\Helper::outMsg($message, $url, $info, $time);
}