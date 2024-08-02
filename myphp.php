<?php

use myphp\Helper;
use myphp\Log;
use myphp\Response;

final class myphp{
    use MyMsg;
    public static $beforeFun = null; //Control_run之前的处理回调 \Closure() @return void|throw|Response
    public static $authFun = null; //自定义验证回调方法 \Closure @return void|false|throw|Response
    public static $sendFun = null; //自定义输出处理 \Closure($code, $data, $header)
    public static $lang = [];
    public static $env = []; //Run执行时的环境值 array
    /**
     * 管道模式
     * @var \myphp\Pipeline
     */
    public static $pipe = null;
    private static $req_cache = null; //请求缓存配置 [key,expire]
    private static $container = []; //容器

    //自动载入配置项
    public static $rootPath = ''; //默认 ROOT
    public static $classDir = []; //设置可加载的目录 ['dir'=>1,....]
    public static $namespaceMap = []; // ['namespace\\'=>'src/']; //命名空间路径映射 不指定完整路径使用rootPath 仅支持"xxx\\"的映射
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

        #var_dump(self::$namespaceMap, $class_name);
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
            #var_dump(self::$rootPath . DIRECTORY_SEPARATOR . strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php');
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
        if (isset(self::$env[$name])) {
            return self::$env[$name];
        }
        return $def;
    }
    public static function setEnv($name, $val=null){
        if (is_array($name)) {
            self::$env = self::$env ? array_merge(self::$env, $name) : $name;
        } else {
            self::$env[$name] = $val;
        }
    }

    /**
     * 执行c->a
     * @return Response|void|null
     * @throws Exception
     */
    private static function _runCA(){
        //权限验证处理
        $res = self::$authFun instanceof \Closure ? call_user_func(self::$authFun) : self::_auth();
        if ($res instanceof Response) return $res;
        if (false === $res) return self::res()->withBody(Helper::outMsg('auth fail'));
        // 请求缓存处理
        $res = self::reqCache();
        if (false === $res) {
            //转驼峰 控制器的类名
            $control = self::$env['app_namespace'] . '\\control\\' . self::$env['CONTROL'];
            if (!class_exists($control)) return self::res()->e404('class not exists ' . $control);
            //throw new \Exception('class not exists ' . $control, 404);
            /**
             * @var \myphp\Control $instance
             */
            $instance = new $control();
            $res = $instance->_run(self::$env['ACTION']);
        }
        if ($res !== null && !$res instanceof Response) {
            self::res()->body = $res;
            $res = self::res();
        }
        return $res;
    }

    /**
     * @return mixed|Response|null
     * @throws Exception
     */
    public static function handle(){
        /**
         * @var Response|null $res
         */
        $res = null;
        //有中间件配置时走管道模式
        if (self::$cfg['middleware']) {
            //前置处理
            if(self::$beforeFun instanceof \Closure){
                array_unshift(self::$cfg['middleware'], function($request, $next){
                    $data = call_user_func(self::$beforeFun);
                    if ($data instanceof Response) return $data;
                    return $next($request);
                });
            }
            //c->a执行
            $res = self::$pipe->send(self::req())->through(self::$cfg['middleware'])->then(function ($request){
                return self::_runCA();
            });
        } else {
            //前置处理
            if (self::$beforeFun instanceof \Closure) {
                $res = call_user_func(self::$beforeFun);
            }
            if ($res === null) { // || !$res instanceof Response
                $res = self::_runCA();
            }
        }
        return $res;
    }
    /**
     * 运行程序 $isCli 可设置CLI模式下false用于解析数据的参数
     * @param null $sendFun
     * @param bool $isCli
     * @throws \Exception
     */
    public static function Run($sendFun=null, $isCli=IS_CLI){
        $_init_cfg = self::$cfg; //全局配置
        self::Analysis($isCli);	//开始解析URL获得请求的控制器和方法及初始化
        self::$sendFun = $sendFun;
        try {
            $res = self::handle();
            $res!==null && self::send($res, self::res()->getStatusCode(), self::req()->expire);
        } catch (\Exception $e) {
            $errCode = $e->getCode();
            //匹配状态码时 //$errCode==404 || $errCode==200
            if ($errCode >= 200 && $errCode < 500 && isset(Response::$phrases[$errCode])) {
                self::send($e->getMessage(), $errCode);
            } else {
                self::send($e->getMessage() . (self::$cfg['debug'] ? "\n" . 'line:' . $e->getLine() . ', file:' . $e->getFile() . "\n" . $e->getTraceAsString() : ''), 500);
                Log::Exception($e, false);
            }
        } catch (\Error $e) {
            self::send($e->getMessage() . (self::$cfg['debug'] ? "\n" . 'line:' . $e->getLine() . ', file:' . $e->getFile() . "\n" . $e->getTraceAsString() : ''), 500);
            Log::Exception($e, false);
        }
        self::req()->clear();
        self::res()->clear();
        //重置处理
        self::$cfg = $_init_cfg;
        self::$env = [];
        self::$lang = [];
    }

    /** 输出数据到页面
     * @param mixed|Response $res
     * @param int $code
     * @param int $expire //请求缓存时间
     * @throws Exception
     */
    public static function send($res, $code=200, $expire=0){
        //非response处理
        if (! $res instanceof Response) {
            self::res()->setStatusCode($code)->body = $res;
            $res = self::res();
        }
        //有请求缓存设置
        if (200 == $code && self::$req_cache) {
            if ($expire > 0) self::$req_cache[1] = $expire;
            $res->header['Cache-Control'] = 'max-age=' . self::$req_cache[1] . ',must-revalidate';
            $res->header['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';
            $res->header['Expires'] = gmdate('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + self::$req_cache[1]) . ' GMT';
            //缓存内容和输出头
            self::$cfg['cache'] && self::cache()->set(self::$req_cache[0], [$res->body, $res->header], self::$req_cache[1]);
        }
        //默认输出类型设置
        if (!isset($res->header['Content-Type'])) {
            $res->setContentType(Helper::isAjax() ? Response::CONTENT_TYPE_JSON : Response::CONTENT_TYPE_HTML);
        }
        // 监听res_send
        \myphp\Hook::listen('res_send', $res);
        if (self::$sendFun === null) {
            $res->send();
        } else {
            call_user_func_array(self::$sendFun, [$code, &$res, &$res->header]);
        }
        // 监听res_end
        \myphp\Hook::listen('res_end', $res);
    }
    /**
     * 请求缓存处理
     * @return Response|false
     * @throws Exception
     */
    private static function reqCache(){
        self::$req_cache = null;
        if(!Helper::isGet()) return false;
        //不使用请求缓存
        if(false === self::$cfg['req_cache']) return false;

        //使用缓存
        if (true === self::$cfg['req_cache']) {
            $ca = '/' . self::$env['c'] . '/' . self::$env['a'];
            //需要排除的请求项
            foreach (self::$cfg['req_cache_except'] as $rule) {
                if (0 === stripos($ca, $rule)) {
                    return false;
                }
            }
            // 缓存key名
            $reqKey = 'req'.str_replace('/','.', $ca);
            foreach ($_GET as $v) {
                $reqKey .= '_' . str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $v);
            }
        } elseif (self::$cfg['req_cache'] instanceof \Closure) { //自定义请求缓存键名处理
            $reqKey = call_user_func_array(self::$cfg['req_cache'], $_GET);
        }
        if (isset($reqKey)) {
            //有启用缓存配置
            if (self::$cfg['cache']){
                //有页面缓存
                if($res = self::cache()->get($reqKey)) {
                    self::res()->setHeader($res[1]);
                    self::res()->body = $res[0];
                    return self::res()->setStatusCode(200);
                }
            }

            self::$req_cache = [$reqKey, self::$cfg['req_cache_expire']]; //记录缓存键名 默认过期时间 用于send
        }
        return false;
    }
    //输出头设置
    public static function setHeader($name, $val=null, $append=false){
        self::res()->setHeader($name, $val, $append);
    }
    public static function rawBody(){
        return self::req()->rawBody();
    }
    public static function setRawBody($rawBody){
        self::req()->setRawBody($rawBody);
    }
    //输出类型设置
    public static function conType($conType, $charset = '')
    {
        self::res()->setContentType($conType, $charset);
    }
    /**
     * 解析URL获得控制器的与方法
     * m c a在GET变量下为内置参数名，不可用于其他
     * url模式
     * 0、http://localhost/index.php?c=控制器&a=方法
     * 2、http://localhost/index.php/[模块/]控制器/方法?其他参数
     * @param bool $isCLI cli命令脚本模式处理
     */
    public static function Analysis($isCLI = IS_CLI){
        $app_path = IS_WIN ? strtr(APP_PATH, '\\', DS) : APP_PATH;
        //引入app下的配置文件
        self::loadConfig($app_path . '/config.php');

        $basename = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : 'index.php'; //当前执行文件名
        $app_root = IS_CLI ? DS : ROOT_DIR . DS; //app_url根路径
        $url_mode = isset($_GET['_url_mode']) ? $_GET['_url_mode'] : self::$cfg['url_mode'];
        if ($isCLI) { //cli命令脚本模式处理 主要用于脚本命令下执行
            $_SERVER['IS_CLI_RUN'] = true; //用于区分是否脚本执行
            //cli_url_mode请求模式 默认2 PATH_INFO模式
            $url_mode = self::$cfg['url_mode'] = isset(self::$cfg['cli_url_mode'])?self::$cfg['cli_url_mode']:2;
            if ($url_mode == 2) { // php xxx.php m/c/a "b=1&d=1"|b=1 d=1
                $_SERVER["REQUEST_URI"] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '/';
                parse_str(implode('&', array_slice($_SERVER['argv'], 2)), $_GET);
            } else { // php xxx.php "c=x&a=y&b=1&d=1"|c=x a=y b=1 d=1
                parse_str(implode('&', array_slice($_SERVER['argv'], 1)), $_GET);
            }
            $_REQUEST = $_GET; //兼容处理
        }
        // 简单 url 映射  //仅支持映射到普通url模式
        $hasMatch = self::parseUrlMap();

        $uri = $app_root . $basename; //当前URL路径
        //echo 'uri=',$uri,PHP_EOL;
        if ($url_mode == 2) {
            $_app = (self::$cfg['url_rewrite'] && $uri == self::$cfg['url_index'] ? '' : $uri) . '/';
        } else {
            $_app = $uri;
        }
        if (!$hasMatch && $url_mode == 2){	//如果Url模式为2，那么就是使用PATH_INFO模式
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
            if ($pos!==false){
                if ($isCLI) { //cli 命令模式支持?b=1&d=1
                    parse_str(substr($url, $pos+1), $_GET);
                    $_REQUEST = $_GET;
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
        if (empty($_GET['c'])) $_GET['c'] = self::$cfg['def_control'];
        if (empty($_GET['a'])) $_GET['a'] = self::$cfg['def_action'];

        self::$env['c'] = $_GET['c'];
        self::$env['a'] = $_GET['a'];
        self::$env['m'] = isset($_GET['m']) ? $_GET['m'] : '';
        self::$env['app_namespace'] = basename(APP_PATH);
        //自动指定app顶层命名空间目录 //realpath目录不存在时会false
        if (!isset(self::$namespaceMap[self::$env['app_namespace'] . '\\'])) {
            self::$namespaceMap[self::$env['app_namespace'] . '\\'] = APP_PATH;
        }
        //指定项目模块
        if (self::$env['m'] != '') {
            if (isset(self::$cfg['module_maps'][self::$env['m']])) {
                if(self::$cfg['module_maps'][self::$env['m']][0] == DS){ //项目根目录
                    $app_path = ROOT . self::$cfg['module_maps'][self::$env['m']];
                    self::$env['app_namespace'] = strtr(substr(self::$cfg['module_maps'][self::$env['m']], 1), DS, '\\');
                } else { //相对项目目录
                    $app_path = APP_PATH . DS . self::$cfg['module_maps'][self::$env['m']];
                    self::$env['app_namespace'] .= '\\'. strtr(self::$cfg['module_maps'][self::$env['m']], DS, '\\');
                }
            } else { //子模块默认 module 目录下
                $app_path = APP_PATH . DS . 'module' . DS . self::$env['m'];
                self::$env['app_namespace'] .= '\\module\\'.self::$env['m'];
            }
            //引入模块配置
            self::loadConfig($app_path . '/config.php');
        }

        //是否开启模板主题
        $view_path = $app_path . DS . 'view' . (self::$cfg['tmp_theme'] ? DS . self::$cfg['site_template'] : '');
        //自定义项目模板目录 用于模板资源路径
        if(!isset(self::$cfg['app_res_path'])){
            $path = $view_path;
            if(strpos($view_path,ROOT)===0){ //这里可能是二级目录
                $path = str_replace(ROOT_DIR !== '' ? str_replace(ROOT_DIR, '', ROOT) : ROOT, '', $view_path);
            }elseif(substr($view_path,0,2) == './'){
                $path = $app_root . substr($view_path,2);
            }
            self::$cfg['app_res_path'] = $path;
        }

        $_url = $_app;
        if ($url_mode == 2) {
            if (self::$env['m']) $_url .= self::$env['m'] . '/';
            $_url .= self::$env['c'];
            $_base_url = $_url . '/' . self::$env['a'];
        } else {
            if (self::$env['m']) {
                $_url = $_app . '?m=' . self::$env['m'] . '&c=' . self::$env['c'];
            } else {
                $_url = $_app . '?c=' . self::$env['c'];
            }
            $_base_url = $_url . '&a=' . self::$env['a'];
        }
        //运行变量
        self::setEnv([
            'URI'=> $uri,
            'APP' => $_app, //相对当前地址的应用入口
            'URL' => $_url, //相对当前地址的url控制
            'BASE_URL' => $_base_url,
            'CONTROL' => (strpos(self::$env['c'], '-') ? str_replace(' ', '', ucwords(str_replace('-', ' ', self::$env['c']), ' ')) : ucfirst(self::$env['c'])) . 'Act',
            'ACTION' => strpos(self::$env['a'], '-') ? str_replace(' ', '', ucwords(str_replace('-', ' ', self::$env['a']), ' ')) : self::$env['a'], //转驼峰  lcfirst首字母转小写
            'MODULE_PATH' => $app_path,
            //路径 自动生成
            'CACHE_PATH' => $app_path . DS . 'cache',
            'CONTROL_PATH' => $app_path . DS . 'control',
            'MODEL_PATH' => $app_path . DS . 'model',
            'LANG_PATH' => $app_path . DS . 'lang',
            'VIEW_PATH' => $view_path,
        ]);
        self::_initApp($app_path, $isCLI);
        //通过命名空间加载可不需要指定目录遍历了
        //self::class_dir(self::$env['CONTROL_PATH']); //当前项目类目录
        //self::class_dir(self::$env['MODEL_PATH']); //当前项目模型目录
    }
    /**
     * 引入合并配置
     * @param string $path
     */
    public static function loadConfig($path){
        if(!is_file($path)) return;

        $config = require($path);
        //指定目录
        if(isset($config['class_dir'])){
            $classDir = is_array($config['class_dir']) ? $config['class_dir'] : explode(',', ROOT . str_replace(',', ',' . ROOT, $config['class_dir']));
            self::class_dir($classDir);
            unset($config['class_dir']);
        }
        //中间件
        if (!empty($config['middleware'])) {
            if (!self::$cfg['middleware']) {
                self::$cfg['middleware'] = (array)$config['middleware'];
            } else {
                self::$cfg['middleware'] = array_merge(self::$cfg['middleware'], (array)$config['middleware']);
            }
            unset($config['middleware']);
        }

        self::$cfg = array_merge(self::$cfg, $config); //array_merge_recursive 可考虑递归合并
    }
    /**
     * 权限验证处理 在config.php配置中设置开启
     * @return Response|bool|void
     */
    private static function _auth(){
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

        //if (!class_exists($auth_class)) throw new \Exception('class not exists ' . $auth_class, 404);

        $auth = new $auth_class(); //self::app($auth_class); //引入权限验证类
        //if(!method_exists($auth, $auth_action)) throw new \Exception('auth method not found! ' . $auth_action, 404);

        //仅登陆验证
        if(strpos( self::$cfg['auth_login_model'] , ','.$c.',')!==false || strpos( self::$cfg['auth_login_action'] , ','.$c.'/'.$a.',')!==false){
            $res = $auth->$auth_login();
            if ($res instanceof Response) return $res;
            if (!$res) {
                $redirect = (strpos(self::$cfg['auth_gateway'], 'http') === 0 ? '' : ROOT_DIR) . self::$cfg['auth_gateway'];
                if (!Helper::isAjax() || $c == self::$cfg['def_control']) {
                    return self::res()->redirect($redirect);
                    #\myphp::setHeader('Location', $redirect);
                    #throw new \Exception('', 302);
                }
                return self::res()->withBody(Helper::outMsg('你未登录,请先登录!', $redirect));
                //throw new \Exception(Helper::outMsg('你未登录,请先登录!', $redirect), 200);
            }
            //验证此模块中需要验证的动作
            if (self::$cfg['auth_login_M_A'] == '') return true;
            if (strpos(self::$cfg['auth_login_M_A'], ',' . $c . '/' . $a . ',') === false) {
                return;
            }
        }
        return $auth->$auth_action(); //启动验证方法
    }
    // app项目初始化
    private static function _initApp($path, $isCLI = IS_CLI){
        if(!$isCLI && self::$env['m']!='') return; //仅cli下自动生成项目模块
        if(is_file($path .'/index.htm')) return;
        // 创建项目目录
        if(!is_dir($path)) mkdir($path,0755, true);
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
        $runConfig = $path . '/config.php';
        if (!is_file($runConfig)) file_put_contents($runConfig, file_get_contents(__DIR__ . '/tpl/config.php'));
        // 写入测试Action
        if (!is_file(self::$env['CONTROL_PATH'] . '/IndexAct.php')) {
            file_put_contents($path . '/index.htm', 'dir');
            file_put_contents(self::$env['CONTROL_PATH'] . '/Base.php', str_replace('__app__', self::$env['app_namespace'], file_get_contents(__DIR__ . '/tpl/Base.php')));
            file_put_contents(self::$env['CONTROL_PATH'] . '/'.self::$env['CONTROL'].'.php', str_replace(['__app__','__c__','__a__'], [self::$env['app_namespace'], self::$env['CONTROL'], self::$env['ACTION']], file_get_contents(__DIR__ . '/tpl/IndexAct.php')));
            file_put_contents(self::$env['VIEW_PATH'] . '/index.html', file_get_contents(__DIR__ . '/tpl/index.html'));
        }
        //生成git忽略文件
        file_put_contents(self::$env['CACHE_PATH'] . '/.gitignore', "*\r\n!.gitignore");
        file_put_contents($path . '/.gitignore', "/config.local.php");
    }
    //初始框架
    public static function init($cfg=null){
        //引入默认配置文件
        self::$cfg = require(__DIR__ . '/def_config.php');
        if (is_array($cfg)) { //组合参数配置
            self::$cfg = array_merge(self::$cfg, $cfg);
            unset($cfg);
        }
        //引入公共配置文件
        if (is_file(COMMON . '/config.php')) {
            self::$cfg = array_merge(self::$cfg, require(COMMON . '/config.php'));
        }

        //相对根目录
        if(!isset(self::$cfg['root_dir'])){ //仅支持识别1级目录 如/www 不支持/www/web 需要支持请手动设置此配置
            if (IS_CLI) {
                self::$cfg['root_dir'] = '';
            } else {
                $appRoot = dirname($_SERVER['SCRIPT_NAME']);
                if (IS_WIN) $appRoot = strtr($appRoot, '\\', DS);
                $appRoot == DS && $appRoot = ''; //$appRoot=='.' cli下会得到.
                self::$cfg['root_dir'] = $appRoot;
            }
        }
        //相对根目录
        define('ROOT_DIR', self::$cfg['root_dir']);
        //相对资源公共目录
        define('PUB', ROOT_DIR . '/pub');

        if (self::$cfg['debug']) { //开启错误提示
            error_reporting(E_ALL);// 报错级别设定,一般在开发环境中用E_ALL,这样能够看到所有错误提示
            ini_set('display_errors', 'On');// 有些环境关闭了错误显示
        } else {
            error_reporting(E_ALL ^ E_NOTICE); #除了 E_NOTICE，报告其他所有错误
            ini_set('display_errors', 'Off');
        }

        //设置本地时差
        date_default_timezone_set(self::$cfg['timezone']);
        //初始类的可加载目录
        self::class_dir([COMMON, COMMON . '/model']); //基础类 扩展类 公共模型
        if(self::$cfg['class_dir']){
            $classDir = is_array(self::$cfg['class_dir']) ? self::$cfg['class_dir'] : explode(',', ROOT . str_replace(',', ',' . ROOT, self::$cfg['class_dir']));
            self::class_dir($classDir);
        }

        if (self::$rootPath === '') self::$rootPath = ROOT;
        //注册类的自动加载
        spl_autoload_register('\myphp::autoload', true, true);
        // 设定错误和异常处理
        Log::register();
        //日志记录初始
        Log::init(self::$cfg['log_dir'], self::$cfg['log_level'], self::$cfg['log_size']);
        is_file(COMMON . '/common.php') && require COMMON . '/common.php';	//引入公共函数

        if(!defined('APP_PATH')){
            define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . '/app');
        }
        self::$pipe = new \myphp\Pipeline();
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
     * @return \myphp\Request
     */
    public static function req()
    {
        $k = '__req';
        if (!isset(self::$container[$k])) {
            self::$container[$k] = new \myphp\Request();
        }
        //$id = $k . '.' . self::env('c') . self::env('a');
        if (!isset(self::$env[$k])) {
            self::$env[$k] = clone self::$container[$k]; //每次请求使用新的对象
        }
        return self::$env[$k];
    }
    /**
     * @return Response
     */
    public static function res()
    {
        $k = '__res';
        if (!isset(self::$container[$k])) {
            self::$container[$k] = new Response();
        }
        //$id = $k . '.' . self::env('c') . self::env('a');
        if (!isset(self::$env[$k])) {
            self::$env[$k] = clone self::$container[$k]; //每次请求使用新的对象
        }
        return self::$env[$k];
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
        $conf = GetC($name);
        if (empty($conf['name'])) $conf['name'] = $name;
        return lib_redis::getInstance($conf);
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
                $pos = strpos($k, '<');
                if (false === $pos) continue; //非动态url

                //是动态执行分析
                $vars = []; //解析变量数组
                if(strpos($k,'[')){ //可选参数或特殊regx模式
                    $k = str_replace(array('[',']'),array('(',')?'),$k);
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
                //todo 缓存解析后的动态url规则
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
            $query = http_build_query($vars, "", "&", PHP_QUERY_RFC3986);
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

    //错误提示设置并返回null
    public static function errNil($msg, $code = 1)
    {
        self::$myMsg = $msg;
        self::$myCode = $code;
        return null;
    }
}
/**
 * Trait MyBaseObj 基础对象复用
 */
trait MyBaseObj{
    /**
     * @var array 附加属性
     */
    protected $_behavior = [];
    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->_behavior[$name] = $value;
    }
    /**
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        return isset($this->_behavior[$name]) ? $this->_behavior[$name] : null;
    }
    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->_behavior[$name]);
    }
    /**
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->_behavior[$name]);
    }
    /**
     * @var static
     */
    public static $instance = null;
    /**
     * @return static
     */
    public static function instance()
    {
        if (!static::$instance) {
            self::$instance = new static();
        }
        return static::$instance;
    }
}