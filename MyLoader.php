<?php
//注册类的自动加载
spl_autoload_register('MyLoader::autoload', true, true);

class MyLoader
{
    public static $rootPath = __DIR__;
    public static $classDir = []; //设置可加载的目录 ['dir'=>1,....]
    public static $namespaceMap = []; // ['namespace\\'=>'src/']; //命名空间路径映射 不指定完整路径使用rootPath
    public static $classMap = []; //['myphp'=>__DIR__.'/myphp.php']; //设置指定的类加载 示例 类名[命名空间]=>文件
    public static $classOldSupport = false; //是否兼容xxx.class.php

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
        if (isset(self::$classMap[$class_name])) { //优先加载类映射
            include self::$classMap[$class_name];
            return;
        }

        $name = $class_name;
        if ($len = strpos($class_name, '\\')) { //命名空间类加载
            $len += 1;
            $namespace = substr($class_name, 0, $len); //包含尾部\
            if (isset(self::$namespaceMap[$namespace])) { //优先加载命名空间映射
                $end = substr(self::$namespaceMap[$namespace], -1);
                if (self::$namespaceMap[$namespace][0] == '/' || (DIRECTORY_SEPARATOR == '\\' && strpos(self::$namespaceMap[$namespace], ':'))) { //绝对路径 | win
                    $path = self::$namespaceMap[$namespace];
                } else {
                    $path = self::$rootPath . DIRECTORY_SEPARATOR . self::$namespaceMap[$namespace];
                }
                self::load($path . ($end == '/' ? '' : DIRECTORY_SEPARATOR) . substr($class_name, $len) . '.php');
                return;
            }
            self::load(self::$rootPath . DIRECTORY_SEPARATOR . strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php');
            return;
        }

        //遍历可存在类的目录
        foreach (self::$classDir as $path => $i) {
            if (self::load($path . DIRECTORY_SEPARATOR . $name . '.php')) {
                return;
            }
            if (self::$classOldSupport && self::load($path . DIRECTORY_SEPARATOR . $name . '.class.php')) { //兼容处理
                return;
            }
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function load($path)
    {
        if (is_file($path)) {
            include $path;
            return true;
        }
        return false;
    }
}