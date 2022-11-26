<?php
namespace __app__\control;

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
		\myphp\View::obStart();
		include \myphp\View::doTemp('index.html');

		$this->assign('mess', '');
		return $this->fetch();

		extract($this->view->vars);
		\myphp\View::obStart();
		include \myphp\View::doTemp();

		//还可以与assign混合使用 此处在__construct 时特别有用 可以对一些类里全局使用的模板变量进行赋值调用
		\myphp\View::obStart();extract($this->view->vars);
		include \myphp\View::doTemp('index.html');
*/
	}
}