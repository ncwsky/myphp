<?php
//Http认证
class HttpAuth
{
    // 401 Unauthorized
    // WWW-Authenticate: xxx
    public static $realm = 'HttpAuth';
    public static $authUsers = [];
    public static $authBasic = true; //Basic Digest

    public static function auth($logout = false)
    {
        if (empty(self::$authUsers)) return true;
        if ($logout) {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['PHP_AUTH_DIGEST']);
            return false;
        }

        $auth = 'Basic realm="' . self::$realm . '"';
        if (self::$authBasic) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authInfo = base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)); //Basic
                if (strpos($authInfo, ':')) {
                    list($user, $password) = explode(':', $authInfo);
                    $_SERVER['PHP_AUTH_USER'] = $user;
                    $_SERVER['PHP_AUTH_PW'] = $password;
                }
            }

            if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
                return $auth; //未输入认证信息
            }
            if (!isset(self::$authUsers[$_SERVER['PHP_AUTH_USER']]) || self::$authUsers[$_SERVER['PHP_AUTH_USER']] != $_SERVER['PHP_AUTH_PW']) {
                return $auth; //认证不匹配
            }
            return true;
        } else {
            $opaque = md5(self::$realm . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
            $auth = 'Digest realm="' . self::$realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . $opaque . '"';
        }

        if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                return $auth; //未输入认证信息
            }
            $_SERVER['PHP_AUTH_DIGEST'] = $_SERVER['HTTP_AUTHORIZATION'];
        }

        $needed_parts = array('nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1);
        $data = array();
        $keys = implode('|', array_keys($needed_parts));

        preg_match_all('/(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))/', $_SERVER['PHP_AUTH_DIGEST'], $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset($needed_parts[$m[1]]);
        }
        if ($needed_parts || !isset(self::$authUsers[$data['username']])) {
            return $auth; //认证无效
        }

        $login = ['name' => $data['username'], 'password' => self::$authUsers[$data['username']]];

        $password = md5($login['name'] . ':' . self::$realm . ':' . $login['password']);
        $response = md5($password . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']));

        if ($data['response'] != $response) {
            return $auth; //认证不匹配
        }

        return true;
    }

    /**
     * @param string $redirect 退出后需要跳转的url
     * @return bool|string
     */
    public static function run($redirect = '')
    {
        $auth = self::auth($redirect ? true : false);
        if ($auth !== true) {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
            if ($auth === false) { //logout
                header($protocol . ' 302 Found');
                header('Location: ' . $redirect);
                return false;
            }
            header($protocol . ' 401 Unauthorized');
            header('WWW-Authenticate: ' . $auth);
            return false;
        }
        return true;
    }
}