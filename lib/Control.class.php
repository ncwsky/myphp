<?php
//控制器基类，所有的控制器需要继承此类
class Control
{
    protected $view = NULL; //模板实例
    protected $cache = NULL; //缓存实例
    protected $db = NULL; //数据库实例
    public $req_cache = null; //输出缓存配置 见myphp->req_cache

    const CODE_OK = 0; //成功
    const CODE_FAIL = 1; //失败
    const DATA_INVALID = 1000; //无效的请求数据

    public static $tpl = array(
        self::CODE_OK => '操作成功',
        self::CODE_FAIL => '操作失败',
        self::DATA_INVALID => '无效的数据'
    );
    public static function tpl($code)
    {
        return isset(self::$tpl[$code]) ? self::$tpl[$code] : 'null';
    }
    public static function out($code = self::CODE_OK, $data = array(), $info = null, $ext=null)
    {
        $out = ['code' => $code, 'msg' => ($info === null ? self::tpl($code) : $info)];
        $out['data'] = $data;
        $out['ext'] = $ext;
        return $out;
    }
    public static function ok($data = null, $info = null, $ext=null, $code = self::CODE_OK)
    {
        $out = ['code' => $code, 'msg' => ($info === null ? self::tpl($code) : $info)];
        $out['data'] = $data;
        $out['ext'] = $ext;
        return $out;
    }
    public static function fail($info = null, $code = self::CODE_FAIL, $data = null)
    {
        $out = ['code' => $code, 'msg' => ($info === null ? self::tpl($code) : $info)];
        $out['data'] = $data;
        return $out;
    }

    //构造方法，实例化视图
    public function __construct()
    {
        $this->view = View::getInstance(myphp::env('VIEW_PATH'), myphp::env('CACHE_PATH'));
        if (GetC('db.name') != '') $this->db = myphp::db(); //数据库;
        if (GetC('cache') != '') $this->cache = myphp::cache();
        $this->_init();
    }

    //初始化执行
    protected function _init()
    {
        //todo
    }
    protected function _before(){
        //todo
    }

    //执行动作
    final function _run($action)
    {
        //判断实例中是否存在action方法，不存在则提示错误
        if (!method_exists($this, $action)) throw new Exception('method not exists ' . $action, 404);
        //todo 前置操作定义处理
        $this->req_cache = null;
        $this->_before();
        return $this->$action();
    }

    //设置模板变量
    final function assign($var, $value)
    {
        $this->view->assign($var, $value);
    }
    //启用输出缓存
    final function cache($expire=0){
        if($expire!==null){ //如果req_cache_except设置项包含此请求则缓存无效
            if($expire==0) $expire = 2592000; //客户端缓存一月
            $this->req_cache = array(true,(int)$expire);
        }
        return $this;
    }
    //在子类控制器及方法中调用 显示模板
    final function display($file = '', $var = null)
    {
        myphp::conType('text/html');
        $this->view->display($file, $var);
    }
    //在子类控制器及方法中调用 取得页面内容
    final function fetch($file = '', $var=null)
    {
        myphp::conType('text/html');
        return $this->view->fetch($file, $var);
    }
    final static function redirect($url, $code=302){
        // 如果报头未发送，则发送
        if (!headers_sent()) {// redirect
            myphp::$statusCode = $code;
            myphp::setHeader('Location', $url);
            return '';
        } else {
            return "<meta http-equiv='Refresh' content='0;URL={$url}'>";
        }
    }
    //json类型输出
    final static function json($data){
        myphp::conType('application/json');
        return Helper::toJson($data);
    }
    //jsonp类型输出
    final static function jsonp($data){
        myphp::conType('application/javascript');

        $jsonp_call = isset($_GET[Config::$cfg['jsonp_call']])?$_GET[Config::$cfg['jsonp_call']]:Config::$cfg['jsonp_call'];
        $data = Helper::toJson($data);
        if ($data === false) {
            throw new Exception('to json fail');
        }

        $data = $jsonp_call . '(' . $data . ');';
        return $data;
    }
    //xml类型输出
    final static function xml($data){
        myphp::conType('text/xml');
        return Helper::toXml($data);
    }
}