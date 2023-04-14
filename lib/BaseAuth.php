<?php

namespace myphp;

class BaseAuth
{
    use \MyMsg;

    //权限验证
    private function chkPurview()
    {
        $roleId = (int)session('role');
        $roles = \myphp::get('roles', []);
        if (!isset($roles[$roleId])) {
            return self::err('无效的角色配置');
        }
        //cfg : roles[id=>[name, purview], ...]

        cookie('roleName', $roles[$roleId]['name']);//角色名
        $purview = json_decode($roles[$roleId]['purview'], true);
        //允许所有权限
        if ($purview === 1) {
            return true;
        }
        if (!is_array($purview)) {
            return self::err('用户所属角色没有权限记录信息!');
        }
        //超级管理员
        if (isset($purview['isadmin'])) {
            return true;
        }
        $control = strtolower(\myphp::$env['c']);    //获得控制器名
        $action = strtolower(\myphp::$env['a']);    //获得方法名
        if (!isset($purview[$control])) {
            return self::err('用户所属角色没有' . $control . '的权限!');
        }
        if ($purview[$control] === 1) { #拥有此模块所有权限
            return true;
        }
        if (!isset($purview[$control][$action])) {
            return self::err('用户所属角色没有' . $control . '/' . $action . '操作的权限!');
        }

        $err = '用户所属角色没有' . $control . '/' . $action . '操作的权限!';
        if ($purview[$control][$action] == '!') { //支持 参数值为“!”表示没有此权限
            return self::err($err);
        }
        //指定参数的权限验证 只支持一个参数的验证  一般是在自定权限中才会设置
        if ($purview[$control][$action] !== 1) { // hy=1 或 hy-1
            $para = strtr($purview[$control][$action], '-', '=');
            if (strpos($para, '=')) {
                list($_k, $_v) = explode('=', $para);
                if (isset($_GET[$_k]) && $_GET[$_k] == $_v) return true;
            }
            return self::err($err);
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
            throw new \Exception(Helper::outMsg('你未登录,请先登录!', $redirect), 200);
        }
        if (!$this->chkPurview()) {
            //log处理
            Log::write(cookie('userName') . '：' . self::err(), 'userlogin');
            throw new \Exception(Helper::outMsg(self::err()), 200);
        }
        return true;
    }
}