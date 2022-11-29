<?php
//在项目所有目录执行此文件 自动获取项目路径 未在
if (empty($_SERVER['argv']) || count($_SERVER['argv']) == 1) {
    die('no argv');
}
//require __DIR__ . "/conf.php";
require __DIR__ . "/base.php";
if (!IS_CLI) {
    die('no cli');
}

print_r($_SERVER['argv']);
$a = $_SERVER['argv'][1];
$params = array_slice($_SERVER['argv'], 2);
//脚本命令处理
try {
    if(!method_exists('\myphp\Tool', $a)){
        throw new \Exception('\myphp\Tool::'.$a.' not exists');
    }
    $ret = call_user_func_array(['\myphp\Tool', $a], $params);
    if (is_bool($ret)) {
        echo $ret ? 'ok' : 'fail:'.\myphp\Tool::err();
    } elseif (is_scalar($ret)) {
        echo $ret;
    } else {
        echo toJson($ret);
    }
} catch (\Throwable $e) {
    echo $e->getMessage();
}
echo PHP_EOL;
//\myphp\Tool::initModel($tbName)
