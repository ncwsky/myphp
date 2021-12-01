<?php
//注册类的自动加载
spl_autoload_register('MyLoader::autoload', true, true);

class MyLoader
{
    public static $classDir = []; //设置可加载的目录 ['dir'=>1,....]
    public static $classList = []; //已加载的类
    public static $namespaceMap = []; // ['namespace\\'=>'src/']
    public static $classMap = []; //['myphp'=>__DIR__.'/myphp.php']; //设置指定的类加载 示例 类名[命名空间]=>文件
    public static $rootPath = __DIR__;

    //读取或设置类加载路径
    public static function class_dir($dir)
    {
        //单独设置类加载路径需要写全地址
        if (is_array($dir)) self::$classDir = array_merge(self::$classDir, array_fill_keys($dir, 1));
        else self::$classDir[$dir] = 1;
    }

    //自动加载对象
    public static function autoload($class_name)
    {
        if (isset(self::$classList[$class_name])) return true;

        if (isset(self::$classMap[$class_name])) { //优先加载类映射
            return self::load($class_name, self::$classMap[$class_name], true);
        }

        $class_path = strtr($class_name, '\\', '/');
        $namespace = strstr($class_name, '\\', true);
        $separator = $class_path[0] == '/' ? '' : '/';
        if ($namespace) $namespace .= '\\';
        if ($namespace && isset(self::$namespaceMap[$namespace])) { //优先加载类映射
            return self::load($class_name, self::$rootPath . DIRECTORY_SEPARATOR . self::$namespaceMap[$namespace] . substr($class_path, strlen($namespace)) . '.php', true);
        }

        $path = self::$rootPath . $separator . $class_path;
        //命名空间类加载 仿psr4
        if ($pos = strrpos($class_path, '/')) {
            if (self::load($class_name, $path . '.php')) {
                return true;
            }
            if (self::load($class_name, $path . '.class.php')) {  //兼容处理
                return true;
            }
        } else {
            //循环判断
            foreach (self::$classDir as $path => $v) {
                if (self::load($class_name, $path . '.php')) {
                    return true;
                }
                if (self::load($class_name, $path . '.class.php')) { //兼容处理
                    return true;
                }
            }
        }

        return false;
    }

    public static function load($class_name, $path, $map = false)
    {
        if (is_file($path)) {
            require_once($path);
            self::$classList[$class_name] = true;
            return true;
        }
        if ($map) self::$classList[$class_name] = false;
        return false;
    }
}