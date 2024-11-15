<?php
namespace __app__\control;

class __c__ extends Base{
	//创建一个方法
	public function __a__(){
		//赋值给模板变量
        $this->assign('title', 'Hi');
        return $this->fetch('index.html', [
            'mess' => '__app__'
        ]);
/*
        //还可以与assign混合使用 此处在__construct 时特别有用 可以对一些需要全局使用的模板变量进行赋值调用
		$this->assign('mess', 'Hello world');
        #$this->view->vars['mess'] = 'Hello world';
		#extract($this->view->vars);
		//可不用assign进行模板赋值
		ob_start();
		require \myphp\View::doTemp('index.html');
		return ob_get_clean();//\myphp\View::end();

		$this->assign('mess', 'Hello world');
		return $this->fetch();
*/
	}
}