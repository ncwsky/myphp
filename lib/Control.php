<?php

declare(strict_types=1);

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

    public const CODE_OK = 0; //成功
    public const CODE_FAIL = 1; //失败
    public const DATA_INVALID = 1000; //无效的请求数据

    public static $tpl = [
        self::CODE_OK => '操作成功',
        self::CODE_FAIL => '操作失败',
        self::DATA_INVALID => '无效的数据',
    ];
    public static function tpl($code): string
    {
        return self::$tpl[$code] ?? 'null';
    }
    public static function out($code = self::CODE_OK, $data = [], $info = null, $ext = null): array
    {
        $out = ['code' => $code, 'msg' => ($info === null ? self::tpl($code) : $info)];
        $out['data'] = $data;
        $out['ext'] = $ext;
        return $out;
    }
    public static function ok($data = null, $info = null, $ext = null, $code = self::CODE_OK): array
    {
        $out = ['code' => $code, 'msg' => ($info === null ? self::tpl($code) : $info)];
        $out['data'] = $data;
        $out['ext'] = $ext;
        return $out;
    }
    public static function fail($info = null, $code = self::CODE_FAIL, $data = null): array
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
    protected function _before()
    {
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
    protected function _after($result)
    {
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
    final public function _run(string $action)
    {
        //判断实例中是否存在action方法，不存在则提示错误
        if (!method_exists($this, $action)) {
            return $this->response->e404('method not exists ' . $action);
        }
        //throw new \Exception('method not exists ' . $action, 404);
        //前后置操作处理
        return $this->_before() ? $this->_after($this->$action()) : null;
    }

    //设置模板变量
    final public function assign(string $var, $value): void
    {
        $this->view->assign($var, $value);
    }
    //启用输出缓存
    final public function cache(int $expire = 0)
    {
        $this->request->expire = $expire; //0使用默认配置req_cache_expire
        return $this;
    }
    //在子类控制器及方法中调用 显示模板 非cli模式下使用
    final public function display(string $file = '', array $var = null, bool $htmlEncode = null)
    {
        $this->response->setContentType(Response::CONTENT_TYPE_HTML);
        if ($htmlEncode === null) {
            $htmlEncode = $this->htmlEncode;
        }
        $content = $this->view->fetch($file, $var, $htmlEncode);
        if (IS_CLI) {
            return $content;
        }
        ob_start();
        echo $content;
        ob_end_flush();
        return ob_get_clean();
    }

    /**
     * 在子类控制器及方法中调用 取得页面内容
     * @param string $file
     * @param array|null $var
     * @param bool $htmlEncode
     * @return Response
     */
    final public function fetch(string $file = '', array $var = null, bool $htmlEncode = null): Response
    {
        if ($htmlEncode === null) {
            $htmlEncode = $this->htmlEncode;
        }
        $this->response->body = $this->view->fetch($file, $var, $htmlEncode);
        return $this->response->setContentType(Response::CONTENT_TYPE_HTML);
    }
    final public static function redirect($url, $code = 302): Response
    {
        return myphp::res()->redirect($url, $code);
    }

    /**
     * html内容
     * @param $data
     * @return Response
     */
    final public static function html($data): Response
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
    final public static function json($data, bool $encode = true): Response
    {
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
    final public static function jsonp($data, bool $encode = true): Response
    {
        $jsonp_call = $_GET[myphp::$cfg['jsonp_call']] ?? myphp::$cfg['jsonp_call'];
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
    final public static function xml($data, bool $encode = true): Response
    {
        myphp::res()->body = $encode ? Helper::toXml($data) : $data;
        return myphp::res()->setContentType(Response::CONTENT_TYPE_XML);
    }
}
