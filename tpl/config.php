<?php
return array_merge([
	/*
	'auth_on' => true, //默认关闭
    'auth_model' => '\myphp\BaseAuth',//验证模块
    'auth_action' => 'check',//验证动作方法
    'auth_login' => 'isLogin',//登陆验证的动作方法
    'auth_gateway'=> '/index/login',//默认验证网关    ------------ 以下权限设置 优先级从上到下 ------------------------
    'auth_action_not' => ',index/login,',// 无需验证的模块动作
    'auth_login_model' => ',index,',//仅登陆验证的模块
    'auth_login_M_A' => '',//仅登陆验证的模块动作
    'auth_login_action' => '',//仅登陆验证的动作
    'app_res_path'=>''
	*/
//可以在此行上面追加配置信息
], is_file(__DIR__ . '/config.local.php') ? require(__DIR__ . '/config.local.php') : []);