<?php
//直接继承基类
class Base extends Control{
	var $db;
	protected static $siteid;
	// 构造函数
    public function __construct() {
		parent::__construct();
		if(GetC('db.name')!='') $this->db = $this->M();	//基类模型;
		self::$siteid = G(0,cookie("siteid"),0,0);
    }
}
?>