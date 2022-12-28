<?php
//视图类
class View
{
    public $vars = array();    //板变量数组
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
        if ($path=='') $path = './view';
        if ($cachePath=='') $cachePath = './cache';

        $this->template = new Template();
        $this->template->view_path = $path;
        $this->template->cache = true;    //设置是否开启缓存
        $this->template->cachePath = $cachePath;    //缓存路径
        $this->template->suffix = isset(\myphp::$cfg['tmp_suffix']) ? \myphp::$cfg['tmp_suffix'] : '.html';    //模板后缀名
        $this->template->leftTag = isset(\myphp::$cfg['tmp_left_tag']) ? \myphp::$cfg['tmp_left_tag'] : '{';    //模板左侧符号
        $this->template->rightTag = isset(\myphp::$cfg['tmp_right_tag']) ? \myphp::$cfg['tmp_right_tag'] : '}';    //模板右侧符号
    }

    //单例模式
    public static function getInstance($path, $cachePath='')
    {
        if (!self::$instance) {
            self::$instance = new self($path, $cachePath);
        }else{
            if($path) self::$instance->template->view_path = $path;
            if($cachePath) self::$instance->template->cachePath = $cachePath;
        }
        return self::$instance;
    }

    //取得页面内容
    public function fetch($file = '', &$var = null)
    {
        if ($file == '') $file = \myphp::env('a') . $this->template->suffix;
        if (is_array($var)) {    //如果是数组，那么将它合并到属性$vars中
            $this->vars = array_merge($this->vars, $var);
        }
        return $this->template->display($file, $this->vars);//返回内容
    }

    //输出页面内容
    public function display($file = '', $var = null)
    {
        $content = $this->fetch($file, $var);
        $this->obStart();
        echo $content;
        ob_end_flush();
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
     * 静态方法 直接返回解析后的php文件
     * @param string $file
     * @return string
     * @throws \Exception
     */
    public static function doTemp($file = '')
    {
        if(!self::$instance){
            self::$instance = new self(\myphp::env('VIEW_PATH'), \myphp::env('CACHE_PATH'));
        }
        if ($file == '') $file = \myphp::env('a') . self::$instance->template->suffix;

        return self::$instance->template->cacheFile($file);
    }

    //打开输出缓冲
    public static function obStart()
    {
        // 配置变量isGzipEnable Gzip压缩开关
        if (function_exists('ob_gzhandler') && isset(\myphp::$cfg['isGzipEnable']) && \myphp::$cfg['isGzipEnable']) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }
}