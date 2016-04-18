<?php
require ROOT . PUB . '/inc/fun.php';
require ROOT . PUB . '/inc/chkuser.php';
//直接继承基类
class Base extends Control{
	var $db;
	var $siteid;
	// 构造函数
    public function __construct() {
		parent::__construct();
		$this->db = $this->M();	//基类模型;
		$this->siteid = GetC('site_id');
		if(GetC('site_isdis')==0) {//站点关闭
			$site_close_des = '<font color=red>站点关闭维护中...</font>';
			if(isset($GLOBALS['cfg']['site_close_des'])) $site_close_des = $GLOBALS['cfg']['site_close_des'];
			exit($site_close_des);
		}
    }
}
?>