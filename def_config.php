<?php
/*
 * 默认配置文件
*/
return array(
	'debug' => TRUE, //是否开启调试模式，true开启，false关闭
	'lang' => 'zh-cn', // 默认语言
	'charset' => 'utf-8',//编码
	'url_mode' => 2,	//url模式，1表示普通模式，2表示PATH_INFO模式
	'cli_url_mode'=>null, //cli模式请求处理模式 默认2 PATH_INFO模式
	// 'default_module'=>'app', //默认模块名 m
	'default_control' => 'index',  //默认控制器名 c
	'default_action' => 'index',	//默认方法名 a
	'url_para_str' => '-',	//参数分隔符，一般不需要修改
	'url_maps_regx'=> null, //url映射正则规则
	'url_maps' => null, //url映射 array()
	'module_maps' => null, //模块映射 array(), 模块名=>模块（项目）路径
	// array('admin'=>'/system')  -> /开头相对网站目录 无/开头相对项目目录 /index.php?m=admin&c=index&a=index 路径ROOT.ROOT_DIR./system
	//数据库连接信息
	'db' => array(
		'pconnect' => FALSE,
		'dsn' => '',//使用pdo驱动时可直接设置dsn
		'type' => 'mysql',	//数据库驱动类型 仅有mysql、pdo
		'dbms' => 'mysql', //数据库
		'server' => 'localhost',	//数据库主机
		'name' => '',	//数据库名称
		'user' => 'root',	//数据库用户
		'pwd' => '',	//数据库密码
		'port' => 3306,     // 端口
		'char' => 'utf8',	//数据库编码
		'prefix' => 'my_'	//数据库表前缀
	),
	'cache' => null, // 'file'
	'cache_option' => array(
		'path' => './',
		'prefix' => 'cache',
		'expire' => 0, //默认有效期
	),
	'session' => null, // 'file'
	'session_option' => array(
		// 'path' => './', //可指定session存放目录
		// 'prefix' => 'myphp', //用于内存模式
		// 'expire' => 1440, //默认有效期
	),
	'root_dir' => null,//相对根目录 未设置时自动获取 结尾不要"/"
	'admin_url' => '/admin.php',//后台执行页面
	'class_dir'=>'',//class扩展路径 多个使用,分隔 应用于myphp.php中
	'def_filter'=>'htmlspecialchars', //默认参数过滤
	'isGzipEnable' => FALSE, //Gzip压缩开关
	'encode_key' => 'sys_ncw_f512',//加密串 用于cookie,api通信md5加密及rc4或其他加密
    'authcode_key'=> 'oz7oQdlUdlPy3gKuBbi67mVxhh', //用于authcode加密
	'timezone' => 'Etc/GMT-8', //网站时区（只对php 5.1以上版本有效），Etc/GMT-8 实际表示的是 GMT+8
	'htmldir' => '/e',//默认静态目录
	'updir' => '/up',//默认上传目录
	'thumb_wh' => '240_180',//默认缩略图大小
	'img_ext' => 'png,jpg,jpeg,gif',//图片允许的文件后缀类型
	'img_size_limit' => 0.5,//图片上传限制大小 单位兆
	'files_ext' => 'swf,mp3,zip,rar,doc,xsl,ppt,wps,pdf,chm,txt',//文件允许的文件后缀类型
	'files_size_limit' => 2,//上传限制大小 单位兆
	'watermark_on' => FALSE,//水印开关
	'watermark_wh' => '280_280', //水印添加条件 宽_高
	'tmp_theme' => FALSE,//模板主题开启-用于前端
	'tmp_suffix' => '.html',//模板后缀名
	'tmp_left_tag'=>'{', //模板左侧符号
	'tmp_right_tag'=>'}', //模板右侧符号
	'site_template' => '',//模板主题
	'tmp_not_allow_fun' => '',//模板中不允许使用的函数 使用,分隔 如：,eval,echo,
	/* cookie设置 */
    'cookie_expire' => 0, //cookie有效期
    'cookie_domain' => '', //cookie作用域 如设为www.test.com,就只在www子域内有效. 跨域共享cookie的域名(例如: .test.com)
    'cookie_path' => '/', // cookie路径 '/' cookie就在整个domain内有效,如设为'/foo/',cookie就只在domain下的/foo/目录及子目录内有效.
    'cookie_pre' => '9e', // cookie前缀 避免冲突 分站时建议在前端配置中重命名
    'cookie_secure' => FALSE, // cookie安全传输
    'cookie_httponly' => FALSE, // cookie httponly设置
	//权限验证
	'auth_on' => FALSE, //默认关闭
	'auth_model' => 'user',//验证模块 
	'auth_action' => 'check',//验证动作方法
	'auth_login' => 'chkLogin',//登陆验证的动作方法
	'auth_err' => 'errmsg',//验证错误信息记录变量
	'auth_gateway'=> '/admin.php/index-login',//默认验证网关    ------------ 以下权限设置 优先级从上到下 ------------------------
	'auth_model_not' => '',//无需验证的模块，多个","分隔  用前后布置，包含  ,index,
	'auth_model_action' => '',//无需验证的模块中需要验证的动作  //,index::index,index::info,
	'auth_action_not' => '',//无需验证的动作，多个","分隔 格式：模块名::动作名
	'auth_login_model' => '',//仅登陆验证的模块
	'auth_login_M_A' => '',//登陆验证模块中需要验证的动作
	'auth_login_action' => '',//仅登陆验证的动作
	//日志设置
    'log_on' => FALSE,// 默认不记录日志
	'log_type' => 'file',// 记录类型
    'log_size' => 2097152,// 日志文件大小限制
	//请求
	//'var_method' => '_method', // 模拟的请求类型变量名
);
?>