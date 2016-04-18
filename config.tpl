<?php
return array(
	//数据库连接信息
	'db' => array(
		'type' => 'mysql',	//数据库驱动类型 仅有mysql、pdo
		'dbms' => 'mysql', //数据库 驱动类型为pdo时使用
		'server' => 'localhost',	//数据库主机
		'name' => '',	//数据库名称
		'user' => 'root',	//数据库用户
		'pwd' => '',	//数据库密码
		'port' => 3306,     // 端口
		'char' => 'utf8',	//数据库编码
		'prefix' => 'my_'	//数据库表前缀
	),
	//'debug' => FALSE,
	//'url_mode' => 2,
	'root_dir' => '',//myphp_dir未设置时自动获取
	//'myphp_dir' => '/myphp',//myphp框架目录
//可以在此行上面追加配置信息
//Do not remove this line
);