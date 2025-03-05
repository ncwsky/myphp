<?php

declare(strict_types=1);

namespace myphp;

/**
 * 简单角色字符串规则权限处理
 */
class BaseAuth
{
    use \MyMsg;

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
     * 权限验证 _all,!admin,!role/del,admin,!admin/del,post admin/save
     * 所有权限 $purview = _all
     * 允许所有权限但存在排除的模块、模块.方法 $purview = _all,!c1,!c2/index
     * @return bool|string
     */
    public static function chkPurview($mca = '', $method = '', $roleId = 0)
    {
        $purview = static::getPurview($roleId);
        if (!$purview) {
            return self::err('用户角色没有权限配置信息');
        }
        if ($mca) {
            if ($pos = strpos($mca, '?')) { // index/ask?id=1
                $mca = substr($mca, 0, $pos);
            }
            [, $c, $a] = \myphp::deMCA($mca);
        } else {
            $c = strtolower(\myphp::$env['c']);  //获得控制器名
            $a = strtolower(\myphp::$env['a']);    //获得方法名
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
