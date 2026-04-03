# myphp

一个轻量级的 PHP MVC 框架，注重简洁与实用。

## 特性

- **MVC 架构** — 控制器、模型、视图分层清晰
- **模块化** — 支持多模块应用，灵活的模块路径映射
- **模板引擎** — 内置轻量模板引擎，支持变量输出、循环、条件、文件包含
- **数据库** — 基于 PDO，支持 MySQL、SQLite、PostgreSQL、MSSQL、Oracle、TDengine
- **ORM** — 流式查询构建器，事务支持
- **缓存** — 文件缓存、Redis 缓存
- **中间件** — Pipeline 中间件管道
- **CLI 支持** — 命令行模式执行控制器方法
- **认证授权** — 内置登录检测与权限校验
- **会话管理** — 文件/Redis 会话存储
- **Hook 系统** — 事件驱动的钩子机制
- **日志** — 分级日志，自动轮转

## 环境要求

- PHP >= 7.2

## 安装

```bash
composer require myphps/myphp
```

## 快速开始

创建入口文件 `index.php`：

```php
<?php
define('APP_PATH', __DIR__ . '/app');

// require 'conf.php'; // 可载入全局配置 $cfg = []
require './vendor/myphps/myphp/base.php';
myphp::Run();
```

访问 `http://localhost/index.php/index/index` 即可运行。

## 目录结构

```
project/
├── web/                        # Web 入口目录
│   ├── index.php              # 主入口文件
│   └── admin.php              # 模块入口文件（可选）
├── app/                        # 应用目录
│   ├── config.php             # 应用配置
│   ├── control/               # 控制器
│   ├── model/                 # 模型
│   ├── view/                  # 视图模板
│   └── lang/                  # 语言包
├── common/                     # 公共代码
│   ├── config.php             # 全局配置
│   └── common.php             # 公共函数
├── runtime/                    # 运行时文件（缓存等）
├── conf.php                    # 全局配置文件
├── vendor/                     # Composer 依赖
└── composer.json
```

## 路由

采用 `m/c/a`（Module/Controller/Action）路由模式：

| 段 | 含义 | 示例 |
|---|------|------|
| m | 模块（可选） | admin |
| c | 控制器 | user |
| a | 方法 | list |

**URL 模式：**

| 模式 | 格式 | 示例 |
|------|------|------|
| 1 | Query String | `/index.php?c=user&a=list` |
| 2（默认） | PATH_INFO | `/index.php/user/list` |

URL 中的控制器名会自动转换为 PascalCase 并加上 `Act` 后缀：

```
/user-profile/list → app\control\UserProfileAct::list()
```

默认控制器和方法均为 `index`，即访问 `/` 等同于 `/index/index`。

## 控制器

控制器放在 `app/control/` 目录下：

```php
<?php
namespace app\control;

use myphp\Control;

class UserAct extends Control
{
    // 前置方法，每个 action 执行前调用
    protected function _before() {}

    public function index()
    {
        $this->assign('title', '用户列表');
        return $this->fetch(['msg'=>'你好']); // 渲染 app/view/user/index.html
    }

    public function info()
    {
        $user = UserModel::where(['id' => $_GET['id']])->one();
        return self::ok($user); // JSON 成功响应
    }
}
```

**响应方法：**

```php
self::ok($data, $info)          // 成功 JSON 响应
self::fail($info, $code)        // 失败 JSON 响应
self::json($data)               // 原始 JSON
self::jsonp($data)              // JSONP
self::html($content)            // HTML
self::redirect($url)            // 重定向
$this->fetch($template)         // 渲染模板
```

## 模型

模型放在 `app/model/` 目录下：

```php
<?php
namespace app\model;

use myphp\Model;

class UserModel extends Model
{
    protected $tbName = 'user';    // 表名
    protected $prikey = 'id';      // 主键
}
```

**查询方法：**

```php
UserModel::where(['id' => 1])->one();                // 单条
UserModel::where(['id' => 1])->lock()->one();        // 单条加锁查询 FOR UPDATE
UserModel::fields('id,name,sex')->all();             // 全部
UserModel::where(['status'=>1])->all();              // 条件查询 all|select效果一样
UserModel::where('age > ?', [18])->order('id DESC')->limit(10)->select();
UserModel::insert($data);                            // 插入
UserModel::insert([$data,$data,...]);                // 批量插入
UserModel::where('id = ?', [1])->update($data);     // 更新
UserModel::updateAll($data, ['id'=>1]);              // 更新
UserModel::where('id = ?', [1])->del();              // 删除
UserModel::delAll(['id'=>1]);                        // 删除

// 事务
Model::beginTrans();
Model::commit();
Model::rollBack();
```

## 模板引擎

模板文件默认使用 `.html` 后缀，放在 `app/view/` 目录下。

### 变量输出

```
{$name}              → <?php echo $name;?>
{$data.name}         → <?php echo $data['name'];?>
```

### 包含文件

```
{include:header.html}
```

### 循环

```
{list $users}
    <p>{$val.name}</p>
{/list}
```

等同于 `foreach($users as $key=>$val)`。自定义变量名：

```
{list $users $user}
    <p>{$user.name}</p>
{/list}
```

等同于 `foreach($users as $k_user=>$user)`。

### 条件

```
{if $age > 18}
    成年
{elseif $age > 12}
    青少年
{else}
    儿童
{/if}
```

### 其他标签

| 标签 | 作用 | 示例 | 输出 |
|------|------|------|------|
| `~` | 执行 PHP 语句 | `{~echo $name}` | `<?php echo $name ?>` |
| `*` | 原样输出表达式 | `{*$obj->name()}` | `<?php echo $obj->name(); ?>` |
| `@` | 语言变量 | `{@name}` | `<?php echo GetL('name');?>` |
| `#` | 配置值 | `{#name}` | `<?php echo GetC('name');?>` |
| `?` | isset 检查 | `{?$name}` | 存在则输出，否则为空 |

`$` 标签会对变量做解析（如点号转数组访问），而 `*` 标签将内容作为原始 PHP 表达式直接输出，适用于方法调用、多维数组、带判断的复杂表达式等：

```
{$data.name}                → <?php echo $data['name'];?>
{*$user->getName()}         → <?php echo $user->getName(); ?>
{*$list[$i]['child'][$j]}   → <?php echo $list[$i]['child'][$j]; ?>
{*isset($a) ? $a : $b}     → <?php echo isset($a) ? $a : $b; ?>
```

**`?` 标签用法：**

```
{?$name}              → isset($name) ? $name : ''
{?$name:默认值}       → isset($name) ? $name : '默认值'
{?$name=trim}         → isset($name) ? trim($name) : ''
{?$name=trim:默认值}  → isset($name) ? trim($name) : '默认值'
```

## 模块

模块通过 `module_maps` 配置映射：

```php
'module_maps' => [
    'admin' => '/admin',           // / 开头相对项目根目录
    'api'   => 'module/api',       // 无 / 开头相对应用目录(/app/)
    'user'  => '/app/module/user'  // 完整路径
]
```
通过配置映射后，访问 `/admin/user/list` 将直接请求到admin模块下的user控制器下的list方法。
而通过单独的入口文件访问，则需要参照以下场景：

### 场景一：模块目录在项目根目录下
入口文件：/web/admin.php  
模块目录直接位于根目录（如 `/admin`），入口文件直接指向该目录：
```php
<?php
define('APP_PATH', __DIR__ . '/../admin');
define('COMMON', __DIR__ . '/../common');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
myphp::Run();
```

### 场景二：使用 DEF_MODULE 指定默认模块
入口文件：/web/admin.php  
通过主应用入口加载指定模块，模块位于根目录下：
```php
<?php
define('APP_PATH', __DIR__ . '/../app');
define('COMMON', __DIR__ . '/../common');
define('DEF_MODULE', 'admin');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
myphp::Run();
```

### 场景三：模块在子目录下，需手动配置命名空间
入口文件：/web/admin.php
模块位于 `app/module/admin`，需指定命名空间映射：
```php
<?php
define('APP_PATH', __DIR__ . '/../app/module/admin');
define('COMMON', __DIR__ . '/../common');
define('DEF_MODULE', 'admin');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conf.php';
require __DIR__ . '/../vendor/myphps/myphp/base.php';
myphp::$cfg['app_namespace'] = 'app\\module\\admin';
myphp::$namespaceMap['app\\'] = __DIR__ . '/../app';
myphp::Run();
```

## CLI 模式

命令行下执行控制器方法，参数格式同 URL：

```bash
php index.php m/c/a "b=1&d=1"
php index.php m/c/a b=1 d=1
php index.php m/c/a?b=1
php index.php "m/c/a?b=1&d=1"
```

使用 `my` 命令行工具：

```bash
php my --init                    # 初始化项目 默认app
php my --init --run=admin        # 初始化模块（应用目录）
php my user/list           # 执行 controller/action
php my admin/user/list           # 执行 module/controller/action
php my --run=admin dashboard/stats "id=5"  # 指定模块执行
```

## 配置

配置加载顺序（后者覆盖前者）：

1. 框架默认配置（`def_config.php`）
2. 全局配置（`conf.php`）— 项目部署环境配置，如数据库连接、调试开关等
3. 共用配置（`common/config.php`）— 跨模块共用的业务配置
4. 应用配置（`app/config.php`）— 当前应用的专属配置
5. 模块配置（模块 `config.php`）— 模块级别的独立配置

**常用配置项：**

```php
$cfg = [
    'debug'       => true,              // 调试模式
    'charset'     => 'utf-8',           // 字符编码
    'timezone'    => 'Asia/Shanghai',   // 时区
    'lang'        => 'zh-cn',           // 默认语言

    // 路由
    'url_mode'    => 2,                 // 1:Query String  2:PATH_INFO
    'url_rewrite' => true,              // 伪静态
    'def_control' => 'index',           // 默认控制器
    'def_action'  => 'index',           // 默认方法

    // 数据库
    'db' => [
        'type'   => 'pdo',
        'dbms'   => 'mysql',
        'server' => 'localhost',
        'name'   => 'database',
        'user'   => 'root',
        'pwd'    => '',
        'port'   => 3306,
        'char'   => 'utf8mb4',
        'prefix' => '',
    ],

    // 缓存
    'cache' => 'file',                  // 'file' 或 Redis 配置数组

    // 会话
    'session' => [
        'type'   => 'file',             // 'file' 或 'redis'
        'expire' => 1440,
    ],

    // 中间件
    'middleware' => [
        \myphp\middleware\Cors::class,
    ],

    // 日志
    'log_level' => 0,                   // 0:全部 1:debug 2:info 3:notice 4:warn 5:error
];
```

完整配置项参见 `def_config.php`。

## 中间件

```php
class AuthMiddleware
{
    public function __invoke(\myphp\Request $request, \Closure $next)
    {
        // 前置处理
        if (!check_login()) {
            return myphp::res()->withStatus(401, 'Unauthorized');
        }
        $response = $next($request);
        // 后置处理
        return $response;
    }
}
```

在配置中注册：

```php
'middleware' => [
    AuthMiddleware::class,
]
```

## 日志

```php
use myphp\Log;

// 按级别记录
Log::DEBUG('调试信息');
Log::INFO('一般信息');
Log::WARN('警告');
Log::ERROR('错误');
Log::SQL($sql);

// 自定义写入
Log::trace('自定义日志', 'trace');   // 追踪日志
Log::write('自定义日志', 'info');    // 写入指定级别
Log::echo('日志1', '日志2');         // 多内容输出

// 请求与异常
Log::miniREQ();                      // 简要请求信息
Log::REQ();                          // 完整请求信息
Log::Exception($e);                  // 记录异常
```

日志自动按大小轮转（默认 4MB），存放在 `log/` 目录。

## 开发工具

```bash
# 安装
composer require --dev phpstan/phpstan friendsofphp/php-cs-fixer

# 静态分析
phpstan analyse -c ./phpstan.neon.dist --memory-limit 1G

# 代码格式化
php-cs-fixer fix --config=./.php-cs-fixer.dist.php
```

## 协议

MIT License
