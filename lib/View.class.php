<?php
//视图模板基类
class View{
	var $vars = array();	//板变量数组
	var $view_path = '';
	private $template = NULL;	//模板引擎实例
	private static $instance = NULL;
	//构造方法，实例化视图
	private function __construct(){
		$this->view_path = VIEW_PATH;
		$this->template = Template::GetInstance();
	}
	//单例模式
	public static function getInstance(){
		if(self::$instance == NULL){
			self::$instance = new self();
		}
		return self::$instance;
	}
	//初始模板
	private function init($file,$cachetime=0){
		$templateFile = $this->view_path . $file;	//定义模板文件路径 .'.html'
		//判断模板文件是否存在
		if(!file_exists($templateFile)){
			exit('模板文件' . $file . '不存在');
		}
		//设置相关信息
		$this->template->templatePath = $this->view_path;	//模板路径
		$this->template->cache = TRUE;	//设置是否开启缓存
		$this->template->cachePath = CACHE_PATH;	//缓存路径
		$this->template->cacheLifeTime = $cachetime;	//缓存更新时间 0为不限制 只有当模板修改后才重新生成缓存
		$this->template->templateSuffix = isset($GLOBALS['cfg']['tmp_suffix']) ? $GLOBALS['cfg']['tmp_suffix'] : '.html';	//模板后缀名
		$this->template->leftTag = isset($GLOBALS['cfg']['tmp_left_tag']) ? $GLOBALS['cfg']['tmp_left_tag'] : '{';	//模板左侧符号
		$this->template->rightTag = isset($GLOBALS['cfg']['tmp_right_tag']) ? $GLOBALS['cfg']['tmp_right_tag'] : '}';	//模板右侧符号		
	}
	//显示模板
	public function display($file='',$cachetime=0){
		if($file=='')
			$file = ACTION.$this->template->templateSuffix;
		$this->init($file,$cachetime);
		$content = $this->template->display($file, $this->vars);//返回内容
		$this->obstart();
        echo $content;
        ob_end_flush();
	}
	//设置模板变量
	public function assign($var, $value = ''){
		if(is_array($var)){	//如果是数组，那么将它合并到属性$vars中
			$this->vars = array_merge($this->vars, $var);
		}else{ //否则以$var为下标$value为值，增加到$vars中
			$this->vars[$var] = $value;
		}
	}
	//静态方法
	public static function dotemp($file='',$cachetime=0){
		if($file=='')
			$file = ACTION.self::$instance->template->templateSuffix;
		self::$instance->init($file,$cachetime);

		return self::$instance->template->cachefile($file);
	}
	//打开输出缓冲
	public static function obstart(){
		// 配置变量isGzipEnable Gzip压缩开关
	    if (function_exists('ob_gzhandler') && isset($GLOBALS['cfg']['isGzipEnable']) && $GLOBALS['cfg']['isGzipEnable']){
	        ob_start('ob_gzhandler');
	    } else {
	        ob_start();
	    }	
	}
}