<?php
class MyLoader{
    public static $classDir = []; //设置可加载的目录
    public static $classList = []; //已加载的类
    public static $phpList = []; //已加载的php
    public static $classMap = []; //['myphp'=>__DIR__.'/myphp.php']; //设置指定的类加载 示例 类名[命名空间]=>文件

    //读取或设置类加载路径
    public static function class_dir($dir){
        //单独设置类加载路径需要写全地址
        if(is_array($dir)) self::$classDir = array_merge(self::$classDir, array_fill_keys($dir, 1));
        else self::$classDir[$dir] = 1;
    }
    //自动加载对象
    public static function autoload($class_name) {
        $class_name = strtr($class_name, '\\', '/');
        if (isset(self::$classList[$class_name])) return true;
        if (isset(static::$classMap[$class_name])) { //优先加载类映射
            return self::loadPHP(static::$classMap[$class_name], '', '');
        }
        //命名空间类加载 仿psr4
        if ($pos = strrpos($class_name, '/')) {
            $path = ROOT . ROOT_DIR . ($class_name[0] == '/' ? '' : '/') . substr($class_name, 0, $pos);
            $name = substr($class_name, $pos + 1);
            if (self::loadPHP($name, $path, '.php')) {
                self::$classList[$class_name] = true;
                return true;
            }
            if (self::loadPHP($name, $path, '.class.php')) {  //兼容处理
                self::$classList[$class_name] = true;
                return true;
            }
        }
        //循环判断
        foreach (self::$classDir as $path=>$v) {
            if (self::loadPHP($class_name, $path, '.class.php')) { //兼容处理
                self::$classList[$class_name] = true;
                return true;
            }
            if (self::loadPHP($class_name, $path, '.php')) {
                self::$classList[$class_name] = true;
                return true;
            }
        }
        return false;
    }
    /** 载入php文件
     * @param string $name         文件名
     * @param string $path  路径
     * @param string $ext   后缀
     * @return bool|object
     */
    public static function loadPHP($name, $path='', $ext='.php') {
        if($path!=='') $path = $path . (substr($path, -1, 1) == '/' ? '' : '/'); //尾部无“/”追加
        $path = $path . $name . $ext;
        if (isset(self::$phpList[$path])) {
            return true;
        }
        if (is_file($path)) {
            include $path;
            self::$phpList[$path] = true;
            return true;
        } else {
            self::$phpList[$path] = false;
        }
        return self::$phpList[$path];
    }
}
#defined('MY_PATH') or define('MY_PATH', __DIR__);
//注册类的自动加载
spl_autoload_register('MyLoader::autoload', true, true);