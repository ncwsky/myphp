<?php

declare(strict_types=1);

namespace myphp;

use Exception;
use myphp;

//模板解析基类 解析模板文件并判断是否需要在缓存目录生成缓存文件
class Template
{
    public $viewPath = ''; //模板文件路径
    public $cachePath = '';	//缓存文件路径
    public $cache = false;	//是否缓存
    public $cacheLifeTime = 0;	//缓存文件更新时间
    public $suffix = '.html';	//模板文件后缀名
    public $leftTag = '{';	//模板左边界符号
    public $rightTag = '}';	//模板右边界符号
    public $var_dot = 'array'; //.语法变量识别，array|obj|'', 为空时自动识别
    private $templateFile = '';	//当前模板文件名
    private $cacheFile = '';	//当前缓存文件名
    private $level = 0;
    private $maxLevel = 0; //模板嵌套层次 层次深度
    private $dir = []; //模板嵌套 模板层次关系 模板内容 存放数组

    public function __construct(string $path = '', string $cachePath = '')
    {
        $this->viewPath = $path ?: APP_PATH . '/view';
        $this->cachePath = $cachePath ?: APP_PATH . '/cache';
    }

    /**
     * 初始化模板文件夹以及缓存文件完整路径
     * @param string $file
     * @throws Exception
     */
    private function initFile(string $file): void
    {
        //判断模板文件是否存在
        $templateFile = ($file[0] == '/' ? ROOT : $this->viewPath . DS) . $file;
        if (!is_file($templateFile)) {
            throw new Exception('模板文件' . str_replace(ROOT, '', $templateFile) . '不存在');
        }

        $this->templateFile = $templateFile;
        $this->cacheFile = $this->cachePath . DS . str_replace(['/','.'], '_', $file) . '.php';

        // 必须重置 level / maxLevel：常驻内存模式（swoole/workerman/roadrunner）下
        // View 是单例，Template 实例跨请求复用；任一次解析中途异常或 build 中途中断，
        // 都会让 maxLevel 残留非零值。下一次请求若进入未命中缓存的 build()，
        // 会用旧的 maxLevel 访问当前 dir['level'][N]，触发 "Undefined array key N"。
        $this->level = 0;
        $this->maxLevel = 0;
        $this->dir['level'] = [];
        $this->dir['file'] = [];
    }

    /**
     * 模板处理并返回模板处理后的缓存文件
     * @param string $file
     * @return string
     * @throws Exception
     */
    public function cacheFile(string $file): string
    {
        $this->cacheLifeTime *= 60;
        $this->initFile($file);
        //验证是否需要更新缓存
        if (!$this->checkCache()) {
            $this->analyze();
            $this->build();
        }
        return $this->cacheFile;
    }
    //显示模板、返回模板内容
    public function display(string $file, array &$tVars = [])
    {
        $this->cacheFile($file);
        //将模板变量数组，导出为变量
        extract($tVars);
        //载入模板缓存文件
        ob_start();
        require $this->cacheFile;
        return ob_get_clean();
    }
    //模板样式 图片路径替换 可设指定路径
    private function resetPath(string &$content): void
    {
        //app资源路径动态输出 app_dynamic_path
        $path = isset(myphp::$cfg['app_dynamic_path']) ? '<?php echo \myphp::$cfg[\'app_dynamic_path\'];?>' : myphp::$cfg['app_res_path'];
        $content = preg_replace('/(link|link rel="stylesheet"|img|script)(\s*?)(src=|href=)"(?!http:\/\/|https:\/\/|\/|<)(.*?)"/i', "$1$2$3\"$path/$4\"", $content); #< -> <?php
    }
    //组合经　analyze　解析后的模板内容
    private function build(): void
    {
        //生成模板缓存嵌套记录数组文件
        $content = '<?php exit;//' . serialize($this->dir['level']);
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        file_put_contents(strtr($this->cacheFile, ['.php' => '_.php']), $content);

        $content = '';
        //'从最次层开始
        for ($this->maxLevel; $this->maxLevel > 0; $this->maxLevel--) {
            //'读取当前层次的页面列表 遍历
            $fileArr = explode(',', $this->dir['level'][$this->maxLevel]);
            foreach ($fileArr as $filelist) {
                $files = explode('->', $filelist);
                //'如果存在上级关系 为上级完成替换
                if (!empty($files[0])) {
                    $s1 = $this->dir['file'][$files[0]];
                    $s2 = $this->dir['file'][$files[1]];

                    $s1 = str_replace('{include:'. $files[1] .'}', "\n $s2 \n", $s1); //'替换包含页面内容
                    $this->dir['file'][$files[0]] = $s1; //'重新植入
                } else {
                    $content .= $this->dir['file'][$files[1]];
                }
            }
        }
        $this->resetPath($content);
        file_put_contents($this->cacheFile, $content);
    }
    //模板文件分析  $fatherPath 当前页面的上级路径 $doFile 待解析模板页面
    private function analyze(string $fatherPath = '', string $doFile = ''): void
    {
        $this->level++;	//层次
        if ($this->level > $this->maxLevel) { //设置最深层次
            $this->maxLevel = $this->level;
        }
        //如果dofile为空表示是模板入口页面
        if ($doFile == '') {
            $doFile = $this->templateFile;
        }

        $content = $this->doTmp($doFile); //获取模板解析后的内容
        $includeVal = $this->doInclude($content); //获取模板中的包含页面

        //取当前模板 关键数组的键名 keyname
        if ($this->level == 1) {
            $keyName = $this->templateFile;
        } else {
            $keyName = $doFile;
        }
        //层次记录检查并记录 返回true就表示当前层次模板已经存在并记录了
        $hasLevel = false; //'层次记录是否已经存在当前页面层次记录标记
        $levelVal = $fatherPath . "->" . $keyName;
        if (!empty($this->dir['level'][$this->level])) {//'判断是否存在此层次记录
            $tmpLevelVal = $this->dir['level'][$this->level]; //'获取此层的层次记录
            if (strstr(','. $tmpLevelVal .',', ','. $levelVal .',')) {
                $hasLevel = true;
            } else {
                $levelVal .= "," . $tmpLevelVal;
                $this->dir['level'][$this->level] = $levelVal; //'重新设置记录关系
            }
        } else {
            $this->dir['level'][$this->level] = $levelVal; //'记录关系
        }

        //获取当递归调用时页面的上级路径
        $fatherPath = $keyName;
        if (!$hasLevel) {
            //'当前模板页面解析内容存入关键数组中 此处需要重复模板验证（完整路径方式）
            if (empty($this->dir['file'][$keyName])) {
                $this->dir['file'][$keyName] = $content;
            }

            //'当前模板包含页面
            if ($includeVal != '') {
                $arrFile = explode(',', $includeVal);
                //遍历包含页面
                foreach ($arrFile as $file) {
                    $this->analyze($fatherPath, $file); //'递归模板分析
                }
                //$fatherPath = ''; //重置
            }
        }
        $this->level--;	//层次上下关系递归递减
    }
    //处理模板文件
    private function doTmp(string $tmpfile)
    {
        $content = file_get_contents($tmpfile);
        if ($content === false) {
            throw new \Exception('模板文件读取失败: ' . str_replace(ROOT, '', $tmpfile)
                . (($this->level > 1 && $this->templateFile !== $tmpfile)
                    ? '（被 ' . str_replace(ROOT, '', $this->templateFile) . ' 包含）'
                    : ''));
        }
        //替换系统常量
        $patt = ['__URL__', '__APP__'];
        $replace = ['<?php echo \myphp::env("URL"); ?>','<?php echo \myphp::env("APP"); ?>'];
        //自定义模板变量
        if (!empty(myphp::$cfg['tmp_variables'])) {
            foreach (myphp::$cfg['tmp_variables'] as $name => $val) {
                $patt[] = $name;
                $replace[] = '<?php echo \myphp::$cfg["tmp_variables"]["'.$name.'"]; ?>';
            }
        }
        $content = str_replace($patt, $replace, $content);
        $patt = preg_quote($this->leftTag) .'(\S.+?)'. preg_quote($this->rightTag);
        $content = preg_replace_callback("/{$patt}/", [$this,'parseTag'], $content); //i 不区分大小写 s .匹配所有的字符，包括换行符 e PHP代码求值并替换
        return $content;
    }
    //包含页面检查 按引用方式传递 正则查找 区分大小写（如果不区分大小写加i)
    private function doInclude(string &$data): string
    {
        $files = ''; //包含页面存放变量
        /*兼容 {include:file} 模式*/
        preg_match_all('/{include:(\.\.\/)*(.+?)}/', $data, $arr);
        if (!empty($arr[0])) {
            foreach ($arr[0] as $k => $v) {
                $file = $this->transPath($arr[1][$k] . $arr[2][$k]);
                $data = str_replace($v, '{include:' . $file . '}', $data);
                $files = $files == '' ? $file : $files . ',' . $file;
            }
            unset($arr);
        }
        //<?php include模式
        preg_match_all('/<\?php\s+include\s*\(?["\'](\.\.\/)*(.+?)["\']\)?;\s*\?>/', $data, $arr);
        if (!empty($arr[0])) {
            foreach ($arr[0] as $k => $v) {
                $file = $this->transPath($arr[1][$k] . $arr[2][$k]);
                $data = str_replace($v, '{include:' . $file . '}', $data);
                $files = $files == '' ? $file : $files . ',' . $file;
            }
            unset($arr);
        }
        return $files;
    }
    //模板地址转换
    private function transPath(string $tagFile): string
    {
        // 此处需要判断是否需要默认目录 '/'开头表示根目录
        return substr($tagFile, 0, 1) == '/' ? ROOT . $tagFile : $this->viewPath . DS . $tagFile;
    }
    //验证缓存是否有效
    private function checkCache(): bool
    {
        if (!is_file($this->cacheFile)) {
            return false;
        }
        //启用缓存 直接使用缓存文件
        if ($this->cache) {
            return true;
        }

        $max_mtime = 0;
        //获取模板缓存嵌套记录数组 可考虑include模式 但cli下会被缓存
        $content = substr(file_get_contents(str_replace('.php', '_.php', $this->cacheFile)), 13);
        if ($content[2] == '1') { //仅一个模板文件时
            $max_mtime = filemtime($this->templateFile);
        } else {
            $dirLevel = unserialize($content);
            if (!empty($dirLevel)) {
                //'读取当前层次的页面最大修改时间
                foreach ($dirLevel as $val) {
                    $fileArr = explode(',', $val);
                    foreach ($fileArr as $filelist) {
                        $files = explode('->', $filelist);
                        $mtime = filemtime($files[1]); //获取各页面的修改时间
                        if ($mtime > $max_mtime) {
                            $max_mtime = $mtime;
                        }
                    }
                }
                unset($dirLevel);
            }
        }
        $cacheFileMtime = filemtime($this->cacheFile);
        if ($cacheFileMtime < $max_mtime) {
            return false;
        }
        if ($this->cacheLifeTime && $cacheFileMtime + $this->cacheLifeTime < time()) {
            return false;
        }
        return true;
    }
    private $n_level = -1; //循环统计标识名层级
    private $n_tag = []; //循环统计标识名
    //解析{}中的内容，根据第一个字符决定使用什么函数进行解析
    private function parseTag(array $matches): string //$label
    {
        if (!isset($matches[1]) || $matches[1] == '') {
            return '';
        }
        $label = $matches[1];
        $flag = substr($label, 0, 1);
        $flags = ['isset' => '?', 'php' => '~', 'var' => '$', 'language' => '@', 'config' => '#', 'echo' => '*'];
        $name = substr($label, 1);//排除标识符
        $n_level = &$this->n_level;//循环统计标识名层级
        $n_tag = &$this->n_tag;//循环统计标识名

        //isset : ?$v[=$fun][:$defVal]
        if ($flag == $flags['isset'] && substr($label, 1, 1) == '$') {
            $defVal = "''";
            if (strpos($name, ':')) {
                [$name, $defVal] = explode(':', $name, 2);
            }
            if (strpos($name, '=')) { // 指定处理方法
                [$name, $fun] = explode('=', $name, 2);
                return '<?php echo isset('.$name.')?'.$fun.'('.$name.'):'.$defVal.'; ?>';
            }
            return '<?php echo isset('.$name.')?'.$name.':'.$defVal.'; ?>';
        }
        //直接php语句
        if ($flag == $flags['php']) {
            return '<?php '.$name.' ?>';
        }
        //普通变量
        if ($flag == $flags['var']) {
            return !empty($name) ? $this->parseVar($name) : '';
        }
        //输出语言
        if ($flag == $flags['language']) {
            return '<?php echo GetL(\''.$name.'\'); ?>';
        }
        //输出配置信息
        if ($flag == $flags['config']) {
            return '<?php echo GetC(\''.$name.'\'); ?>';
        }
        //输出
        if ($flag == $flags['echo']) {
            return '<?php echo '.$name.'; ?>';
        }
        //语句结束部分 list -> foreach结束
        if ($flag == '/') { //list if
            if ($name == 'list') {
                $ix = $n_level--;
                return '<?php $'.$n_tag[$ix].'++;}unset($'.$n_tag[$ix].'); ?>';
            } elseif ($name == 'if') {
                return '<?php end'.$name.'; ?>';
            }
        }
        //foreach开始
        if (substr($label, 0, 4) == 'list') {
            //示例 list $retData -> $retData as $key=>$val; list $retData $custom -> $retData as $k_custom=>$custom
            preg_match_all('/(\S+)/', substr($label, 5), $arr);
            $arr = $arr[1];
            if (count($arr) > 0) {
                $n_level++;
                $n_tag[$n_level] = 'n';
                $key_name = '$key';
                if (!isset($arr[1])) {
                    $arr[1] = 'val';
                } else {
                    if (substr($arr[1], 0, 1) == '$') {
                        $arr[1] = substr($arr[1], 1);
                    }
                    $n_tag[$n_level] = 'n_'.$arr[1];//n_数据别名 从1自增
                    $key_name = '$k_'.$arr[1];
                }
                //if(substr($arr[0],0,1)!='$') $arr[0]='$'.$arr[0];
                return '<?php $'.$n_tag[$n_level].'=1;if(isset('.$arr[0].') && is_array('.$arr[0].')) foreach('.$arr[0].' as '.$key_name.'=>$'.$arr[1].') { ?>';
            }
        }
        //if elseif
        if (substr($label, 0, 2) == 'if' || substr($label, 0, 6) == 'elseif') {
            $arr = explode(' ', $name);
            array_shift($arr);
            $param = [];
            foreach ($arr as $v) {
                if (strpos($v, '.') > 0) {
                    $args = explode('.', $v);
                    $p = $this->arrayHandler($args, 1);
                    $param[] = $args[0] . $p;
                } else {
                    $param[] = $v;
                }
            }
            $str = implode(' ', $param);
            $tag = substr($label, 0, 2) == 'if' ? 'if' : 'elseif';
            return '<?php '.$tag.'('.$str.') : ?>';
        }
        //else
        if (substr($label, 0, 4) ==  'else') {
            return '<?php else :?>';
        }
        return $this->leftTag . $label . $this->rightTag;
    }
    //解析变量
    private function parseVar(string $varStr): string
    {
        static $tVars = [];
        if (isset($tVars[$varStr])) { //存在相同的变量将直接返回
            return $tVars[$varStr];
        }
        //以=分割，数组第一位是变量名，之后的都是函数及参数  示例：row = getfolder($id)
        $varArray = explode('=', $varStr);
        $var = array_shift($varArray);//弹出第一个元素，也就是变量名
        /*  变量处理 start */
        if (strpos($var, '.')) { //$var.xxx方式访问数组或属性
            $var = explode('.', $var);
            $first = '$'.array_shift($var);
            switch ($this->var_dot) {
                case 'array': // 识别为数组
                    $name = $first . '[\'' . implode('\'][\'', $var) . '\']';
                    break;
                case 'obj': // 识别为对象
                    $name = $first . '->' . implode('->', $var);
                    break;
                default: // 自动判断数组或对象
                    $name = '(is_array(' . $first . ')?' . $first . '[\'' . implode('\'][\'', $var) . '\']:' . $first . '->' . implode('->', $var) . ')';
            }
        } else {
            $name = '$'.$var;
        }
        /*  变量处理 end  */
        if (count($varArray) > 0) {//如果有使用函数
            //传入变量名，和函数参数继续解析，这里的变量名是上面的判断设置的值
            $name = $this->parseFunction($name, $varArray); //多分函数支持 = 分隔 从左到右
            $code = '<?php '. $name .' ?>';
        } else {
            $code = '<?php echo '. $name .'; ?>';
        }
        $tVars[$varStr] = $code;//记录模板变量
        return $code;
    }

    //解析函数
    private function parseFunction(string $name, array $varArray): string
    {
        $code = '';
        $len = count($varArray);
        for ($i = 0; $i < $len; $i++) {
            //以=分割函数参数，第一个元素就是函数名，之后的都是参数
            $arr = explode('|', $varArray[$i]);
            $funcName = array_shift($arr);//函数名
            $arr = array_shift($arr);//函数参数

            $param = '';
            if ($arr !== '') { //参数不为空   字符的参数需要加上单引号
                $args = explode(',', $arr);
                foreach ($args as $var) {
                    $var = trim($var);
                    if ($var == 'this') {
                        $var = $name;
                    }
                    $param = $param == '' ? $var : $param . ', ' . $var;
                }
            }
            if ($funcName == 'echo') {
                $code .= $funcName . '(' . $name . ');';
            } else {
                $code .= $name . '=' . $funcName . '(' . $param . ');';
            }
        }
        return $code;
    }
    //构造数组下标
    private function arrayHandler(array &$arr, int $go = 2): string
    {
        $len = count($arr);
        $param = '';
        for ($i = $go; $i < $len; $i++) {
            $param .= "['" . $arr[$i] . "']";
        }
        return $param;
    }
}
