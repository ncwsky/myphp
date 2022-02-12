<?php
//直接继承基类
class IndexAct extends Base{
	//创建一个方法
	public function index(){
/*
		$res = db()->query("select * from test");	//查询数据库
		$row = db()->fetch_array($res);	//获得查询结果
		return $row;	//输出结果
*/
		$title = '欢迎信息';
		$mess = '我的一个MVC框架';
		//赋值给模板变量
		$this->assign('title', $title);
		$this->assign('mess', $mess);
		return $this->fetch('index.html');
/*
		//可不用assign进行模板赋值
		$this->view->obstart();
		include $this->view->dotemp('index.html');

		$this->assign('mess', '');
		return $this->fetch();

		extract($this->view->vars);
		View::obstart();
		include View::dotemp();
		
		//还可以与assign混合使用 此处在__construct 时特别有用 可以对一些类里全局使用的模板变量进行赋值调用
		$this->view->obstart();extract($this->view->vars);
		include $this->view->dotemp('index.html');
*/
	}
}