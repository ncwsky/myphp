<?php
namespace myphp;

//辅助类
class Helper{
    public static $isProxy = false;
    //日期检测函数(格式:2007-5-6[ 15:30:33])
    public static function is_date($date) {
        return preg_match('/^(\d{4})-(0[1-9]|[1-9]|1[0-2])-(0[1-9]|[1-9]|1\d|2\d|3[0-1])(| (0[0-9]|[0-9]|1[0-9]|2[0-3]):([0-5][0-9]|0[0-9]|[0-9])(|:([0-5][0-9]|0[0-9]|[0-9])))$/', $date);
    }
    //Ymd检测函数(格式:2007-5[-6])
    public static function is_ymd($date) {
        return preg_match('/^(\d{4})-(0[1-9]|[1-9]|1[0-2])(|-(0[1-9]|[1-9]|1\d|2\d|3[0-1]))$/', $date);
    }
    //His检测函数(格式:15:30[:33])
    public static function is_his($date) {
        return preg_match('/^(0[0-9]|[0-9]|1[0-9]|2[0-3]):([0-5][0-9]|0[0-9]|[0-9])(|:([0-5][0-9]|0[0-9]|[0-9]))$/', $date);
    }
    public static function is_json($data){
        return json_decode($data)===null?false:true;
    }
    //判断email格式是否正确
    public static function is_email($email) {
        return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
    }
    //判断是否手机号
    public static function is_tel($mobile){
        return strlen($mobile)==11 && preg_match("/^1[3456789]\d{9}$/", $mobile);
    }
    //判断是否IP
    public static function is_ip($ip){
        return preg_match("/^((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d))))$/", $ip);
    }
    // Returns true if $string is valid UTF-8 and false otherwise.
    public static function is_utf8($word){
        $regx = '(['.chr(228).'-'.chr(233).']{1}['.chr(128).'-'.chr(191).']{1}['.chr(128).'-'.chr(191).']{1})';
        if (preg_match('/^'.$regx.'{1}/',$word) == true || preg_match('/'.$regx.'{1}$/',$word) == true || preg_match('/'.$regx.'{2,}/',$word) == true){
            return true;
        }else{
            return false;
        }
    }
    //检测是否手机浏览 return bool true|false
    public static function is_mobile() {
        static $is_mobile;
        if ( isset($is_mobile) ) return $is_mobile;

        if ( empty($_SERVER['HTTP_USER_AGENT']) ) {
            $is_mobile = false;
        } elseif ( strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false // many mobile devices (all iPhone, iPad, etc.)
            || strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], 'Silk/') !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], 'Kindle') !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi') !== false ) {
            $is_mobile = true;
        } else {
            $is_mobile = false;
        }
        return $is_mobile;
    }
    //是否微信
    public static function is_weixin(){
        if ( isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            return true;
        }
        return false;
    }

    //生成单号
    public static function createSN($prefix=''){
        //$chars = substr(microtime(),2,6); substr(str_shuffle($chars.$chars),0,6);
        $sn = date('YmdHis').substr(microtime(),2,4).str_pad(mt_rand(0,99), 2, '0', STR_PAD_LEFT); //20位
        return $prefix.$sn;
    }
    /**
     * 分页初始
     * @param [int|obj] $tb [需分页的模型/或数据行数]
     * @param [array] $where [查询条件]
     * @param int|string $num [每页数量|每页数,显示页码数]
     * @param string $id [count字段]
     * @param string $parm [附加参数]
     * @param string $pName
     * @return array
     */
    public static function initPage($tb, $where = '', $cPage=1, $num = 10, $id = '', $parm = '', $pName='page') {
        $initPageNum = 5;
        if (strpos($num, ',')) {
            list($num, $initPageNum) = explode(',', $num);
        }
        $total = is_numeric($tb) ? (int)$tb : ($id != '' ? $tb->where($where)->count($id) : $tb->where($where)->count()); //获取总行数
        $pCount = $total ? ceil($total / $num) : 1;
        if ($cPage < 1) $cPage = 1;
        if ($cPage > $pCount) $cPage = $pCount;
        $prev = $cPage > 1 ? $cPage - 1 : ''; //上一页
        $next = $cPage < $pCount ? $cPage + 1 : ''; //下一页
        $path = \myphp::$env['BASE_URL'];
        $path = $path != '/' ? rtrim($path, '/') : $path;
        $qstr = !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        if (strpos($qstr, $pName) !== false) {
            $qstr = preg_replace("/$pName=\d{1,}/i", '', $qstr);
            $qstr = str_replace('&&', '&', trim($qstr, '&'));
        }
        $pages = ['size'=>$num, 'total' => $total, 'cPage' => $cPage, 'pCount' => $pCount, 'pNum' => array()];
        //获取页码范围
        if ($cPage <= 1) {
            $TmpPageNo = 1;
            $TmpPageNum = $initPageNum;
        } elseif ($cPage <= $initPageNum) {
            $TmpPageNo = 1;
            $TmpPageNum = $initPageNum + $cPage - 1;
        } elseif ($cPage > $initPageNum && $cPage < $pCount) {
            $TmpPageNo = $cPage - $initPageNum;
            $TmpPageNum = $initPageNum + $cPage - 1;
        } elseif ($cPage >= $pCount) {
            $TmpPageNo = $cPage - $initPageNum;
            $TmpPageNum = $pCount;
        }
        //页码超出范围
        if ($TmpPageNum > $pCount) $TmpPageNum = $pCount;
        for ($PageNo = $TmpPageNo; $PageNo <= $TmpPageNum; $PageNo++) {
            $pages['pNum'][$PageNo] = $cPage == $PageNo ? 'javascript:' : "$path?$pName=$PageNo" . ($qstr != '' ? '&' . $qstr : '') . ($parm != '' ? '&' . $parm : '');
        }
        $pages['first'] = $prev == '' ? 'javascript:' : "$path?$pName=1" . ($qstr != '' ? '&' . $qstr : '') . ($parm != '' ? '&' . $parm : '');
        $pages['prev'] = $prev == '' ? 'javascript:' : "$path?$pName=$prev" . ($qstr != '' ? '&' . $qstr : '') . ($parm != '' ? '&' . $parm : '');
        $pages['nopage'] = "$path?" . ($qstr != '' ? $qstr . '&' : '') . ($parm != '' ? $parm . '&' : '') . "$pName="; //可自行在尾部补加页数
        $pages['curr'] = "$path?$pName=$cPage" . ($qstr != '' ? '&' . $qstr : '') . ($parm != '' ? '&' . $parm : '');
        $pages['next'] = $next == '' ? 'javascript:' : "$path?$pName=$next" . ($qstr != '' ? '&' . $qstr : '') . ($parm != '' ? '&' . $parm : '');
        $pages['last'] = $next == '' ? 'javascript:' : "$path?$pName=$pCount" . ($qstr != '' ? '&' . $qstr : '') . ($parm != '' ? '&' . $parm : '');
        return $pages; //当前页码 总页码 总数 上一页链接 下一页链接
    }
    //获取分页偏移值
    public static function getOffset($init=1, $num=10, $step=0)
    {
        if ($init < 1) $init = 1; //验证（>＝1）
        if ($init == 1) {
            $offset =  $step . ',' . $num;
        } else {
            $offset = (($init - 1) * $num + $step) . ',' . $num;
        }
        return $offset;
    }
    // 跳转提示信息输出: ([0,1]:)信息标题, url, 辅助信息, 等待时间（秒） 用于前端自动定义信息输出模板
    public static function outMsg($msg, $url='', $info='', $time = 1) {
        $is_url = false;
        if ($url=='') {
            $jumpUrl = 'javascript:window.history.back()';
            $js = 'window.history.back()';
        } elseif (substr($url,0,11)=='javascript:') {
            $jumpUrl = $url;
            $js = substr($url,11);
        } else {
            $jumpUrl = $url;
            $js = "window.location='$jumpUrl'";
            $is_url = true;
        }
        $ok = substr($msg, 0, 2); //提示状态 默认为普通
        $code = 0;
        $flag = 'normal'; //普通提示
        if ($ok == '1:' || $ok == '0:') {
            $msg = substr($msg, 2);
            if ($ok == '0:') {
                $code = 1;
                $flag = 'error'; //错误提示
            } else {
                $flag = 'success'; //成功提示
            }
        }

        if (ob_get_length() !== false) ob_clean();//清除页面
        if (self::isAjax()) { //ajax输出
            $data = ['info' => $info, 'time' => $time];
            if ($is_url) $data['_url'] = $jumpUrl;
            $json = ['code' => $code, 'msg' => $msg, 'data' => $data];
            if (IS_CLI) return self::toJson($json);
            exit(self::toJson($json));
        }

        $out_html = '<!doctype html><html><head><meta charset="utf-8"><title>'.($url!='nil'?'跳转提示':'信息提示').'</title><style type="text/css">*{padding:0;margin:0}body{background:#fff;font-family:"Microsoft YaHei";color:#333;font-size:100%}.system-message{padding:1.5em 3em}.system-message h1{font-size:6.25em;font-weight:400;line-height:120%;margin-bottom:.12em}.system-message .jump{padding-top:.625em}.system-message .jump a{color:#333}.system-message .success{color:#207E05}.system-message .error{color:#da0404}.system-message .normal,.system-message .success,.system-message .error{line-height:1.8em;font-size:2.25em}.system-message .detail{font-size:1.2em;line-height:160%;margin-top:.8em}</style></head><body><div class="system-message">';

        $out_html .= '<h1>'. ($code ? ':(' : ':)').'</h1><p class="'.$flag.'">'.$msg.'</p>'; //输出

        $out_html .= $info!=''?'<p class="detail">'.$info.'</p>':'';
        if($url!='nil') //提示不跳转
            $out_html .= '<p class="jump">页面自动 <a id="href" href="'.$jumpUrl.'">跳转</a>  等待时间： <b id="time">'.$time.'</b></p></div><script type="text/javascript">var pgo=0,t=setInterval(function(){var time=document.getElementById("time");var val=parseInt(time.innerHTML)-1;time.innerHTML=val;if(val<=0){clearInterval(t);if(pgo==0){pgo=1;'.$js.';}}},1000);</script></body></html>';
        if(IS_CLI) {
            \myphp::conType('text/html');
            return $out_html;
        }
        exit($out_html);
    }

    /**
     * 是否允许的ip
     * @param string $ip 验证ip
     * @param string|array $allowIps
     * @return bool
     */
    public static function allowIp($ip, $allowIps=""){
        if (is_string($allowIps)) { // "127.0.0.1 10.0.0.2"
            if (!$allowIps || $allowIps === "0.0.0.0") return true;
            return strpos($allowIps, $ip) !== false;
        }
        if (is_array($allowIps)) {
            foreach ($allowIps as $allow_ip) { // [10.1.1.2,10.1.,127.0.0.1]
                if (strpos($ip, $allow_ip) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
    public static function getSiteUrl(){
        return Request::siteUrl();
    }
    public static function getHost(){
        return Request::host();
    }
    //获得当前的脚本网址  如/ab.php?b=1
    public static function getUri() {
        return Request::uri();
    }
    //获取当前页面完整URL地址 如http://xx/a.php?b=1
    public static function getUrl() {
        return Request::url();
    }
    //来源获取
    public static function getReferer(){
        return Request::referer();
    }
    //获取用户真实地址 返回用户ip  type:0 返回IP地址 1 返回IPV4地址数字
    public static function getIp($type=0) {
        return Request::ip($type ? true : false);
    }
    /**
     * 当前的请求类型
     * @return string
     */
    public static function getMethod()
    {
        return Request::method();
    }
    public static function isPost(){
        return Request::isPost();
    }
    public static function isGet(){
        return Request::isGet();
    }
    // 当前是否Ajax请求
    public static function isAjax()
    {
        //跨域情况  // javascript 或 JSONP 格式    //  JSON 格式
        //isset($_SERVER['HTTP_ACCEPT']) && ( $_SERVER['HTTP_ACCEPT']=='text/javascript, application/javascript, */*' || $_SERVER['HTTP_ACCEPT']=='application/json, text/javascript, */*')
        return Request::isAjax();
    }
    /**
     * 简要的mime_type类型
     * @param string $filename
     * @return string
     */
    public static function minMimeType($filename)
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filename);
        }

        static $mimeType = array(
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',

            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/x-wav',
            'ogg' => 'audio/ogg',

            'avi' => 'video/x-msvideo',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'ogv' => 'video/ogg',
            'webm' => 'video/webm',
            'flv' => 'video/x-flv',

            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'ppt' => 'application/vnd.ms-powerpoint',
            'xls' => 'application/vnd.ms-excel',
            'xlt' => 'application/vnd.ms-excel',

            'tar' => 'application/x-tar',
            '7z' => 'application/x-7z-compressed',
            'rar' => 'application/x-rar-compressed',
            'zip' => 'application/zip',
        );
        $mime = 'application/octet-stream';
        $pos = strrpos($filename, '.');
        if (!$pos) return $mime;
        $type = strtolower(substr($filename, $pos + 1));
        if (isset($mimeType[$type])) {
            $mime = $mimeType[$type];
        }
        return $mime;
    }
    //json_encode 缩写
    public static function toJson($res, $option=0){
        if ($option == 0 && defined('JSON_UNESCAPED_UNICODE')) {
            $option = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $option |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        return json_encode($res, $option);
    }
    //toXml 转换成xml
    public static function toXml($res,$rec=false){
        $xml = $rec ? '' : '<root>';
        foreach ($res as $k => $v) {
            if (is_array($v)) {
                $xml .= '<' . $k . '>' . self::toXml($v, true) . '</' . $k . '>';
            } else {
                $xml .= '<' . $k . '>' . (is_numeric($v) ? $v : '<![CDATA[' . $v . ']]>') . '</' . $k . '>';
            }
        }
        $xml .= $rec ? '' : '</root>';
        unset($res);
        return $xml;
    }
    //将XML转为array
    public static function xmlToArr($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
    //仅记录指定大小的日志 超出大小重置重新记录
    public static function toFileLog($file, $content, $size = 4194304){ //日志大小 4M
        if(is_file($file) && $size <= filesize($file) ){
            file_put_contents($file, '', LOCK_EX);
            clearstatcache(true, $file);
        }
        file_put_contents($file, "[".date("Y-m-d H:i:s").'.'.substr(microtime(), 2, 3)."]".(is_scalar($content)?$content:self::toJson($content))."\n", FILE_APPEND);
    }
    /**
     * 字符串转十六进制函数
     * @param string $str ='abc';
     * @param bool $toUpper 是否转大写
     * @return string
     */
    public static function strToHex($str, $toUpper = false)
    {
        $hex = "";
        for ($i = 0; $i < strlen($str); $i++){
            $dec = ord($str[$i]);
            $hex .= ($dec < 16 ? '0' : '') . dechex($dec);
        }
        $toUpper && $hex = strtoupper($hex);
        return $hex;
    }
    /**
     * 十六进制转字符串函数
     * @param string $hex ='616263';
     * @return string
     */
    public static function hexToStr($hex)
    {
        $str = "";
        for ($i = 0; $i < strlen($hex) - 1; $i += 2)
            $str .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        return $str;
    }

    /* ini内容 解析
     * string str  ini配置内容
     * string find  ini配置项  [name]
     * string attr  查找配置项的属性
     */
    public static function readIni($str, $find, $attr=''){
        $str = trim($str);
        if($str=='') return '';
        $br = chr(13).chr(10);
        $val = '';
        $wz = strpos($str, $find.$br);
        if($wz!==false){ //无此配置项
            $wz += strlen($find.$br);
            if($attr!=''){
                $wz = strpos($str, $attr.'=', $wz);
                if($wz!==false){ //无属性值
                    $wz += strlen($attr.'=');
                    $wz_end = strpos($str, $br, $wz);
                    $val = $wz_end!==false ? substr($str, $wz, $wz_end-$wz) : substr($str, $wz);
                }
            }else{ //读取配置项所有属性
                $wz_end = strpos($str, $br.'[', $wz);
                $val = $wz_end!==false ? substr($str, $wz, $wz_end-$wz) : substr($str, $wz);
            }
        }
        return $val;
    }
    /* ini内容 设置
     * string str  ini配置内容
     * string find  ini配置项 [name]
     * string attr_val  配置项属性=属性值 attr=val
     * bool $isBat 批量直接替换写入 $attr_val\n$attr_val
     */
    public static function writeIni($str, $find, $attr_val,$isBat=false){
        $str = trim($str);
        $attr_val = trim($attr_val);
        if(strpos($attr_val,'=')===false && !$isBat) return $str;

        $br = chr(13).chr(10);
        $wz = strpos($str, $find.$br);
        $attr = ''; $val = '';
        if($isBat){ //多项直接替换
            $attrs = explode("\n", $attr_val);
            $attr_val = '';
            foreach ($attrs as $aVal) {
                if(strpos($aVal,'=')){
                    list($attr,$val) = explode('=', $aVal);
                    if($attr=='') continue;

                    $attr_val = $attr_val==''?$aVal:$attr_val."\n".$aVal;
                }
            }
        }else{
            list($attr,$val) = explode('=', $attr_val);
            if($attr=='') return $str;
        }

        if($wz!==false){ //无此配置项
            $wz += strlen($find.$br); $wz_a = 0;

            !$isBat && $wz_a = strpos($str, $attr.'=', $wz);
            if(!$isBat && $wz_a!==false && strpos(substr($str,$wz,$wz_a-$wz),'[')===false){ //存在此属性
                $wz_a += strlen($attr.'=');
                $wz_end = strpos($str, $br, $wz_a);
                if($wz_end!==false)
                    $str = substr($str,0,$wz_a). $val .substr($str,$wz_end);
                else
                    $str = substr($str,0,$wz_a). $val;
            }else{//不存在
                $wz = $wz-strlen($br);
                $wz_end = strpos($str, $br.'[', $wz);
                if($wz_end!==false)
                    $str = substr($str,0, $isBat?$wz:$wz_end). $br.$attr_val . substr($str,$wz_end);
                else //配置项在最尾
                    $str = ($isBat?substr($str,0, $wz):$str).$br.$attr_val;
            }
        }else //直接追加
            $str .= $br.$find.$br.$attr_val;
        return $str;
    }
    /** 异或加密
     * @param string $str 加密解密串
     * @param string $key 加密key
     * @return string
     */
    public static function xorEnc($str,$key)
    {
        $code = '';
        $keyLen = strlen($key);
        for($i=0;$i<strlen($str);$i++)
        {
            $k = $i%$keyLen;
            $code .= $str[$i] ^ $key[$k];
        }
        return $code;
    }
    /** rc4加密算法
     * @param string $data 要加密的数据
     * @param string $key 密钥
     * @return string
     */
    public static function rc4($data, $key)//$pwd密钥　$data需加密字符串
    {
        $sBox = array(); $keys = array();
        $keyLen = strlen($key); $dataLen = strlen($data);
        $cipher = '';

        for ($i = 0; $i < 256; $i++){
            $sBox[$i] = $i;
            $keys[$i] = ord($key[$i % $keyLen]);
        }

        for ($j = $i = 0; $i < 256; $i++){
            $j = ($j + $sBox[$i] + $keys[$i]) % 256;
            $tmp = $sBox[$i];
            $sBox[$i] = $sBox[$j];
            $sBox[$j] = $tmp;
        }

        $j = $l = $k = 0;
        for ($i = 0; $i < $dataLen; $i++){
            $j = ($j + 1) % 256;
            $l = ($l + $sBox[$j]) % 256;

            $tmp = $sBox[$j];
            $sBox[$j] = $sBox[$l];
            $sBox[$l] = $tmp;

            $k = ($sBox[$j] + $sBox[$l]) % 256;
            $cipher .= chr(ord($data[$i]) ^ $sBox[$k]);
        }
        return $cipher;
    }
    public static function authCode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
        $ckey_length = 4;
        $key = md5($key != '' ? $key : GetC('encode_key'));
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE') {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }
    }

    /**
     * aes加密
     * @param string $str
     * @param string $key
     * @param string $method cbc|cfb|ecb|nofb|ofb|stream
     * @return string
     */
    public static function aesEncrypt($str,$key,$method='cbc')
    {
        $keyLen = strlen($key);
        if(!function_exists('openssl_encrypt')){ //mcrypt_encrypt
            if($keyLen<=16){
                $cipher = 'rijndael-128';
                if($keyLen<16) $key = str_pad($key,16,"\0");
            }elseif($keyLen<=24){
                $cipher = 'rijndael-192';
                if($keyLen<24) $key = str_pad($key,24,"\0");
            }else{ //超出32位截断
                $cipher = 'rijndael-256';
                if($keyLen>32) $key = substr($key,0,32);
                elseif($keyLen<32) $key = str_pad($key,32,"\0");
            }
            $ivlen = mcrypt_get_iv_size($cipher,$method);
            $iv = strlen($key)<$ivlen?str_pad($key,$ivlen,"\0"):substr($key,0, $ivlen);
            //echo $ivlen,$cipher,'===',$key,'===',$iv,'<br>';
            $block = mcrypt_get_block_size($cipher, $method);
            $pad = $block - (strlen($str) % $block);
            $str .= str_repeat(chr($pad), $pad);
            return base64_encode(mcrypt_encrypt($cipher, $key, $str, $method, $iv));
        }

        if($keyLen<=16){
            $method = 'aes-128-'.$method;
            if($keyLen<16) $key = str_pad($key,16,"\0");
        }elseif($keyLen<=24){
            $method = 'aes-192-'.$method;
            if($keyLen<24) $key = str_pad($key,24,"\0");
        }else{ //超出32位截断
            if($keyLen>32) $key = substr($key,0,32);
            elseif($keyLen<32) $key = str_pad($key,32,"\0");
            $method = 'aes-256-'.$method;
        }

        $ivlen = openssl_cipher_iv_length($method);
        $iv = strlen($key)<$ivlen?str_pad($key,$ivlen,"\0"):substr($key,0, $ivlen);
        //echo $ivlen,$method,'===',$key,'===',$iv,'<br>';
        return base64_encode(openssl_encrypt($str, $method, $key, OPENSSL_RAW_DATA, $iv)); //OPENSSL_RAW_DATA  OPENSSL_ZERO_PADDING
    }

    /**
     * aes解密
     * @param string $str
     * @param string $key
     * @param string $method cbc|cfb|ecb|nofb|ofb|stream
     * @return false|string
     */
    public static function aesDecrypt($str,$key,$method='cbc')
    {
        $keyLen = strlen($key);
        if(!function_exists('openssl_encrypt')){ //mcrypt_decrypt
            if($keyLen<=16){
                $cipher = 'rijndael-128';
                if($keyLen<16) $key = str_pad($key,16,"\0");
            }elseif($keyLen<=24){
                $cipher = 'rijndael-192';
                if($keyLen<24) $key = str_pad($key,24,"\0");
            }else{ //超出32位截断
                $cipher = 'rijndael-256';
                if($keyLen>32) $key = substr($key,0,32);
                elseif($keyLen<32) $key = str_pad($key,32,"\0");
            }

            $ivlen = mcrypt_get_iv_size($cipher,$method);
            $iv = strlen($key)<$ivlen?str_pad($key,$ivlen,"\0"):substr($key,0, $ivlen);

            $encryptedData = mcrypt_decrypt($cipher, $key, base64_decode($str), $method, $iv);
            $e = ord($encryptedData[strlen($encryptedData)-1]);
            if($e<=$ivlen) $encryptedData=substr($encryptedData, 0,strlen($encryptedData)-$e);
            return $encryptedData;
        }

        if($keyLen<=16){
            $method = 'aes-128-'.$method;
            if($keyLen<16) $key = str_pad($key,16,"\0");
        }elseif($keyLen<=24){
            $method = 'aes-192-'.$method;
            if($keyLen<24) $key = str_pad($key,24,"\0");
        }else{ //超出32位截断
            if($keyLen>32) $key = substr($key,0,32);
            elseif($keyLen<32) $key = str_pad($key,32,"\0");
            $method = 'aes-256-'.$method;
        }
        $ivlen = openssl_cipher_iv_length($method);
        $iv = strlen($key)<$ivlen?str_pad($key,$ivlen,"\0"):substr($key,0, $ivlen);
        return openssl_decrypt(base64_decode($str), $method, $key, OPENSSL_RAW_DATA, $iv);
    }
    //uuid生成
    public static function UUID($upper = false, $prefix = '')
    {
        $data = uniqid($prefix, true) .
            '-' . $_SERVER['SCRIPT_FILENAME'] .
            (isset($_SERVER['HTTP_USER_AGENT']) ? '-' . $_SERVER['HTTP_USER_AGENT'] : '') .
            '-' . mt_rand(0, 0xffff) .
            '-' . microtime();

        $hash = hash('ripemd128', uniqid($prefix, true) . '-' . $data);
        if ($upper) $hash = strtoupper($hash);
        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-' . substr($hash, 12, 4) . '-' . substr($hash, 16, 4) . '-' . substr($hash, 20, 12);
    }
    /**
     * 获取[多维]数组指定键名的值 不存在返回默认值.
     * examples
     * // working with array
     * $username = Helper::getValue($_POST, 'username');
     * or
     * $value = Helper::getValue($users, 'x.y');
     * or
     * $value = Helper::getValue($versions, ['x', 'y']);
     * // working with anonymous function
     * $fullName = Helper::getValue($user, function ($user, $defaultValue) {
     *     return $user->firstName . ' ' . $user->lastName;
     * });
     *
     * @param array $array array or object to extract value from
     * @param string|\Closure|array $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue($array, $key, $default = null)
    {
        if (!is_array($array)) return $default;
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }
        if (is_string($key) && strpos($key, '.')) {
            $key = explode('.', $key);
        }
        if (is_array($key)) {
            foreach ($key as $k) {
                if (is_array($array) && (isset($array[$k]) || array_key_exists($k, $array))) {
                    $array = $array[$k];
                } else {
                    return $default;
                }
            }
            return $array;
        }
        return is_array($array) && (isset($array[$key]) || array_key_exists($key, $array)) ? $array[$key] : $default;
    }
    /**
     * 返回多维数组或对象数组指定的列
     *
     * For example,
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc'],
     *     ['id' => '345', 'data' => 'def'],
     * ];
     * $result = Helper::getColumn($array, 'id');
     * // the result is: ['123', '345']
     *
     * // using anonymous function
     * $result = Helper::getColumn($array, function ($element) {
     *     return $element['id'];
     * });
     * ```
     * @param array $array
     * @param string|\Closure $name 列名
     * @param bool $keepKeys 保持键名.
     * @return array 数组列
     */
    public static function getColumn($array, $name, $keepKeys = true)
    {
        $result = [];
        if ($keepKeys) {
            foreach ($array as $k => $element) {
                $result[$k] = static::getValue($element, $name);
            }
        } else {
            foreach ($array as $element) {
                $result[] = static::getValue($element, $name);
            }
        }

        return $result;
    }
    /**
     * 数组多键名排序
     * @param array $array
     * @param string|array $key 要排序的字段
     * @param int|array $direction 排序方式 数组时要同key的个数相同. `SORT_ASC` or `SORT_DESC`.
     * @param int|array $sortFlag 排序类型
     * `SORT_REGULAR`, `SORT_NUMERIC`, `SORT_STRING`, `SORT_LOCALE_STRING`, `SORT_NATURAL` and `SORT_FLAG_CASE`.
     * Please refer to [PHP manual](http://php.net/manual/en/function.sort.php)
     * for more details. When sorting by multiple keys with different sort flags, use an array of sort flags.
     * @throws \InvalidArgumentException if the $direction or $sortFlag parameters do not have
     */
    public static function arrayMultiSort(&$array, $key, $direction = SORT_ASC, $sortFlag = SORT_REGULAR)
    {
        $keys = is_array($key) ? $key : [$key];
        if (empty($keys) || empty($array)) {
            return;
        }
        $n = count($keys);
        if (is_scalar($direction)) {
            $direction = array_fill(0, $n, $direction);
        } elseif (count($direction) !== $n) {
            throw new \InvalidArgumentException('The length of $direction parameter must be the same as that of $keys.');
        }
        if (is_scalar($sortFlag)) {
            $sortFlag = array_fill(0, $n, $sortFlag);
        } elseif (count($sortFlag) !== $n) {
            throw new \InvalidArgumentException('The length of $sortFlag parameter must be the same as that of $keys.');
        }
        $args = [];
        foreach ($keys as $i => $key) {
            $flag = $sortFlag[$i];
            $args[] = static::getColumn($array, $key);
            $args[] = $direction[$i];
            $args[] = $flag;
        }

        // This fix is used for cases when main sorting specified by columns has equal values
        // Without it it will lead to Fatal Error: Nesting level too deep - recursive dependency?
        $args[] = range(1, count($array));
        $args[] = SORT_ASC;
        $args[] = SORT_NUMERIC;

        $args[] = &$array;
        call_user_func_array('array_multisort', $args);
    }
}