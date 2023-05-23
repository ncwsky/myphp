<?php
namespace myphp;

//控制器基类，所有的控制器需要继承此类
class Control
{
    protected $view = null; //模板实例
    public $response = null;
    public $request = null;

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
        $this->response = \myphp::res();
        $this->request = \myphp::req();
        $this->view = View::getInstance(\myphp::env('VIEW_PATH'), \myphp::env('CACHE_PATH'));
        $this->_init();
    }

    protected function _init()
    {
        //todo
    }

    /**
     * @return bool
     */
    protected function _before(){
        $this->request->checkCsrfToken();
        return true;
    }

    /**
     * @param $result
     * @return mixed
     */
    protected function _after($result){
        return $result;
    }

    //执行动作
    final function _run($action)
    {
        //判断实例中是否存在action方法，不存在则提示错误
        if (!method_exists($this, $action)) return $this->response->e404('method not exists ' . $action);
        //throw new \Exception('method not exists ' . $action, 404);
        //前后置操作处理
        return $this->_before() ? $this->_after($this->$action()) : null;
    }

    //设置模板变量
    final function assign($var, $value)
    {
        $this->view->assign($var, $value);
    }
    //启用输出缓存
    final function cache($expire=0){
        $this->request->expire = (int)$expire; //0使用默认配置req_cache_expire
        return $this;
    }
    //在子类控制器及方法中调用 显示模板
    final function display($file = '', $var = null)
    {
        \myphp::conType('text/html');
        $this->view->display($file, $var);
    }

    /**
     * 在子类控制器及方法中调用 取得页面内容
     * @param string $file
     * @param null $var
     * @return Response
     */
    final function fetch($file = '', $var=null)
    {
        $this->response->body = $this->view->fetch($file, $var);
        return $this->response->setContentType(Response::CONTENT_TYPE_HTML);
        //\myphp::conType('text/html');
        //return $this->view->fetch($file, $var);
    }
    final static function redirect($url, $code=302){
        return \myphp::res()->redirect($url, $code);
    }

    /**
     * html内容
     * @param $data
     * @return Response
     */
    final static function html($data)
    {
        \myphp::res()->body = $data;
        return \myphp::res()->setContentType(Response::CONTENT_TYPE_HTML);
        //\myphp::conType('text/html');
        //return $data;
    }

    /**
     * json类型输出
     * @param $data
     * @return Response
     */
    final static function json($data){
        \myphp::res()->body = Helper::toJson($data);
        return \myphp::res()->setContentType(Response::CONTENT_TYPE_JSON);
        //\myphp::conType('application/json');
        //return Helper::toJson($data);
    }

    /**
     * jsonp类型输出
     * @param $data
     * @return Response
     * @throws \Exception
     */
    final static function jsonp($data){
        $jsonp_call = isset($_GET[\myphp::$cfg['jsonp_call']])?$_GET[\myphp::$cfg['jsonp_call']]:\myphp::$cfg['jsonp_call'];
        $data = Helper::toJson($data);
        if ($data === false) {
            throw new \Exception('Invalid JSONP');
        }
        \myphp::res()->body = $jsonp_call . '(' . $data . ');';
        return \myphp::res()->setContentType(Response::CONTENT_TYPE_JSONP);
        //\myphp::conType('application/javascript');
        //return $jsonp_call . '(' . $data . ');';
    }

    /**
     * xml类型输出
     * @param $data
     * @return Response
     */
    final static function xml($data){
        \myphp::res()->body = Helper::toXml($data);
        return \myphp::res()->setContentType(Response::CONTENT_TYPE_XML);
        //\myphp::conType('application/xml');
        //return Helper::toXml($data);
    }
}