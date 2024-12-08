<?php

declare(strict_types=1);

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
     * 选项的解析会终止于找到的第一个非选项，之后的任何东西都会被丢弃。
     * @param string $short
     * @param array $long
     * @return array
     */
    public static function parse(string $short, array $long = []): array
    {
        self::$options = getopt($short, $long);
        if (self::$options === false) {
            self::$options = [];
        }
        return self::$options;
    }

    /**
     * @param string $name
     * @param string $longName
     * @param string|int $def
     * @return mixed
     */
    public static function val(string $name, string $longName = '', $def = '')
    {
        $val = self::$options[$name] ?? $def;
        if ($longName !== '') {
            $val = self::$options[$longName] ?? $val;
        }
        return $val;
    }

    /**
     * 是否存在命令参数
     * @param string $name
     * @param string $longName
     * @return bool
     */
    public static function has(string $name, string $longName = ''): bool
    {
        if (isset(self::$options[$name])) {
            return true;
        }
        if ($longName !== '' && isset(self::$options[$longName])) {
            return true;
        }
        return false;
    }
}
