<?php

use myphp\Helper;
use myphp\Log;

final class myphp{
    use MyMsg;
    public static $beforeFun = null; //Control_run之前的前置处理回调 \Closure()
    public static $authFun = null; //验证回调方法 \Closure
    public static $sendFun = null; //自定义输出处理 \Closure($code, $data, $header)
    public static $lang = [];
    public static $env = []; //Run执行时的环境值 array
    public static $header = [];
    public static $statusCode = 200;
    private static $req_cache = null;
    private static $container = []; //容器

    //自动载入配置项
    public static $rootPath = __DIR__;
    public static $classDir = []; //设置可加载的目录 ['dir'=>1,....]
    public static $namespaceMap = []; // ['namespace\\'=>'src/']; //命名空间路径映射 不指定完整路径使用rootPath
    public static $classMap = []; //['myphp'=>__DIR__.'/myphp.php']; //设置指定的类加载 示例 类名[命名空间]=>文件
    public static $classOldSupport = false; //是否兼容xxx.class.php
    //配置处理 start
    public static $cfg = [];
    //获取配置值 支持二维数组
    public static function get($name, $defVal = null){
        if ( false === ($pos = strpos($name, '.')) )
            return isset(self::$cfg[$name]) ? self::$cfg[$name] : $defVal;
        // 二维数组支持
        $name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
        return isset(self::$cfg[$name1][$name2]) ? self::$cfg[$name1][$name2] : $defVal;
    }
    //动态设置配置值
    public static function set($name, $val=null){
        if(is_array($name)) {
            self::$cfg = array_merge(self::$cfg, $name); return;
        }
        if ( false === ($pos = strpos($name, '.')) ){
            self::$cfg[$name]=$val; return;
        }
        // 二维数组支持
        $name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
        self::$cfg[$name1][$name2]=$val;
    }
    //删除配置
    public static function del($name){
        if ( false === ($pos = strpos($name, '.')) ){
            unset(self::$cfg[$name]); return;
        }
        // 二维数组支持
        $name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
        unset(self::$cfg[$name1][$name2]);
    }
    //配置处理 end

    //自动载入 start
    //读取或设置类加载路径
    public static function class_dir($dir)
    {
        //单独设置类加载路径需要写全地址
        if (is_array($dir)) self::$classDir = array_merge(self::$classDir, array_fill_keys($dir, 1));
        else self::$classDir[$dir] = 1;
    }
    //自动加载对象
    public static function autoload($class_name)
    {
        if (isset(self::$classMap[$class_name])) { //优先加载类映射
            include self::$classMap[$class_name];
            return;
        }

        $name = $class_name;
        if ($len = strpos($class_name, '\\')) { //命名空间类加载
            $len += 1;
            $namespace = substr($class_name, 0, $len); //包含尾部\
            if (isset(self::$namespaceMap[$namespace])) { //优先加载命名空间映射
                $end = substr(self::$namespaceMap[$namespace], -1);
                if (self::$namespaceMap[$namespace][0] == '/' || (DIRECTORY_SEPARATOR == '\\' && strpos(self::$namespaceMap[$namespace], ':'))) { //绝对路径 | win
                    $path = self::$namespaceMap[$namespace];
                } else {
                    $path = self::$rootPath . DIRECTORY_SEPARATOR . self::$namespaceMap[$namespace];
                }
                self::load($path . ($end == '/' ? '' : DIRECTORY_SEPARATOR) . strtr(substr($class_name, $len), '\\', DIRECTORY_SEPARATOR) . '.php');
                return;
            }
            self::load(self::$rootPath . DIRECTORY_SEPARATOR . strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php');
            return;
        }

        //遍历可存在类的目录
        foreach (self::$classDir as $path => $i) {
            if (self::load($path . DIRECTORY_SEPARATOR . $name . '.php')) {
                return;
            }
            if (self::$classOldSupport && self::load($path . DIRECTORY_SEPARATOR . $name . '.class.php')) { //兼容处理
                return;
            }
        }
    }
    /**
     * 引入文件
     * @param string $path
     * @return bool
     */
    public static function load($path)
    {
        if (is_file($path)) {
            include $path;
            return true;
        }
        return false;
    }
    //自动载入 end

    // 获取环境变量的值
    public static function env($name, $def = '')
    {
        if(isset(self::$env[$name])){
            return self::$env[$name];
        }
        self::$env[$name] = $def;
        return $def;
    }
    public static function setEnv($name, $val=null){
        if(is_array($name)){
            self::$env = self::$env ? array_merge(self::$env, $name) : $name;
        }else{
            self::$env[$name] = $val;
        }
    }

    /**
     * 运行程序 $isCli 可设置CLI模式下false用于解析数据的参数
     * @param null $sendFun
     * @param bool $isCli
     * @throws \Exception
     */
    public static function Run($sendFun=null, $isCli=IS_CLI){
        self::Analysis($isCli);	//开始解析URL获得请求的控制器和方法
        self::init_app($isCli);
        self::$sendFun = $sendFun;
        try {
            if(self::$beforeFun instanceof \Closure){ //前置处理
                call_user_func(self::$beforeFun);
            }
            if(self::$authFun instanceof \Closure){ //权限验证处理
                call_user_func(self::$authFun);
            }else{
                self::Auth();
            }
            //转驼峰 控制器的类名
            $control = self::$env['app_namespace'] . '\\control\\' . self::$env['CONTROL'];
            if (!class_exists($control)) throw new \Exception('class not exists ' . $control, 404);
            // 请求缓存检查
            if(!self::reqCache(self::$cfg['req_cache'], self::$cfg['req_cache_expire'], self::$cfg['req_cache_except'])){
                /**
                 * @var \myphp\Control $instance
                 */
                $instance = new $control();
                $data = $instance->_run(self::$env['ACTION']);
                null!==$data && self::send($data, self::$statusCode, $instance->req_cache);
            }
        } catch (\Exception $e) {
            $errCode = $e->getCode();
            //匹配状态码时 //$errCode==404 || $errCode==200
            if ($errCode >= 200 && $errCode < 500 && isset(self::$httpCodeStatus[$errCode])) {
                self::send($e->getMessage(), $errCode);
            } else {
                self::send($e->getMessage() . (self::$cfg['debug'] ? "\n" . 'line:' . $e->getLine() . ', file:' . $e->getFile() . "\n" . $e->getTraceAsString() : ''), 500);
                Log::Exception($e, false);
            }
        } catch (\Error $e) {
            self::send($e->getMessage() . (self::$cfg['debug'] ? "\n" . 'line:' . $e->getLine() . ', file:' . $e->getFile() . "\n" . $e->getTraceAsString() : ''), 500);
            Log::Exception($e, false);
        }
        self::$env = [];
        self::$lang = [];
        self::$header = [];
        self::$statusCode = 200;
    }

    /** 输出数据到页面
     * @param $data
     * @param int $code
     * @param null $req_cache //缓存配置[键名,缓存时间]
     * @throws Exception
     */
    public static function send($data, $code=200, $req_cache=null){
        // 监听res_send
        \myphp\Hook::listen('res_send', $data);
        if (200 == $code) {
            if($req_cache===null) $req_cache = self::$req_cache;
            if ($req_cache) {
                self::$header['Cache-Control'] = 'max-age=' . $req_cache[1] . ',must-revalidate';
                self::$header['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';
                self::$header['Expires'] = gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + $req_cache[1]) . ' GMT';
                self::$cfg['cache'] && self::cache()->set($req_cache[0], [$data, self::$header], $req_cache[1]); //缓存内容和输出头
            }
        }
        if(!isset(self::$header['Content-Type'])){
            self::conType(Helper::isAjax() ? 'application/json' : 'text/html'); //默认输出类型设置
        }
        if(self::$sendFun===null){
            if (!IS_CLI) {
                // 发送状态码
                self::httpCode($code); #http_response_code($code); #>=5.4
                // 发送头部信息
                self::sendHeader();
            }
            echo is_string($data) ? $data : Helper::toJson($data);
        }else{
            call_user_func(self::$sendFun, $code, $data, self::$header);
        }
        // 监听res_end
        \myphp\Hook::listen('res_end', $data);
    }

    /**
     * 请求缓存处理
     * @param $req_cache
     * @param null $expire
     * @param array $except
     * @return bool
     * @throws Exception
     */
    private static function reqCache($req_cache, $expire = null, $except = []){
        self::$req_cache = null;
        if(!Helper::isGet()) return false;

        //有客户端缓存
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) + $expire > $_SERVER['REQUEST_TIME'])) {
            self::send('',304);
            return true;
        }
        //有启用缓存
        if (self::$cfg['cache']){
            $reqKey = 'req';
            foreach ($_GET as $v){
                $reqKey .= '_'.str_replace(['\\','/',':','*','?','"','<','>','|'],'',$v);
            }
            //有页面缓存
            if($res = self::cache()->get($reqKey)) {
                if($res[1]) self::setHeader($res[1]);
                self::send($res[0],200);
                return true;
            }
        }
        //不使用缓存
        if (false === $req_cache || false === $expire) return false;

        //使用缓存
        if (true === $req_cache) {
            $ca = '/' . self::$env['c'] . '/' . self::$env['a'];
            foreach ($except as $rule) {
                if (0 === stripos($ca, $rule)) {
                    return false;
                }
            }
            if (!isset($reqKey)) {
                // 缓存key名
                $reqKey = 'req';
                foreach ($_GET as $v) {
                    $reqKey .= '_' . str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $v);
                }
            }
        } elseif ($req_cache instanceof \Closure) {
            $reqKey = call_user_func_array($req_cache, $_GET);
        }
        if (isset($reqKey)) {
            self::$req_cache = array($reqKey, $expire); //记录缓存键名 过期时间 用于send
        }
        return false;
    }
    public static $httpCodeStatus = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        410 => 'Gone',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        423 => 'Locked',
        460 => 'Checksum Mismatch',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
    );
    //http状态输出
    public static function httpCode($code=200){
        if(!isset(self::$httpCodeStatus[$code])) $code=200;
        $msg = self::$httpCodeStatus[$code];
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
        header($protocol.' '.$code.' '.$msg);
    }
    //http头输出
    public static function sendHeader(){
        if(!self::$header) return;
        foreach (self::$header as $name => $val) {
            header($name . ':' . $val);
        }
        self::$header = [];
    }
    //输出头设置
    public static function setHeader($name, $val=null){
        if (is_array($name)) {
            self::$header = array_merge(self::$header, $name);
        } else {
            self::$header[$name] = $val;
        }
    }
    public static function rawBody(){
        if(isset(self::$env['rawBody'])){
            return self::$env['rawBody'];
        }
        self::$env['rawBody'] = file_get_contents("php://input");
        return self::$env['rawBody'];
    }
    public static function setRawBody($rawBody){
        self::setEnv('rawBody', $rawBody);
    }
    //输出类型设置
    public static function conType($conType, $charset = '')
    {
        self::$header['Content-Type'] = $conType . '; charset=' . ($charset ? $charset : self::$cfg['charset']);
    }
    /*
    url模式：分隔符 "-"
        0、http://localhost/index.php?c=控制器&a=方法
        1、http://localhost/index.php?do=控制器-方法-id-1-page-1
        2、http://localhost/index.php/控制器-方法-其他参数
    */
    //解析URL获得控制器的与方法
    public static function Analysis($isCLI = IS_CLI){
        $module_path = IS_WIN ? strtr(APP_PATH, '\\', DS) : APP_PATH;
        //引入app下的配置文件
        self::loadConfig($module_path);

        $basename = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : 'index.php'; //获取当前执行文件名
        $app_root = IS_CLI ? DS : APP_ROOT . DS; //app_url根路径
        $url_mode = isset(self::$cfg['url_mode']) ? self::$cfg['url_mode'] : -1;
        if($isCLI){ //cli模式请求处理
            //cli_url_mode请求模式 默认2 PATH_INFO模式
            $url_mode = self::$cfg['url_mode'] = isset(self::$cfg['cli_url_mode'])?self::$cfg['cli_url_mode']:2;
            if($url_mode == 2){
                $_SERVER["REQUEST_URI"] = implode('/', array_slice($_SERVER['argv'], 1));
            }else{
                parse_str(implode('&', array_slice($_SERVER['argv'], 1)), $_GET);
            }
        }
        // 简单 url 映射  //仅支持映射到普通url模式
        $hasMatch = self::parseUrlMap();

        $uri = $app_root . $basename; //当前URL路径
        //echo 'uri=',$uri,PHP_EOL;
        if ($url_mode == 2) {
            $_url = $_app = (self::$cfg['url_rewrite'] && $uri == self::$cfg['url_index'] ? '' : $uri) . '/';
        } else {
            $_app = $uri;
            $_url = $_app . '?c=';
        }
        if(!$hasMatch && $url_mode == 2){	//如果Url模式为2，那么就是使用PATH_INFO模式
            $url = $_SERVER["REQUEST_URI"];//获取完整的路径，包含"?"之后的字
            //去除url包含的当前文件的路径信息
            if (strpos($url, $uri, 0) === 0) {
                $url = substr($url, strlen($uri));
            } else { //伪静态时去除
                $len = strlen($app_root);
                if (substr($url, 0, $len) == $app_root) {
                    $url = substr($url, $len);
                }
            }
            //去除?处理
            $pos = strpos($url,'?');
            if($pos!==false){
                if($isCLI){ #cli 命令模式支持?b=1&d=1
                    parse_str(substr($url, $pos+1), $_GET);
                    $_REQUEST = array_merge($_REQUEST, $_GET);
                }
                $url = substr($url, 0, $pos);
            }
            if (self::$cfg['url_rewrite'] && isset($_GET['c']) && strpos($url, '.htm')) {
                //是伪静态地址
            } else {
                //分解m c a
                self::deMCA($url);
            }
        }
        //控制器和方法是否为空，为空则使用默认
        if (empty($_GET['c'])) {
            $_GET['c'] = self::$cfg['def_control'];
        }
        if (empty($_GET['a'])) {
            $_GET['a'] = self::$cfg['def_action'];
        }
        self::$env['c'] = $_GET['c'];
        self::$env['a'] = $_GET['a'];
        self::$env['app_namespace'] = basename(APP_PATH);
        //自动指定app命名空间目录
        if (!isset(self::$namespaceMap[self::$env['app_namespace'] . '\\'])) {
            self::$namespaceMap[self::$env['app_namespace'] . '\\'] = APP_PATH;
        }
        //var_dump($_GET);
        $module = isset($_GET['m']) ? $_GET['m'] : ''; //self::$env['m'] =
        //指定项目模块
        if ($module != '') {
            if (isset(self::$cfg['module_maps'][$module])) {
                if(substr(self::$cfg['module_maps'][$module], 0, 1) == DS){
                    $module_path = ROOT . ROOT_DIR . self::$cfg['module_maps'][$module];
                    self::$env['app_namespace'] = strtr(substr(self::$cfg['module_maps'][$module], 1), DS, '\\');
                } else {
                    $module_path = APP_PATH . DS . self::$cfg['module_maps'][$module];
                    self::$env['app_namespace'] .= '\\'. strtr(self::$cfg['module_maps'][$module], DS, '\\');
                }
            } else {
                $module_path = APP_PATH . DS . $module;
                self::$env['app_namespace'] .= '\\'.$module;
            }
            //引入模块配置
            self::loadConfig($module_path);
        }

        //是否开启模板主题
        $view_path = $module_path . DS . 'view' . (self::$cfg['tmp_theme'] ? DS . self::$cfg['site_template'] : '');
        //自定义项目模板目录 用于模板资源路径
        if(!isset(self::$cfg['app_res_path'])){
            $path = $view_path;
            if(strpos($view_path,ROOT)===0){
                $path = str_replace(ROOT,'', $view_path);
            }elseif(substr($view_path,0,2) == './'){
                $path = APP_ROOT . substr($view_path,1);
            }
            self::$cfg['app_res_path'] = $path;
        }

        //运行变量
        self::setEnv([
            'URI'=> $uri,
            'APP' => $_app, //相对当前地址的应用入口
            'URL' => $_url . self::$env['c'], //相对当前地址的url控制
            'BASE_URL' => $_url . self::$env['c'] . '/' . self::$env['a'],
            'CONTROL' => (strpos(self::$env['c'], '-') ? str_replace(' ', '', ucwords(str_replace('-', ' ', self::$env['c']), ' ')) : ucfirst(self::$env['c'])) . 'Act',
            'ACTION' => strpos(self::$env['a'], '-') ? str_replace(' ', '', ucwords(str_replace('-', ' ', self::$env['a']), ' ')) : self::$env['a'], //转驼峰  lcfirst首字母转小写
            'MODULE' => $module,
            'MODULE_PATH' => $module_path,
            //路径 自动生成
            'CACHE_PATH' => $module_path . DS . 'cache',
            'CONTROL_PATH' => $module_path . DS . 'control',
            'MODEL_PATH' => $module_path . DS . 'model',
            'LANG_PATH' => $module_path . DS . 'lang',
            'VIEW_PATH' => $view_path,
            //相对项目的模板目录
            'APP_VIEW'=>self::$cfg['app_res_path'],
        ]);
        //通过命名空间加载可不需要指定目录遍历了
        //self::class_dir(self::$env['CONTROL_PATH']); //当前项目类目录
        //self::class_dir(self::$env['MODEL_PATH']); //当前项目模型目录
    }

    /**
     * 引入合并配置
     * @param $module_path
     */
    private static function loadConfig($module_path){
        static $hasAppConfig = [];
        if(isset($hasAppConfig[$module_path])) return;
        if(!is_file($module_path . '/config.php')) return;

        //引入配置
        $hasAppConfig[$module_path] = 1; //标识已引入
        $config = require($module_path . '/config.php');
        if(isset($config['class_dir'])){
            $classDir = is_array($config['class_dir']) ? $config['class_dir'] : explode(',', ROOT . ROOT_DIR . str_replace(',', ',' . ROOT . ROOT_DIR, $config['class_dir']));
            self::class_dir($classDir);
            unset($config['class_dir']);
        }
        self::$cfg = array_merge(self::$cfg, $config);
    }

    // 权限验证处理 在config.php配置中设置开启
    public static function Auth(){
        if(!self::$cfg['auth_on']) return;
        $auth_class = self::$cfg['auth_model'];
        $auth_action = self::$cfg['auth_action'];
        $auth_login = self::$cfg['auth_login'];
        $c = self::$env['c']; $a = self::$env['a'];
        //无需验证模块
        if(strpos( self::$cfg['auth_model_not'] , ','.$c.',')!==false){
            if(self::$cfg['auth_model_action']=='') return;
            //验证此模块中需要验证的动作
            if(strpos( self::$cfg['auth_model_action'] , ','.$c.'/'.$a.',')===false){
                return;
            }
        }
        //无需验证方法
        if(strpos( self::$cfg['auth_action_not'] , ','.$c.'/'.$a.',')!==false){
            return;
        }

        if (!class_exists($auth_class)) throw new \Exception('class not exists ' . $auth_class, 404);

        $auth = self::app($auth_class);	//引入权限验证类
        if(!method_exists($auth, $auth_action)) throw new \Exception('auth method not found! ' . $auth_action, 404);

        //仅登陆验证
        if(strpos( self::$cfg['auth_login_model'] , ','.$c.',')!==false || strpos( self::$cfg['auth_login_action'] , ','.$c.'/'.$a.',')!==false){
            if(!$auth->$auth_login()) {
                if($c==self::$cfg['def_control']) redirect(ROOT_DIR .self::$cfg['auth_gateway']);
                else Helper::outMsg('你未登录,请先登录!', ROOT_DIR .self::$cfg['auth_gateway']);
            }
            //验证此模块中需要验证的动作
            if(self::$cfg['auth_login_M_A']=='') return;
            if(strpos( self::$cfg['auth_login_M_A'] , ','.$c.'/'.$a.',')===false){
                return;
            }
        }
        $auth->$auth_action();	//启动验证方法
    }

    // app项目初始化
    private static function init_app($isCLI = IS_CLI){
        if(!$isCLI && self::$env['MODULE']!='') return; //仅cli下自动生成项目模块
        if(is_file(self::$env['MODULE_PATH'] .'/index.htm')) return;
        // 创建项目目录
        if(!is_dir(self::$env['MODULE_PATH'])) mkdir(self::$env['MODULE_PATH'],0755, true);
        $dirs  = array(
            self::$env['CACHE_PATH'],
            self::$env['CONTROL_PATH'],
            self::$env['LANG_PATH'],
            self::$env['MODEL_PATH'],
            self::$env['VIEW_PATH']
        );
        foreach ($dirs as $dir){
            if(!is_dir($dir))  mkdir($dir,0755, true);
        }
        // 生成项目配置
        $runConfig = self::$env['MODULE_PATH'] . '/config.php';
        if (!is_file($runConfig)) file_put_contents($runConfig, file_get_contents(__DIR__ . '/tpl/config.php'));
        // 写入测试Action
        if (!is_file(self::$env['CONTROL_PATH'] . '/IndexAct.php')) {
            file_put_contents(self::$env['MODULE_PATH'] . '/index.htm', 'dir');
            file_put_contents(self::$env['CONTROL_PATH'] . '/Base.php', str_replace('__app__', self::$env['app_namespace'], file_get_contents(__DIR__ . '/tpl/Base.php')));
            file_put_contents(self::$env['CONTROL_PATH'] . '/'.self::$env['CONTROL'].'.php', str_replace(['__app__','__c__','__a__'], [self::$env['app_namespace'], self::$env['CONTROL'], self::$env['ACTION']], file_get_contents(__DIR__ . '/tpl/IndexAct.php')));
            file_put_contents(self::$env['VIEW_PATH'] . '/index.html', file_get_contents(__DIR__ . '/tpl/index.html'));
        }
        //生成git忽略文件
        file_put_contents(self::$env['CACHE_PATH'] . '/.gitignore', "*\r\n!.gitignore");
        file_put_contents(self::$env['MODULE_PATH'] . '/.gitignore', "/config.php");
    }
    //初始框架
    public static function init($cfg=null){
        //项目相对根目录
        $appRoot = IS_WIN ? strtr(dirname($_SERVER['SCRIPT_NAME']), '\\', DS) : dirname($_SERVER['SCRIPT_NAME']);
        ($appRoot=='.' || $appRoot==DS) && $appRoot='';
        #self::$cfg['APP_ROOT'] = $appRoot;
        define('APP_ROOT', $appRoot);
        //引入默认配置文件
        self::set(include __DIR__ . '/def_config.php');
        if(is_array($cfg)){ //组合参数配置
            self::set($cfg);
            unset($cfg);
        }
        //引入公共配置文件
        is_file(COMMON . '/config.php') && self::set(include COMMON . '/config.php');

        //相对根目录
        if(!isset(self::$cfg['root_dir'])){ //仅支持识别1级目录 如/www 不支持/www/web 需要支持请手动设置此配置
            self::$cfg['root_dir'] = '';
            if($appRoot!=''){
                if($_s_pos=strpos($appRoot,DS,1)) self::$cfg['root_dir'] = substr($appRoot,0,$_s_pos);
                else self::$cfg['root_dir'] = $appRoot;
                if(self::$cfg['root_dir']=='.') self::$cfg['root_dir'] = '';
            }
        }
        //相对根目录
        define('ROOT_DIR', self::$cfg['root_dir']);
        //相对公共目录
        define('PUB', ROOT_DIR . '/pub');

        if (self::$cfg['debug']) { //开启错误提示
            error_reporting(E_ALL);// 报错级别设定,一般在开发环境中用E_ALL,这样能够看到所有错误提示
            ini_set('display_errors', 'On');// 有些环境关闭了错误显示
        } else {
            error_reporting(E_ALL ^ E_NOTICE); #除了 E_NOTICE，报告其他所有错误
            ini_set('display_errors', 'Off');
/*
            $runFile = ROOT . '/~run.php';
            if (!is_file($runFile)) {
                $php = self::compile(__DIR__ . '/inc/comm.func.php');
                $php .= self::compile(__DIR__ . '/lib/Cache.php');
                $php .= self::compile(__DIR__ . '/lib/Value.php');
                $php .= self::compile(__DIR__ . '/lib/Control.php');
                $php .= self::compile(__DIR__ . '/lib/Db.php');
                $php .= self::compile(__DIR__ . '/lib/Helper.php');
                $php .= self::compile(__DIR__ . '/lib/Hook.php');
                $php .= self::compile(__DIR__ . '/lib/Log.php');
                $php .= self::compile(__DIR__ . '/lib/Model.php');
                $php .= self::compile(__DIR__ . '/lib/Template.php');
                $php .= self::compile(__DIR__ . '/lib/View.php');
                file_put_contents($runFile, '<?php ' . $php);
                unset($php);
            }
            require $runFile;*/
        }

        //设置本地时差
        date_default_timezone_set(self::$cfg['timezone']);
        //初始类的可加载目录
        self::class_dir([COMMON, COMMON . '/model']); //基础类 扩展类 公共模型
        if(self::$cfg['class_dir']){
            $classDir = is_array(self::$cfg['class_dir']) ? self::$cfg['class_dir'] : explode(',', ROOT . ROOT_DIR . str_replace(',', ',' . ROOT . ROOT_DIR, self::$cfg['class_dir']));
            self::class_dir($classDir);
        }

        //注册类的自动加载
        spl_autoload_register('\myphp::autoload', true, true);
        // 设定错误和异常处理
        Log::register();
        //日志记录初始
        Log::init(self::$cfg['log_dir'], self::$cfg['log_level'], self::$cfg['log_size']);
        is_file(COMMON . '/common.php') && require COMMON . '/common.php';	//引入公共函数

        if(!defined('APP_PATH')){
            define('APP_PATH', realpath(dirname($_SERVER['SCRIPT_FILENAME'])) . '/app');
            if (!IS_CLI) {
                self::conType(Helper::isAjax() ? 'application/json' : 'text/html'); //默认输出类型设置
                self::sendHeader();
            }
        }
    }
    //php代码格式化
    public static function compile($filename) {
        $content = php_strip_whitespace($filename);
        $content = substr(trim($content), 5);
        if ('?>' == substr($content, -2)) $content = substr($content, 0, -2);
        return $content;
    }

    /**
     * 载入php文件
     * @param string $path  路径
     * @return bool
     */
    public static function loadPHP($path) {
        return self::load($path);
    }

    //语言
    public static function loadLang($file){
        self::$lang = array_merge(self::$lang, is_array($file) ? $file : include $file);
    }
    public static function lang($name, $val=''){
        if($val!==''){ //设置值
            if ( false === ($pos = strpos($name, '.')) )
                self::$lang[$name]=$val;
            // 二维数组支持
            $name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
            //$name = explode('.', $name);
            self::$lang[$name1][$name2]=$val;
            return null;
        }
        //获取值
        if ( false === ($pos = strpos($name, '.')) )
            return isset(self::$lang[$name]) ? self::$lang[$name] : null;
        // 二维数组支持
        $name1 = substr($name,0,$pos); $name2 = substr($name,$pos+1);
        return isset(self::$lang[$name1][$name2]) ? self::$lang[$name1][$name2] : null;
    }

    /** 组件使用
     * @param string $name 组件名称 唯一
     * @param null $option 自定义载入'aa'+['class'=>'ab\aa', param1,....]||'ab\aa'+[param1,....]
     * @return object
     */
    public static function app($name, $option=null){
        if(isset(self::$container[$name])) return self::$container[$name];

        $appConf = null;
        $class = $name;
        if($option){
            if(is_callable($option) || is_object($option)){
                self::$container[$name] = $option;
            }else{
                $appConf = $option;
            }
        }else{
            $appConf = self::get('app.'.$name);
        }
        if(isset($appConf['class'])) {
            $class = $appConf['class'];
            unset($appConf['class']);
        }

        self::$container[$name] = $appConf ? new $class($appConf) : new $class();
        return self::$container[$name];
    }

    /**
     * db实例化
     * @param string $name 数据库配置名
     * @param bool $force 是否强制生成新实例
     * @return \myphp\Db
     * @throws \Exception
     */
    public static function db($name = 'db', $force=false)
    {
        $k = '__db_' . $name;
        if ($force || !isset(self::$container[$k])) {
            self::$container[$k] = new \myphp\Db($name, $force);
        }
        return self::$container[$k];
    }
    /**
     * @param string $name
     * @return lib_redis
     */
    public static function redis($name = 'redis'){
        //lib_redis::$isExRedis = false; //不使用redis扩展
        return lib_redis::getInstance(GetC($name));
    }
    /**
     * 释放容器资源
     * @param $name
     */
    public static function free($name)
    {
        unset(self::$container[$name]);
    }

    /**
     * 默认缓存实例
     * @return \myphp\cache\File|\myphp\cache\Redis
     * @throws \Exception
     */
    public static function cache(){
        $type = isset(self::$cfg['cache']) ? self::$cfg['cache'] : 'file';
        return \myphp\Cache::getInstance($type, self::$cfg['cache_option']);
    }

    public static function runTime(){
        return '页面耗时'.run_time().'秒, 内存占用'.run_mem().', 执行'.\myphp\Db::$times.'次SQL';
    }

    //url地址转换对应的模块/控制/方法
    public static function parseUrlMap(){
        /*
        url映射(无)：直接原样url分析
        url映射(有)：
            1、静态url	如：/ask=> /index.php?a=ask(直接返回地址) | /ask=> ask | index/ask 解析成对应的执行url
                    'news'=>'info/lists?id=7', // 解析-> /index.php?c=info&a=lists&id=7
            2、动态url	如：<参数1>[<可选参数>]; [正则]->[*]特殊情况下使用 不支持‘.<>[]’
                'news-<id\d>[-<page\d>]'=>'info/lists?<id>[&<page>]' 解析-> /index.php?c=info&a=lists&id=7&page=$2
        */
        if(empty(self::$cfg['url_maps'])) return false;
        $url = rtrim($_SERVER["REQUEST_URI"], '/');
        if(ROOT_DIR!='')
            $url = str_replace(ROOT_DIR,'',$url);
        if(strpos($url,self::$cfg['url_index'])===0)
            $url = substr($url,strlen(self::$cfg['url_index']));
        if($url_pos=strpos($url,'?'))
            $url = substr($url,0,$url_pos);

        $uri = $mca = $para = '';
        if(isset(self::$cfg['url_maps'][$url])){ //静态url
            $uri = self::$cfg['url_maps'][$url];
            //}elseif(isset(self::$cfg['url_maps'][Helper::getMethod().' '.$url])){ //仅支持单项 GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS
            //    $uri = self::$cfg['url_maps'][Helper::getMethod().' '.$url];
        }else{ //动态url '/news/<id>[-<page>][-<pl>]'=>'info/lists?<id>[&<page>][&<pl>]',
            foreach(self::$cfg['url_maps'] as $k=>$v){
                $reg_match = false;
                if(false!==$pos=strpos($k,'<')){ //是动态执行分析
                    $reg_match = true; $vars = array(); //解析变量数组
                    if(strpos($k,'[')){ //可选参数或特殊regx模式
                        $k = str_replace(array('[',']'),array('(',')?'),$k);
                        //$reg_match = true;
                    }
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
                            }elseif($type=='a'){ //非空白字符
                                $regx='(\S+)';
                            }elseif($type=='!'){ //正则 <all\*!> -> '*'=>'(.*)'
                                $x = substr($__var,$depr+1,-1);
                                $regx = isset(self::$cfg['url_maps_regx'][$x]) ? self::$cfg['url_maps_regx'][$x] : '([\w=]+)';
                                $regx = str_replace('.','#dot',$regx);
                            }
                        }
                        $vars[$var]=true;
                        if(substr($k,$end+1,2)==')?')//可选 "]"->")?"
                            $vars[$var]=false;
                        $k = str_replace('<'.$__var.'>',$regx,$k);
                        $pos=strpos($k,'<',$pos);
                    } while ($pos);

                    $k = str_replace(array('.','#','#dot'),array('\.','\#','.'),$k);
                }

                if($reg_match){
                    //Log::trace($k.'|||'.$url);Log::trace($vars);
                    if (preg_match ('#^'.$k.'$#u', $url, $regArr)) {
                        $count = count($regArr);
                        if(isset($vars) && $count>1){
                            //var_dump($regArr);
                            $count -= 1; //排除正则全匹配的第一项
                            $i = 1;
                            foreach($vars as $_k=>$_v){
                                if($_v) $vars[$_k]=$regArr[$i];
                                else $vars[$_k]=$regArr[++$i]; //可选 因是双括号匹配 目标索引得加1
                                if(++$i>$count) break;
                            }
                            //$_GET = $vars;
                            $_GET = array_merge($_GET, $vars);
                            unset($regArr, $vars);
                        }
                        //var_dump($_GET);//Log::trace($_GET);
                        $uri = $v;
                        break;
                    }
                }
            }
        }

        //echo $uri;
        if ($uri == '') return false;
        $uri = trim($uri, DS);
        //解析获取实际执行的地址
        if($pos = strpos($uri,'?')){ // index/ask?id=1
            $mca = substr($uri,0,$pos);
            $para = substr($uri,$pos+1);
        }else{ // ask | index/ask | pub/index/ask
            $mca = $uri;
        }

        //echo $_SERVER['SCRIPT_NAME'].'<br>'; //当前URL路径
        //echo $mca.'===='.$para;

        if ($para != '') {
            parse_str($para, $get);
            $_GET = array_merge($_GET, $get);
            $_REQUEST = array_merge($_REQUEST, $_GET);
        }
        //分解m c a
        self::deMCA($mca);
        return true;
    }
    //分解m c a
    private static function deMCA(&$mca){
        $mca = trim($mca, '/');
        if ($mca === '') return;
        if (strpos($mca, '/')) {
            $path = explode('/', $mca);
            if (isset($path[2])) {
                $_GET['m'] = $path[0];
                $_GET['c'] = $path[1];
                $_GET['a'] = $path[2];
            } elseif (isset(self::$cfg['module_maps'][$path[0]])) {
                $_GET['m'] = $path[0];
                $_GET['c'] = $path[1];
            } else {
                $_GET['c'] = $path[0];
                $_GET['a'] = $path[1];
            }
            unset($path);
        } else {
            if (isset(self::$cfg['module_maps'][$mca])) { //有配置模块优先
                $_GET['m'] = $mca;
            } else {
                $_GET['c'] = $mca;
            }
        }
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
    public static function reverse_url($k,$vars){
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
                array('adm'=>'/admin');  模块名=>模块（项目）路径  ->  /index.php
                此处会自动的设置项目路径及加载项目配置		配置中未设置 log cache 时，会默认框架的log cache做来设置
            2、实际的访问地址 如 /admin.php
            3、实际的访问路径 如 /pub -> /pub/index.php

        U('/admin/index/show?b=2&c=4',$option=null)  /index.php/index-show-m-adm-b-2-c-4	此时的项目路径 是/admin
        U('/admin.php/index/show?b=2&c=4',$option=null)  /admin.php/index-show-b-2-c-4
        U('/pub/index/show?b=2&c=4',$option=null)  /pub/index.php/index-show-b-2-c-4

        U('/index/show?b=2&c=4',$option=null)  /index.php/index-show-b-2-c-4	当前项目 index->show方法
        U('/show?b=2&c=4',$option=null) 同上  当前项目 默认控制器/show方法
    */

    //url正向解析 地址 [!]admin/index/show?b=c&d=e&....[#锚点@域名（待实现）], 附加参数 数组|null, url字符串如：/pub/index.php
    public static function forward_url($uri='', $vars=null, $url=''){
        $normal = false;
        $m = $c = $a = $mac = $para = '';
        if(substr($uri,0,1)=='!'){ //普通url模式
            $normal = true; $uri = substr($uri,1);
        }

        $pos = strpos($uri,'?');
        if($pos!==false && isset($vars['!'])){ //排除参数处理 参数!的参数值为排除项 多个使用,分隔
            $del_paras = is_array($vars['!']) ? $vars['!'] : explode(',', $vars['!']);
            foreach($del_paras as $k){
                if($k!='' && $_s = strpos($uri, $k, $pos)){ //?位置开始
                    $_e = strpos($uri, '&', $_s); //参数位置开始
                    $uri = substr($uri, 0, $_s).($_e?substr($uri, $_e+1):'');
                }
            }
            if(count($vars)>1) unset($vars['!']);
            else $vars=null;
        }
        //url映射
        if(is_array(self::$cfg['url_maps']) && !empty(self::$cfg['url_maps'])){
            static $url_maps;
            if(!isset($url_maps)){
                foreach (self::$cfg['url_maps'] as $k => $v) {
                    $url_maps[$v]=$k;
                }
            }
        }
        $query = '';
        //分析mac及参数
        if ($pos !== false) {// 参数处理
            $mca = substr($uri, 0, $pos);
            $query = substr($uri, $pos + 1);
            if (is_array($vars)) {
                parse_str($query, $get);
                $vars = array_merge($get, $vars);
            } else {
                parse_str($query, $vars);
            }
        } else {
            $mca = $uri;
        }
        if (is_array($vars)) {
            $query = http_build_query($vars);
        }

        //直接解析 普通模式
        if(self::$cfg['url_mode']!=2 || $normal){
            if($mca!=''){//分解m a c
                if($pos=strpos($mca,'.php')){ #有指定入口php url
                    $url = substr($mca,0, $pos+4);
                    $mca = substr($mca, $pos+4);
                }
                if (strpos($mca, '/') !== false) {
                    $path = explode('/', trim($mca, '/'));
                    $a = array_pop($path);
                    $c = array_pop($path);
                    if (!empty($path)) $m = array_pop($path);
                } else {
                    $c = self::env('c');
                    $a = $mca ? $mca : self::env('a');
                }
            }

            if($c=='' && $a=='' && !$query){
                return $url==''?self::env('URI'):ROOT_DIR.$url;
            }

            $para = '';
            if ($m) $para .= '&m=' . $m;
            if ($c) $para .= '&c=' . $c;
            if ($a) $para .= '&a=' . $a;

            $url = $url == '' ? self::env('URI') : ROOT_DIR . $url;
            if ($query) $para .= '&' . $query;
            return $url . '?' . substr($para,1);
        }

        $mca = ($mca[0] == '/' ? '' : '/') . $mca;

        //url映射处理
        if(isset($url_maps)){
            $_url = $url == '' ? substr(self::env('URI'), strlen(ROOT_DIR)) : $url;
            if ($query) $_url .= '?' . $query;
            //先完整比配 再mac?附加参数比配 再 mac比配
            if (isset($url_maps[$_url])) {
                return ROOT_DIR . $url_maps[$_url];
            } elseif (isset($url_maps[$mca])) {
                return ROOT_DIR . self::reverse_url($url_maps[$mca], $vars);
            }
        }

        if ($url != '') {
            $url = ROOT_DIR . $url;
        } else {
            $url = rtrim(self::env('APP'), '/');
        }
        return $url . $mca . ($query ? '?' . $query : '');
    }
}
//异常类
class myException extends Exception{}
//配置处理类
class Config{
    //载入配置
    public static function load($file){
        myphp::set(include $file);
    }
    //获取配置值 支持二维数组
    public static function get($name, $defVal = null){
        return myphp::get($name, $defVal);
    }
    //动态设置配置值
    public static function set($name, $val=null){
        myphp::set($name, $val);
    }
    //删除配置
    public static function del($name){
        myphp::del($name);
    }
}
//消息复用
trait MyMsg
{
    public static $myMsg = '';
    public static $myCode = 0;
    //消息记录
    public static function msg($msg=null, $code=0){
        if ($msg === null) {
            return self::$myMsg;
        } else {
            self::$myMsg = $msg;
            self::$myCode = $code;
        }
        return null;
    }
    //错误提示设置或读取
    public static function err($msg=null, $code=1){
        if ($msg === null) {
            return self::$myMsg;
        } else {
            self::msg($msg, $code);
            return false;
        }
    }
}