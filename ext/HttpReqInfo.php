<?php

/**
 * Class HttpReqInfo
 */
class HttpReqInfo
{
    use MyBaseObj;

    /**
     * @var null|array
     */
    public $headers = null;

    public static $isProxy = false;
    public static $ipHeaders = ['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'];

    /**
     * @var null|string
     */
    protected $_rawBody = null;

    /**
     * 请求方法
     * @return mixed|string
     */
    public static function method()
    {
        return isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) : (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
    }

    /**
     * @return bool
     */
    public static function isPost()
    {
        return self::method() == 'POST';
    }

    /**
     * @return bool
     */
    public static function isGet()
    {
        return self::method() == 'GET';
    }

    /**
     * 当前是否Ajax请求
     * @return bool
     */
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * 获取当前站点地址
     * @return string
     */
    public static function siteUrl()
    {
        $scheme = 'http';
        if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') {
            $scheme = 'https';
        } elseif (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        return $scheme . '://' . self::host();
    }

    /**
     * 获取当前页面完整URL地址 如 http://xxx/a.php?b=1
     * @return string
     */
    public static function url()
    {
        return self::siteUrl() . self::uri();
    }

    /**
     * 获取主机地址
     * @return mixed|string
     */
    public static function host()
    {
        return isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '') . (!isset($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' || $_SERVER['SERVER_PORT'] == '443' ? '' : ':' . $_SERVER['SERVER_PORT']));
    }

    /**
     * 获取当前请求路径 如 /ab.php
     * @return false|mixed|string
     */
    public static function pathInfo()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $pos = strpos($_SERVER['REQUEST_URI'], '?');
            return $pos ? substr($_SERVER['REQUEST_URI'], 0, $pos) : $_SERVER['REQUEST_URI'];
        }
        return isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    }

    /**
     * 获得当前请求地址  如 /ab.php?b=1
     * @return mixed|string
     */
    public static function uri()
    {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    }

    /**
     * 来源获取
     * @return mixed|string
     */
    public static function referer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }


    /**
     * @return mixed|string
     */
    public static function remoteIP()
    {
        //重置ipv6
        //if(isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
        //  $_SERVER['REMOTE_ADDR']='127.0.0.1';
        //}
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * @return mixed|string
     */
    public static function userIP()
    {
        //HTTP_X_REAL_IP HTTP_X_FORWARDED_FOR 可能被伪装
        foreach (self::$ipHeaders as $name) {
            if (isset($_SERVER[$name])) {
                $arr = explode(',', $_SERVER[$name]);
                foreach ($arr as $ip) {
                    $ip = trim($ip);
                    if ($ip != 'unknown') {
                        return $ip;
                    }
                }
            }
        }

        return self::remoteIP();
    }

    /**
     * 获取ip
     * @param bool $number 返回IP地址 true返回IPV4地址数字
     * @return mixed|string
     */
    public static function ip($number = false)
    {
        $realIP = self::$isProxy ? self::userIP() : self::remoteIP();
        return $number ? sprintf("%u", ip2long($realIP)) : $realIP;
    }

    public function clear()
    {
        $this->_rawBody = null;
        $this->headers = null;
    }

    /**
     * @return false|string|null
     */
    public function rawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = file_get_contents("php://input");
        }
        return $this->_rawBody;
    }

    /**
     * @param null|string $rawBody
     * @return $this
     */
    public function setRawBody($rawBody)
    {
        $this->_rawBody = $rawBody;
        return $this;
    }

    /**
     * @param null|string $header_name
     * @param null|string $default
     * @return array|false|mixed|null
     */
    public function header($header_name = null, $default = null)
    {
        if ($this->headers === null) {
            $upper = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $lower = ' abcdefghijklmnopqrstuvwxyz';
            //首字母大写
            foreach ($_SERVER as $name => $value) {
                if (strncmp($name, 'HTTP_', 5) === 0) {
                    $_name = strtr(ucwords(strtr(substr($name, 5), $upper, $lower)),' ', '-');
                    $this->headers[$_name] = $value;
                } elseif (strncmp($name, 'CONTENT_', 8) === 0) {
                    if ($value === '') continue;
                    $_name = strtr(ucwords(strtr(substr($name, 8), $upper, $lower)),' ', '-');
                    $this->headers[$_name] = $value;
                }
            }
            if (!isset($this->headers['Authorization'])) {
                if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                    $this->headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                    $this->headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
                } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
                    $this->headers['Authorization'] = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
                }
            }
        }

        if ($header_name === null) return $this->headers;
        if (is_array($header_name)) {
            $values = [];
            foreach ($header_name as $item) {
                $values[$item] = isset($this->headers[$item]) ? $this->headers[$item] : $default;
            }
            return $values;
        }
        return isset($this->headers[$header_name]) ? $this->headers[$header_name] : $default;
    }

    /**
     * 设置指定请求头信息
     * @param $name
     * @param $value
     * @return $this
     */
    public function setHeader($name, $value)
    {
        //首字母大写
        $name = strtr(ucwords(strtr($name, '-', ' ')), ' ', '-');
        if (isset($this->headers[$name])) {
            if (!is_array($this->headers[$name])) {
                $this->headers[$name] = [$this->headers[$name]];
            }
            $this->headers[$name][] = $value;
        } else {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * 设置请求头信息
     * @param $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->headers = [];
        //首字母大写
        foreach ($headers as $name => $value) {
            $name = strtr(ucwords(strtr($name, '-', ' ')), ' ', '-');
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * @param null|string $name
     * @param null|mixed $default
     * @return array|mixed|null
     */
    public function post($name=null, $default = null)
    {
        if ($name === null) return $_POST ?? [];
        return $_POST[$name] ?? $default;
    }

    /**
     * @param string $name
     * @param mixed $val
     * @return $this
     */
    public function setPost($name, $val)
    {
        $_POST[$name] = $val;
        return $this;
    }

    /**
     * @param null|string $name
     * @param null|mixed $default
     * @return array|mixed|null
     */
    public function get($name=null, $default = null)
    {
        if ($name === null) return $_GET ?? [];
        return $_GET[$name] ?? $default;
    }

    /**
     * @param string $name
     * @param mixed $val
     * @return $this
     */
    public function setGet($name, $val)
    {
        $_GET[$name] = $val;
        return $this;
    }
}