<?php
//视图类
class View
{
    public $vars = array();    //板变量数组
    public $view_path = './view';
    private $template = null;    //模板引擎实例
    /**
     * @var View $instance
     */
    private static $instance = null;

    //构造方法，实例化视图
    public function __construct($path='', $cachePath='')
    {
        if($path) $this->view_path = $path;
        if(!$cachePath) $cachePath = './cache';

        $this->template = new Template();
        $this->template->cache = true;    //设置是否开启缓存
        $this->template->cachePath = $cachePath;    //缓存路径
        $this->template->suffix = isset(Config::$cfg['tmp_suffix']) ? Config::$cfg['tmp_suffix'] : '.html';    //模板后缀名
        $this->template->leftTag = isset(Config::$cfg['tmp_left_tag']) ? Config::$cfg['tmp_left_tag'] : '{';    //模板左侧符号
        $this->template->rightTag = isset(Config::$cfg['tmp_right_tag']) ? Config::$cfg['tmp_right_tag'] : '}';    //模板右侧符号
    }

    //单例模式
    public static function getInstance($path, $cachePath='')
    {
        if (!self::$instance) {
            self::$instance = new self($path, $cachePath);
        }else{
            if($path) self::$instance->view_path = $path;
            if($cachePath) self::$instance->template->cachePath = $cachePath;
        }
        return self::$instance;
    }

    //初始模板
    private function init($file)
    {
        if($file[0]=="/") { //模板路径
            $this->template->path = ROOT.ROOT_DIR;
        }else{
            $this->template->path = $this->view_path;
        }
        $templateFile = $this->template->path . DS . $file;    //定义模板文件路径 .'.html'
        //判断模板文件是否存在
        if (!is_file($templateFile)) {
            throw new Exception('模板文件' . $file . '不存在');
        }
    }

    //取得页面内容
    public function fetch($file = '', $var = null)
    {
        if ($file == '')
            $file = myphp::env('ACTION') . $this->template->suffix;
        $this->init($file);
        if (is_array($var)) {    //如果是数组，那么将它合并到属性$vars中
            $this->vars = array_merge($this->vars, $var);
        }
        return $this->template->display($file, $this->vars);//返回内容
    }

    //输出页面内容
    public function display($file = '', $var = null)
    {
        $content = $this->fetch($file, $var);
        $this->obstart();
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

    //静态方法 直接返回解析后的php文件
    public static function dotemp($file = '')
    {
        if(!self::$instance){
            self::$instance = new self(myphp::env('VIEW_PATH'), myphp::env('CACHE_PATH'));
        }
        if ($file == '')
            $file = myphp::env('ACTION') . self::$instance->template->suffix;
        self::$instance->init($file);

        return self::$instance->template->cacheFile($file);
    }

    //打开输出缓冲
    public static function obstart()
    {
        // 配置变量isGzipEnable Gzip压缩开关
        if (function_exists('ob_gzhandler') && isset(Config::$cfg['isGzipEnable']) && Config::$cfg['isGzipEnable']) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }
}