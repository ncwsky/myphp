<?php
namespace myphp;

use Exception;
use myphp;
//视图类
class View
{
    public $vars = [];    //板变量数组
    /**
     * @var Template
     */
    private $template;    //模板引擎实例
    /**
     * @var View $instance
     */
    private static $instance = null;

    //构造方法，实例化视图
    public function __construct($path='', $cachePath='')
    {
        if ($path=='') $path = APP_PATH.'/view';
        if ($cachePath=='') $cachePath = APP_PATH.'/cache';

        $this->template = new Template($path, $cachePath);
        $this->template->cache = !myphp::$cfg['debug'];    //设置是否开启缓存
        $this->template->suffix = isset(myphp::$cfg['tmp_suffix']) ? myphp::$cfg['tmp_suffix'] : '.html';    //模板后缀名
        $this->template->leftTag = isset(myphp::$cfg['tmp_left_tag']) ? myphp::$cfg['tmp_left_tag'] : '{';    //模板左侧符号
        $this->template->rightTag = isset(myphp::$cfg['tmp_right_tag']) ? myphp::$cfg['tmp_right_tag'] : '}';    //模板右侧符号
    }

    //单例模式
    public static function getInstance($path, $cachePath='')
    {
        if (!self::$instance) {
            self::$instance = new self($path, $cachePath);
        } else {
            if ($path) self::$instance->template->viewPath = $path;
            if ($cachePath) self::$instance->template->cachePath = $cachePath;
        }
        return self::$instance;
    }

    //取得页面内容
    public function fetch($file = '', &$var = null)
    {
        if ($file == '') $file = myphp::env('a') . $this->template->suffix;
        if (is_array($var)) {    //如果是数组，那么将它合并到属性$vars中
            $this->vars = array_merge($this->vars, $var);
        }

        $cacheFile = $this->template->cacheFile($file);
        //将模板变量数组，导出为变量
        extract($this->vars);
        //载入模板缓存文件
        ob_start();
        require $cacheFile;
        return ob_get_clean();
        return $this->template->display($file, $this->vars);//返回内容
    }

    //输出页面内容
    public function display($file = '', $var = null)
    {
        $content = $this->fetch($file, $var);
        ob_start();
        echo $content;
        ob_end_flush();
        return ob_get_clean();
    }

    //设置模板变量
    public function assign($var, $value = null)
    {
        if (is_array($var)) {    //如果是数组，那么将它合并到属性$vars中
            $this->vars = array_merge($this->vars, $var);
        } else { //否则以$var为下标$value为值，增加到$vars中
            $this->vars[$var] = $value;
        }
    }

    /**
     * 直接返回解析后的php文件  ob_start(); require self::doTemp(); return ob_get_clean();
     * @param string $file
     * @return string
     * @throws Exception
     */
    public static function doTemp($file = '')
    {
        if(!self::$instance){
            self::$instance = new self(myphp::env('VIEW_PATH'), myphp::env('CACHE_PATH'));
        }
        if ($file == '') $file = myphp::env('a') . self::$instance->template->suffix;

        return self::$instance->template->cacheFile($file);
    }

    public static function end()
    {
        return ob_get_clean();
    }
}