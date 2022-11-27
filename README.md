//待补充
todo 
主从读写  主主读写  一个请求生命周期数据使用同一个连接
轻量数据库队列、数据库[id:数据]+redis[list:存id, zAdd 延时执行排序通过延时执行时间按时段生成key，超出1天的统一放到daykey] 重启服务初始数据到Redis、redis [list:存id, hash:id->数据, zAdd 延时执行排序通过延时执行时间按时段生成key，超出1天的统一放到daykey]
工具：model生成、脚本、命令模式脚本执行GetOpt解析参数
扩展包casbin访问控制 https://docs.casbin.cn/zh/docs/overview 
https://github.com/php-casbin/php-casbin
登陆错误次数限制｜通用密码登陆+来源ip｜账户不存在错误次数限制ip


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