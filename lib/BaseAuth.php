<?php

declare(strict_types=1);

namespace myphp;

/**
 * 简单角色字符串规则权限处理
 */
class BaseAuth
{
    use \MyMsg;

    protected static $roleRules = [];

    //cfg : roles[role=>purview, ...]
    public static function getPurview($roleId = 0): string
    {
        if ($roleId === 0) {
            $roleId = session('role');
        }

        $roles = \myphp::get('roles', []);
        return $roles[$roleId] ?? '';
    }

    /**
     * 简易权限验证 _all,!admin,!role/del,admin,!admin/del,post admin/save
     * 所有权限 $purview = _all
     * 允许所有权限但存在排除的模块、模块.方法 $purview = _all,!c1,!c2/index
     * @return bool|string
     */
    public static function tinyPurview(string $mca = '', string $method = '', $roleId = 0)
    {
        if ($mca) {
            if ($pos = strpos($mca, '?')) { // index/ask?id=1
                $mca = substr($mca, 0, $pos);
            }
            [, $c, $a] = \myphp::deMCA($mca);
        } else {
            $c = strtolower(\myphp::$env['c']);  //获得控制器名
            $a = strtolower(\myphp::$env['a']);  //获得方法名
        }
        $purview = static::getPurview($roleId);
        if (!$purview) {
            return self::err('用户角色没有权限配置信息');
        }

        $cErr = '用户角色没有' . $c . '的权限!';
        $purview = ',' . $purview . ',';
        if ($method === '') {
            $method = strtolower(Request::method());
        }
        //优先排除
        //排除的模块
        if (strpos($purview, ',!' . $c . ',') !== false) {
            return self::err($cErr);
        }
        $ca = $c . '/' . $a;
        $aErr = '用户角色没有' . $ca . '操作的权限!';
        //排除的模块.方法|模块.[指定请求]方法
        if (strpos($purview, ',!' . $ca . ',') !== false || strpos($purview, ',!' . $method . ' ' . $ca . ',') !== false) {
            return self::err($aErr);
        }
        //所有权限 | 模块权限| 模块.方法 | 模块.[指定请求]方法
        if (strpos($purview, ',_all,') !== false || strpos($purview, ',' . $c . ',') !== false || strpos($purview, ',' . $ca . ',') !== false || strpos($purview, ',' . $method . ' ' . $ca . ',') !== false) {
            return true;
        }
        return self::err($aErr);
    }

    protected static function parsePurview($roleId): array
    {
        if (isset(static::$roleRules[$roleId]) && static::$roleRules[$roleId][0] > time()) {
            return static::$roleRules[$roleId][1];
        }/*
        if ($rules = \myphp::cache()->get('roles.' . $roleId)) {
            return $rules;
        }*/

        $purview = static::getPurview($roleId);
        $rules = ['deny' => [], 'allow' => []];
        $items = explode(',', $purview);
        //$httpMethods = ['get', 'post', 'patch', 'put', 'delete', 'head', 'options'];
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) {
                continue;
            }

            $deny = false;
            if ($item[0] === '!') {
                if (substr($item, 1) === '_all') { //_all前加“!”无效
                    continue;
                }
                $deny = true;
                $item = substr($item, 1);
            }

            if ($item === '_all') {
                $rules['allow'][] = ['*', '*', '*', []];
                continue;
            }

            // 解析方法和路径
            $method = '*';
            if ($pos = strpos($item, ' ')) { //不对有效性判断
                $method = substr($item, 0, $pos);
                $item = substr($item, $pos + 1);
            }
            // 解析参数
            $params = [];
            if ($pos = strpos($item, '?')) {
                parse_str(substr($item, $pos + 1), $params);
                $item = substr($item, 0, $pos);
            }

            // 解析控制器和方法
            if (strpos($item, '/')) {
                [$controller, $action] = explode('/', $item, 2);
            } else {
                $controller = $item;
                $action = '*';
            }

            $rule = [$method, $controller, $action, $params];
            if ($deny) {
                $rules['deny'][] = $rule;
            } else {
                $rules['allow'][] = $rule;
            }
        }
        static::$roleRules[$roleId] = [time() + 180, $rules]; //cli模式下缓存x秒
        //\myphp::cache()->set('roles.' . $roleId, $rules);
        return $rules;
    }

    protected static function matchRule(array $rule, string $method, string $c, string $a, array $params): bool
    {
        list($rMethod, $rC, $rA, $rParams) = $rule;

        // 方法匹配
        if ($rMethod !== '*' && strpos($rMethod, $method) === false) {
            return false;
        }

        // 控制器匹配
        if ($rC !== '*' && $rC !== $c) {
            return false;
        }

        // 方法匹配
        if ($rA !== '*' && $rA !== $a) {
            return false;
        }

        // 参数匹配
        foreach ($rParams as $k => $v) {
            if (!isset($params[$k]) || (string)$params[$k] !== (string)$v) {
                return false;
            }
        }

        return true;
    }

    public static function matchPurview($mca = '', $method = '', $roleId = 0)
    {
        $get = [];
        if ($mca) {
            if ($pos = strpos($mca, '?')) { // index/ask?id=1
                parse_str(substr($mca, $pos + 1), $get);
                $mca = substr($mca, 0, $pos);
            }
            [, $c, $a] = \myphp::deMCA($mca);
        } else {
            $c = strtolower(\myphp::$env['c']);  //获得控制器名
            $a = strtolower(\myphp::$env['a']);    //获得方法名
            $get = $_GET;
        }
        if ($method === '') {
            $method = strtolower(Request::method());
        }

        $rules = static::parsePurview($roleId);
        $aErr = '用户角色没有' . $c . '/' . $a . '操作的权限!';
        //优先拒绝规则
        foreach ($rules['deny'] as $rule) {
            if (static::matchRule($rule, $method, $c, $a, $get)) {
                return self::err($aErr);
            }
        }
        //允许规则
        foreach ($rules['allow'] as $rule) {
            if (static::matchRule($rule, $method, $c, $a, $get)) {
                return true;
            }
        }
        return self::err($aErr);
    }

    protected static $prevChr = ['!', ' ', ','];
    protected static $nextChr = ['?', ','];

    /**
     * 复杂权限验证 统一小写 _all,!admin,!role/del,admin,!admin/del,post admin/save?var=1
     * 所有权限 $purview = _all
     * 允许所有权限但存在排除的模块、模块.方法 $purview = _all,!c1,!c2/index
     * $purview = ['c1'=>true|1,'c2'=>['a2'=>true,'a21'=>true],'c3'=>['_all'=>true,'a3'=>false]]
     * @return bool|string
     */
    public static function chkPurview($mca = '', $method = '', $roleId = 0)
    {
        $purview = static::getPurview($roleId);
        if (!$purview) {
            return self::err('用户角色没有权限配置信息');
        }
        $get = [];
        if ($mca) {
            if ($pos = strpos($mca, '?')) { // index/ask?id=1
                parse_str(substr($mca, $pos + 1), $get);
                $mca = substr($mca, 0, $pos);
            }
            [, $c, $a] = \myphp::deMCA($mca);
        } else {
            $c = strtolower(\myphp::$env['c']);  //获得控制器名
            $a = strtolower(\myphp::$env['a']);    //获得方法名
            $get = $_GET;
        }
        if ($method === '') {
            $method = strtolower(Request::method());
        }

        $cErr = '用户角色没有' . $c . '的权限!';
        $purview = ',' . $purview . ',';
        //优先排除
        //排除的模块
        if (strpos($purview, ',!' . $c . ',') !== false) {
            return self::err($cErr);
        }
        $ca = $c . '/' . $a;
        $aErr = '用户角色没有' . $ca . '操作的权限!';
        $allow = false;
        $offset = 0;
        $len = strlen($ca);
        $aLen = strlen($purview);
        while (true) {
            $pos = strpos($purview, $ca, $offset);
            if (!$pos) {
                break;
            }
            $offset = $pos + $len;
            $prevChr = $purview[$pos - 1]; // c/a前一个字符 可能是[, !]
            $nextChr = $purview[$offset]; // c/a后一个字符 可能是[,?]
            #Log::write(sprintf('$ca:%s, $aLen:%s, $pos:%s, $len:%s, $offset:%s, $prevChr:%s, $nextChr:%s', $ca, $aLen, $pos, $len, $offset, $prevChr, $nextChr));
            if (!in_array($prevChr, self::$prevChr) || !in_array($nextChr, self::$nextChr)) { //未匹配
                continue;
            }

            if ($prevChr === ',' && $nextChr === ',') { //匹配:,c/a,
                $allow = true;
                continue; //可能有多个 c/a匹配
            }

            $startPos = strrpos($purview, ',', -($aLen - $pos)); // 从倒数第 x 个字节起从右向左寻找“,”
            $endPos = strpos($purview, ',', $offset);
            // [!][method ]c/a[?v=1]
            $item = substr($purview, $startPos + 1, $endPos - $startPos - 1);
            #Log::write(sprintf('$item:%s, $startPos:%s, $endPos:%s', $item, $startPos, $endPos));
            $deny = false;
            if ($item[0] === '!') {
                $deny = true;
                $item = substr($item, 1);
            }
            // 解析方法和路径
            if ($pos = strpos($item, ' ')) { //不对有效性判断
                $methods = explode('|', substr($item, 0, $pos));
                //var_dump($method, $methods);
                if (!in_array($method, $methods)) { //请求方法未匹配
                    continue;
                }
                $item = substr($item, $pos + 1);
            }
            // 解析参数
            if ($pos = strpos($item, '?')) {
                parse_str(substr($item, $pos + 1), $params);
                $ok = true;
                foreach ($params as $_k => $_v) {
                    if (!isset($get[$_k]) || $get[$_k] != $_v) { //请求参数未匹配
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }
            if ($deny) { //拒绝优先
                return self::err($aErr);
            }
            $allow = true;
        }

        if ($allow) {
            return true;
        }
        //所有权限 | 模块权限
        if (strpos($purview, ',_all,') !== false || strpos($purview, ',' . $c . ',') !== false) {
            return true;
        }
        return self::err($aErr);
    }

    /**
     * 检测是否登录
     * @return bool
     */
    public static function isLogin(): bool
    {
        return (bool)session('userId');
    }

    /**
     * 验证登录及权限
     * @return void
     * @throws \Exception
     */
    public static function check(): void
    {
        if (!static::isLogin()) {
            $redirect = (strpos(\myphp::$cfg['auth_gateway'], 'http') === 0 ? '' : ROOT_DIR) . \myphp::$cfg['auth_gateway'];
            throw new \Exception(Helper::outMsg('0:你未登录,请先登录!', $redirect), 200);
        }
        if (!static::chkPurview()) {
            //log处理
            Log::write('[' . session('userId') . ']' . cookie('userName') . '：' . self::err(), 'auth');
            throw new \Exception(Helper::outMsg('0:' . self::err()), 200);
        }
    }
}
