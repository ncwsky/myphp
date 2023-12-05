<?php

namespace myphp;

class BaseAuth
{
    use \MyMsg;

    //cfg : roles[role=>purview, ...]
    protected function getPurview(){
        $roleId = session('role');
        $roles = \myphp::get('roles', []);
        if (!isset($roles[$roleId])) {
            return self::err('无效的角色配置');
        }

        $rolesName = \myphp::get('roles_name', []);
        //cookie('roleName', $rolesName[$roleId] ?? '-'); //角色名

        return $roles[$roleId];
    }

    /**
     * 权限验证
     * 所有权限 $purview = true|1
     * 允许所有权限 $purview = ['_all'=>true|1]
     * 允许所有权限但存在排除的模块、模块.方法 $purview = ['_all'=>true|1, 'c1'=>false|0, 'c2'=>['a2'=>false|0]]
     * $purview = ['c1'=>true|1,'c2'=>['a2'=>true,'a21'=>true],'c3'=>['_all'=>true,'a3'=>false]]
     * @return bool|string
     */
    protected function chkPurview()
    {
        $purview = $this->getPurview();
        if (!$purview) {
            return false;
            //return self::err('用户所属角色没有权限记录信息!');
        }
        //所有权限
        if ($purview === true || $purview === 1) {
            return true;
        }
        $c = strtolower(\myphp::$env['c']);    //获得控制器名
        $a = strtolower(\myphp::$env['a']);    //获得方法名

        $cErr = '用户所属角色没有' . $c . '的权限!';
        $aErr = '用户所属角色没有' . $c . '/' . $a . '操作的权限!';
        //允许所有权限但存在排除的模块、模块.方法
        if (isset($purview['_all']) && $purview['_all']) {
            //排除的模块
            if (isset($purview[$c]) && !$purview[$c]) {
                return self::err($cErr);
            }
            //排除的模块.方法
            if (isset($purview[$c][$a]) && !$purview[$c][$a]) {
                return self::err($aErr);
            }
            return true;
        }
        //没有此权限
        if (!isset($purview[$c]) || !$purview[$c]) {
            return self::err($cErr);
        }
        //模块所有权限
        if ($purview[$c] === true || $purview[$c] === 1) {
            return true;
        }
        //允许模块所有权限但存在排除的模块.方法
        if (isset($purview[$c]['_all']) && $purview[$c]['_all']) {
            //排除的模块.方法
            if (isset($purview[$c][$a]) && !$purview[$c][$a]) {
                return self::err($aErr);
            }
            return true;
        }

        //没有模块.方法权限
        if (!isset($purview[$c][$a]) || !$purview[$c][$a]) {
            return self::err($aErr);
        }

        //指定参数的权限验证 只支持多个参数的验证  一般是在自定权限中才会设置
        if ($purview[$c][$a] !== true && $purview[$c][$a] !== 1) { // arg1=xx&arg2=xx....
            parse_str($purview[$c][$a], $paras);
            $ok = true;
            foreach ($paras as $_k => $_v) {
                if (!isset($_GET[$_k]) || $_GET[$_k] != $_v) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                return self::err($aErr);
            }
        }
        return true;
    }

    /**
     * 检测是否登录
     * @return bool
     */
    public function isLogin()
    {
        return session('userId') ? true : false;
    }

    /**
     * 验证登录及权限
     * @return bool
     * @throws \Exception
     */
    public function check()
    {
        if (!$this->isLogin()) {
            $redirect = (strpos(\myphp::$cfg['auth_gateway'], 'http') === 0 ? '' : ROOT_DIR) . \myphp::$cfg['auth_gateway'];
            throw new \Exception(Helper::outMsg('0:你未登录,请先登录!', $redirect), 200);
        }
        if (!$this->chkPurview()) {
            //log处理
            Log::write('['.session('userId').']'.cookie('userName') . '：' . self::err(), 'auth');
            throw new \Exception(Helper::outMsg('0:'.self::err()), 200);
        }
        return true;
    }
}