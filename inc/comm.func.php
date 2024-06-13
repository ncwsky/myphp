<?php
/**
 *  comm.func.php 功能函数库
 */
function e404(){header("HTTP/1.1 404 Not Found");header('Status:404 Not Found');exit;}
/**
 * URL重定向
 * @param string $url 重定向的URL地址
 * @param integer $time 重定向的等待时间（秒）
 * @param string $msg 重定向前的提示信息
 * @return string|void
 */
function redirect($url, $time=0, $msg='') {
    if ($time && empty($msg)) {
        $msg = "系统将在{$time}秒之后自动跳转到{$url}！";
    }
    // 如果报头未发送，则发送
    if (!headers_sent()) {// redirect
        if (0 === $time) {
            header('Location: ' . $url);
            $msg = '';
        } else {
            header("refresh:{$time};url={$url}");
        }
    } else {
        $msg = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>" . $msg;
    }
    if(IS_CLI) return $msg;
    exit($msg);
}

//转义 javascript 代码标记
function trim_script($str) {
    if(is_array($str)){
        foreach ($str as $key => $val){
            $str[$key] = trim_script($val);
        }
    }else{
        $str = trim($str);
        $str = remove_xss($str);
        /*
        $str = preg_replace ( '/\<([\/]?)script([^\>]*?)\>/si', '&lt;\\1script\\2&gt;', $str );
       $str = preg_replace ( '/\<([\/]?)iframe([^\>]*?)\>/si', '&lt;\\1iframe\\2&gt;', $str );
       $str = preg_replace ( '/\<([\/]?)frame([^\>]*?)\>/si', '&lt;\\1frame\\2&gt;', $str );
       $str = preg_replace ( '/]]\>/si', ']] >', $str );*/
    }
    return $str;
}
//字符截取 $string中汉字、英文字母、数字、符号每个在$len中占一个数，不存在汉字占两个字节的考虑
function cutstr($str, $len, $suffix = '', $offset=0) {
    if (function_exists('mb_substr')){
        $str = mb_substr($str, $offset, $len, 'UTF-8').(mb_strlen($str)>$len?$suffix:'');
    }
    else{
        preg_match_all('/./u', $str, $arr);//su 匹配所有的字符，包括换行符 /u 匹配所有的字符，不包括换行符
        //var_dump($arr);
        $str = implode('', array_slice($arr[0], $offset, $len));
        if (count($arr[0]) > $len) {
            $str .= $suffix;
        }
    }
    return $str;
}
/**
 * 内容截取 substr_cut
 *
 * @param [string] $str   要处理的字符串
 * @param [string] $s_flag 开始标识
 * @param [string] $e_flag 结束标识
 * @param int $offset 起始位置
 * @param boolean $case 区分大小写 true不区分大小写
 * @param int $pos_e 结束标识位置
 * @return string
 */
function substr_cut($str, $s_flag, $e_flag, $offset=0, $case=true, &$pos_e=0){
    $pos_s = $case ? stripos($str, $s_flag, $offset) : strpos($str, $s_flag, $offset);
    if($pos_s===false) return '';
    $pos_s += strlen($s_flag);
    $pos_e = $case ? stripos($str, $e_flag, $pos_s) : strpos($str, $e_flag, $pos_s);
    return $pos_e ? substr($str, $pos_s, $pos_e-$pos_s) : substr($str, $pos_s);
}
//正则去除字符串首尾处空白字符-支持中文
function cn_trim($str, $charlist='\s'){
    return preg_replace('/^['.$charlist.']+|['.$charlist.']+$/u', '', $str);
}
//字符长度 汉字、英文字母、数字、符号每个在$len中占一个数
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding='UTF-8'){
        preg_match_all('/./u', $str, $arr);
        $len = count($arr[0]);
        unset($arr);
        return $len;
    }
}else{
    /* 设置内部字符编码为 UTF-8 */
    mb_internal_encoding("UTF-8");
}
// 清除HTML代码
function html_clean($str) {
    $str = htmlspecialchars($str);
    $str = str_replace("\n", "<br />", $str);
    $str = str_replace("  ", "&nbsp;&nbsp;", $str);
    $str = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $str);
    return $str;
}
// html_clean 反转
function html_clean_decode($str){
    $str = htmlspecialchars_decode($str);
    $str = str_replace(array('<br>','<br />'), "\n", $str);
    $str = str_replace("&nbsp;&nbsp;", "  ", $str);
    return $str;
}
function html_encode($content)
{
    return htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function html_decode($content)
{
    return htmlspecialchars_decode($content, ENT_QUOTES);
}
//html to txt
function Html2Text($str){
    $alltext = strip_tags($str);
    $alltext = str_replace("　"," ",$alltext);
    $alltext = preg_replace("/&[^;&]*[;&]/","",$alltext);
    $alltext = preg_replace("/[\s]+/s"," ",$alltext);
    return $alltext;
}
//自动闭合html标签
function closetags($html) {
    preg_match_all('#<([a-z]+)(?: .*)?(?<!/|/ )>#iU', $html, $result);
    $openedtags = $result[1];

    preg_match_all('#</([a-z]+)>#iU', $html, $result);
    $closedtags = $result[1];
    $len_opened = count($openedtags);

    if (count($closedtags) == $len_opened) {
        return $html;
    }
    $openedtags = array_reverse($openedtags);

    for ($i=0; $i < $len_opened; $i++) {
        if ($openedtags[$i] == 'br') continue;
        if (!in_array($openedtags[$i], $closedtags)){
            $html .= '</'.$openedtags[$i].'>';
        } else {
            unset($closedtags[array_search($openedtags[$i], $closedtags)]);
        }
    }
    return $html;
}
//html截取 并自动闭合
function cut_html($s,$max_len=250){
    if(strlen($s)>$max_len){
        $i=strpos($s,"\n");//查询是否存在回车换行符
        if($i>0){
            $t=explode("\n",$s);//'分隔回车换行符
            $s="";
            foreach($t as $k){
                $s .= $k;
                if(strlen($s)>$max_len) break;
            }
        }
    }
    return closetags($s);
}

/**
 * 递归合并数组-键不存在、类型不同或指定更新
 * @param array $arr1 目标数组
 * @param array $arr2 合并数组
 * @param bool $strict 严格验证
 */
function array_walk_merge(&$arr1, &$arr2, $strict=false){
    foreach ($arr2 as $k => $v) {
        if (substr($k, 0, 1) == '@') continue;
        if ($strict) {
            $t1 = isset($arr1[$k]) ? gettype($arr1[$k]) : 'NULL';
            $t2 = gettype($v);
            if ($t1 != $t2) { //类型不同
                $arr1[$k] = $v;
            } elseif ($t1 == 'array') {
                array_walk_merge($arr1[$k], $v, $strict);
            }
        } else {
            $updateK = '@' . $k;
            //键不存在、有指定更新标识或存在的更新标识不相同 覆盖值
            if (!isset($arr1[$k]) || (isset($arr2[$updateK]) && (!isset($arr1[$updateK]) || $arr1[$updateK] != $arr2[$updateK]))) {
                $arr1[$k] = $v;
            } elseif (is_array($v) && is_array($arr1[$k])) {
                array_walk_merge($arr1[$k], $v, $strict);
            }
        }
    }
}

/**
 * 二维数组 排序
 * @param array $array 传入的数组
 * @param string $row_id 数组想排序的项
 * @param string $order_type 排序的方式，asc或者desc；
 * @param bool $auto 是否开启自动键值（默认开启，字符键值时可以关闭）
 * @return array
 */
function array_sort($array, $row_id, $order_type='asc', $auto=true){
    $array_temp=array();
    foreach($array as $key=>$value){//循环一层；
        $array_temp[$key]=$value[$row_id];//新建一个一维的数组，索引值用二维数组的索引值；值为二维数组要比较的项目的值；
    }
    if($order_type=="asc"){
        asort($array_temp);
    }else{
        arsort($array_temp);
    }
    $result_array=array();
    if($auto){
        foreach($array_temp as $key=>$value){//对进行筛选过的数组遍历；s
            $result_array[]=$array[$key];//新建一个结果数组，将原来传入的数组改变键值顺序后赋值给结果数组（原来数组不变）；
        }
    }else{
        foreach($array_temp as $key=>$value){
            $result_array[$key]=$array[$key];//新建一个结果数组，将原来传入的数组按原有的键值顺序后赋值给结果数组（原来数组不变）；
        }
    }
    return $result_array;
}
/**
 * 打乱数组,保持键值对关系
 * @param array $array
 * @return true
 */
function shuffle_assoc(&$array) {
    $keys = array_keys($array);
    shuffle($keys);
    $new = array();
    foreach ($keys as $key){
        $new[$key] = $array[$key];
    }
    $array = $new;
    return true;
}

/**
 * 将日期格式根据以下规律修改为不同显示样式
 * 小于1分钟 则显示多少秒前
 * 小于1小时，显示多少分钟前
 * 一天内，显示多少小时前
 * 3天内，显示前天22:23或昨天:12:23。
 * 超过3天，则显示完整日期 'Y-m-d H:i'。
 * @param int $time 数据源日期 unix时间戳
 * @param bool $hi
 * @return false|string
 */
function getDateStyle($time,$hi=true){
    $nowTime = time();  //获取今天时间戳
    //一分钟
    if(($time+60)>=$nowTime){
        return ($nowTime-$time) ."秒前";
    }
    //小时
    if(($time+3600)>=$nowTime){
        return floor(($nowTime-$time)/60) ."分钟前";
    }
    //天
    if($time+86400>=$nowTime){
        return floor(($nowTime-$time)/3600) .'小时前';
    }
    $time_0 = strtotime(date('Y-m-d',$time));
    //昨天
    if(($time_0+86400*2)>=$nowTime){
        return '昨天'.date('H:i',$time);
    }
    //前天
    if(($time_0+86400*3)>=$nowTime){
        return '前天'.date('H:i',$time);
    }
    //3天前
    if(($time_0+86400*4)>=$nowTime){
        return '3天前';
    }

    return $hi ? date('Y-m-d H:i',$time) : date('Y-m-d',$time);
}

/*
' 对于数组变量检测 1、数组变量 $var 2、数组检测项(a.b) $var['a']['b'] 便于变量isset检查
*/
function Q2($var, $key, $defValue = ''){
    return \myphp\Helper::getValue($var, $key, $defValue);
}
//提示信息显示
function ShowMsg($message, $url='', $info='', $time = 2) {
    if ($url=='') {
        $jumpUrl = 'javascript:window.history.back()';
        $js = 'window.history.back()';
    } elseif (substr($url,0,11)=='javascript:') {
        $jumpUrl = $url;
        $js = substr($url,11);
    } else {
        $jumpUrl = $url;
        $js = "window.location='$jumpUrl'";
    }
    if(ob_get_length() !== false) ob_clean();//清除页面

    $out_html = '<!doctype html><html><head><meta charset="utf-8"><title>信息提示</title><script type="text/javascript">var pgo=0;function jumpUrl(){if(!pgo){'. $js .'; pgo=1;}}setTimeout("jumpUrl()",'. ($time*1000) .');var t,s;function time(){var time = document.getElementById("time");s = parseInt(time.innerHTML)-1;time.innerHTML=s;if(s<=0) clearInterval(t);}t=setInterval("time()",1000);</script>
<style type="text/css">
body {background-color:#FFF;font-size:12px;font-family: SimSun,Arial; color:#555;}
body, ul, ol, li, dl, dd, p, h1, h2, h3, h4, h5, h6, form, fieldset, hr {margin: 0; padding: 0; border:0;}
table {empty-cells: show; border-collapse: collapse;}ul li {list-style: none;}img {border: none;}
a {color: #5f5f5f; text-decoration: none;}a:hover {text-decoration: underline;}
.msg{ width:400px;margin: 0 auto;border:5px solid #cdcdcd; background-color:#fafafa;text-align:center;line-height:20px;color:#5f5f5f;}
.msg .t{ font-size:14px; padding-top:15px; font-weight:bold;color:#f41f00;}
.msg .g{ margin:12px;text-align:left; }
.msg .g a{ padding:8px 20px; line-height:30px;border:1px solid #4672C4; border-top:#cdcdcd;border-left:#cdcdcd;background-color:#3D6AD3; color:#FFF; margin-right:20px;}
.msg .g a:hover{background-color:#5588D8;text-decoration:none;}
.msg .z{padding-bottom:15px; font-family:Verdana, Geneva, sans-serif;}
.msg .z a{text-decoration: underline; }.tabmsg .z a:hover{color:#f30;}
</style></head><body><div style="padding:89px 10px 10px;"><div class="msg"><div class="t">'.$message.'</div><div class="g">'.$info.'</div><div class="z"><a href="'. $jumpUrl . '"><span id="time">'.$time.'</span> 秒后自动跳转，如未跳转请点击此处手工跳转。</a></div></div></div></body></html>';
    if(IS_CLI) return $out_html;
    exit($out_html);
}

//获取用户真实地址 返回用户ip  type:0 返回IP地址 1 返回IPV4地址数字
function GetIP($type=0) {
    return \myphp\Helper::getIp($type);
}

//获得当前的脚本网址  如/ab.php?b=1
function get_uri() {
    return \myphp\Helper::getUri();
}
//获取当前页面完整URL地址 如http://xx/a.php?b=1
function get_url() {
    return \myphp\Helper::getUrl();
}

/**
 * 生成csrf-token
 * @param string $type input:隐藏表彰文本项 meta:head meta 默认:仅token
 * @return string
 */
function csrfToken($type = '')
{
    $token = random(14, '01'); //bin2hex(random_bytes(8));
    session('csrf_token', $token);
    if ($type == 'input') return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    elseif ($type == 'meta') return '<meta name="csrf_token" content="' . $token . '">';

    return $token;
}
/**
 * 校验csrf
 * @return bool
 */
function verifyCsrfToken()
{
    if (in_array(\myphp\Request::method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $csrfToken = (string)session('csrf_token');
        if ($csrfToken === '' || ($csrfToken !== ($_POST['csrf_token'] ?? '') && $csrfToken !== ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            return false;
        }
    }
    return true;
}
/**
 * 产生随机字符串
 *
 * @param int $len 输出长度
 * @param string $chars 字符串 0:数字 1:字母 01:数字字母 11:特殊、数字字母 xxx:自定义
 * @return string
 */
function random($len=6, $chars='0') {
    if($chars=='0') $chars = '0123456789';
    elseif($chars=='1') $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ';
    elseif($chars=='01') $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ0123456789';
    elseif($chars=='11') $chars = '~!@#$%^&*_-abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars.$chars.$chars), 0, $len);
}
//生成流水号
function create_sn(){ //20位
    $chars = substr(microtime(),2,6);
    return date('YmdHis').substr(str_shuffle($chars.$chars),0,6);
    //return date('YmdHis').str_pad( mt_rand( 1, 99999 ), 5, '0', STR_PAD_LEFT);
}
/*
my_hash
@hash验证用户数据安全性  为真验证，为假返回hash值 
@echo 为真直接返回错误信息，为假直接输出错误提示
按需求调用执行
*/
function my_hash($verify=false, $echo=true){
    $my_hash = session('my_hash');
    if ($verify) {
        if ($my_hash && isset($_GET['my_hash']) && $my_hash == $_GET['my_hash']) {
            session('my_hash', null);
            return true;
        } elseif ($my_hash && isset($_POST['my_hash']) && $my_hash == $_POST['my_hash']) {
            session('my_hash', null);
            return true;
        } else {
            if ($echo) return out_msg('0:[hash]数据验证失败', \myphp\Helper::getReferer());
            else return false;
        }
    } else {
        if (!$my_hash) {
            $my_hash = random(8,'01'); //bin2hex(random_bytes(6));
            session('my_hash', $my_hash);
        }
        return $my_hash;
    }
}
function my_hash_md5($val,$verify=false){
    $my_hash = my_hash();
    if ($verify) {
        if ($my_hash && isset($_GET['my_hash_md5']) && md5(getMd5($val . $my_hash) == $_GET['my_hash_md5'])) {
            return true;
        } elseif ($my_hash && isset($_POST['my_hash_md5']) && md5(getMd5($val . $my_hash)) == $_POST['my_hash_md5']) {
            return true;
        } else {
            return false;//'[hash]数据验证失败'
        }
    } else {
        return md5(getMd5($val . $my_hash));
    }
}
//验证码
function GetCode($w=80, $h=36, $fontsize=18, $len = 4, $reurl=false,$number=false) {
    $codeurl = myphp::$cfg['root_dir'].'/myphp/inc/imagecode.php?';
    $codeurl .= "w=$w&h=$h&size=$fontsize&len=$len&t=".time().($number?'&number=1':'');
    if(!$reurl) return '<img src="'. $codeurl .'" alt="验证码,看不清楚?请点击刷新验证码" style="cursor:pointer; vertical-align:middle;" onclick="this.src=\''. $codeurl .'\'+\'&t=\'+Math.random();return false;" />';
    else return $codeurl;
}
//检查验证码
function CodeIsTrue($codename) {
    if(!isset($_POST[$codename])) return false;
    $codeStr = strtolower(trim($_POST[$codename]));
    $code = session($codename);
    if ($code && $codeStr && $code == $codeStr) {
        session($codename, null);
        return true;
    } else {
        return false;
    }
}

//返回经过随机串组合的加密md5  (encode_key)
function getMd5($val,$encode = '') {
    $encode = $encode != '' ? $encode : myphp::$cfg['encode_key'];
    return md5($encode.$val);
}
/**
 * 字符串加密、解密函数
 *
 * @param string $string  字符串
 * @param string $operation ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
 * @param string $key  密钥：数字、字母、下划线
 * @param int $expiry  过期时间
 * @return string|null
 */
function sys_auth($string, $operation = 'ENCODE', $key = '', $expiry = 0) {
    $key_length = 4;
    $key = md5($key != '' ? $key : myphp::$cfg['encode_key']);
    $fixedkey = md5($key);
    $egiskeys = md5(substr($fixedkey, 16, 16));
    $runtokey = $key_length ? ($operation == 'ENCODE' ? substr(md5(microtime(true)), -$key_length) : substr($string, 0, $key_length)) : '';
    $keys = md5(substr($runtokey, 0, 16) . substr($fixedkey, 0, 16) . substr($runtokey, 16) . substr($fixedkey, 16));
    $string = $operation == 'ENCODE' ? sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$egiskeys), 0, 16) . $string : base64_decode(substr($string, $key_length));

    $result = '';
    $string_length = strlen($string);
    for ($i = 0; $i < $string_length; $i++){
        $result .= chr(ord($string[$i]) ^ ord($keys[$i % 32]));
    }
    if($operation == 'ENCODE') {
        return $runtokey . str_replace('=', '', base64_encode($result));
    } else {
        $time = (int)substr($result, 0, 10);
        if(($time == 0 || $time - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$egiskeys), 0, 16)) {
            return substr($result, 26);
        } else {
            return null;
        }
    }
}
/*参数验证并返回
args: allow_num|filetype|allow_del|
$sp:参数分隔符
*/
function setargs($args){
    return '&args='.$args.'&key='.getMd5($args.my_hash());
}
function return_args($args){
    return [
        'args' => $args,
        'key' => getMd5($args . my_hash())
    ];
}
//key=args_val+my_hash
function getargs($args_item='',$gtype=1){
    $args_val = urldecode(Q('args',''));
    $key = Q('key','');
    if($args_val=='' || $args_item=='' || $key=='') throw new \Exception('args error!');
    if(getMd5($args_val.my_hash()) != $key) throw new \Exception('Invalid args!');//参数有效验证 $key.'!='.$args_val.'-'.my_hash()

    $para = array('args'=>$args_val, 'key'=>$key);
    $args_val = explode('|', $args_val);$args_item = explode('|', $args_item);
    if(count($args_val) != count($args_item)) throw new \Exception('args num error!');//参数个数验证
    foreach($args_item as $key => $keyname){
        $para[$keyname] = $args_val[$key];
    }
    return $para;
}

//转换字节数为其他单位 $byte:字节
function toByte($byte){
    $v = 'unknown';
    if($byte >= 1099511627776){
        $v = round($byte / 1099511627776  ,2) . 'TB';
    } elseif($byte >= 1073741824){
        $v = round($byte / 1073741824  ,2) . 'GB';
    } elseif($byte >= 1048576){
        $v = round($byte / 1048576 ,2) . 'MB';
    } elseif($byte >= 1024){
        $v = round($byte / 1024, 2) . 'KB';
    } else{
        $v = $byte . 'B';
    }
    return $v;
}
//获取图片
function get_image($image, $nopic='/pub/images/nopic.gif'){
    $root_dir_len=strlen(ROOT_DIR);
    if($image=='') $image = ROOT_DIR.$nopic;
    if(substr($image,0,4)=='http' || substr($image,0,$root_dir_len)==ROOT_DIR){
        return $image;
    }else{
        return ROOT_DIR.(substr($image,0,1)=='/' ? $image : '/'.$image);
    }
}
//获取缩略图 不存在返回原图 $thumb_wh : 240_180
function get_thumb($image,$thumb_wh='',$nopic='/pub/images/itemi.png'){
    if(substr($image,0,4)=='http'){
        return $image;
    }else{
        $image = get_image($image,$nopic);
        $dot = strrpos($image,'.');
        if($thumb_wh==''){
            $thumb_wh = GetC('thumb_wh');
            $__has = strpos($thumb_wh,',');
            if($__has!==false) $thumb_wh = substr($thumb_wh, 0, $__has);
        }
        $thumb = substr($image, 0, $dot).$thumb_wh.substr($image, $dot);
        return is_file(SITE_WEB.$thumb) ? $thumb : $image;
    }
}
//生成缩略图  return array 缩略图列表
function make_thumb($image){
    $thumb = array();
    $image = SITE_WEB.$image;
    if(!is_file($image)) return false;
    $imgType = '.png,.jpg,.jpeg,.bmp,.gif';//图片类型
    $dot = strrpos($image,'.');
    $base = substr($image, 0, $dot);
    $ext = substr($image, $dot);
    if(strpos($imgType, strtolower($ext))===false) return false;//图片验证

    $thumbs_wh = explode(',', myphp::$cfg['thumb_wh']);//获取默认缩略图大小
    foreach($thumbs_wh as $thumb_wh){
        $thumbname =  $base.$thumb_wh.$ext;
        $wh = explode('_', $thumb_wh);
        //生成图片缩略图
        if(Image::thumb($image,$thumbname,$wh[0],$wh[1])){
            $thumb[$thumb_wh] = $thumbname;
        }
    }
    return $thumb;
}
//删除上传文件 文件路径 是否图片
function del_up_file($file, $is_img=0){
    $realFile = SITE_WEB.$file;//真实路径
    if (is_file($realFile)) {
        if($is_img){
            $dot = strrpos($realFile,'.');
            $base = substr($realFile, 0, $dot);
            $ext = substr($realFile, $dot);
            $thumbs_wh = explode(',', myphp::$cfg['thumb_wh']);//获取默认缩略图大小
            foreach($thumbs_wh as $thumb_wh){
                is_file($base.$thumb_wh.$ext) && @unlink($base.$thumb_wh.$ext);
            }
        }
        @unlink($realFile);
    }
}
//编辑器 field:数组,value:内容  $field:array('name'=>'name','width'=>0,'height'=>0,'ext'=>array('editor'=>'ueditor','config'=>''))
function editor_fun($field,$value=''){
    $str = '';
    $editor = isset($field['ext']['editor'])?$field['ext']['editor']:'ueditor';
    $config = isset($field['ext']['config'])?$field['ext']['config']:'';
    $field['width'] = empty($field['width'])?760:$field['width'];
    $field['height'] = empty($field['height'])?350:$field['height'];
    if($editor=='ueditor'){
        static $ueditor;
        if(empty($ueditor)) {
            $ueditor=true;
            $str .='<script type="text/javascript" src="'.($config==''?ROOT_DIR.'/pub/ueditor/ueditor.config.js':ROOT_DIR.'/pub/ueditor/'.$config).'"></script><script type="text/javascript" src="'.ROOT_DIR.'/pub/ueditor/ueditor.all.min.js"></script>';
            $str .= '<script>var ueditor;</script>';
        }
        //<textarea id="'. $field['name'] .'" name="'. $field['name'] .'">'. htmlspecialchars($value) .'</textarea>
        $str .= '<script id="'. $field['name'] .'" name="'. $field['name'] .'" type="text/plain">'.$value.'</script><script type="text/javascript">ueditor = UE.getEditor("'. $field['name'] .'",{'.(isset($field['params'])? $field['params'].', ':'').'initialFrameWidth:'. $field['width'] .', initialFrameHeight:'. $field['height'] .'});</script>';
    }else{
        static $keditor;
        if(empty($keditor)) {
            $keditor=true;
            $str .='<script src="'.ROOT_DIR.'/pub/keditor/kindeditor.js"></script><script src="'.ROOT_DIR.'/pub/keditor/lang/zh_CN.js"></script>';
            $str .= '<script>var keditor;</script>';
        }
        $str .= '<textarea id="'. $field['name'] .'" name="'. $field['name'] .'">'. htmlspecialchars($value) .'</textarea>';
        $str .= '<script>var options = {width : "'. $field['width'] .'",height : "'. $field['height'] .'",newlineTag:"br"};keditor = KindEditor.create("#'. $field['name'] .'", options);</script>';
    }
    return $str;
}
// 设置config文件 $config 配属信息,$file 配置文件名(相对根目录/xxx/config.php) $allow_val 允许的值名替换 默认不限制
// 示例 allow_val = array('js_path','css_path','img_path'); 配置值不允许有换行符、单引号 以防出错
function set_config($config, $file="/config.php", $allow_val=null) {
    $configFile = ROOT.$file;
    $pattern = $replacement = array();
    foreach($config as $k=>$v) {
        if(is_array($allow_val) && !in_array($k,$allow_val)) continue;
        $v = trim($v);
        $pattern[$k] = "/'".$k."'\s*=>\s*([']?)[^']*([']?)(\s*),/is"; //[^']
        $replacement[$k] = "'".$k."' => \${1}".$v."\${2}\${3},";
    }
    $str = file_get_contents($configFile);
    $str = preg_replace($pattern, $replacement, $str);
    return file_put_contents($configFile, $str, LOCK_EX);
}

/**
 * 安全cookie md5加密
 * @param string|null $name 名|null清空所有
 * @param string|null $value 值|null删除
 * @param null|array|int $option cookie参数 可数字、数组
 * @return mixed|string|null
 */
function cookie($name, $value='', $option=null) {
    $prefix = isset(myphp::$cfg['cookie_pre']) ? myphp::$cfg['cookie_pre'] : ''; // cookie 名称前缀
    // 默认设置
    $config = array(
        'expire' => isset(myphp::$cfg['cookie_expire']) ? myphp::$cfg['cookie_expire'] : 0, // cookie 保存时间
        'path' => isset(myphp::$cfg['cookie_path']) ? myphp::$cfg['cookie_path'] : '/', // cookie 保存路径
        'domain' => isset(myphp::$cfg['cookie_domain']) ? myphp::$cfg['cookie_domain'] : '', // cookie 有效域名
        'secure' => isset(myphp::$cfg['cookie_secure']) ? myphp::$cfg['cookie_secure'] : false, //  cookie 启用安全传输
        'httponly' => isset(myphp::$cfg['cookie_httponly']) ? myphp::$cfg['cookie_httponly'] : false, // httponly设置
        'same_site' => isset(myphp::$cfg['cookie_same_site']) ? myphp::$cfg['cookie_same_site'] : false
    );
    // 参数处理
    if (!is_null($option)) {
        if (is_numeric($option))
            $config['expire'] = (int)$option;
        elseif (is_array($option))
            $config = array_merge($config, $option);
    }
    // 清除指定前缀的所有cookie
    if (is_null($name)) {
        if (empty($_COOKIE)) return null;

        foreach ($_COOKIE as $name => $val) {
            if ($prefix!== '' && strpos($name, $prefix . '_') !== 0) {
                continue;
            }
            if (IS_CLI) {
                myphp::res()->header['Set-Cookie'][] = $name . "=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=0"
                    . ($config['path'] ? '; Path=' . $config['path'] : '')
                    . ($config['domain'] ? '; Domain=' . $config['domain'] : '')
                    . ($config['secure'] ? '; Secure' : '')
                    . ($config['httponly'] ? '; HttpOnly' : '')
                    . ($config['same_site'] ? '; SameSite=' . $config['same_site'] : '');
            } else {
                setcookie($name, '', 0, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            }
            unset($_COOKIE[$name]);
        }
        return null;
    }
    $encode = substr($name,0,1)=='_' ? false : true; //是否编码 以下划线开头的不编码
    $name = ($prefix !== '' ? $prefix . '_' : '') . $name;
    if ('' === $value) {//获取cookie值
        if(isset($_COOKIE[$name])){
            $value = $encode ? sys_auth($_COOKIE[$name], 'DECODE') : $_COOKIE[$name];
            $flag = substr($value, 0, 2);
            if ($flag == '@:' || $flag == '@*') {
                $value = substr($value, 2);
                $value = json_decode($flag == '@*'?gzuncompress($value) : $value, true);
            }
            return $value;
        }
    } else {
        if (!isset(myphp::res()->header['Set-Cookie'])) myphp::res()->header['Set-Cookie'] = [];
        if (is_null($value)) {//删除cookie值
            if (IS_CLI) {
                myphp::res()->header['Set-Cookie'][] = $name . "=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT; Max-Age=0"
                    . ($config['path'] ? '; Path=' . $config['path'] : '')
                    . ($config['domain'] ? '; Domain=' . $config['domain'] : '')
                    . ($config['secure'] ? '; Secure' : '')
                    . ($config['httponly'] ? '; HttpOnly' : '')
                    . ($config['same_site'] ? '; SameSite=' . $config['same_site'] : '');
            } else {
                setcookie($name, '', 0, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            }
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {// 设置cookie
            if(is_array($value)) {
                $value = json_encode($value);
                $value = strlen($value)>768 ? '@*'.gzcompress($value) : '@:'.$value;
            }
            $value = $encode ? sys_auth($value, 'ENCODE') : $value;
            if (IS_CLI) {
                myphp::res()->header['Set-Cookie'][] = $name . '=' . \rawurlencode($value)
                    . ($config['expire'] ? '; Expires=' . \gmdate('D, d M Y H:i:s \G\M\T', time() + $config['expire']) : '')
                    . ($config['expire'] ? '; Max-Age=' . $config['expire'] : '')
                    . ($config['path'] ? '; Path=' . $config['path'] : '')
                    . ($config['domain'] ? '; Domain=' . $config['domain'] : '')
                    . ($config['secure'] ? '; Secure' : '')
                    . ($config['httponly'] ? '; HttpOnly' : '')
                    . ($config['same_site'] ? '; SameSite=' . $config['same_site'] : '');
            } else {
                setcookie($name, $value, $config['expire'], $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            }
            $_COOKIE[$name] = $value;
        }
    }
    return null;
}
// session 辅助类
function session($name='', $value='') {
    !\myphp\Session::$instance && \myphp\Session::init(isset(myphp::$cfg['session']) ? myphp::$cfg['session'] : null);
    if (is_null($name)) { //清除所有 session
        \myphp\Session::destroy();
        return null;
    } elseif ($name == '') { //获取所有 session
        return \myphp\Session::all();
    }
    //$name = $name.Helper::getIp(1);//ip安全限制 实现ip变了对应session也无效了
    if ('' === $value) { //获取 session
        return \myphp\Session::get($name);
    } else {
        if (is_null($value)) { //删除 session
            return \myphp\Session::del($name);
        } else { // 设置 session
            \myphp\Session::set($name, $value);
        }
    }
    return null;
}

/**
 * cache类 辅助方法
 * @param array|string|null $name
 * @param array|string|null $value
 * @param array|int|null $option
 * @return mixed|\myphp\Cache
 * @throws \Exception
 */
function cache($name, $value='', $option=null) {
    static $cache;
    if(is_array($name)){
        return $cache = \myphp\Cache::getInstance($value?$value:'file', $name);
    }elseif(is_array($option)){ //单独设置缓存并返回
        return \myphp\Cache::getInstance($name, $option);
    }elseif(!isset($cache)){ //默认缓存设定
        $type = isset(myphp::$cfg['cache'])?myphp::$cfg['cache']:'file';
        $cache = \myphp\Cache::getInstance($type, myphp::$cfg['cache_option']);
    }
    if (is_null($name)) { // 清除所有cache
        $cache->clear();
        return null;
    }
    if ('' === $value) {//获取 cache
        return $cache->get($name);
    } else {
        if (is_null($value)) {//删除 cache
            $cache->del($name);
        } else {// 设置 session
            $cache->set($name, $value, is_numeric($option)?$option:0);
        }
    }
    return null;
}

//递归自定方法数组处理 传址
function array_call_func($func, &$data){
    //$r=array();
    foreach ($data as $key => $val) {
        $data[$key] = is_array($val) ? array_call_func($func, $val) : call_user_func($func, $val);
    }
    return $data;
}
/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * Q('id',0); 获取id参数 自动判断get或者post
 * Q('post.name:htmlspecialchars'); 获取$_POST['name']
 * Q('get.:null'); 获取$_GET 且不执行过滤操作 filter=null
 * </code>
 * @param string $name 变量的名称 支持指定类型  post.name%s{1,20}:filter  {1,20}取值范围
 * @param mixed $defVal 变量的默认值
 * @param mixed $datas 要获取的额外数据源
 * @return mixed
 */
/*
string,bool,int,float,arr,date
%s,%b,%d,%f,%a,%date [2014-01-11 13:23:32 | 2014-01-11]
filter:fun1,fun2,/regx/i正则过滤
*/

function Q($name, $defVal='', $datas=null) {
    static $_PUT = null;
    $filter = $min = $max = null;
    $type = 's'; // 默认转换为字符串
    $digit = 0; //小数位处理 四舍五入
    \myphp\Value::parseType($name, $type, $min, $max, $filter, $digit);

    $method = 'request'; // 默认为_REQUEST
    if(strpos($name,'.')!==false) { // 指定参数来源
        list($method,$name) = explode('.',$name,2);
        if(!$method) $method = 'request';
    }
    #echo $method.'--'.$name.'--'.$type.'--'.$min.'--'.$max.'--'.$filter,PHP_EOL;
    switch($method) { #strtolower($method)
        case 'request': $input = &$_REQUEST; break;
        case 'get' : $input = &$_GET; break;
        case 'post': $input = &$_POST; break;
        case 'data'   : $input = &$datas; break;
        case 'put' :
            if(IS_CLI || is_null($_PUT)) { // cli模式下每次都需要解析
                $rawBody = myphp::rawBody();
                $first_c = substr($rawBody,0,1);
                if($first_c=='[' || $first_c=='{'){
                    $_PUT = (array)json_decode($rawBody, true);
                }else{
                    parse_str($rawBody, $_PUT);
                }
            }
            $input = &$_PUT; break;
        case 'files': $input = &$_FILES; break;
        case 'globals': $input = $GLOBALS; break; // >=8.1为只读不可&引用
        default:
            if($type=='d' || $type=='f'){
                $defVal = $type=='d'?(int)$defVal:(float)$defVal;
            }
            return $defVal;
    }
    $isAllVal = ''===$name;
    if($isAllVal) { // 获取全部变量
        $val = $input;
        if(is_array($val)) $type = 'a';
    }
    else{
        if(strpos($name,'.')){ //多维数组
            $val = \myphp\Helper::getValue($input, $name);
        }else{
            $val = isset($input[$name]) ? $input[$name] : null;
        }
    }
    //if($filter===null) $filter = true; #使用默认过滤处理 取消
    if($filter==='null') $filter = null; #禁用默认过滤

    \myphp\Value::type2val($val, [$type, 'min' => $min, 'max' => $max, 'digit' => $digit, 'filter' => $filter], $defVal);

    return $val;
}
//xss 过滤
function remove_xss($val) {
    // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
    // this prevents some character re-spacing such as <java\0script>
    // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
    $val = preg_replace('/([\x00-\x08\x0b-\x0c\x0e-\x19])/', '', $val);

    $search = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*()~`";:?+/={}[]-_|\'\\';
    $search = '`#%\'\\';
    for ($i = 0; $i < strlen($search); $i++) {
        // @ @ search for the hex values
        $val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val); // with a ;
        // @ @ 0{0,7} matches '0' zero to seven times
        $val = preg_replace('/(&#0{0,8}'.ord($search[$i]).';?)/', $search[$i], $val); // with a ;
    }

    // now the only remaining whitespace attacks are \t, \n, and \r
    $ra = array( //'style', 'title', 'embed', 'object', 'xml', 'base', 'meta', 'link', 'blink',
        'script', 'layer', 'ilayer', 'javascript', 'vbscript', 'expression', 'applet', 'frame', 'iframe', 'frameset', 'bgsound',
        'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');

    $found = true; // keep replacing as long as the previous round replaced something
    while ($found == true) {
        $val_before = $val;
        for ($i = 0; $i < sizeof($ra); $i++) {
            $pattern = '/';
            for ($j = 0; $j < strlen($ra[$i]); $j++) {
                if ($j > 0) {
                    $pattern .= '(';
                    $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                    $pattern .= '|';
                    $pattern .= '|(&#0{0,8}([9|10|13]);)';
                    $pattern .= ')*';
                }
                $pattern .= $ra[$i][$j];
            }
            $pattern .= '/i';
            $replacement = substr($ra[$i], 0, 2).'-'.substr($ra[$i], 2); // add in <> to nerf the tag
            $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
            if ($val_before == $val) {
                // no replacements were made, so exit the loop
                $found = false;
            }
        }
    }
    return $val;
}
/**
 * 数字转换为中文
 * @param  string|integer|float  $num  目标数字
 * @param  boolean $mode 模式[true:金额（默认）,false:普通数字表示]
 * @param  boolean $sim 使用小写（默认）
 * @return string
 */
function num2ch($num, $mode = true, $sim = true){
    if(!is_numeric($num) || floatval($num)==0) return '零'.($mode==='rmb'?'元':'').($sim?'':'整');

    $char = $sim ? array('零','一','二','三','四','五','六','七','八','九') : array('零','壹','贰','叁','肆','伍','陆','柒','捌','玖');
    $unit = $sim ? array('','十','百','千','','万','亿','兆') : array('','拾','佰','仟','','萬','億','兆');
    $cnVal = '';
    //小数部分
    if(strpos($num, '.')){
        list($num,$dec) = explode('.', $num);
        if($mode==='rmb'){
            $dec = round(floatval('0.'.$dec),2);
            if($dec>0){
                $cnVal = '元'.($sim?'':'整');
                $dec = strval($dec);
                if(!empty($dec[2])) $cnVal .= $char[$dec[2]].'角';
                if(!empty($dec[3])) $cnVal .= $char[$dec[3]].'分';
            }
        }else{
            $cnVal = '点';
            for($i = 0,$c = strlen($dec);$i < $c;$i++) {
                $cnVal .= $char[$dec[$i]];
            }
        }
    }
    if($cnVal=='' && $mode)  $cnVal = '元'.($sim?'':'整');

    if(!$num && $mode) return str_replace(['元','整'],'', $mode==='rmb'?$cnVal:'零'.$cnVal);
    //整数部分
    $str = strrev($num);
    for($i = 0,$c = strlen($str);$i < $c;$i++) {
        $out[$i] = $char[$str[$i]];
        if($mode){
            $out[$i] .= $str[$i] != '0'? $unit[$i%4] : '';
            if(($i>1) && ($str[$i]+$str[$i-1]==0)){
                $out[$i] = '';
            }
            if($i%4 == 0){
                $out[$i] .= $unit[4+floor($i/4)];
            }
        }
    }
    $cnVal = join('',array_reverse($out)) . $cnVal;
    if(strpos($cnVal,'一十')===0) $cnVal = substr($cnVal, 3);
    $cnVal = str_replace(['零零','零元','零点'], ['零','元','点'], $cnVal);
    $cnVal = $sim ? str_replace(['零万','零亿'], ['万','亿'], $cnVal) : str_replace(['零萬','零億'], ['萬','億'], $cnVal);
    if($mode && $mode!=='rmb') $cnVal = str_replace(['元','整'],'', $cnVal);
    return $cnVal;
}
//中文转数字
function ch2num($str) {
    $map = array(
        '一' => '1','二' => '2','三' => '3','四' => '4','五' => '5','六' => '6','七' => '7','八' => '8','九' => '9',
        '壹' => '1','贰' => '2','叁' => '3','肆' => '4','伍' => '5','陆' => '6','柒' => '7','捌' => '8','玖' => '9',
        '零' => '0','两' => '2','万万' => '亿','萬'=>'万','仟' => '千','佰' => '百','拾' => '十','圆'=>'','元'=>'','整'=>'','点'=>'.'
    );
    $plus = array('兆'=>1000000000000,'亿' => 100000000,'万' => 10000,'千' => 1000,'百' => 100,'十' => 10,'角'=>0.1,'分'=>0.01);
    $str = str_replace(array_keys($map), array_values($map), $str);

    if(is_numeric($str)) return $str;

    $func_c2i = function ($str,&$plus) use(&$func_c2i) {
        if(is_numeric($str)) return $str;
        $i = 0;
        foreach($plus as $k => $v) {
            $i++;
            if(strpos($str, $k) !== false) {
                $ex = explode($k, $str, 2);
                $new_plus = array_slice($plus, $i, null, true);
                $l = $func_c2i($ex[0], $new_plus);
                $r = $func_c2i($ex[1], $new_plus);
                if($l == 0) $l = 1;
                return $l * $v + $r;
            }
        }
        return $str;
    };
    return $func_c2i($str,$plus);
}

/**
 * 数字金额转换成中文大写金额的函数 小数位为两位
 * @param int $num 要转换的小写数字或小写字符串
 * @return string
 */
function num_to_rmb($num){
    $c1 = "零壹贰叁肆伍陆柒捌玖";
    $c2 = "分角元拾佰仟万拾佰仟亿";
    //精确到分后面就不要了，所以只留两个小数位
    $num = round($num, 2);
    //将数字转化为整数
    $num = $num * 100;
    if (strlen($num) > 10) {
        return "金额太大，请检查";
    }
    $i = 0;
    $c = "";
    while (1) {
        if ($i == 0) {
            //获取最后一位数字
            $n = substr($num, strlen($num)-1, 1);
        } else {
            $n = $num % 10;
        }
        //每次将最后一位数字转化为中文
        $p1 = substr($c1, 3 * $n, 3);
        $p2 = substr($c2, 3 * $i, 3);
        if ($n != '0' || ($n == '0' && ($p2 == '亿' || $p2 == '万' || $p2 == '元'))) {
            $c = $p1 . $p2 . $c;
        } else {
            $c = $p1 . $c;
        }
        $i = $i + 1;
        //去掉数字最后一位了
        $num = $num / 10;
        $num = (int)$num;
        //结束循环
        if ($num == 0) {
            break;
        }
    }
    $j = 0;
    $slen = strlen($c);
    while ($j < $slen) {
        //utf8一个汉字相当3个字符
        $m = substr($c, $j, 6);
        //处理数字中很多0的情况,每次循环去掉一个汉字“零”
        if ($m == '零元' || $m == '零万' || $m == '零亿' || $m == '零零') {
            $left = substr($c, 0, $j);
            $right = substr($c, $j + 3);
            $c = $left . $right;
            $j = $j-3;
            $slen = $slen-3;
        }
        $j = $j + 3;
    }
    //这个是为了去掉类似23.0中最后一个“零”字
    if (substr($c, strlen($c)-3, 3) == '零') {
        $c = substr($c, 0, strlen($c)-3);
    }
    //将处理的汉字加上“整”
    if (empty($c)) {
        return "零元整";
    }else{
        return $c . "整";
    }
}
/* 
 * 经典的概率算法 算法简单且效率非常高
 *
 * @param array $data array('a'=>5000,'b'=>1,'c'=>4999)
 * @param int $max 概率总值 默认10000,数据总值小此值时可能会轮空,可设置为0取消轮空
 * @return string $data的key | null为轮空
 */
function luck_rand($data, $max=10000) {
    $result = null;
    if(!$data) return $result;
    $sum = array_sum($data); //数组的总概率值
    $max = intval(($sum>$max ? $sum : $max)*2); //获取概率总值
    if($max<=0) return $result;
    for($i=0;$i<mt_rand(1,3);$i++){ mt_rand(1, $max); }
    asort($data);
    $rnd = mt_rand(1, $max); $arr = [];
    //概率筛选
    foreach ($data as $key=>$val) {
        if($result===null){
            //$rnd = mt_rand(1, $max);
            if ($rnd <= $val) {
                $result = $key; //break;
                $arr[] = $key; $rnd = $val;
            } else {
                //$max -= $val;
            }
        }else{
            if($rnd==$val) $arr[] = $key; //相同机率的
        }
    }
    if($result===null) {
        unset($data[$key]);
        arsort($data);
        foreach ($data as $k=>$v){
            if($v!=$val) break;
            $arr[] = $k;
        }
        if(!$arr) return $key;
        $arr[] = $key;
    }
    if($len=count($arr)) {
        //mt_rand(0, $len-1);
        $result = $arr[mt_rand(0, $len-1)];
    }
    return $result; //null为轮空
}

//参数说明：TotalResult(记录条数),Page_Size(每页记录条数),CurrentPage(当前记录页),paraUrl(URL参数)
//分页函数1：PageList1
function PageList1($TotalResult, $Page_Size, $currentPage, $paraUrl, $pageName = 'page') {
    $Page_Count=$TotalResult / $Page_Size;
    if ($Page_Count>floor($Page_Count)) $Page_Count = floor($Page_Count)+1;
    $out = '共有 <span id="totalresult">'. $TotalResult .'</span> 条信息&nbsp;&nbsp';
    if ($currentPage <= 1) {
        $out .= '首页&nbsp;&nbsp;上一页&nbsp;&nbsp;';
    } else {
        $out .= '<a href="'. str_replace('{'.$pageName.'}',1,$paraUrl) .'">首页</a>&nbsp;&nbsp;<a href="'. str_replace('{'.$pageName.'}',($currentPage-1),$paraUrl) .'">上一页</a>&nbsp;&nbsp;';
    }
    if ($currentPage >= $Page_Count) {
        $out .= '下一页&nbsp;&nbsp;尾页&nbsp;';
    } else {
        $out .= '<a href="'. str_replace('{'.$pageName.'}',($currentPage+1),$paraUrl) .'">下一页</a>&nbsp;&nbsp;<a href="'. str_replace('{'.$pageName.'}',$Page_Count,$paraUrl) .'">尾页</a>&nbsp;';
    }
    $out .= '&nbsp;页次:'. $currentPage .'/'. $Page_Count .'页&nbsp;&nbsp;'. $Page_Size .'条信息/页&nbsp;&nbsp;转到<select name="select" onChange="javascript:var url=\''. $paraUrl .'\';url=url.replace(\'{'. $pageName .'}\',this.options[this.selectedIndex].value);window.location.href=url;">';
    for ($ipg = 1; $ipg <= $Page_Count; $ipg++) {
        if ($ipg == $currentPage) {
            $out .= '<option value='. $ipg .' selected>'. $ipg .'</option>"';
        } else {
            $out .= '<option value='. $ipg .'>'. $ipg .'</option>';
        }
    }
    $out .= '</select>';
    return $out;
}
//分页函数2：PageList2 , 参数同上
function PageList2($TotalResult, $Page_Size, $currentPage, $paraUrl, $pageName = 'page') {
    $Page_Count=$TotalResult / $Page_Size;
    if ($Page_Count>floor($Page_Count)) $Page_Count = floor($Page_Count)+1;
    $out = '第 '. $currentPage .'页/总 '. $Page_Count .'页 | 总 <span id="totalresult">'. $TotalResult .'</span>条 | ';
    if ($currentPage <= 1)
        $out .= '首页 | 上页 | ';
    else
        $out .= '<A href="'. str_replace('{'.$pageName.'}',1,$paraUrl) .'">首页</A> | <A href="'. str_replace('{'.$pageName.'}',($currentPage-1),$paraUrl) .'">上页</A> | ';

    if ($currentPage >= $Page_Count)
        $out .= '下页 | 尾页';
    else
        $out .= '<A href="'. str_replace('{'.$pageName.'}',($currentPage+1),$paraUrl) .'">下页</A> | <A href="'. str_replace('{'.$pageName.'}',$Page_Count,$paraUrl) .'">尾页</A>';
    return $out;
}
//分页函数3：PageList3 ,参数同上,InitPageNum初始显示数*2
function PageList3($TotalResult, $Page_Size, $currentPage, $paraUrl, $InitPageNum, $pageName = 'page') {
    $Page_Count=ceil($TotalResult / $Page_Size);

    $out = '<a class="page_total">'. $Page_Count .'页/<span id="totalresult">'. $TotalResult .'</span>条</a>';
    if ($TotalResult <= $Page_Size) return $out;
    if ($currentPage>$InitPageNum) {
        $out .= '<a href="'. str_replace('{'.$pageName.'}',1,$paraUrl) .'">首页</a>';// <a href="'. str_replace('{'.$pageName.'}',($currentPage-1),$paraUrl) .'">上一页</a>
    }
    //获取页码范围
    if ($currentPage <= 1) {
        $TmpPageNo = 1;
        $TmpPageNum = $InitPageNum;
    } elseif ($currentPage <= $InitPageNum) {
        $TmpPageNo = 1;
        $TmpPageNum = $InitPageNum + $currentPage-1;
    } elseif ($currentPage > $InitPageNum && $currentPage < $Page_Count) {
        $TmpPageNo = $currentPage - $InitPageNum;
        $TmpPageNum = $InitPageNum + $currentPage-1;
    } elseif ($currentPage >= $Page_Count) {
        $TmpPageNo = $currentPage - $InitPageNum;
        $TmpPageNum = $Page_Count;
    }
    //页码超出范围
    if ($TmpPageNum > $Page_Count) $TmpPageNum = $Page_Count;

    for ($PageNo = $TmpPageNo; $PageNo <= $TmpPageNum; $PageNo++) {
        if ($currentPage == $PageNo)
            $out .= '<strong>'.$PageNo .'</strong>';
        else
            $out .= '<a href="'. str_replace('{'.$pageName.'}',$PageNo,$paraUrl) .'">'. $PageNo .'</a>';
    }

    if ($currentPage <= $Page_Count-$InitPageNum)
        $out .= '<a href="'. str_replace('{'.$pageName.'}',$Page_Count,$paraUrl) .'">末页</a>';//<a href="'. str_replace('{'.$pageName.'}',($currentPage+1),$paraUrl) .'">下一页</a>

    return $out;
}
function PageList4($TotalResult, $Page_Size, $currentPage, $paraUrl, $InitPageNum, $pageName = 'page') {
    $Page_Count=ceil($TotalResult / $Page_Size);

    $out = '';//'<a class="page_total">'. $Page_Count .'页/<span id="totalresult">'. $TotalResult .'</span>条</a>';
    if ($TotalResult <= $Page_Size) return $out;
    if ($currentPage>1) {
        $out .= '<a href="'. str_replace('{'.$pageName.'}',($currentPage-1),$paraUrl) .'">上一页</a>';
    }else{
        $out .= '<a>上一页</a>';
    }
    //获取页码范围
    if ($currentPage <= 1) {
        $TmpPageNo = 1;
        $TmpPageNum = $InitPageNum;
    } elseif ($currentPage <= $InitPageNum) {
        $TmpPageNo = 1;
        $TmpPageNum = $InitPageNum + $currentPage-1;
    } elseif ($currentPage > $InitPageNum && $currentPage < $Page_Count) {
        $TmpPageNo = $currentPage - $InitPageNum;
        $TmpPageNum = $InitPageNum + $currentPage-1;
    } elseif ($currentPage >= $Page_Count) {
        $TmpPageNo = $currentPage - $InitPageNum;
        $TmpPageNum = $Page_Count;
    }
    //页码超出范围
    if ($TmpPageNum > $Page_Count) $TmpPageNum = $Page_Count;

    for ($PageNo = $TmpPageNo; $PageNo <= $TmpPageNum; $PageNo++) {
        if ($currentPage == $PageNo)
            $out .= '<strong>'.$PageNo .'</strong>';
        else
            $out .= '<a href="'. str_replace('{'.$pageName.'}',$PageNo,$paraUrl) .'">'. $PageNo .'</a>';
    }

    if ($currentPage < $Page_Count){
        $out .= '<a href="'. str_replace('{'.$pageName.'}',($currentPage+1),$paraUrl) .'">下一页</a>';
    }else{
        $out .= '<a>下一页</a>';
    }

    return $out;
}

/**
 * 格式化商品价格
 * @param float $price
 * @param int $price_format
 * @param string $currency_format
 * @return string
 */
function price_format($price, $price_format=0, $currency_format='￥%s元') {
    switch ($price_format){
        case 0:// 四舍五入，不保留小数
            $price = round($price);
            break;
        case 1: // 不四舍五入，保留 1 位
            $price = number_format($price, 1, '.', '');
            break;
        case 2:// 不四舍五入
            $price = number_format($price, 2, '.', '');
            break;
        case 3: // 保留不为 0 的尾数
            $price = rtrim((string)$price,'0.');
            break;
    }
    return sprintf($currency_format, $price);
}
/**
 * 2幂拆分 2次方和反解析
 * @param $num
 * @return array
 */
function pow2split($num){
    $ret = [];
    $n = 0;
    while ($num) {
        if ($num % 2) {
            $ret[] = 2 ** $n;
            $num = ($num - 1) >> 1; // 除2
        } else {
            $num = $num >> 1; // 除2
        }
        //echo $num . ' ';
        $n++;
        if ($num == 0) break;
    }
    return $ret;
}
//输入2位小数的数字
function num2fixed($number){
    return sprintf('%.2f', $number);
}

//AES 128位加密
function aes($string, $operation = 'ENCODE', $key = ''){
    $key = $key != '' ? $key : myphp::$cfg['encode_key'];//md5($key != '' ? $key : myphp::$cfg['encode_key'])
    $aes = new AES(true);// 把加密后的字符串按十六进制进行存储
    //$aes = new AES(true,true);// 带有调试信息且加密字符串按十六进制存储
    $keys = $aes->makeKey($key);//128bit

    if($operation == 'ENCODE') {
        return $aes->encryptString($string, $keys);
    }else{
        return $aes->decryptString($string, $keys);
    }
}
//替换字符串中间位置字符为星号  仅英文字符
function half_replace($str){
    $len = strlen($str) / 2;
    return substr_replace($str, str_repeat('*', $len), ceil($len / 2), $len);
}
//替换字符串中间位置字符为星号  仅英文字符
function half2_replace($str){
    $len = ceil(strlen($str) / 3);
    $cLen = floor(strlen($str) / 2 - ($len / 2));
    return substr_replace($str, str_repeat('*', $len), $cLen, $len);
}
//替换字符串中间位置字符为星号  支持中文
function cn_half_replace($str){
    preg_match_all('/./u', $str, $arr);
    $len =  ceil(count($arr[0])/2);
    $offset = ceil($len/2);
    $a = implode('', array_slice($arr[0], 0, $offset));
    $b = implode('', array_slice($arr[0], $offset+$len));
    return $a.str_repeat('*', $len).$b;
}
//字母数字及部分符号全角转半角
function toSemiAngle($str){
    static $arr;
    if(!$arr) $arr = array(
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
        'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
        'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
        'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
        'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y', 'Ｚ' => 'Z',
        'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd', 'ｅ' => 'e',
        'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i', 'ｊ' => 'j',
        'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n', 'ｏ' => 'o',
        'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's', 'ｔ' => 't',
        'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x', 'ｙ' => 'y', 'ｚ' => 'z',
        '～' => '~', '！' => '!', '＠' => '@', '＃' => '#', '＄' => '$','％' => '%', '＆' => '&', '＊' => '*', '×' => '*',
        '（' => '(', '）' => ')', '—' => '-', '－' => '-', '＋' => '+', '｜' => '|', '＾' => '^',
        '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '《' => '<', '》' => '>', '｛' => '{', '｝' => '}', '【' => '[', '】' => ']',
        '：' => ':', '；' => ';', '、' => ',', '，' => ',', '。' => '.', '？' => '?', '｀' => '`', '…' => '-', '　' => ' ',
        '＜' => '<', '＞' => '>', '［' => '[', '］' => ']', '．' => '.', '＼' => '\\', '＂' => '"',
    );
    return strtr($str, $arr);
}