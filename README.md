//待补充

使用参考实例：
<?php
//定义项目路径
define('APP_PATH','./app');

// require 'conf.php'; // 这里可以载入全局配置参数数组 $cfg = array();
// 加载框架入口文件
//require("./myphp/base.php");

$myphp = new myphp();	//实例化一个类
$myphp->Run();	//运行类的Run的方法
?>

cli示例：
php index.php c-a-id  默认请求模式下(url模式1示例同此示例)
php index.php c=index a=test id=23  0 普通模式下