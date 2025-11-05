**TODO**   
主从读写  主主读写  一个请求生命周期数据使用同一个连接   
轻量数据库队列、数据库[id:数据]+redis[list:存id, zAdd 延时执行排序通过延时执行时间按时段生成key，超出1天的统一放到daykey] 重启服务初始数据到Redis、redis [list:存id, hash:id->数据, zAdd 延时执行排序通过延时执行时间按时段生成key，超出1天的统一放到daykey]   
工具：model生成、脚本、命令模式脚本执行GetOpt解析参数    
扩展包casbin访问控制 https://docs.casbin.cn/zh/docs/overview 
https://github.com/php-casbin/php-casbin    
登陆错误次数限制｜通用密码登陆+来源ip｜账户不存在错误次数限制ip  
编辑器     

**使用参考实例**  
```
<?php
//定义项目路径
define('APP_PATH', __DIR__ . '/app');

// require 'conf.php'; // 这里可以载入全局配置参数数组 $cfg = array();
// 加载框架入口文件
//require("./myphp/base.php");

myphp::Run();	//运行类的Run的方法
```

**cli示例**   
>脚本参数输入基本同url地址  
```
php index.php m/c/a "b=1&d=1"|b=1 d=1  
php index.php m/c/a?b=1  
php index.php "m/c/a?b=1&d=1"  
```

**模板标签**    
```
#引入文件 
{include:文件名.后缀名}  

#循环数据    
list $retData -> $retData as $key=>$val;
list $retData $custom -> $retData as $k_custom=>$custom
{list $retData}
{/list}

#条件
{if x}{else}{elseif x}{/if}

#标签
~ => php    ~echo $name -> echo $name;
$ => var    $name -> echo $name;
* => echo   *$name -> echo $name;
@ => lang   @name -> echo GetL('name');
# => config #name -> echo Getc('name');
? => isset  ?$v[=$fun][:$defval]
    ?$name -> echo isset($name)?$name:'';
    ?$name:0 -> echo isset($name)?$name:0;
    ?$name=trim:$defval -> echo isset($name)?trim($name):$defval;
 
```

**模块**  
> 模块目录放置到项目/app目录下或/根目录下，放置其他位置必需配置模块映射，示例如下：

_项目入口文件 index.php_  
> 模块通过app目录下的配置文件或全局配置的模块映射自动识别
> module_maps=>['admin'=>'/admin','api'=>'module/api','user'=>'/app/module/user']
```php
<?php
define('APP_PATH',__DIR__.'/../app');
define('COMMON', __DIR__.'/../common');
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../conf.php";
require __DIR__ . "/../vendor/myphps/myphp/base.php";
myphp::Run();
```
_admin模块入口文件 admin.php_ 
> 模块需放置在项目根目录/或/app下    
> 在app目录下的时必需要在文件或全局配置里设置模块路径映射  
> app_namespace有配置时同时需要配置$namespaceMap命名空间前缀路径
```php
<?php
define('APP_PATH',__DIR__.'/../app');
define('COMMON', __DIR__.'/../common');
define('DEF_MODULE', 'admin'); #未配置此项，则在app目录下的配置文件或全局配置的模块映射自动识别 等同项目入口文件
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../conf.php";
require __DIR__ . "/../vendor/myphps/myphp/base.php";
myphp::Run();

#或

define('APP_PATH', __DIR__ . '/../app/module/admin');
define('COMMON', __DIR__ . '/../common');
define('DEF_MODULE', 'admin');
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../conf.php";
require __DIR__ . "/../vendor/myphps/myphp/base.php";
myphp::$cfg['app_namespace'] = 'app\\module\\admin'; //模块命名空间前缀
myphp::$namespaceMap['app\\'] = __DIR__ . '/../app'; //命名空间前缀路径
myphp::Run();
```

**静态分析、代码格式化**
```
composer require --dev phpstan/phpstan
composer require --dev friendsofphp/php-cs-fixer
composer global require friendsofphp/php-cs-fixer
composer global require phpstan/phpstan

php-cs-fixer fix --config=./.php-cs-fixer.dist.php   
phpstan analyse -c ./phpstan.neon.dist --memory-limit 1G
```