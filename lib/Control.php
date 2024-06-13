<?php
namespace myphp;

//控制器基类，所有的控制器需要继承此类
use myphp;

class Control
{
    /**
     * @var View|null
     */
    public $view = null; //模板实例
    public $enableCsrf = false;
    public $htmlEncode = false; //对模板数据html实体处理
    /**
     * @var Response|null
     */
    public $response = null;
    /**
     * @var Request|null
     */
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
        $this->response = myphp::res();
        $this->request = myphp::req();
        $this->view = View::getInstance(myphp::env('VIEW_PATH'), myphp::env('CACHE_PATH'));
        $this->_init();
    }

    protected function _init()
    {
        //todo
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function _before(){
        if ($this->enableCsrf) {
            if ($this->request::method() == 'GET') {
                //$this->view->vars['csrfToken'] = $this->request->csrfToken();
            } else {
                if (!verifyCsrfToken()) {
                    throw new \Exception('Unable to verify your data submission.', 400);
                }
            }
        }
        return true;
    }

    /**
     * @param $result
     * @return Response|mixed|null
     */
    protected function _after($result){
        if (!empty(myphp::$cfg['gzip'])) {
            if ($result instanceof Response) {
                $body = $result->getBody();
                if (strlen($body) > myphp::$cfg['gzip_min_length']) {
                    $result->setHeader('Content-Encoding', 'gzip');
                    $result->body = gzencode($body, myphp::$cfg['gzip_comp_level']);
                }
                return $result;
            } elseif (is_object($result)) {
                return $result;
            } elseif (is_array($result)) { // || is_object($result)
                $result = Helper::toJson($result);
            }
            if (strlen($result) > myphp::$cfg['gzip_min_length']) {
                $this->response->setHeader('Content-Encoding', 'gzip');
                $result = gzencode($result, myphp::$cfg['gzip_comp_level']);
            }
        }
        return $result;
    }

    /**
     * 执行动作
     * @param string $action
     * @return mixed|Response|null
     * @throws \Exception
     */
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
    //在子类控制器及方法中调用 显示模板 非cli模式下使用
    final function display($file = '', $var = null, $htmlEncode=null)
    {
        $this->response->setContentType(Response::CONTENT_TYPE_HTML);
        if ($htmlEncode === null) $htmlEncode = $this->htmlEncode;
        $content = $this->view->fetch($file, $var, $htmlEncode);
        if (IS_CLI) return $content;
        ob_start();
        echo $content;
        ob_end_flush();
        return ob_get_clean();
    }

    /**
     * 在子类控制器及方法中调用 取得页面内容
     * @param string $file
     * @param null $var
     * @param bool $htmlEncode
     * @return Response
     */
    final function fetch($file = '', $var=null, $htmlEncode=null)
    {
        if ($htmlEncode === null) $htmlEncode = $this->htmlEncode;
        $this->response->body = $this->view->fetch($file, $var, $htmlEncode);
        return $this->response->setContentType(Response::CONTENT_TYPE_HTML);
    }
    final static function redirect($url, $code=302){
        return myphp::res()->redirect($url, $code);
    }

    /**
     * html内容
     * @param $data
     * @return Response
     */
    final static function html($data)
    {
        myphp::res()->body = $data;
        return myphp::res()->setContentType(Response::CONTENT_TYPE_HTML);
    }

    /**
     * json类型输出
     * @param mixed $data
     * @param bool $encode
     * @return Response
     */
    final static function json($data, $encode=true){
        myphp::res()->body = $encode ? Helper::toJson($data) : $data;
        return myphp::res()->setContentType(Response::CONTENT_TYPE_JSON);
    }

    /**
     * jsonp类型输出
     * @param mixed $data
     * @param bool $encode
     * @return Response
     * @throws \Exception
     */
    final static function jsonp($data, $encode=true){
        $jsonp_call = isset($_GET[myphp::$cfg['jsonp_call']])?$_GET[myphp::$cfg['jsonp_call']]: myphp::$cfg['jsonp_call'];
        $data = $encode ? Helper::toJson($data) : $data;
        if ($data === false) {
            throw new \Exception('Invalid JSONP');
        }
        myphp::res()->body = $jsonp_call . '(' . $data . ');';
        return myphp::res()->setContentType(Response::CONTENT_TYPE_JSONP);
    }

    /**
     * xml类型输出
     * @param mixed $data
     * @param bool $encode
     * @return Response
     */
    final static function xml($data, $encode=true){
        myphp::res()->body = $encode ? Helper::toXml($data) : $data;
        return myphp::res()->setContentType(Response::CONTENT_TYPE_XML);
    }
}