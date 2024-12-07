<?php

declare(strict_types=1);

use myphp\Db;
use myphp\Helper;

//系统开始时间
define('SYS_START_TIME', microtime(true));//时间戳.微秒数
define('SYS_TIME', time());//时间戳
// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
MEMORY_LIMIT_ON && define('SYS_MEMORY', memory_get_usage());
//系统变量
const IS_CLI = PHP_SAPI === 'cli';
const IS_WIN = DIRECTORY_SEPARATOR === '\\'; //strpos(PHP_OS, 'WIN') !== false
const DS = '/';
//定义MY_PATH常量
const MY_PATH = __DIR__;

//REQUEST_URI 处理 ORIG_PATH_INFO REDIRECT_PATH_INFO REDIRECT_URL
if (!IS_CLI && !isset($_SERVER['REQUEST_URI'])) {
    if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
    } else {
        $_SERVER['REQUEST_URI'] = ($_SERVER['PHP_SELF'] ?? ($_SERVER['SCRIPT_NAME'] ?? '')) . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    }
}

//项目根目录处理
if (defined('APP_PATH')) {
    $root = dirname(APP_PATH);
    if ($root === '' || $root[0] === '.' || strpos($root, '..')) {
        $root = realpath($root);
    }
} else {
    $root = IS_CLI ? dirname(realpath($_SERVER['SCRIPT_FILENAME'])) : str_replace($_SERVER['SCRIPT_NAME'], '', IS_WIN ? strtr($_SERVER['SCRIPT_FILENAME'], '\\', DS) : $_SERVER['SCRIPT_FILENAME']);
}
define('ROOT', IS_WIN ? strtr($root, '\\', DS) : $root);
//临时目录
defined('RUNTIME') || define('RUNTIME', ROOT . '/runtime');
//公共目录
defined('COMMON') || define('COMMON', ROOT . '/common');
//Web目录
defined('SITE_WEB') || define('SITE_WEB', ROOT . '/web');

require __DIR__ . '/myphp.php';
require __DIR__ . '/inc/comm.func.php';
require __DIR__ . '/lib/Db.php';

myphp::$namespaceMap = [
    'myphp\\' => __DIR__ . '/lib',
    'common\\' => COMMON,
];
//类映射
myphp::$classMap = [
    'myphp\Cache' => __DIR__ . '/lib/Cache.php',
    'myphp\CacheAbstract' => __DIR__ . '/lib/Cache.php',
    'myphp\Control' => __DIR__ . '/lib/Control.php',
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
    'myphp\Request' => __DIR__ . '/lib/Request.php',
    'myphp\Response' => __DIR__ . '/lib/Response.php',
    'myphp\Session' => __DIR__ . '/lib/Session.php',
    'myphp\Template' => __DIR__ . '/lib/Template.php',
    'myphp\Value' => __DIR__ . '/lib/Value.php',
    'myphp\View' => __DIR__ . '/lib/View.php',

    'myphp\cache\File' => __DIR__ . '/lib/cache/File.php',
    'myphp\cache\Redis' => __DIR__ . '/lib/cache/Redis.php',
    'myphp\driver\Redis' => __DIR__ . '/lib/driver/Redis.php',
    'myphp\middleware\Cors' => __DIR__ . '/lib/middleware/Cors.php',
    'myphp\middleware\Options' => __DIR__ . '/lib/middleware/Options.php',
    'myphp\session\Redis' => __DIR__ . '/lib/session/Redis.php',

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
    'Zip' => __DIR__ . '/ext/Zip.php',
];
//初始框架
myphp::init($cfg ?? null);
/*---------- 辅助方法 ----------*/
/**
 * 统计程序运行时间 秒
 * @param float|string $micro
 * @return string
 */
function run_time($micro = SYS_START_TIME): string
{
    return number_format(microtime(true) - $micro, 4);
}

//统计程序内存开销
function run_mem(): string
{
    return MEMORY_LIMIT_ON ? toByte(memory_get_usage() - SYS_MEMORY) : 'unknown';
}

/**
 * 获取配置值 支持二维数组
 * @param string $name
 * @param null $defVal
 * @return mixed|null
 */
function GetC(string $name, $defVal = null)
{
    return myphp::get($name, $defVal);
}

/**
 * 动态设置配置值
 * @param string|array $name
 * @param mixed $val
 */
function SetC($name, $val)
{
    myphp::set($name, $val);
}

/**
 * 获取语言信息 支持二维 需要先载入语言数组文件
 * @param string $name
 * @return mixed|null
 */
function GetL(string $name)
{
    return myphp::lang($name);
}

/**
 * url解析 地址 [! 普通模式]admin/index/show?b=c&d=e, 附加参数 数组|null, url字符串如：/pub/index.php
 * @param string $uri
 * @param null $vars
 * @param string $url
 * @return string
 */
function U(string $uri = '', $vars = null, string $url = ''): string
{
    return myphp::toUrl($uri, $vars, $url);
}

/**
 * db实例化
 * @param string $name 数据库配置名
 * @param bool $force 是否强制生成新实例
 * @return Db
 * @throws Exception
 */
function db(string $name = 'db', bool $force = false): Db
{
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
 * @param mixed $res
 * @param int $option
 * @return false|string
 */
function toJson($res, int $option = 0)
{
    return Helper::toJson($res, $option);
}

function out_msg(string $message, string $url = '', string $info = '', int $time = 1)
{
    return Helper::outMsg($message, $url, $info, $time);
}
