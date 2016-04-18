<?php
//入口类
class myphp{
	//运行程序
	public function Run(){
		$this->Analysis();	//开始解析URL获得请求的控制器和方法
		MODULE=='' && $this->init_app();
		
		$this->Auth(); //权限验证处理
		//log处理
		if(CONTROL!='log' && $GLOBALS['cfg']['log_on']){
			$auth_model = $GLOBALS['cfg']['auth_model'];
			if($auth_model=='user') Log::sys_log('run','<b>'. $GLOBALS['auth']->doUserName .'</b>：'.URL);
			elseif($auth_model=='member') Log::sys_log('run','<b>'. $GLOBALS['auth']->user .'</b>：'.URL);
			else Log::sys_log('run',URL);
		}//
		$control = ucwords($_GET['c']).'Act';	//获得控制器名  将控制器名称中每个单词首字母大写，来当作控制器的类名
		$action = $_GET['a'];	//获得方法名
		$controlFile = CONTROL_PATH . $control . '.class.php';	//构造控制器文件的路径
		//判断文件是否存在，如果不存在提示错误
		if(!file_exists($controlFile)){
			header("HTTP/1.1 404 Not Found");
			exit('控制器不存在' . $controlFile);
		}
		include $controlFile;	//引入控制器文件
		$class = $control;	
		//判断类是否存在，如果不存在则提示错误
		if(!class_exists($class,FALSE)){
			header("HTTP/1.1 404 Not Found");
			exit('未定义的控制器类' . $class);
		}
		$instance = new $class();	//创建类的实例
		//判断实例$instance中是否存在action方法，不存在则提示错误
		if(!method_exists($instance, $action)){
			header("HTTP/1.1 404 Not Found");
			exit('不存在的方法' . $action);
		}

		$instance->$action();	//启动实例方法
	}
	/*
	url模式：分隔符 "-"
		0、http://localhost/index.php?c=控制器&a=方法
		1、http://localhost/index.php?do=控制器-方法-id-1-page-1
		2、http://localhost/index.php/控制器-方法-其他参数
	*/
	//解析URL获得控制器的与方法
	protected function Analysis(){
		$script_name = $_SERVER["SCRIPT_NAME"];//获取当前文件的路径
		$url_mode = isset($GLOBALS['cfg']['url_mode'])?$GLOBALS['cfg']['url_mode']:-1;
		// 简单 url 映射  //仅支持映射到普通url模式
		if(isset($GLOBALS['cfg']['url_maps'])){
			UrlRoute::run();
		}

		$_app = $_url = '';
		defined('__URI__') || define('__URI__', APP_ROOT . '/' . basename($script_name));//当前URL路径
		if($url_mode == 1){
			$_url = $_app = __URI__ .'?do=';
			$do = isset($_GET['do']) ? trim($_GET['do']) : '';	//获得执行参数
			$this->parseUrl($do);
		}elseif($url_mode == 2){	//如果Url模式为2，那么就是使用PATH_INFO模式
			$_url = $_app = __URI__ .'/';
			$url = $_SERVER["REQUEST_URI"];//获取完整的路径，包含"?"之后的字
			//去除url包含的当前文件的路径信息
			if ( strpos($url,__URI__,0) !== false ){
				$url = substr($url, strlen(__URI__));
			} else { //伪静态时去除
				$script_name = str_replace(basename(__URI__), '', __URI__);
				if ( strpos($url, $script_name, 0) !== false ){
					$url = substr($url, strlen($script_name));
				}
			}
			
			//去除?处理
			$pos = strpos($url,'?');
			if($pos!==false)
				$url = substr($url, 0, $pos);
			$url = trim($url, '/');
			$this->parseUrl($url);
		}else{//0
			$_app = __URI__;
			$_url = $_app.'?c=';
		}
		//控制器和方法是否为空，为空则使用默认
		$_GET['c'] = !empty($_GET['c']) ? $_GET['c'] : $GLOBALS['cfg']['default_control'];
		$_GET['a'] = !empty($_GET['a']) ? $_GET['a'] : $GLOBALS['cfg']['default_action'];
		
		define('CONTROL', $_GET['c']);
		define('ACTION', $_GET['a']);
		define('__APP__', $_app);//相对当前地址的应用入口
		define('__URL__', $_url.CONTROL);//相对当前地址的url控制
		define('APP', __APP__);
		define('URL', __URL__);
		define('MODULE', isset($_GET['m'])?$_GET['m']:'');
		//指定项目模块
		$module_path = APP_PATH;
		if(MODULE!=''){
			if(isset($GLOBALS['cfg']['module_maps'][MODULE])){
				$module_path = substr($GLOBALS['cfg']['module_maps'][MODULE],0,1)=='/'?ROOT.ROOT_DIR.$GLOBALS['cfg']['module_maps'][MODULE]:APP_PATH.'/'.$GLOBALS['cfg']['module_maps'][MODULE];
			}else{
				$module_path = APP_PATH.'/'.MODULE;
			}
		}
		
		define('MODULE_PATH', $module_path);
		//引入模块配置
		is_file(MODULE_PATH.'/config.php') && $GLOBALS['cfg']=array_merge($GLOBALS['cfg'], require MODULE_PATH.'/config.php');
		//路径
		define('CACHE_PATH', MODULE_PATH .'/'. CACHE_DIR .'/');
		define('CONTROL_PATH', MODULE_PATH .'/'. CONTROL_DIR .'/');
		define('MODEL_PATH', MODULE_PATH .'/'. MODEL_DIR .'/');
		define('VIEW_PATH', MODULE_PATH .'/'. VIEW_DIR .'/');
		define('LANG_PATH', MODULE_PATH .'/'. LANG_DIR .'/');
		define('LOG_PATH', MODULE_PATH .'/'. LOG_DIR.'/');
	}
	//网址解析生成
	protected function parseUrl($url = ''){
		if($url=='' || isset($_GET['c']) || isset($_GET['c'])) return;
		$url_para_str = $GLOBALS['cfg']['url_para_str'];
		$paths = explode($url_para_str, $url);	//分离路径
		$_GET['c'] = array_shift($paths);	//获得控制器名
		$_GET['a'] = array_shift($paths);	//获得方法名
		//获取参数设置到get中
		if(!empty($paths)) {
			$param=$paths;
			$param_count=count($param);
			for($i=0; $i<$param_count;$i=$i+2) {			
				if(isset($param[$i+1]) && !is_numeric($param[$i])) {//预防最后个参数没有获取值。
					$_REQUEST[$param[$i]] = $_GET[$param[$i]]=$param[$i+1];
				}
			}
		}
	}
	// 权限验证处理 在config.php配置中设置开启
	public static function Auth($auth_on=FALSE){
		if($GLOBALS['cfg']['auth_on']===FALSE && !$auth_on) return;
		$auth_class = $GLOBALS['cfg']['auth_model'];
		$auth_action = $GLOBALS['cfg']['auth_action'];
		$auth_login = $GLOBALS['cfg']['auth_login'];
		$auth = new $auth_class();	//创建类的实例
		$GLOBALS['auth'] = $auth;//把验证类设为全局
		//无需验证模板
		if(strpos( $GLOBALS['cfg']['auth_model_not'] , ','.CONTROL.',')!==FALSE){
			if($GLOBALS['cfg']['auth_model_action']=='') return;
			//验证此模板中需要验证的动作
			if(strpos( $GLOBALS['cfg']['auth_model_action'] , ','.CONTROL.'::'.ACTION.',')===FALSE){
				return;	
			}
		}
		//无需验证方法
		if(strpos( $GLOBALS['cfg']['auth_action_not'] , ','.CONTROL.'::'.ACTION.',')!==FALSE){
			return;
		}
		//仅登陆验证
		if(strpos( $GLOBALS['cfg']['auth_login_model'] , ','.CONTROL.',')!==FALSE || strpos( $GLOBALS['cfg']['auth_login_action'] , ','.CONTROL.'::'.ACTION.',')!==FALSE){
			method_exists($auth, $auth_login) or exit('验证方法不存在!' . $auth_login);
			if(!$auth->$auth_login()) {
				if($_GET['c']=='index') redirect(ROOT_DIR .$GLOBALS['cfg']['auth_gateway']);
				else ShowMsg('你未登录,请先登录!', ROOT_DIR .$GLOBALS['cfg']['auth_gateway']);
				exit;
			}
			//验证此模块中需要验证的动作
			if($GLOBALS['cfg']['auth_login_M_A']=='') return;
			if(strpos( $GLOBALS['cfg']['auth_login_M_A'] , ','.CONTROL.'::'.ACTION.',')===FALSE){
				return;	
			}
		}
		method_exists($auth, $auth_action) or exit('验证方法不存在!' . $auth_action);
		$auth->$auth_action();	//启动验证方法
	}
	// app项目初始化
	private function init_app(){
		if(!is_file(MODULE_PATH .'/index.htm')) {// 生成项目目录
			// 创建项目目录
			if(!is_dir(MODULE_PATH)) mkdir(MODULE_PATH,0777);
			$dirs  = array(
				CACHE_PATH,
				CONTROL_PATH,
				MODEL_PATH,
				VIEW_PATH,
				LANG_PATH
			);
			foreach ($dirs as $dir){
				if(!is_dir($dir))  mkdir($dir,0777);
			}
			// 写入测试Action
			if(!is_file(CONTROL_PATH.'IndexAct.class.php')){
				file_put_contents(MODULE_PATH.'/index.htm', 'dir');
				$issite = isset($_GET['issite'])?intval($_GET['issite']):0;
				if($issite==0){//前端站点判断
					$content = file_get_contents(MY_PATH.'/'.'Base.class.tpl');
				}else{
					$content = file_get_contents(MY_PATH.'/'.'Base_Site.class.tpl');
				}
				file_put_contents(CONTROL_PATH.'Base.class.php',$content);
				
				$content = file_get_contents(MY_PATH.'/'.'IndexAct.class.tpl');
				file_put_contents(CONTROL_PATH.'IndexAct.class.php',$content);
				
				$content = file_get_contents(MY_PATH.'/'.'index.tpl');
				file_put_contents(VIEW_PATH.'index.html',$content);
				
				$content = file_get_contents(MY_PATH.'/'.'Mydb.class.tpl');
				file_put_contents(MODEL_PATH.'Mydb.class.php',$content);
			}
			// 生成项目配置
			$runconfig = MODULE_PATH .'/config.php';
			if(!is_file($runconfig)) 
				file_put_contents($runconfig, file_get_contents(MY_PATH.'/'.'config.tpl'));
		}
	}
}
//控制器基类，所有的控制器需要继承此类
class Control{
	//定义一个视图类实例
	protected $view = NULL;
	//构造方法，实例化视图
	function __construct(){
		$this->view = View::getInstance();
	}
	//设置模板变量
	public function assign($var, $value){
		$this->view->assign($var, $value);
	}
	//在子类控制器及方法中调用 显示模板 
	public function display($file=''){
		$this->view->display($file);
	}
	//在子类控制器及方法中调用模型
	public static function LoadModel($name){
		static $_model  = array();
		$name = ucwords($name).'Db';//获得模型名  将模型名称中每个单词首字母大写，来当作模型的类名
		if (!isset($_model[$name])){
			$modelFile = MODEL_PATH . $name . '.class.php';	//构造模型文件路径
			!file_exists($modelFile) && exit('模型' . $name . '不存在');	//如果模型不存在提示错误
			
			$class = $name;	//获得模型类名
			if(!class_exists($class,FALSE)){//不存在则引入
				include $modelFile;	
			}
			!class_exists($class,FALSE) && exit('模型' . $name . '未定义');	//判断是否定义了模型类，如果没有则提示错误
	
			$_model[$name] = new $class();	//实例化模型类 
		}
		return $_model[$name];	//返回实例
	}
	//实例化一个模型基类 ：数据库配置数组名称
	public static function M($name='db'){
		return M($name); //返回实例
	}
	/**
	 * 加载函数库
	 * @param string $func 函数库名
	 * @param string $path 地址
	 */
	public static function load_func($func, $path = '') {
		return load_func($func, $path);
	}
	/**
	 * 加载类文件函数
	 * @param string $classname 类名
	 * @param string $path 扩展地址
	 * @param intger $initialize 是否初始化
	 */
	public static function load_class($classname, $path = '', $initialize = 1) {
		return load_class($classname, $path, $initialize);
	}
	/**
	 * 加载其他类文件函数
	 * @param string $path 类地址
	 * @param string $classname 指定类名并初始化
	 */
	public static function load_other_class($path, $classname='') {
		return load_other_class($path, $classname);
	}
}
//异常类
class myException extends Exception
{
}
//url路由器
class UrlRoute{
	private $para_str; // 分隔符 -
	public function __construct(){
		$this->para_str = isset($GLOBALS['cfg']['url_para_str'])?$GLOBALS['cfg']['url_para_str']:'-';
	}
	static public function run(){
		/*
        url映射(无)：直接原样url分析
        url映射(有)：
            1、静态url	如：/ask=> /index.php?a=ask(直接返回地址) | /ask=> ask | index/ask 解析成对应的执行url
                    'news'=>'info/lists?id=7', // 解析-> /index.php?c=info&a=lists&id=7
            2、动态url	如：<参数1>[<可选参数>]; [正则]->[*]特殊情况下使用 不支持‘.<>[]’
                'news-<id\d>[-<page\d>]'=>'info/lists?<id>[&<page>]' 解析-> /index.php?c=info&a=lists&id=7&page=$2
		*/
		if(empty($GLOBALS['cfg']['url_maps'])) return false;
		//echo $_SERVER["REQUEST_URI"].'<br>';
		$url = rtrim($_SERVER["REQUEST_URI"], '/');
		if(ROOT_DIR!='') 
			$url = str_replace(ROOT_DIR,'',$url);
		$uri = $mac = $para = '';
		if(isset($GLOBALS['cfg']['url_maps'][$url])){ //静态url
			$uri = $GLOBALS['cfg']['url_maps'][$url];
		}else{ //动态url '/news/<id>[-<page>][-<pl>]'=>'info/lists?<id>[&<page>][&<pl>]',
			foreach($GLOBALS['cfg']['url_maps'] as $k=>$v){
				$reg_match = false;
                if(strpos($k,'[')){ //可选参数或特殊regx模式
					$k = str_replace(array('[',']'),array('(',')?'),$k);
					$reg_match = true;
				}
				if($pos=strpos($k,'<')){ //是动态执行分析
                    $reg_match = true; $vars = array(); //解析变量数组
					do{
						$end = strpos($k,'>',$pos);
						$var = $__var = substr($k,$pos+1,$end-$pos-1);
						$regx='(\w+)'; //字符数字下划线
						if($depr=strpos($__var,'\\')){
							$type = substr($__var,-1);
							$var = substr($__var,0,$depr);
							if($type=='d'){ //仅数字
								$regx='(\d+)';
							}elseif($type=='s'){ //仅字母
								$regx='([A-Za-z]+)';
							}elseif($type=='a'){ //字符数字下划线等号
								$regx='([\w=]+)';
							}
						}
						$vars[$var]=1;
						if(substr($k,$end+1,2)==')?')//可选 "]"->")?"
							$vars[$var]=0;
						$k = str_replace('<'.$__var.'>',$regx,$k);
						$pos=strpos($k,'<',$pos);
					} while ($pos);
				}
				
				if($reg_match){
					$k = str_replace(array('.','/'),array('\.','\/'),$k);
					//Log::trace($k.'|||'.$url);
					if (preg_match ('/^'.$k.'$/i', $url, $regArr)) {
                        if(isset($vars)){
                          $count = count($regArr)-1; $i=1; //var_dump($regArr);
                          foreach($vars as $_k=>$_v){
                            if($_v==1) $vars[$_k]=$regArr[$i];
                            else $vars[$_k]=$regArr[++$i]; //可选 因是双括号匹配 目标索引得加1
                            if(++$i>$count) break;
                          }
                          //$_GET = $vars;
                          $_GET = array_merge($_GET, $vars);
                          unset($regArr, $vars);
                        }
						//var_dump($_GET);//Log::trace(json_encode($_GET));
						$uri = $v;
						break;
					}
				}
			}
		}
		//echo $uri;
		if($uri=='') return false;
		//解析获取实际执行的地址
		if(substr($uri,0,1)=='/'){ //静态url /index.php?a=ask | /pub/index.php?a=ask(待实现)
			$pos = strpos($uri,'?');
			if($pos!==FALSE) {// para获取
				$script_name = substr($uri, 0, $pos);
				$para = substr($uri,$pos+1);
			}else{
				$script_name = $uri;
			}
		}elseif($pos = strpos($uri,'?')){ // index/ask?id=1
			$mac = substr($uri,0,$pos);
			$para = substr($uri,$pos+1);
		}else{ // ask | index/ask | pub/index/ask
			$mac = $uri;
		}
		// url_mode 2 $_SERVER["REQUEST_URI"] = ROOT_DIR.$uri;
		// if($script_name=='/') $script_name .='index.php';
		
		if(isset($script_name) && $script_name!=$_SERVER["SCRIPT_NAME"]){
			if(substr_count($script_name,'/')>1){
				$_SERVER["SCRIPT_NAME"] = substr($script_name,-1)=='/'?$script_name.'index.php':$script_name;
				$_GET['m'] = md5($script_name);
				$GLOBALS['cfg']['module_maps'][$_GET['m']]=substr($script_name,0,strrpos($script_name,'/')).'/app';
			}
			//var_dump($GLOBALS['cfg']['module_maps']);
		}
		define('__URI__', $_SERVER["SCRIPT_NAME"]);//当前URL路径  //APP_ROOT . '/' . basename($script_name)
		//echo __URI__.'<br>';
		//echo $script_name.'<br>';
		// echo $mac.'===='.$para;
		
		if($para!=''){
			parse_str($para,$get);
			$_GET = array_merge($_GET,$get);
		}
		if($mac!=''){//分解m a c
			if($pos = strpos($mac,'/')){
				$path = explode('/',$mac);
				$_GET['a'] = array_pop($path);
				$_GET['c'] = array_pop($path);
				if(!empty($path)) $_GET['m'] = array_pop($path);
			}else{
				$_GET['a'] = $mac;
			}
		}
		//var_dump($_GET);//exit;
		return true;
	}
/*	
	url反转时 使用静态变量存放 v 记录 如 info/lists?id=7 第二次调用时就要以array来验证
	伪静态模式：
	'/wydz'=>'/index.php?c=do&a=wydz',
	'/kjzc'=>'do/kjzc', // 
	正常模式： /index.php/news  , /index.php/news-1-9
	'news'=>'info/lists?id=7', // -> /index.php?c=info&a=lists&id=7
	'news-<id\d>[-<page\d>]'=>'info/lists?<id>[&<page>]' -> /index.php?c=info&a=lists&id=7&page=$2
	
	
	规则：字母、下划线、数字   /news-1     /news-1-9
	默认情况：\w  指定数字：\d	指定字母 \s -> [A-Za-z]
	/news-<id\d>[-<page\d>] -> /new-(\d+?)(-\d+)?
*/
	// 反转url	如：info/lists?id=7 -> /news
	// url模式:url_maps_k, url实际参数
	static public function reverse_url($k,$vars){
		if($pos=strpos($k,'<')){ //是动态执行分析
			do{
				$end = strpos($k,'>',$pos);
				$var = $__var = substr($k,$pos+1,$end-$pos-1);
				if($depr=strpos($__var,'\\'))
					$var = substr($__var,0,$depr);
				if(!isset($vars[$var]))
					$vars[$var] = null;

				if(substr($k,$end+1,1)==']'){//可选
					$pos = strpos($k,'[');
					$end = strpos($k,']');
					if($vars[$var]==null){
						$k = substr($k,0,$pos).substr($k,$end+1);
					}else{
						$k = substr($k,0,$pos).substr($k,$pos+1,$end-$pos-1).substr($k,$end+1);
					}
				}
				$k = str_replace('<'.$__var.'>',$vars[$var],$k);
				$pos=strpos($k,'<',$pos);
			} while ($pos);
			if(strpos($k,'['))
				$k = str_replace(array('[',']'),'',$k);
		}
		return $k;
	}
/*
url解析重写： 模块/控制器/方法?参数1=值1&....[#锚点@域名], 附加参数选项（待）
	模块：
		1、设定的模块参数 如： module_maps = array(),
			array('admin'=>'/system');  模块名=>模块（项目）路径  ->  /index.php
			此处会自动的设置项目路径及加载项目配置		配置中未设置 log cache 时，会默认框架的log cache做来设置
		2、实际的访问地址 如 /admin.php
		3、实际的访问路径 如 /pub -> /pub/index.php
		
	GetU('/admin/index/show?b=2&c=4',$option=null)  /index.php/index-show-m-admin-b-2-c-4	此时的项目路径 是/system
	GetU('/admin.php/index/show?b=2&c=4',$option=null)  /admin.php/index-show-b-2-c-4
	GetU('/pub/index/show?b=2&c=4',$option=null)  /pub/index.php/index-show-b-2-c-4
	
	GetU('/index/show?b=2&c=4',$option=null)  /index.php/index-show-b-2-c-4	当前项目 index->show方法
	GetU('/show?b=2&c=4',$option=null) 同上  当前项目 默认控制器/show方法
*/
	//解析url
	public function parse_url($url,$option=null){
		if(strpos('<',$url)!==false){
		parse_str('id=7&page=2',$para); // -->info/lists?id&page
		$_url = 'info/lists?';
		foreach($para as $k=>$v){
			$_url .=$k.'&';
		}
		$_url = substr($_url,0,-1); //-->info/lists?id&page
		}
	}
}