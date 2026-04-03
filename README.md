**TODO**   
主从读写  主主读写  一个请求生命周期数据使用同一个连接   
轻量数据库队列、数据库[id:数据]+redis[list:存id, zAdd 延时执行排序通过延时执行时间按时段生成key，超出1天的统一放到daykey] 重启服务初始数据到Redis、redis [list:存id, hash:id->数据, zAdd 延时执行排序通过延时执行时间按时段生成key，超出1天的统一放到daykey]   
工具：model生成、脚本、命令模式脚本执行GetOpt解析参数    
扩展包casbin访问控制 https://docs.casbin.cn/zh/docs/overview 
https://github.com/php-casbin/php-casbin    
登陆错误次数限制｜通用密码登陆+来源ip｜账户不存在错误次数限制ip  
编辑器|cfg配置只读|运行env变量可设置|lang读取设置     

**使用参考实例**  
```php
<?php
//定义项目路径
define('APP_PATH', __DIR__ . '/app');

// require 'conf.php'; // 这里可以载入全局配置参数数组 $cfg = [];
// 加载框架入口文件
require('./myphp/base.php');
myphp::Run(); //运行
```

**cli模式执行示例**   
>脚本参数输入基本同url地址  
```
php index.php m/c/a "b=1&d=1" 或 php index.php m/c/a b=1 d=1  
php index.php m/c/a?b=1  
php index.php "m/c/a?b=1&d=1"  
```

**模板标签**    
```
# 引入文件 
{include:文件名.后缀名}  

# 循环数据   
{list $retData}
{/list} 
list $retData -> $retData as $key=>$val;
list $retData $custom -> $retData as $k_custom=>$custom

# 条件
{if x}{else}{elseif x}{/if}

# 标签
~ => 代码 {~echo $name}   -> <?php echo $name;?>
$ => 变量 {$name}         -> <?php echo $name;?>
         {$data.name}    -> <?php echo $data['name'];?>
* => 输出 {*$name}        -> <?php echo $name;?>
@ => 语言 {@name}         -> <?php echo GetL('name');?>
# => 配置 {#name}         -> <?php echo Getc('name');?>
? => isset  {?$v[=$fun][:$defval]}
     {?$name}               -> <?php echo isset($name)?$name:'';?>
     {?$name:0}             -> <?php echo isset($name)?$name:0;?>
     {?$name=trim:$defval}  -> <?php echo isset($name)?trim($name):$defval;?>
```

**项目入口文件**  
/web/index.php
```php
<?php
define('APP_PATH',__DIR__.'/../app');
define('COMMON', __DIR__.'/../common');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
myphp::Run();
```
**模块**
- 模块通过app目录下的配置文件(_/app/config.php_)或全局配置(_/conf.php_)的 _module_maps_ 配置识别
- 未配置 module_maps 时，需要模块放到项目/根目录下
- 配置模块映射的示例如下：
```php
'module_maps'=> [ //模块映射 模块路由名=>模块目录
    'admin' => '/admin', # /开头相对项目根目录
    'api' => 'module/api', # 无/开头相对项目目录 /app/module/api
    'user' => '/app/module/user'
]
```
admin模块入口文件 _/web/admin.php_  
- 在非项目根目录下时，必需在app下配置文件或全局配置里设置模块路径映射  
- 针对模块独立入口文件时，需要配置myphp::$cfg['app_namespace']（模块命名空间）、myphp::$namespaceMap（命名空间前缀路径）

```php
<?php 
define('APP_PATH',__DIR__.'/../admin'); #不需要配置模块映射或DEF_MODULE 此入口文件等同项目入口文件
define('COMMON', __DIR__.'/../common');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
myphp::Run();
```
或
```php
<?php
define('APP_PATH',__DIR__.'/../app');
define('COMMON', __DIR__.'/../common');
define('DEF_MODULE', 'admin'); # 指定默认模块名 同时模块在根目录
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
myphp::Run();
```
或
```php
<?php
define('APP_PATH', __DIR__ . '/../app/module/admin');
define('COMMON', __DIR__ . '/../common');
define('DEF_MODULE', 'admin');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
#未配置模块映射且模块目录未在项目根目录下时需要指定命令空间和识别命名空间的前缀路径
myphp::$cfg['app_namespace'] = 'app\\module\\admin'; //模块命名空间
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