<?php

class HttpReqInfo
{
    public static $req_header = null;
    public static $isProxy = false;

    public static function getMethod()
    {
        return isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) : (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
    }
    public static function isPost(){
        return self::getMethod() == 'POST';
    }
    public static function isGet(){
        return self::getMethod() == 'GET';
    }
    // 当前是否Ajax请求
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    public static function getSiteUrl(){
        $scheme = 'http';
        if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') {
            $scheme = 'https';
        } elseif (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        return $scheme . '://' . self::getHost();
    }
    public static function getHost(){
        return isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] == '80' || $_SERVER['SERVER_PORT'] == '443' ? '' : ':' . $_SERVER['SERVER_PORT']));
    }

    public static function getPathInfo()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $pos = strpos($_SERVER['REQUEST_URI'], '?');
            return $pos ? substr($_SERVER['REQUEST_URI'], 0, $pos) : $_SERVER['REQUEST_URI'];
        }
        return isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    }

    public static function getUri()
    {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING']!=='' ? '?' . $_SERVER['QUERY_STRING'] : '');
    }

    public static function getReqHeader($name = null, $default = null)
    {
        if (self::$req_header === null) {
            if (function_exists('getallheaders')) {
                self::$req_header = getallheaders();
            } else {
                foreach ($_SERVER as $name => $value) {
                    if (strncmp($name, 'HTTP_', 5) === 0) {
                        $_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                        self::$req_header[$_name] = $value;
                    } elseif (strncmp($name, 'CONTENT_', 8) === 0) {
                        $_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 8)))));
                        self::$req_header[$_name] = $value;
                    }
                }
                if (!isset(self::$req_header['Authorization'])) {
                    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                        self::$req_header['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                    } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                        self::$req_header['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
                    } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
                        self::$req_header['Authorization'] = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
                    }
                }
            }
        }

        if ($name === null) return self::$req_header;
        if (is_array($name)) {
            $values = [];
            foreach ($name as $item) {
                $values[$item] = isset(self::$req_header[$item]) ? self::$req_header[$item] : $default;
            }
            return $values;
        }
        return isset(self::$req_header[$name]) ? self::$req_header[$name] : $default;
    }

    public static function getRawBody()
    {
        static $rawBody;
        if (isset($rawBody)) {
            return $rawBody;
        }
        $rawBody = file_get_contents("php://input");
        return $rawBody;
    }
    //获取用户真实地址 返回用户ip  type:0 返回IP地址 1 返回IPV4地址数字
    public static function getIp($type=0){
        $realIP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        if(self::$isProxy){
            if (isset($_SERVER['HTTP_X_REAL_IP'])) {
                $realIP = $_SERVER['HTTP_X_REAL_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) { //有可能被伪装
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($arr as $ip) { //取X-Forwarded-For中第x个非unknown的有效IP字符?
                    $ip = trim($ip);
                    if ($ip != 'unknown') {
                        $realIP = $ip;
                        break;
                    }
                }
            }
        }
        return $type ? sprintf("%u", ip2long($realIP)) : $realIP;
    }
    //来源获取
    public static function getReferer(){
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }
}