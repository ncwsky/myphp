<?php
/**
 * 示例
//解析命令参数 后面跟随冒号的字符（此选项需要值）,后面跟随两个冒号的字符（此选项的值可选）
GetOpt::parse('hasp:n:', ['help', 'all', 'swoole', 'port:', 'num:']);
//处理命令参数
$isSwoole = GetOpt::has('s', 'swoole');
$port = GetOpt::val('p', 'port', '55011');
$num = intval(GetOpt::val('n', 'num', 1));
$isAll = GetOpt::has('a', 'all');

if (GetOpt::has('h', 'help')) {
echo 'Usage: php Client.php OPTION [restart|stop]
or: Client.php OPTION [restart|stop]

-h --help
-n --num     进程数
-p --port    端口
-s --swoole     swolle运行',PHP_EOL;
exit(0);
}
 */

class GetOpt
{
    private static $options = [];
    /**
     * 解析命令 参见 https://www.php.net/manual/zh/function.getopt
     * @param $short
     * @param array $long
     * @return array
     */
    public static function parse($short, $long = [])
    {
        self::$options = getopt($short, $long);
        return self::$options;
    }

    /**
     * 获取命令参数值
     * @param $name
     * @param string $longName
     * @param mixed $def
     * @return mixed|string
     */
    public static function val($name, $longName = '', $def = '')
    {
        $val = $def;
        $val = isset(self::$options[$name]) ? self::$options[$name] : $val;
        if ($longName !== '') {
            $val = isset(self::$options[$longName]) ? self::$options[$longName] : $val;
        }
        return $val;
    }

    /**
     * 是否存在命令参数
     * @param $name
     * @param string $longName
     * @return bool
     */
    public static function has($name, $longName = '')
    {
        if (isset(self::$options[$name])) return true;
        if ($longName !== '' && isset(self::$options[$longName])) {
            return true;
        }
        return false;
    }
}