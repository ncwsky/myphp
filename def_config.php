<?php
/*
 * 默认配置文件
*/
return array(
	'debug' => true, //是否开启调试模式，true开启，false关闭
	'lang' => 'zh-cn', // 默认语言
	'charset' => 'utf-8',//编码
	'url_mode' => 2,	//url模式，1表示普通模式，2表示PATH_INFO模式
    'url_rewrite' => true,	//启用url伪静态 用于url_mode=2
    'url_index' => '/index.php',	//启用url伪静态 对应的入口文件
	'cli_url_mode'=>null, //cli模式请求处理模式 默认2 PATH_INFO模式
	'def_control' => 'index',  //默认控制器名 c
	'def_action' => 'index',	//默认方法名 a
	'url_maps_regx'=> null, //url映射正则规则
	'url_maps' => null, //url映射 array()
	'module_maps' => null, //模块映射 [模块名=>（子项目）前置命令空间名称|路径,...] 放置到app项目下 自动识别app项目下module目录的子模块
	// array('admin'=>'/system')  -> /开头相对网站目录 无/开头相对项目目录 /index.php?m=admin&c=index&a=index 路径ROOT./system
	//数据库连接信息
	'db' => array(
		'pconnect' => false,
		'dsn' => '', //使用pdo驱动时可直接设置dsn
		'type' => 'pdo',   //数据库连接类型 仅pdo、mysqli
		'dbms' => 'mysql', //数据库
		'server' => 'localhost', //数据库主机
		'name' => '',     //数据库名称
		'user' => 'root', //数据库用户
		'pwd' => '',	  //数据库密码
		'port' => 3306,   // 端口
		'char' => 'utf8', //数据库编码
		'prefix' => '',   //数据库表前缀
        'options'=>[]     //辅助配置
	),
	'cache' => null, // 'file'
	'cache_option' => array(
		'path' => RUNTIME.'/cache',
		'prefix' => 'cache',
		'expire' => 0, //默认有效期
	),
	'session' => null, /*array(
	    //'class'=> null, //[优先]自定义session类,需要满足EnvSessionInterface接口的方法,如:\myphp\EnvSession
        // #'type'=> 'redis', //redis|file 内置处理 默认系统file
        // 'name'=>'sid',
        // 'path' => RUNTIME.'/sess', //可指定session存放目录
        // 'prefix' => 'my_', //用于非file方式的名前缀
        // 'expire' => 1440, //默认有效期
	),*/
    'req_cache' => false, //请求缓存 true
    'req_cache_expire' => 3600, //请求缓存时间 默认过期时间 秒
    'req_cache_except' => array(), //请求缓存排除项 ['/index/reg',  '/index/login']
    'jsonp_call'=>'callback', //JSONP处理方法 url请求传递此值可指定处理方法 否则默认此值
	'root_dir' => null,//相对根目录 未设置时自动获取 结尾不要"/"
	'class_dir'=>'',//class扩展路径 相对根目录 路径开头使用/ 多个使用,分隔 应用于myphp.php中
	'def_filter'=>'htmlspecialchars', //默认参数过滤
	'isGzipEnable' => false, //Gzip压缩开关
	'encode_key'  => 'oz7oQdlUdlP#y3gKuBbi67mVxhh',//加密串 用于cookie,api通信md5加密及rc4、authcode或其他加密
	'timezone' => 'PRC', //PRC中国 Etc/GMT-8东八区  Asia/Chongqing重庆 Asia/Shanghai上海
	'htmldir' => '/e',//默认静态目录
	'updir' => '/up',//默认上传目录
	'thumb_wh' => '240_180',//默认缩略图大小
	'img_ext' => 'png,jpg,jpeg,gif',//图片允许的文件后缀类型
	'img_size_limit' => 0.5,//图片上传限制大小 单位兆
	'files_ext' => 'swf,mp3,zip,rar,doc,xsl,ppt,wps,pdf,chm,txt',//文件允许的文件后缀类型
	'files_size_limit' => 2,//上传限制大小 单位兆
	'watermark_on' => false,//水印开关
	'watermark_wh' => '280_280', //水印添加条件 宽_高
	'tmp_theme' => false,//模板主题开启-用于前端
	'tmp_suffix' => '.html',//模板后缀名
	'tmp_left_tag'=>'{', //模板左侧符号
	'tmp_right_tag'=>'}', //模板右侧符号
    'tmp_variables'=>[], //模板自定义变量 key=>val|callable 如： '__PUBLIC__'=>ROOT_DIR.'/pub',...
	'site_template' => '',//模板主题
	'tmp_not_allow_fun' => '',//模板中不允许使用的函数 使用,分隔 如：,eval,echo,
	/* cookie设置 */
    'cookie_expire' => 0, //cookie有效期
    'cookie_domain' => '', //cookie作用域 如设为www.test.com,就只在www子域内有效. 跨域共享cookie的域名(例如: .test.com)
    'cookie_path' => '/', // cookie路径 '/' cookie就在整个domain内有效,如设为'/foo/',cookie就只在domain下的/foo/目录及子目录内有效.
    'cookie_pre' => '', // cookie前缀 避免冲突 分站时建议在前端配置中重命名
    'cookie_secure' => false, // cookie安全传输
    'cookie_httponly' => true, // httponly设置
    'cookie_same_site' => false,
    //中间件
    'middleware' => [
        //'xx::class', ...
    ],
	//权限验证
    //'roles' => [], //角色权限配置 [角色=>['name'=>'', 'purview'=>[]], ...]
    //'roles_name' => [], //角色名称 [角色=>'角色名',...]
	'auth_on' => false, //默认关闭
	'auth_model' => '\myphp\BaseAuth',//验证模块
	'auth_action' => 'check',//验证动作方法
	'auth_login' => 'isLogin',//登陆验证的动作方法
	'auth_gateway'=> '',//默认登录网关 如/index/login    ------------ 以下权限设置 优先级从上到下 ------------------------
	'auth_model_not' => '',//无需验证的模块，多个","分隔  用前后布置，包含  ,index,
	'auth_model_action' => '',//无需验证的模块中需要验证的动作  //,index/index,index/info,
	'auth_action_not' => '',//无需验证的动作，多个","分隔 格式：控制器/方法名
	'auth_login_model' => '',//仅登陆验证的模块
    'auth_login_action' => '',//仅登陆验证的方法
	'auth_login_M_A' => '',//登陆验证模块中需要验证的动作
	//日志设置
	'log_type' => 'file',// 记录类型
	'log_dir' => ROOT.'/log', //日志记录主目录名称
    'log_size' => 4194304,// 日志文件大小限制
	'log_level' => 0,// 日志记录等级
);