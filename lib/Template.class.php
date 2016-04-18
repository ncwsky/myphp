<?php
//判断是否定义了路径，如果没有定义，退出程序
!defined('MY_PATH') && exit();	//或提示 exit('未定义路径')
//模板解析基类 解析模板文件并判断是否需要在缓存目录生成缓存文件
class Template{
	private static $instance = NULL;	//模板实例
	public $templatePath = '';	//模板文件路径
	public $cachePath = '';	//缓存文件存入路径
	public $cache = FALSE;	//是否开户缓存
	public $cacheLifeTime = 0;	//缓存文件更新时间
	public $templateSuffix = '.html';	//模板文件后缀名
	public $leftTag = '{';	//模板左边界符号
	public $rightTag = '}';	//模板右边界符号
	public $tVars = array();	//模板变量
	private $templateFile = '';	//当前模板文件名
	private $cacheFile = '';	//当前缓存文件名
	private $level = 0, $maxLevel = 0, $limitLevel = 3; //模板嵌套层次 层次深度 限制层次深度
	private $dir = array('level' => array(), 'file' => array()); //模板嵌套 模板层次关系 模板内容 存放数组
	//私有的构造函数，不允许直接创建对象
	private function __construct(){}
	//初始化模板文件夹以及缓存文件完整路径
	private function InitFilePath($file){
		$this->templateFile = $this->templatePath . $file;
		$this->cacheFile = $this->cachePath . str_replace(array('/','.'),array('_','_'),$file) . '.php';//md5($file)
	}
	//获取模板类实例
	public static function GetInstance(){
		if(is_null(self::$instance)){
			self::$instance = new Template();
		}
		return self::$instance;
	}
	//返回模板处理后的缓存文件
	public function cachefile($file){
		$this->tmp_process($file);
		return $this->cacheFile;
	}
	//显示模板、返回模板内容
	public function display($file, $data = array()){//, $display = TRUE
		$this->tmp_process($file);
		$this->tVars = $data;
		//将模板变量数组，导出为变量
		extract($this->tVars);
		//载入模板缓存文件
		ob_start();
		include $this->cacheFile;
		//if(!$display){
			$data = ob_get_clean();
			return $data;
		//}
	}
	//模板处理
	private function tmp_process($file){
		$this->cacheLifeTime *= 60;
		$this->InitFilePath($file);
		//验证是否需要更新缓存
		if(!$this->checkCache()){
			$this->analyze();
			$this->build();
		}
	}
	//模板样式 图片路径替换 可设指定路径
	private function resetPath(&$content){
		$path = $GLOBALS['cfg']['app_res_path']; 
		// $patt = '(link|img)(\s*?)(src=|href=)\"(?!http://|\/)(.*?)\"';
    	$content = preg_replace("/(link|img|script)(\s*?)(src=|href=)\"(?!http:\/\/|\/|<)(.*?)\"/i", "$1$2$3\"$path$4\"", $content);
	}
	//组合经　analyze　解析后的模板内容
	private function build(){
		//生成模板缓存嵌套记录数组文件
		$content = '<?php exit;//' . serialize($this->dir['level']);
		file_put_contents(strtr($this->cacheFile, array('.php' => '_.php')), $content);

		$content = ''; // "<?php !defined('MY_PATH') && exit; /* 生成时间：" . date('Y-m-d H:i:s', time()) . " */ ? >\r\n";
		//'从最次层开始
		for($this->maxLevel; $this->maxLevel>0; $this->maxLevel--) {
			//'读取当前层次的页面列表 遍历
			$fileArr = explode(',', $this->dir['level'][$this->maxLevel]);

			foreach($fileArr as $filelist) {
				$files = explode('->', $filelist);
				//'如果存在上级关系 为上级完成替换
				if (!empty($files[0])) {
					$s1 = $this->dir['file'][$files[0]];
					$s2 = $this->dir['file'][$files[1]];

					$s1 = str_replace('{include:'. $files[1] .'}' , "\n $s2 \n", $s1); //'替换包含页面内容
					$this->dir['file'][$files[0]] = $s1; //'重新植入
				} else {
					$content .= $this->dir['file'][$files[1]];
				}
			}
		}
		$this->resetPath($content);
		return file_put_contents($this->cacheFile, $content);
	}
	//模板文件分析  $fatherPath 当前页面的上级路径 $dofile 待解析模板页面
	private function analyze($fatherPath = '', $dofile = '') {
		$this->level++;	//层次
		if($this->level > $this->maxLevel) $this->maxLevel = $this->level; //设置最深层次
		//如果dofile为空表示是模板入口页面
		if($dofile == '') $dofile = $this->templateFile; 
		$content = $this->doTmp($dofile); //获取模板解析后的内容
		$includeVal = $this->doInclude($content); //获取模板中的包含页面
		//取当前模板 关键数组的键名 keyname
		if($this->level == 1) {
			$keyname = $this->templateFile;
		} else {
			$keyname = $dofile;
		}
		//层次记录检查并记录 返回true就表示当前层次模板已经存在并记录了
		$hasLevel = FALSE; //'层次记录是否已经存在当前页面层次记录标记
		$levelVal = $fatherPath . "->" . $keyname;
		if (!empty($this->dir['level'][$this->level])) {//'判断是否存在此层次记录
			$tmpLevelVal = $this->dir['level'][$this->level]; //'获取此层的层次记录
			if (strstr(','. $tmpLevelVal .',', ','. $levelVal .',')) {
				$hasLevel = TRUE;
			} else {
				$levelVal .= "," . $tmpLevelVal;
				$this->dir['level'][$this->level] = $levelVal; //'重新设置记录关系
			}
		} else {
			$this->dir['level'][$this->level] = $levelVal; //'记录关系
		}
		//获取当递归调用时页面的上级路径
		$fatherPath = $keyname;
		if (!$hasLevel){
			//'当前模板页面解析内容存入关键数组中 此处需要重复模板验证（完整路径方式）
			if (empty($this->dir['file'][$keyname])) $this->dir['file'][$keyname] = $content;
			
			//'当前模板包含页面
			if($includeVal != '') {
				$arrfile = explode(',', $includeVal);
				//遍历包含页面	
				foreach($arrfile as $file) {
					$this->analyze($fatherPath, $file); //'递归模板分析
				}
				$fatherPath = ''; //' 重置
			}
		}
		$this->level--;	//层次上下关系递归递减
	}
	//处理模板文件
	private function doTmp($tmpfile){
		$content = file_get_contents($tmpfile);
		//替换系统常量
		$patt = array('__ROOT_DIR__','__PUBLIC__', '__ACTION__', '__CONTROL__', '__ROOT__', '__APP_ROOT__', '__APP__', '__URL__');
		$replace = array('<?php echo ROOT_DIR; ?>','<?php echo PUB; ?>', '<?php echo ACTION; ?>', '<?php echo CONTROL; ?>', '<?php echo ROOT; ?>', '<?php echo APP_ROOT; ?>', '<?php echo APP; ?>', '<?php echo URL; ?>');
		$content = str_replace($patt, $replace, $content);
		$patt = '(' . $this->leftTag . ')(\S.+?)(' . $this->rightTag . ')';
		$content = preg_replace("/{$patt}/eis", "\$this->ParseTag('\\2')", $content); //i 不区分大小写 s .匹配所有的字符，包括换行符 e PHP代码求值并替换

		return $content;
	}
	//包含页面检查 按引用方式传递 正则查找 区分大小写（如果不区分大小写加i)
	private function doInclude(&$data) {
		$files = ''; //包含页面存放变量
		/*兼容 {include:file} 模式*/
		preg_match_all('/{include:(\.\.\/)*(.+?)}/', $data, $arr);
		if(!empty($arr[0])) {
		//if (count($arr[0])>0){
			foreach($arr[0] as $k => $v) {
				$file = $this->transPath($arr[1][$k] . $arr[2][$k]);

				$data = str_replace($v , '{include:'. $file .'}', $data);
				$files = $files == '' ? $file : $files .','. $file;
			}
		}
		unset($arr);
		//<?php include模式
		preg_match_all('/<\?php\s+include\s*\(?["\'](\.\.\/)*(.+?)["\']\)?;\s*\?>/', $data, $arr);
		if(!empty($arr[0])) {
			foreach($arr[0] as $k => $v) {
				$file = $this->transPath($arr[1][$k] . $arr[2][$k]);

				$data = str_replace($v , '{include:'. $file .'}', $data);
				$files = $files == '' ? $file : $files .','. $file;
			}
		}
		unset($arr);
		return $files;
	}
	//模板地址转换
	public function transPath($tagfile) {
		//此处需要判断是否需要默认目录
		if (substr($tagfile, 0, 1) =='/') // '表示根目录 其他非默认模板目录的页面
			$tofile = $tagfile;
		else
			$tofile = $this->templatePath . $tagfile;
		return $tofile;
	}
	//验证缓存是否有效
	private function checkCache(){
		if(!file_exists($this->cacheFile)) return FALSE;

		//获取模板缓存嵌套记录数组
		$content = file_get_contents(strtr($this->cacheFile, array('.php' => '_.php')));
		$dirLevel = unserialize(substr($content,13));
		$t = array();
		if(!empty($dirLevel)) {
			foreach($dirLevel as $val) {
				//'读取当前层次的页面列表 遍历
				$fileArr = explode(',', $val);
	
				foreach($fileArr as $filelist) {
					$files = explode('->', $filelist);
					$t[] = filemtime($files[1]);//获取各页面的修改时间
				}	
			}
			rsort($t);//对修改时间降序
			unset($dirLevel);
		}
		$cacheFileMtime = filemtime($this->cacheFile) ;
		if($cacheFileMtime < $t[0] && $this->cache) {
			unset($t);
			return FALSE;
		}
		if($this->cacheLifeTime && $cacheFileMtime + $this->cacheLifeTime< time() && $this->cache){
			return FALSE;
		}
		return TRUE;
	}
	//解析{}中的内容，根据第一个字符决定使用什么函数进行解析
	private function ParseTag($label){
		$label = stripslashes(trim($label));
		$flag = substr($label, 0, 1);
		$flags = array('php'=>'~', 'var' => '$', 'language' => '@', 'config' => '#', 'cookie' => '+', 'session' => '-', 'get' => '%', 'post' => '&', 'constant' => '*');
		$name = substr($label, 1);//排除标识符
		static $n_level = -1;//循环统计标识名层级
		static $n_tag = array();//循环统计标识名
		
		//直接php语句
		if($flag == $flags['php']){
			return '<?php '.$name.' ?>';
		}
		//普通变量
		if($flag == $flags['var']){
			return !empty($name) ? $this->ParseVar($name) : NULL;
		}
		//输出语言
		if($flag == $flags['language']){
			return '<?php echo(GetL(\''.$name.'\')); ?>';
		}
		//输出配置信息
		if($flag == $flags['config']){
			return '<?php echo(GetC(\''.$name.'\')); ?>';
		}
		//输出Cookie
		if($flag == $flags['cookie']){
			return '<?php echo(cookie(\''.$name.'\')); ?>';
		}
		//输出Session
		if($flag == $flags['session']){
			return '<?php echo(\$_SESSION[\''.$name.'\']); ?>';
		}
		//输出get
		if($flag == $flags['get']){
			return '<?php echo(\$_GET[\''.$name.'\']); ?>';
		}
		//输出post
		if($flag == $flags['post']){
			return '<?php echo($_POST[\''.$name.'\']); ?>';
		}
		//输出常量
		if($flag == $flags['constant']){
			return '<?php echo('.$name.'); ?>';
		}
		//语句结束部分 list -> foreach结束
		if($flag == '/'){
			if($name == 'list') {
				$ix = $n_level--;
				return '<?php $'.$n_tag[$ix].'++;}unset($'.$n_tag[$ix].'); ?>';
			}else{
				return '<?php end'.$name.'; ?>';
			}
		}
		//foreach开始
		if(substr($label, 0, 4) == 'list'){
			preg_match_all('/\\$([\w->]+)/', $label, $arr);
			$arr = $arr[1];
			if(count($arr) > 0){
				$n_level++;
				$n_tag[$n_level] = 'n';
				$key_name = '$key';
				if(!isset($arr[1])) {
					$arr[1] = 'data';
				}else{
					$n_tag[$n_level] ='n_'.$arr[1];//n_数据别名
					$key_name = '$key_'.$arr[1];
				}
				return '<?php $'.$n_tag[$n_level].'=1;if(is_array($'.$arr[0].'))  foreach($'.$arr[0].' as '.$key_name.'=>$'.$arr[1].') { ?>';
			}
		}
		//if elseif
		if(substr($label, 0, 2) == 'if' || substr($label, 0, 6) == 'elseif'){
			$arr = explode(' ',$name);
			array_shift($arr);
			$param = array();
			foreach($arr as $v){
				if(strpos($v, '.') > 0){
					$args = explode('.', $v);
					$p = $this->ArrayHandler($args, 1);
					$param[] = $args[0] . $p;
				}else{
					$param[] = $v;
				}
			}
			$str = join(' ', $param);
			$tag = substr($label, 0, 2) == 'if' ? 'if' : 'elseif';
			return '<?php '.$tag.'('.$str.') : ?>';
		} 
		//else
		if(substr($label, 0, 4) ==  'else'){
			return '<?php else :?>';
		}
		return trim($this->leftTag, '\\') . $label . trim($this->rightTag, '\\');
	}
	//解析变量
	private function ParseVar($varStr){
		static $tVars = array();
		if(isset($tVars[$varStr])) return $tVars[$varStr]; //存在相同的变量将直接返回
		//以=分割，数组第一位是变量名，之后的都是函数及参数  示例：row = getfolder($id)
		$varArray = explode('=', $varStr);
		$var = array_shift($varArray);//弹出第一个元素，也就是变量名
		/*  变量处理 start */
		if(substr($varStr, 0, 2) == 'T.'){//系统变量
			$name = $this->ParseT($var);
		}elseif(strpos($var, '.')){	//$var.xxx方式访问数组或属性
			$vars = explode('.', $var);
			$name = 'is_array($' . $vars[0] . ') ? $' . $vars[0] . '["' . $vars[1] . '"] : $' . $vars[0] . '->' . $vars[1];
		}else{
			$name = '$'.$var;
		}
		/*  变量处理 end  */
		if(count($varArray) > 0){//如果有使用函数
			//传入变量名，和函数参数继续解析，这里的变量名是上面的判断设置的值
			$name = $this->ParseFunction($name, $varArray); //多分函数支持 = 分隔 从左到右
			$code = !empty($name) ? '<?php '. $name .' ?>' : '';
		}else{
			$code = !empty($name) ? '<?php echo('. $name .'); ?>' : '';
		}
		$tVars[$varStr] = $code;//记录模板变量
		return $code;
	}
	/**
	 * 解析系统变量$T开头的
	 * 示例：$T.version 、$T.config.db.type 、 $T.lang.ab 、$T.GET
	 */
	private function ParseT($var){
		$vars = explode('.', $var);
		$vars[1] = strtoupper(trim($vars[1]));
		$len = count($vars);
		if($len >= 3){
			if(strpos(',COOKIE,SESSION,GET,POST,SERVER,', ','.$vars[1].',')!==FALSE ){
				//替换调名称，并将使用ArrayHandler函数获取下标，支持多维
				$code = '$_'. $vars[1] . $this->ArrayHandler($vars);
			}elseif($vars[1] == 'CONFIG' || $vars[1] == 'LANG'){//这里替换为函数
				if($len==4) $vars[2] = $vars[2].'.'.$vars[3];//支持二维
				$key = substr($vars[1], 0, 1);
				$code = 'Get'.$key.'(\''. $vars[2] .'\')';
			}else{
				$code = '';
			}
		}elseif($len == 2){
			if($vars[1] == 'NOW'){
				$code = "date('Y-m-d H:i:s', time())";
			}elseif($vars[1] == 'VERSION'){
				$code = 'VERSION';
			}else{
				$code = '';
			}
		}
		unset($vars);
		return $code;
	}
	//解析函数
	private function ParseFunction($name, $varArray){
		$code = '';
		$len = count($varArray);
		// 获取不允许使用的函数
		$not_fun = $GLOBALS['cfg']['tmp_not_allow_fun'];
		for($i = 0; $i < $len; $i++){
			//以=分割函数参数，第一个元素就是函数名，之后的都是参数
			$arr = explode('|', $varArray[$i]);
			$funcName = array_shift($arr);//函数名
			$arr = array_shift($arr);//函数参数
			if(strpos( $not_fun, ','.$funcName.',')===FALSE){//不允许使用的函数判断
				$args = explode(',', $arr);
				$param = '';
				if(count($arr)>0){//参数不为空   字符的参数需要加上单引号
					foreach($args as $var){
						$var = trim($var);
						if($var == 'this') $var = $name;
						$param = $param=='' ? $var : $param.', '.$var;
					}
				}
				if($funcName=='echo'){
					$code .= $funcName.'('. $name .');';
				}else{
					$code .= $name.'='.$funcName.'('.$param.');';
				}
			}
		}
		return $code;
	}
	//构造数组下标
	private function ArrayHandler(&$arr, $go = 2){
		$len = count($arr);
		$param = '';
		for($i = $go; $i < $len; $i++){
			$param .= "['{$arr[$i]}']";
		}
		return $param;
	}
}