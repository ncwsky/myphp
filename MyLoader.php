<?php
//注册类的自动加载
spl_autoload_register('MyLoader::autoload', true, true);

class MyLoader
{
    public static $rootPath = __DIR__;
    public static $classDir = []; //设置可加载的目录 ['dir'=>1,....]
    public static $namespaceMap = []; // ['namespace\\'=>'src/']
    public static $classMap = []; //['myphp'=>__DIR__.'/myphp.php']; //设置指定的类加载 示例 类名[命名空间]=>文件
    public static $classOldSupport = false; //是否兼容xxx.class.php

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

        $name = $class_name;
        if ($pos = strpos($class_name, '\\')) { //命名空间类加载
            $class_path = strtr($class_name, '\\', DIRECTORY_SEPARATOR);
            $namespace = substr($class_name, 0, $pos + 1);
            if (isset(self::$namespaceMap[$namespace])) { //优先加载命名空间映射
                return self::load(self::$rootPath . DIRECTORY_SEPARATOR . self::$namespaceMap[$namespace] . substr($class_path, strlen($namespace)) . '.php');
            }

            if (self::load(self::$rootPath . DIRECTORY_SEPARATOR . $class_path . '.php')) {
                return true;
            }
            //未匹配-取类名
            $pos = strrpos($class_path, DIRECTORY_SEPARATOR);
            $name = substr($class_path, $pos + 1);
        }

        //循环可存在类的目录
        foreach (self::$classDir as $path => $i) {
            if (self::load($path . DIRECTORY_SEPARATOR . $name . '.php')) {
                return true;
            }
            if (self::$classOldSupport && self::load($path . DIRECTORY_SEPARATOR . $name . '.class.php')) { //兼容处理
                return true;
            }
        }
        return false;
    }

    public static function load($path, $class_name = null)
    {
        if (is_file($path)) {
            include $path;
            return true;
            if ($class_name === null || \class_exists($class_name, false)) {
                return true;
            }
        }
        return false;
    }
}