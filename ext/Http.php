<?php
//数据采集 doGET,doPOST,doSend自定义发送数据,文件下载
class Http
{
    public static $way = 0;
    public static $curlOpt = []; //curl配置 ssl cookie header redirect opts[curl_opt=>value,..]
    public static $curlProxy = []; //代理 [user,pass,host,port]
    public static $curlBeforeCall = null; //curl前置处理 function($url, $type, $data, $timeout, $header, $opt):void
    public static $curlRetries = 0;
    public static $curlRetryCond = null; //curl重试条件，未指定使用的默认 function($url, $err):bool
    public static $curlErr = '';
    /**
     * @param $proxy `proxy://user:pass@hostname:port`
     */
    public static function setCurlProxy($proxy)
    {
        $proxy = parse_url($proxy);
        $proxy['user'] = isset($proxy['user']) ? $proxy['user'] : null;
        $proxy['pass'] = isset($proxy['pass']) ? $proxy['pass'] : null;
        $proxy['port'] = isset($proxy['port']) ? $proxy['port'] : null;
        self::$curlProxy = $proxy;
    }

    public static function getSupport()
    {
        //如果指定访问方式，则按指定的方式去访问
        if(isset(self::$way) && in_array(self::$way,array(1,2,3)))
            return self::$way;
        //自动获取最佳访问方式
        if(function_exists('curl_init'))//curl方式
            return 1;
        elseif(function_exists('fsockopen'))//socket
            return 2;
        elseif(function_exists('file_get_contents'))//php系统函数file_get_contents
            return 3;
        else
            return 0;
    }
    //通过get方式获取数据 $header string|array
    public static function doGet($url, $timeout=30, $header='', $opt=[])
    {
        $code = self::doInit($url, $timeout);
        if(!$code) return false;
        switch($code)
        {
            case 1:return self::curlGet($url,$timeout,$header,$opt);
            case 2:return self::socketGet($url,$timeout,$header);
            case 3:return self::phpGet($url,$timeout,$header);
        }
        return false;
    }
    //通过POST方式发送数据 $header string|array
    public static function doPost($url, $data=null, $timeout=30, $header='', $opt=[])
    {
        $code = self::doInit($url, $timeout);
        if(!$code) return false;
        switch($code)
        {
            case 1:return self::curlPost($url,$data,$timeout,$header,$opt);
            case 2:return self::socketPost($url,$data,$timeout,$header);
            case 3:return self::phpPost($url,$data,$timeout,$header);
        }
        return false;
    }
    //通过Send自定义方式发送数据 $header string|array
    public static function doSend($url, $type='GET', $data=null, $timeout=30, $header='', $opt=[])
    {
        $code = self::doInit($url, $timeout);
        if(!$code) return false;
        switch($code)
        {
            case 1:return self::curlSend($url,$type,$data,$timeout,$header,$opt);
            case 2:return self::socketSend($url,$type,$data,$timeout,$header);
            case 3:return self::phpSend($url,$type,$data,$timeout,$header);
        }
        return false;
    }
    public static function doInit(&$url, $timeout){
        if (empty($url) || empty($timeout)) return false;
        if (stripos($url, 'http') !== 0) $url = 'http://' . $url;
        return self::getSupport();
    }

    //通过curl get数据
    public static function curlGet($url, $timeout=30, $header='', $opt=[])
    {
        return self::curlSend($url, 'GET', null, $timeout, $header, $opt);
    }
    //通过curl post数据 支持post string
    public static function curlPost($url, $data=null, $timeout=30, $header='', $opt=[])
    {
        return self::curlSend($url, 'POST', $data, $timeout, $header, $opt);
    }
    //通过curl 自定义发送请求
    public static function curlSend($url, $type='GET', $data=null, $timeout=30, $header='', $opt=[])
    {
        if(!$opt) $opt = self::$curlOpt;
        if(!$header) $header = self::defaultHeader();
        if(self::$curlBeforeCall){
            //call_user_func不支持引用传值
            call_user_func_array(self::$curlBeforeCall, [&$url, &$type, &$data, &$timeout, &$header, &$opt]);
        }
        /*
        GET（SELECT）：从服务器取出资源（一项或多项）。
        POST（CREATE）：在服务器新建一个资源。
        PUT（UPDATE）：在服务器更新资源（客户端提供改变后的完整资源）。
        PATCH（UPDATE）：在服务器更新资源（客户端提供改变的属性）。
        DELETE（DELETE）：从服务器删除资源。
        HEAD：获取资源的元数据。
        */
        $connect_timeout = isset($opt['connect_timeout']) ? $opt['connect_timeout'] : $timeout;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if(substr($url,0,5)=='https'){ //ssl
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //检查服务器SSL证书 正式环境中使用 2
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //取消验证证书

            if(isset($opt['cert']) && isset($opt['key'])){
                $opt['type'] = isset($opt['type']) ? $opt['type'] : 'PEM';
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, $opt['type']);
                curl_setopt($ch, CURLOPT_SSLKEYTYPE, $opt['type']);
                curl_setopt($ch, CURLOPT_SSLCERT, $opt['cert']);
                curl_setopt($ch, CURLOPT_SSLKEY, $opt['key']);
            }
            if(isset($opt['cainfo']) || isset($opt['capath'])){
                isset($opt['cainfo']) && curl_setopt($ch, CURLOPT_CAINFO , $opt['cainfo']);
                isset($opt['capath']) && curl_setopt($ch, CURLOPT_CAPATH , $opt['capath']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            }
        }

        if (isset(self::$curlProxy['host'])) {
            $host = self::$curlProxy['host'];
            if (!empty(self::$curlProxy['port'])) {
                $host .= ':' . self::$curlProxy['port'];
            }
            curl_setopt($ch, CURLOPT_PROXY, $host);
            if (isset(self::$curlProxy['user']) && isset(self::$curlProxy['pass'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, self::$curlProxy['user'] . ':' . self::$curlProxy['pass']);
            }
        }

        $type = strtoupper($type);
        switch ($type) {
            case 'GET':
                if ( $data ) {
                    $data = is_array($data) ? http_build_query($data) : $data;
                    $url = strpos($url, '?') === false ? ($url . '?' . $data) : ($url . '&' . $data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                if(!empty($opt['redirect'])){ #是否重定向
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); #302 redirect
                    curl_setopt($ch, CURLOPT_MAXREDIRS, (int)$opt['redirect']); #次数
                }
                break;
            case 'POST':
                //https 使用数组的在某些未知情况下数据长度超过一定长度会报SSL read: error:00000000:lib(0):func(0):reason(0), errno 10054
                if(is_array($data) && (!isset($opt['post_encode']) || $opt['post_encode'])){
                    //针对 CURLFile 上传文件不要编码
                    $data = http_build_query($data);
                    /*$toBuild = true;
                    if(class_exists('CURLFile')){ //针对上传文件处理
                        foreach ($data as $v){
                            if($v instanceof CURLFile){
                                $toBuild = false;
                                break;
                            }
                        }
                    }
                    if($toBuild) $data = http_build_query($data);*/
                }
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                //取消 100-continue应答
                if (is_array($header)) {
                    $header[] = "Expect:";
                } else {
                    $header .= "\r\nExpect:";
                }
                break;
            case 'PATCH':
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, true); //将不对HTML中的BODY部分进行输出
                break;
        }

        if(isset($opt['referer'])){
            curl_setopt($ch, CURLOPT_REFERER, $opt['referer']);
        }

        if (isset($opt['cookie'])) curl_setopt($ch, CURLOPT_COOKIE, $opt['cookie']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, is_string($header) ? explode("\r\n", $header) : $header);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout * 1000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout * 1000);
        //$timeoutRequiresNoSignal = false; $timeoutRequiresNoSignal |= $timeout < 1;
        if ($timeout < 1 && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        }

        //批量配置
        if (isset($opt['opts']) && is_array($opt['opts'])) {
            foreach ($opt['opts'] as $option => $value) {
                curl_setopt($ch, $option, $value);
            }
        }
        //header没有配置Expect时添加 'Expect:' todo

        $result = false;
        if(isset($opt['res'])){
            curl_setopt($ch, CURLOPT_HEADER, true);    // 是否需要响应 header
            $output          = curl_exec($ch);
            if($output!==false){
                $header_size     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);    // 获得响应结果里的：头大小
                //$res_header = substr($output, 0, $header_size);    // 根据头大小去获取头信息内容
                //$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);    // 获取响应状态码
                //$res_body   = substr($output, $header_size);
                $result = [
                    //'request_url'        => $url,
                    //'request_body'       => $data,
                    //'request_header'     => $header,
                    'res_http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), // 获取响应状态码
                    'res_body'      => substr($output, $header_size),
                    'res_header'    => substr($output, 0, $header_size),
                    'res_errno'     => curl_errno($ch),
                    'res_error'     => curl_error($ch),
                ];
            }
        }else{
            $result = curl_exec($ch);
        }
        self::$curlErr = '';
        if(curl_errno($ch)){
            self::$curlErr = curl_error($ch);
            \myphp\Log::write('err:'. self::$curlErr."\nurl:".$url.($data!==null?"\ndata:".(is_scalar($data)?urldecode($data):json_encode($data)):''), 'curl');
            if (self::_curlIsRetry($url, self::$curlErr)) {
                $runRetry = true;
                if (preg_match('/_retry=(\d)/', $url, $retryMatch)) {
                    $retry = (int)$retryMatch[1];
                    if ($retry >= self::$curlRetries) {
                        $runRetry = false;
                    }
                    $url = str_replace($retryMatch[0], '_retry=' . ($retry + 1), $url);
                } else {
                    if (strpos($url, '?')) {
                        $url .= '&_retry=1';
                    } else {
                        $url .= '?_retry=1';
                    }
                }
                if ($runRetry) {
                    curl_close($ch);
                    return self::curlSend($url, $type, $data, $timeout, $header, $opt);
                }
            }
        }

        curl_close($ch);
        return $result;
    }
    //重试条件
    private static function _curlIsRetry($url, $err)
    {
        if (self::$curlRetries <= 0) return false;
        //有指定重试判断
        if (self::$curlRetryCond) {
            return call_user_func(self::$curlRetryCond, $url, $err);
        }
        return strpos($err, 'Connection timed out')!==false || strpos($err, 'Failed to connect') !== false || strpos($err, 'Unknown SSL protocol') !== false;
    }
    //通过socket get数据
    public static function socketGet($url,$timeout=30,$header='')
    {
        return self::socketSend($url, 'GET', null, $timeout, $header);
    }
    //通过socket post数据
    public static function socketPost($url, $data=null, $timeout=30,$header='')
    {
        return self::socketSend($url, 'POST', $data, $timeout, $header);
    }
    //通过socket 自定义发送请求
    public static function socketSend($url, $type='GET', $data=null, $timeout=30,$header='')
    {
        if (!$header) $header = self::defaultHeader();
        $header = self::header2string($header);
        $post_string = is_array($data)?http_build_query($data):$data;

        $def_port = 80;$scheme = '';
        if(substr($url,0,5)=='https'){ //ssl
            $def_port = 443;
            $scheme = 'ssl://';
        }

        $url2 = parse_url($url);
        $url2["path"] = isset($url2["path"])? $url2["path"]: "/" ;
        $url2["port"] = isset($url2["port"])? $url2["port"] : $def_port;

        if(!($fsock = fsockopen($scheme.$url2["host"], $url2['port'], $errno, $errstr, $timeout))){
            return false;
        }
        $request =  $url2["path"].(isset($url2["query"]) ? "?" . $url2["query"] : "");

        $type = strtoupper($type);
        $in  = $type." " . $request . " HTTP/1.1\r\n";
        if(false===stripos($header, "Host:"))
            $in .= "Host: " . $url2["host"] . "\r\n";
        if(stripos($header, 'Referer')===false)
            $in .= "Referer: " . $url . "\r\n";

        $in .= $header. "\r\n";
        switch ($type) {
            case 'POST':
            case 'PATCH':
            case 'PUT':
                $in .= "Content-type: application/x-www-form-urlencoded\r\n";
                $in .= "Content-Length: " . strlen($post_string) . "\r\n";
                break;
        }

        $in .= "Connection: Close\r\n\r\n";
        $in .= $post_string . "\r\n\r\n";
        unset($post_string);
        if(!@fwrite($fsock, $in, strlen($in))){
            @fclose($fsock);
            return false;
        }
        return self::_getHttpContent($fsock);
    }

    //通过file_get_contents函数get数据
    public static function phpGet($url,$timeout=30, $header='')
    {
        return self::phpSend($url, 'GET', null, $timeout, $header);
    }
    //通过file_get_contents 函数post数据
    public static function phpPost($url, $data=null, $timeout=30, $header='')
    {
        return self::phpSend($url, 'POST', $data, $timeout, $header);
    }
    //通过file_get_contents 函数自定义Send数据
    public static function phpSend($url, $type='GET', $data=null, $timeout=30, $header='')
    {
        if (!$header) $header = self::defaultHeader();
        $header = self::header2string($header);
        $opt_http = 'http';
        if(substr($url,0,5)=='https'){ //ssl
            //$opt_http = 'https';
        }
        $post_string = is_array($data)?http_build_query($data):$data;
        $type = strtoupper($type);
        switch ($type) {
            case 'POST':
            case 'PATCH':
            case 'PUT':
                $header .= "\r\nContent-type: application/x-www-form-urlencoded\r\n";
                $header .= "Content-length: ".strlen($post_string);
                break;
        }
        $opts = array(
            $opt_http=>array(
                'protocol_version'=>'1.1',
                'method'=>$type,//获取方式
                'timeout'=> $timeout ,//超时时间
                'header'=> $header
            )
        );
        switch ($type) {
            case 'POST':
            case 'PATCH':
            case 'PUT':
                $opts[$opt_http]['content'] = $post_string;
                break;
        }
        $context = stream_context_create($opts);
        return @file_get_contents($url,false,$context);
    }

    private static function header2string($header)
    {
        if (is_array($header)) {
            $headers = '';
            foreach ($header as $k => $v) {
                $headers .= "\r\n" . (is_int($k) ? $v : $k . ':' . $v);
            }
            return substr($headers, 2);
        }
        return $header;
    }

    private static function defaultHeader()
    {
        return [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
            "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,en-US;q=0.6",
            "Cache-Control: no-cache",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.124 Safari/537.36 Edg/102.0.1245.44",
        ];
    }

    //获取通过socket方式get和post页面的返回数据
    private static function _getHttpContent($fsock=null)
    {
        $out = null;
        while($buff = @fgets($fsock, 2048)){
            $out .= $buff;
        }
        fclose($fsock);
        $pos = strpos($out, "\r\n\r\n");
        $head = substr($out, 0, $pos);    //http head
        $status = substr($head, 0, strpos($head, "\r\n"));    //http status line
        $body = substr($out, $pos+4, strpos($out, "\r\n0\r\n\r\n")-$pos-4);
        $body = substr($body, strpos($body, "\r\n")+2);
        if(preg_match("/^HTTP\/\d\.\d\s([\d]+)\s.*$/", $status, $matches))
        {
            if(intval($matches[1]) / 100 == 2)
                return $body;
            else
                return false;
        }
        else
        {
            return false;
        }
    }

    /*
     功能： 下载文件
     参数:$filename 下载文件路径
     $showname 下载显示的文件名
     $expire  下载内容浏览器缓存时间
    */
    public static function download($filename, $showname='',$expire=1800)
    {
        if(is_file($filename))
        {
            $length = filesize($filename);
        }
        else
        {
            die('下载文件不存在！');
        }

        $type = mime_content_type($filename);

        //发送Http Header信息 开始下载
        header("Pragma: public");
        header("Cache-control: max-age=".$expire);
        //header('Cache-Control: no-store, no-cache, must-revalidate');
        header("Expires: " . gmdate("D, d M Y H:i:s",time()+$expire) . "GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s",time()) . "GMT");
        header("Content-Disposition: attachment; filename=".$showname);
        header("Content-Length: ".$length);
        header("Content-type: ".$type);
        header('Content-Encoding: none');
        header("Content-Transfer-Encoding: binary" );
        readfile($filename);
        return true;
    }
}

if( !function_exists ('mime_content_type')) {
    /**
    +----------------------------------------------------------
     * 获取文件的mime_content类型
    +----------------------------------------------------------
     * @return string
    +----------------------------------------------------------
     */
    function mime_content_type($filename)
    {
        static $contentType = array(
            'ai'	=> 'application/postscript',
            'aif'	=> 'audio/x-aiff',
            'aifc'	=> 'audio/x-aiff',
            'aiff'	=> 'audio/x-aiff',
            'asc'	=> 'application/pgp', //changed by skwashd - was text/plain
            'asf'	=> 'video/x-ms-asf',
            'asx'	=> 'video/x-ms-asf',
            'au'	=> 'audio/basic',
            'avi'	=> 'video/x-msvideo',
            'bcpio'	=> 'application/x-bcpio',
            'bin'	=> 'application/octet-stream',
            'bmp'	=> 'image/bmp',
            'c'	=> 'text/plain', // or 'text/x-csrc', //added by skwashd
            'cc'	=> 'text/plain', // or 'text/x-c++src', //added by skwashd
            'cs'	=> 'text/plain', //added by skwashd - for C# src
            'cpp'	=> 'text/x-c++src', //added by skwashd
            'cxx'	=> 'text/x-c++src', //added by skwashd
            'cdf'	=> 'application/x-netcdf',
            'class'	=> 'application/octet-stream',//secure but application/java-class is correct
            'com'	=> 'application/octet-stream',//added by skwashd
            'cpio'	=> 'application/x-cpio',
            'cpt'	=> 'application/mac-compactpro',
            'csh'	=> 'application/x-csh',
            'css'	=> 'text/css',
            'csv'	=> 'text/comma-separated-values',//added by skwashd
            'dcr'	=> 'application/x-director',
            'diff'	=> 'text/diff',
            'dir'	=> 'application/x-director',
            'dll'	=> 'application/octet-stream',
            'dms'	=> 'application/octet-stream',
            'doc'	=> 'application/msword',
            'dot'	=> 'application/msword',//added by skwashd
            'dvi'	=> 'application/x-dvi',
            'dxr'	=> 'application/x-director',
            'eps'	=> 'application/postscript',
            'etx'	=> 'text/x-setext',
            'exe'	=> 'application/octet-stream',
            'ez'	=> 'application/andrew-inset',
            'gif'	=> 'image/gif',
            'gtar'	=> 'application/x-gtar',
            'gz'	=> 'application/x-gzip',
            'h'	=> 'text/plain', // or 'text/x-chdr',//added by skwashd
            'h++'	=> 'text/plain', // or 'text/x-c++hdr', //added by skwashd
            'hh'	=> 'text/plain', // or 'text/x-c++hdr', //added by skwashd
            'hpp'	=> 'text/plain', // or 'text/x-c++hdr', //added by skwashd
            'hxx'	=> 'text/plain', // or 'text/x-c++hdr', //added by skwashd
            'hdf'	=> 'application/x-hdf',
            'hqx'	=> 'application/mac-binhex40',
            'htm'	=> 'text/html',
            'html'	=> 'text/html',
            'ice'	=> 'x-conference/x-cooltalk',
            'ics'	=> 'text/calendar',
            'ief'	=> 'image/ief',
            'ifb'	=> 'text/calendar',
            'iges'	=> 'model/iges',
            'igs'	=> 'model/iges',
            'jar'	=> 'application/x-jar', //added by skwashd - alternative mime type
            'java'	=> 'text/x-java-source', //added by skwashd
            'jpe'	=> 'image/jpeg',
            'jpeg'	=> 'image/jpeg',
            'jpg'	=> 'image/jpeg',
            'js'	=> 'application/x-javascript',
            'kar'	=> 'audio/midi',
            'latex'	=> 'application/x-latex',
            'lha'	=> 'application/octet-stream',
            'log'	=> 'text/plain',
            'lzh'	=> 'application/octet-stream',
            'm3u'	=> 'audio/x-mpegurl',
            'man'	=> 'application/x-troff-man',
            'me'	=> 'application/x-troff-me',
            'mesh'	=> 'model/mesh',
            'mid'	=> 'audio/midi',
            'midi'	=> 'audio/midi',
            'mif'	=> 'application/vnd.mif',
            'mov'	=> 'video/quicktime',
            'movie'	=> 'video/x-sgi-movie',
            'mp3'	=> 'audio/mpeg',
            'mp4'	=> 'video/mp4',
            'mpe'	=> 'video/mpeg',
            'mpeg'	=> 'video/mpeg',
            'mpg'	=> 'video/mpeg',
            'mpga'	=> 'audio/mpeg',
            'ms'	=> 'application/x-troff-ms',
            'msh'	=> 'model/mesh',
            'mxu'	=> 'video/vnd.mpegurl',
            'nc'	=> 'application/x-netcdf',
            'oda'	=> 'application/oda',
            'patch'	=> 'text/diff',
            'pbm'	=> 'image/x-portable-bitmap',
            'pdb'	=> 'chemical/x-pdb',
            'pdf'	=> 'application/pdf',
            'pgm'	=> 'image/x-portable-graymap',
            'pgn'	=> 'application/x-chess-pgn',
            'pgp'	=> 'application/pgp',//added by skwashd
            'php'	=> 'application/x-httpd-php',
            'php3'	=> 'application/x-httpd-php3',
            'pl'	=> 'application/x-perl',
            'pm'	=> 'application/x-perl',
            'png'	=> 'image/png',
            'pnm'	=> 'image/x-portable-anymap',
            'po'	=> 'text/plain',
            'ppm'	=> 'image/x-portable-pixmap',
            'ppt'	=> 'application/vnd.ms-powerpoint',
            'ps'	=> 'application/postscript',
            'qt'	=> 'video/quicktime',
            'ra'	=> 'audio/x-realaudio',
            'rar'=>'application/octet-stream',
            'ram'	=> 'audio/x-pn-realaudio',
            'ras'	=> 'image/x-cmu-raster',
            'rgb'	=> 'image/x-rgb',
            'rm'	=> 'audio/x-pn-realaudio',
            'roff'	=> 'application/x-troff',
            'rpm'	=> 'audio/x-pn-realaudio-plugin',
            'rtf'	=> 'text/rtf',
            'rtx'	=> 'text/richtext',
            'sgm'	=> 'text/sgml',
            'sgml'	=> 'text/sgml',
            'sh'	=> 'application/x-sh',
            'shar'	=> 'application/x-shar',
            'shtml'	=> 'text/html',
            'silo'	=> 'model/mesh',
            'sit'	=> 'application/x-stuffit',
            'skd'	=> 'application/x-koan',
            'skm'	=> 'application/x-koan',
            'skp'	=> 'application/x-koan',
            'skt'	=> 'application/x-koan',
            'smi'	=> 'application/smil',
            'smil'	=> 'application/smil',
            'snd'	=> 'audio/basic',
            'so'	=> 'application/octet-stream',
            'spl'	=> 'application/x-futuresplash',
            'src'	=> 'application/x-wais-source',
            'stc'	=> 'application/vnd.sun.xml.calc.template',
            'std'	=> 'application/vnd.sun.xml.draw.template',
            'sti'	=> 'application/vnd.sun.xml.impress.template',
            'stw'	=> 'application/vnd.sun.xml.writer.template',
            'sv4cpio'	=> 'application/x-sv4cpio',
            'sv4crc'	=> 'application/x-sv4crc',
            'swf'	=> 'application/x-shockwave-flash',
            'sxc'	=> 'application/vnd.sun.xml.calc',
            'sxd'	=> 'application/vnd.sun.xml.draw',
            'sxg'	=> 'application/vnd.sun.xml.writer.global',
            'sxi'	=> 'application/vnd.sun.xml.impress',
            'sxm'	=> 'application/vnd.sun.xml.math',
            'sxw'	=> 'application/vnd.sun.xml.writer',
            't'	=> 'application/x-troff',
            'tar'	=> 'application/x-tar',
            'tcl'	=> 'application/x-tcl',
            'tex'	=> 'application/x-tex',
            'texi'	=> 'application/x-texinfo',
            'texinfo'	=> 'application/x-texinfo',
            'tgz'	=> 'application/x-gtar',
            'tif'	=> 'image/tiff',
            'tiff'	=> 'image/tiff',
            'tr'	=> 'application/x-troff',
            'tsv'	=> 'text/tab-separated-values',
            'txt'	=> 'text/plain',
            'ustar'	=> 'application/x-ustar',
            'vbs'	=> 'text/plain', //added by skwashd - for obvious reasons
            'vcd'	=> 'application/x-cdlink',
            'vcf'	=> 'text/x-vcard',
            'vcs'	=> 'text/calendar',
            'vfb'	=> 'text/calendar',
            'vrml'	=> 'model/vrml',
            'vsd'	=> 'application/vnd.visio',
            'wav'	=> 'audio/x-wav',
            'wax'	=> 'audio/x-ms-wax',
            'wbmp'	=> 'image/vnd.wap.wbmp',
            'wbxml'	=> 'application/vnd.wap.wbxml',
            'wm'	=> 'video/x-ms-wm',
            'wma'	=> 'audio/x-ms-wma',
            'wmd'	=> 'application/x-ms-wmd',
            'wml'	=> 'text/vnd.wap.wml',
            'wmlc'	=> 'application/vnd.wap.wmlc',
            'wmls'	=> 'text/vnd.wap.wmlscript',
            'wmlsc'	=> 'application/vnd.wap.wmlscriptc',
            'wmv'	=> 'video/x-ms-wmv',
            'wmx'	=> 'video/x-ms-wmx',
            'wmz'	=> 'application/x-ms-wmz',
            'wrl'	=> 'model/vrml',
            'wvx'	=> 'video/x-ms-wvx',
            'xbm'	=> 'image/x-xbitmap',
            'xht'	=> 'application/xhtml+xml',
            'xhtml'	=> 'application/xhtml+xml',
            'xls'	=> 'application/vnd.ms-excel',
            'xlt'	=> 'application/vnd.ms-excel',
            'xml'	=> 'application/xml',
            'xpm'	=> 'image/x-xpixmap',
            'xsl'	=> 'text/xml',
            'xwd'	=> 'image/x-xwindowdump',
            'xyz'	=> 'chemical/x-xyz',
            'z'	=> 'application/x-compress',
            'zip'	=> 'application/zip',
        );
        $type = strtolower(substr(strrchr($filename, '.'),1));
        if(isset($contentType[$type])) {
            $mime = $contentType[$type];
        }else {
            $mime = 'application/octet-stream';
        }
        return $mime;
    }
}