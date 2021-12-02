<?php
//注册类的自动加载
spl_autoload_register('MyLoader::autoload', true, true);

class MyLoader
{
    public static $rootPath = __DIR__;
    public static $classDir = []; //设置可加载的目录 ['dir'=>1,....]
    public static $namespaceMap = []; // ['namespace\\'=>'src/']
    public static $classMap = []; //['myphp'=>__DIR__.'/myphp.php']; //设置指定的类加载 示例 类名[命名空间]=>文件

    //读取或设置类加载路径
    public static function class_dir($dir)
    {
        //单独设置类加载路径需要写全地址
        if (is_array($dir)) self::$classDir = array_merge(self::$classDir, array_fill_keys($dir, 1));
        else self::$classDir[$dir] = 1;
    }

    public static function namespace_map($namespace, $dir = null)
    {
        if (is_array($namespace)) self::$namespaceMap = array_merge(self::$namespaceMap, $namespace);
        else self::$namespaceMap[$namespace] = $dir;
    }

    public static function class_map($name, $file = null)
    {
        if (is_array($name)) self::$classMap = array_merge(self::$classMap, $name);
        else self::$classMap[$name] = $file;
    }

    //自动加载对象
    public static function autoload($class_name)
    {
        if (isset(self::$classMap[$class_name])) { //优先加载类映射
            return self::load(self::$classMap[$class_name]);
        }

        $class_path = strtr($class_name, '\\', '/');
        $pos = strpos($class_name, '\\'); //strstr($class_name, '\\', true);
        if ($pos) {
            $namespace = substr($class_name, 0, $pos + 1);
            if (isset(self::$namespaceMap[$namespace])) { //优先加载类映射
                return self::load(self::$rootPath . DIRECTORY_SEPARATOR . self::$namespaceMap[$namespace] . substr($class_path, strlen($namespace)) . '.php');
            }
        }

        $separator = $class_path[0] == '/' ? '' : '/';
        $path = self::$rootPath . $separator . $class_path;
        //命名空间类加载
        if ($pos = strrpos($class_path, '/')) {
            if (self::load($path . '.php')) {
                return true;
            }
            if (self::load($path . '.class.php')) {  //兼容处理
                return true;
            }
        } else {
            //循环判断
            foreach (self::$classDir as $path => $v) {
                if (self::load($path . '.php')) {
                    return true;
                }
                if (self::load($path . '.class.php')) { //兼容处理
                    return true;
                }
            }
        }

        return false;
    }

    public static function load($path)
    {
        if (is_file($path)) {
            include $path;
            return true;
        }
        return false;
    }
}