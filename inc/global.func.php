<?php
/**
 *  global.func.php 公共函数库
 */
function e404(){header("HTTP/1.1 404 Not Found");header('Status:404 Not Found');exit;}
 /**
 * URL重定向
 * @param string $url 重定向的URL地址
 * @param integer $time 重定向的等待时间（秒）
 * @param string $msg 重定向前的提示信息
 * @return void
 */
function redirect($url, $time=0, $msg='') {
    if (empty($msg))
        $msg    = "系统将在{$time}秒之后自动跳转到{$url}！";
	// 如果报头未发送，则发送
    if (!headers_sent()) {// redirect
        if (0 === $time) {
            header('Location: ' . $url);
        } else {
            header("refresh:{$time};url={$url}");
            echo($msg);
        }
        exit();
    } else {
        $str    = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
        if ($time != 0) $str .= $msg;
        exit($str);
    }
}
 
/**
 * 返回经addslashes处理过的字符串或数组
 * @param $string 需要处理的字符串或数组
 * @return mixed
 */
function new_addslashes($string){
	if(!is_array($string)) return addslashes($string);
	foreach($string as $key => $val) $string[$key] = new_addslashes($val);
	return $string;
}

/**
 * 返回经stripslashes处理过的字符串或数组
 * @param $string 需要处理的字符串或数组
 * @return mixed
 */
function new_stripslashes($string) {
	if(!is_array($string)) return stripslashes($string);
	foreach($string as $key => $val) $string[$key] = new_stripslashes($val);
	return $string;
}

/**
 * 返回经htmlspecialchars处理过的字符串或数组
 * @param $obj 需要处理的字符串或数组
 * @return mixed
 */
function new_html_special_chars($string) {
	if(!is_array($string)) return htmlspecialchars($string);
	foreach($string as $key => $val) $string[$key] = new_html_special_chars($val);
	return $string;
}
/**
 * iconv 编辑转换
 */
if (!function_exists('iconv')) {
	function iconv($in_charset, $out_charset, $str) {
		if($pos = strpos($out_charset,'//ignore')) $out_charset = substr($out_charset,0,$pos);
		$in_charset = strtoupper($in_charset);
		$out_charset = strtoupper($out_charset);
		if (function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($str, $out_charset, $in_charset);
		} else {
			load_func('iconv');
			$in_charset = strtoupper($in_charset);
			$out_charset = strtoupper($out_charset);
			if ($in_charset == 'UTF-8' && ($out_charset == 'GBK' || $out_charset == 'GB2312')) {
				return utf8_to_gbk($str);
			}
			if (($in_charset == 'GBK' || $in_charset == 'GB2312') && $out_charset == 'UTF-8') {
				return gbk_to_utf8($str);
			}
			return $str;
		}
	}
}
/**
 * 格式化文本域内容
 *
 * @param $string 文本域内容
 * @return string
 */
function trim_textarea($string) {
	$string = nl2br ( str_replace ( ' ', '&nbsp;', $string ) );
	return $string;
}
//转义 javascript 代码标记
function trim_script($str) {
	if(is_array($str)){
		foreach ($str as $key => $val){
			$str[$key] = trim_script($val);
		}
 	}else{
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
function cutstr($str, $len, $suffix = '') {
	//$str = stripslashes($str);
	$str = str_replace(array('&nbsp;', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;'), array(' ', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…'), $str);
	preg_match_all('/./u', $str, $arr);//su 匹配所有的字符，包括换行符 /u 匹配所有的字符，不包括换行符
	//var_dump($arr);
	$str = join('', array_slice($arr[0], 0, $len));
	if (count($arr[0]) > $len) {
		$str .= $suffix;
	}
	return $str;//addslashes($str);
}
//安全过滤函数
function safe_replace($string) {
	$string = str_replace('%20','',$string);
	$string = str_replace('%27','',$string);
	$string = str_replace('%2527','',$string);
	$string = str_replace('*','',$string);
	$string = str_replace('"','&quot;',$string);
	$string = str_replace("'",'',$string);
	$string = str_replace('"','',$string);
	$string = str_replace(';','',$string);
	$string = str_replace('<','&lt;',$string);
	$string = str_replace('>','&gt;',$string);
	$string = str_replace("{",'',$string);
	$string = str_replace('}','',$string);
	$string = str_replace('\\','',$string);
	return $string;
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
//html to txt
function Html2Text($str){
	//$str = preg_replace("/<sty(.*)\\/style>|<scr(.*)\\/script>|<!--(.*)-->/isU","",$str);
	/*$alltext = '';
	$start = 1;
	for($i=0;$i<strlen($str);$i++){
		if($start==0 && $str[$i]=='>'){
			$start = 1;
		} else if($start==1) {
			if($str[$i]=='<') {
				$start = 0; $alltext .= ' ';
			} else if(ord($str[$i])>31) {
				$alltext .= $str[$i];
			}
		}
	}*/
	$alltext = strip_tags($str);
	$alltext = str_replace("　"," ",$alltext);
	$alltext = preg_replace("/&([^;&]*)(;|&)/","",$alltext);
	$alltext = preg_replace("/[\s]+/s"," ",$alltext);
	return $alltext;
}
//自动闭合html标签
function closetags($html) {
    preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
    $openedtags = $result[1];
   
    preg_match_all('#</([a-z]+)>#iU', $html, $result);
    $closedtags = $result[1];
    $len_opened = count($openedtags);
   
    if (count($closedtags) == $len_opened) {
        return $html;
    }
    $openedtags = array_reverse($openedtags);
   
    for ($i=0; $i < $len_opened; $i++) {
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
* 将字符串转换为数组
*
* @param	string	$data	字符串
* @return	array	返回数组格式，如果，data为空，则返回空数组
*/
function string2array($data) {
	if($data == '') return array();
	@eval("\$array = $data;");
	return $array;
}
/**
* 将数组转换为字符串
*
* @param	array	$data		数组
* @param	bool	$isformdata	如果为0，则不使用new_stripslashes处理，可选参数，默认为1
* @return	string	返回字符串，如果，data为空，则返回空
*/
function array2string($data, $isformdata = 1) {
	if($data == '') return '';
	if($isformdata) $data = new_stripslashes($data);
	return escape_string(var_export($data, TRUE));
	//return addslashes(var_export($data, TRUE));
}
/* 二维数组 排序
$array_name:传入的数组；
$row_id:数组想排序的项；
$order_type：排序的方式，asc或者desc；
$auto:是否开启自动键值（默认开启，字符键值时可以关闭）
*/
function array_sort($array_name,$row_id,$order_type='asc',$auto=TRUE){
 	$array_temp=array();
 	foreach($array_name as $key=>$value){//循环一层；
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
			$result_array[]=$array_name[$key];//新建一个结果数组，将原来传入的数组改变键值顺序后赋值给结果数组（原来数组不变）；
		}
	}else{
		foreach($array_temp as $key=>$value){
			$result_array[$key]=$array_name[$key];//新建一个结果数组，将原来传入的数组按原有的键值顺序后赋值给结果数组（原来数组不变）；
		}
	}
	return $result_array;
}

//日期显示
function toDate($time, $format='Y-m-d H:i:s'){
	switch($format){
		case 'ymd':
			$date = date('Y-m-d', $time);
			break;
		case 'ymd his':
			$date = date('Y-m-d H:i:s', $time);
			break;
		case 'Y-m-d H:i':
			$date = date('Y-m-d H:i', $time);
			break;
		case 'tips':
			$date = getDateStyle($time);
			break;
		default:
			$date = date($format, $time);
	}
	return $date;	
}
/**
* 将日期格式根据以下规律修改为不同显示样式
* 小于1分钟 则显示多少秒前
* 小于1小时，显示多少分钟前
* 一天内，显示多少小时前
* 3天内，显示前天22:23或昨天:12:23。
* 超过3天，则显示完整日期 'Y-m-d H:i'。
* @param  $time 数据源日期 unix时间戳
*/
function getDateStyle($time,$hi=TRUE){
    $nowTime = time();  //获取今天时间戳
    $timeHtml = ''; //返回文字格式
    $temp_time = 0;
    switch($time){
    //一分钟
    case ($time+60)>=$nowTime:
        $temp_time = $nowTime-$time;
        $timeHtml = $temp_time ."秒前";
        break;
    //小时
    case ($time+3600)>=$nowTime:
        $temp_time = date('i',$nowTime-$time);
        $timeHtml = $temp_time ."分钟前";
        break;
    //天
    case ($time+3600*24)>=$nowTime:
        $temp_time = date('G',$nowTime-$time);
        if($temp_time==0) $temp_time=24;
        $timeHtml = $temp_time .'小时前';
        break;
    //昨天
    case ($time+3600*24*2)>=$nowTime:
        $temp_time = date('H:i',$time);
        $timeHtml = '昨天'.$temp_time ;
        break;
    //前天
    case ($time+3600*24*3)>=$nowTime:
        $temp_time  = date('H:i',$time);
        $timeHtml = '前天'.$temp_time ;
        break;
    //3天前
    case ($time+3600*24*4)>=$nowTime:
        $timeHtml = '3天前';
        break;
    default:
        $timeHtml = $hi ? date('Y-m-d H:i',$time) : date('Y-m-d',$time);
        break;
    }
    return $timeHtml;
}
/*
'全功能安全过滤函数
'	1、请求方式 2、请求名  3、值类型
'	4、默认值  5、值大小/长度
*/
function G($request, $reqName, $vType = 1, $defValue = '', $vSize = 0, $filter = 0){
	switch ($request) {//数据请求方式
	case 0:
		$val = isset($reqName) ? $reqName : '';//非数组变量
		break;
	case 1:
		$val = isset($_REQUEST[$reqName]) ? $_REQUEST[$reqName] : '';
		break;
	case 2:
		$val = isset($_POST[$reqName]) ? $_POST[$reqName] : '';
		break;
	case 3:
		$val = isset($_GET[$reqName]) ? $_GET[$reqName] : '';
		break;
	case 4:
		$val = isset($_COOKIE[$reqName]) ? $_COOKIE[$reqName] : '';
		break;
	}
	return qType($val, $vType, $defValue, $vSize, $filter);
}
/*
'G函数变体 对于数组变量检测 1、数组变量 2、数组检测项(var.a.c) 
*/
function Q2($var, $reqName, $vType = 1, $defValue = '', $vSize = 0, $filter = 0){
	if(!is_array($var)) return $defValue;
	//支持 'a.c' : $var['a']['b'] ，此方式便于变量isset检查
	$reqName = '[\''. str_replace('.', '\'][\'', $reqName) .'\']';
	eval('$val = isset($var'.$reqName.') ? $var'.$reqName.' : \'\';');
	return qType($val, $vType, $defValue, $vSize, $filter);
}
/*
'数据类型过滤
'输入参数：
'	1、数组变量 2、值类型
'	3、默认值  4、值大小/长度 5、过滤方式用于字符串
*/
function qType($val, $vType = 1, $defValue = '', $vSize = 0, $filter = 0){
	if(is_array($val)) return $defValue;
	$val = trim($val);
	switch ($vType) {//数据类型
	case 0://数字
		if (!IsNum($val)) {
			$val = $defValue;
		}
		if ($vSize!=0 && $val>$vSize) $val = $defValue;
		break;
	case 1://'字符串过滤
		if ($val=='') {
			$val = $defValue;
		}else{
			if($filter==0) $val = trim_script($val);//script iframe 转义
			elseif($filter==1) $val = html_clean($val);//html过滤
			if ($vSize!=0 && strlen($val)>$vSize) $val = cutstr($val,$vSize);
		}
		if (get_magic_quotes_gpc()) $val = stripslashes($val);
		$val = escape_string($val);
		break;
	case 2://'日期
		if (!IsTime($val)) { 
			$val = $defValue;
		}
		break;
	case -1://不作任何处理 用非数据库写入 仅trim
		if ($val=='') {
			$val = $defValue;
		}
		if (get_magic_quotes_gpc()) $val = stripslashes($val);
		break;
	}
	return $val;
}
// rollback 把转义的单引号还原
function escape_string($str,$rollback=FALSE){
	$dbms = isset($GLOBALS['cfg']['db']['dbms']) ? $GLOBALS['cfg']['db']['dbms'] : 'mysql';
	if($dbms=='mysql') {
		//$str = !$rollback ? str_replace(array("\\","'"),array("\\\\","\'"),$str) : str_replace(array("\\\\","\'"),array("\\","'"),$str); // 斜杠 \转义bug
		$str = $rollback ? stripslashes($str) : addslashes($str);
	}else {
		$str = $rollback ? str_replace("''","'",$str) : str_replace("'","''",$str);//oracle,sqlite,mssql
	}
	return $str;
}
//数字检测函数
function IsNum($num) {
	return preg_match('/^[-\+]?\d+(\.\d+)?$/m', $num);
}
//日期检测函数(格式:2007-5-6 15:30:33)
function IsTime($DateStr) {
	return preg_match('/^(\d{4})-(0[1-9]|[1-9]|1[0-2])-(0[1-9]|[1-9]|1\d|2\d|3[0-1])(| (0[1-9]|[1-9]|1[0-9]|2[0-3]):([0-5][0-9]|0[0-9]|[0-9])(|:([0-5][0-9]|0[0-9]|[0-9])))$/m', $DateStr);
}
//判断email格式是否正确
function IsEmail($email) {
	return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
}

//显示错误信息提示过程  错误号,错误信息 
function ShowErrMsg($msg,$types=0){
	if(ob_get_length() !== FALSE) ob_clean();//清除页面
	$str ='';
	switch ($types) {
		case 0:
			$str = '<font style="font-family:Verdana;font-size:14px;color:red">'.$msg.'</font>';
			break;
		case 1:
			$str = '<script language="JavaScript">alert("'.$msg.'");history.back();</script>';
			break;
		case 2:
			$str = '<script language="JavaScript">alert("'.$msg.'");</script>';
			break;
	}
	exit($str);
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
	$status = substr($message,0,1);
	if($status==1) $success = substr($message,1);
	elseif($status==0) $error = substr($message,1);
	
	if(ob_get_length() !== false) ob_clean();//清除页面

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title></title><script type="text/javascript">var pgo=0;function jumpUrl(){if(pgo==0){'. $js .'; pgo=1;}}setTimeout("jumpUrl()",'. $time*1000 .');var t,val;function time(){var time = document.getElementById("time");val = parseInt(time.innerHTML)-1;time.innerHTML=val;if(val<=0) clearInterval(t);}t=setInterval("time()",1000);</script>
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
</style>
	</head><body>
	<div style="padding:89px 10px 10px;">
		<div class="msg">
			<div class="t">'.$message.'</div>
			<div class="g">'.$info.'</div>
			<div class="z"><a href="'. $jumpUrl . '"><span id="time">'.$time.'</span> 秒后自动跳转，如未跳转请点击此处手工跳转。</a></div>
		</div>
	</div>
</body></html>';
	exit;
}

//获取用户真实地址 返回用户ip
function GetIP() {
	static $realip = NULL;
	if ($realip !== NULL) {
		return $realip;
	}
	if (isset($_SERVER)) {
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			/* 取X-Forwarded-For中第x个非unknown的有效IP字符? */
			foreach ($arr as $ip) {
				$ip = trim($ip);
				if ($ip != 'unknown') {
					$realip = $ip;
					break;
				}
			}
		} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
			$realip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			if (isset($_SERVER['REMOTE_ADDR'])) {
				$realip = $_SERVER['REMOTE_ADDR'];
			} else {
				$realip = '0.0.0.0';
			}
		}
	} else {
		if (getenv('HTTP_X_FORWARDED_FOR')) {
			$realip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_CLIENT_IP')) {
			$realip = getenv('HTTP_CLIENT_IP');
		} else {
			$realip = getenv('REMOTE_ADDR');
		}
	}
	preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
	$realip = ! empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';
	return $realip;
}

//获得当前的脚本网址  如ab.php
function getCurUrl() {
	if(!empty($_SERVER["REQUEST_URI"])) {
		$scriptName = $_SERVER["REQUEST_URI"];
		$nowurl = $scriptName;
	} else {
		$scriptName = $_SERVER["PHP_SELF"];
		if(empty($_SERVER["QUERY_STRING"])) {
			$nowurl = $scriptName;
		} else {
			$nowurl = $scriptName."?".$_SERVER["QUERY_STRING"];
		}
	}
	return $nowurl;
}
//获取当前页面完整URL地址
function get_url() {
	$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
	$php_self = $_SERVER['PHP_SELF'] ? safe_replace($_SERVER['PHP_SELF']) : safe_replace($_SERVER['SCRIPT_NAME']);
	$path_info = isset($_SERVER['PATH_INFO']) ? safe_replace($_SERVER['PATH_INFO']) : '';
	$relate_url = isset($_SERVER['REQUEST_URI']) ? safe_replace($_SERVER['REQUEST_URI']) : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.safe_replace($_SERVER['QUERY_STRING']) : $path_info);
	return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
}

/**
* 产生随机字符串
*
* @param    int        $len  输出长度
* @param    string     $chars   可选的 ，默认为 0123456789
* @return   string     字符串 0:数字 1:仅字母 01:数字字母混合
*/
function random($len=6, $chars='0') {
	if($chars=='0') $chars = '0123456789';
	elseif($chars=='1') $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ';
	elseif($chars=='01') $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ0123456789';
	return substr(str_shuffle($chars.$chars), 0, $len);
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
function my_hash($hash=FALSE,$echo=TRUE){
	if(!isset($_SESSION)) session_start();
	$val = !empty($_SESSION['my_hash']) ? $_SESSION['my_hash'] : $_SESSION['my_hash']=random(6,'abcdefghigklmnopqrstuvwxwyABCDEFGHIGKLMNOPQRSTUVWXWY0123456789');
	if($hash){
		if(isset($_GET['my_hash']) && $_SESSION['my_hash'] != '' && ($_SESSION['my_hash'] == $_GET['my_hash'])) {
			$_SESSION['my_hash']=null;
			return TRUE;
		} elseif(isset($_POST['my_hash']) && $_SESSION['my_hash'] != '' && ($_SESSION['my_hash'] == $_POST['my_hash'])) {
			$_SESSION['my_hash']=null;
			return TRUE;
		} else {
			if($echo) ShowMsg('[hash]数据验证失败',HTTP_REFERER);
			else return FALSE;
		}
	}else{
		return $val;
	}
}
function my_hash_md5($val,$hash=FALSE){
	if(!isset($_SESSION)) session_start();
	if($hash){
		if(isset($_GET['my_hash_md5']) && $_SESSION['my_hash'] != '' && (md5(getMd5($val.$_SESSION['my_hash'])) == $_GET['my_hash_md5'])) {
			return TRUE;
		} elseif(isset($_POST['my_hash_md5']) && $_SESSION['my_hash'] != '' && (md5(getMd5($val.$_SESSION['my_hash'])) == $_POST['my_hash_md5'])) {
			return TRUE;
		} else {
			return FALSE;//'[hash]数据验证失败'
		}
	}else{
		return md5(getMd5($val.my_hash()));
	}
}
//验证码
function GetCode($w=80, $h=36, $fontsize=18, $len = 4, $reurl=FALSE,$number=false) {
	$codeurl = $GLOBALS['cfg']['root_dir'].'/myphp/inc/imagecode.php?';
	$codeurl .= "w=$w&h=$h&size=$fontsize&len=$len&t=".time().($number?'&number=1':'');
	if(!$reurl) return '<img src="'. $codeurl .'" alt="验证码,看不清楚?请点击刷新验证码" style="cursor:pointer; vertical-align:middle;" onclick="this.src=\''. $codeurl .'\'+\'&t=\'+Math.random();return false;" />';
	else return $codeurl;
}
//检查验证码
function CodeIsTrue($codename) {
	if(!isset($_POST[$codename])) return FALSE;
	$CodeStr = strtolower(trim($_POST[$codename]));
	if(!isset($_SESSION)) session_start();
	if ($_SESSION[$codename] == $CodeStr && !empty($CodeStr) ) {
		$_SESSION[$codename]='';
		return TRUE;
	} else {
		//$_SESSION[$codename]='';
		return FALSE;
	}
}

//返回经过随机串组合的加密md5  (encode_key)
function getMd5($val,$encode = '') {
	//if(!isset($GLOBALS['cfg']['encode_key'])) $GLOBALS['cfg']['encode_key'] = 'myphp';
	$encode = $encode != '' ? $encode : $GLOBALS['cfg']['encode_key'];
	return md5($encode.$val);
}
/**
* 字符串加密、解密函数
*
* @param	string	$txt		字符串
* @param	string	$operation	ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
* @param	string	$key		密钥：数字、字母、下划线
* @param	string	$expiry		过期时间
* @return	string
*/
function sys_auth($string, $operation = 'ENCODE', $key = '', $expiry = 0) {
	$key_length = 4;
	$key = md5($key != '' ? $key : $GLOBALS['cfg']['encode_key']);
	$fixedkey = md5($key);
	$egiskeys = md5(substr($fixedkey, 16, 16));
	$runtokey = $key_length ? ($operation == 'ENCODE' ? substr(md5(microtime(true)), -$key_length) : substr($string, 0, $key_length)) : '';
	$keys = md5(substr($runtokey, 0, 16) . substr($fixedkey, 0, 16) . substr($runtokey, 16) . substr($fixedkey, 16));
	$string = $operation == 'ENCODE' ? sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$egiskeys), 0, 16) . $string : base64_decode(substr($string, $key_length));

	$i = 0; $result = '';
	$string_length = strlen($string);
	for ($i = 0; $i < $string_length; $i++){
		$result .= chr(ord($string{$i}) ^ ord($keys{$i % 32}));
	}
	if($operation == 'ENCODE') {
		return $runtokey . str_replace('=', '', base64_encode($result));
	} else {
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$egiskeys), 0, 16)) {
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
function setargs($args,$base=FALSE){
	$a = $GLOBALS['cfg']['url_para_str'];//url参数分隔符
	if($base){
		$qstr = '&args='.$args.'&key='.getMd5($args.my_hash());
	}else{
		$qstr = 'args'. $a . $args . $a .'key'. $a .getMd5($args.my_hash());
	}
	return $qstr;
}
function return_args($args){
	$para = array();
	$para['args'] = $args;
	$para['key'] = getMd5($args.my_hash());
	return $para;
}
//key=args_val+my_hash
function getargs($args_item='',$gtype=1){
	$args_val = urldecode(G($gtype,'args',1,''));
	$key = G($gtype,'key',1,'');
	if($args_val=='' || $args_item=='' || $key=='') exit('0:args error!');
	if(getMd5($args_val.my_hash()) != $key) exit('0:Invalid args!');//参数有效验证 $key.'!='.$args_val.'-'.my_hash()
	
	$para = array('args'=>$args_val, 'key'=>$key);
	$args_val = explode('|', $args_val);$args_item = explode('|', $args_item);
	if(count($args_val) != count($args_item)) exit('0:args num error!');//参数个数验证
	foreach($args_item as $key => $keyname){
		$para[$keyname] = $args_val[$key];	
	}
	return $para;
}

//文件处理：目录创建，文件上传，文件读写

// 数组保存到文件
function arr2file($filename, $arr=''){
	if(is_array($arr)){
		$con = var_export($arr,true);
	} else{
		$con = $arr;
	}
	$data = "<?php\nreturn $con;\n?>";
	file_put_contents($filename, $data, LOCK_EX);
}

//递归创建目录 createPath ("./NcwCms/up/img/ap")
function createPath( $folderPath, $mode=0777 ) {
	$sParent = dirname( $folderPath );
	//Check if the parent exists, or create it.
	if ( !is_dir($sParent)) createPath( $sParent, $mode );
	if ( !is_dir($folderPath)) mkdir($folderPath, $mode) or ShowMsg('创建目录（'. $folderPath .'）失败！');
}
//取得文件扩展 $filename 文件名
function FileExt($filename) {
	return strtolower(trim(substr(strrchr($filename, '.'), 1, 10)));
}
/**
 * 文件下载
 * @param $filepath 文件路径
 * @param $filename 文件名称
 */
function FileDown($filepath, $filename = '') {
	if(!$filename) $filename = basename($filepath);
	if(is_ie()) $filename = rawurlencode($filename);
	$filetype = FileExt($filename);
	$filesize = sprintf("%u", filesize($filepath));
	if(ob_get_length() !== false) @ob_end_clean();
	header('Pragma: public');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: pre-check=0, post-check=0, max-age=0');
	header('Content-Transfer-Encoding: binary');
	header('Content-Encoding: none');
	header('Content-type: '.$filetype);
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Content-length: '.$filesize);
	readfile($filepath);
	exit;
}
//IE浏览器判断
function is_ie() {
	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
	if((strpos($useragent, 'opera') !== false) || (strpos($useragent, 'konqueror') !== false)) return false;
	if(strpos($useragent, 'msie ') !== false) return true;
	return false;
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
		$v = $byte . 'Byte';
	}
	return $v;
}
//获取图片
function get_image($image){
	static $root_dir_len;
	$root_dir_len=strlen(ROOT_DIR);
	if($image=='') $image = PUB.'/images/nopic.gif';
	if(substr($image,0,4)=='http' || substr($image,0,$root_dir_len)==ROOT_DIR){
		return $image;
	}else{
		return ROOT_DIR.(substr($image,0,1)=='/' ? $image : '/'.$image);
	}
}
//获取缩略图 不存在返回原图 $thumb_wh : 240_180
function get_thumb($image,$thumb_wh=''){
	if(substr($image,0,4)=='http'){
		return $image;
	}else{
		$image = get_image($image);
		$dot = strrpos($image,'.');
		$thumb = substr($image, 0, $dot).($thumb_wh==''?GetC('thumb_wh'):$thumb_wh).substr($image, $dot);
		return is_file(__ROOT__.$thumb) ? $thumb : $image;
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
			$ueditor=TRUE;
			$str .='<script type="text/javascript" src="'.($config==''?PUB.'/ueditor/ueditor.config.js':PUB.'/ueditor/'.$config).'"></script><script type="text/javascript" src="'.PUB.'/ueditor/ueditor.all.min.js"></script>';
			$str .= '<script>var ueditor;</script>';
		}
		$str .= '<textarea id="'. $field['name'] .'" name="'. $field['name'] .'">'. htmlspecialchars($value) .'</textarea><script type="text/javascript">ueditor = UE.getEditor("'. $field['name'] .'",{initialFrameWidth:'. $field['width'] .',initialFrameHeight:'. $field['height'] .'});</script>';
	}else{
		static $keditor;
		if(empty($keditor)) {
			$keditor=TRUE;
			$str .='<script src="'.PUB.'/keditor/kindeditor.js"></script><script src="'.PUB.'/keditor/lang/zh_CN.js"></script>';
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
	$configfile = __ROOT__.ROOT_DIR.$file;
	if(!is_writable($configfile)) ShowMsg('Please chmod '.$configfile.' to 0777 !');
	$pattern = $replacement = array();
	foreach($config as $k=>$v) {
		if(is_array($allow_val) && !in_array($k,$allow_val)) continue;
		$v = trim($v);
		$pattern[$k] = "/'".$k."'\s*=>\s*([']?)[^']*([']?)(\s*),/is"; //[^']
		$replacement[$k] = "'".$k."' => \${1}".$v."\${2}\${3},";
	}
	$str = file_get_contents($configfile);
	$str = preg_replace($pattern, $replacement, $str);
	return file_put_contents($configfile, $str, LOCK_EX);		
}

/*
 * 安全cookie md5加密
 * @param     string  $name   名
 * @param     string  $value  值
 * @param     string  $option cookie参数  可数字、字符串、数组
*/
function cookie($name, $value='', $option=null) {
    // 默认设置
    $config = array(
        'prefix' => isset($GLOBALS['cfg']['cookie_pre']) ? $GLOBALS['cfg']['cookie_pre'] : '', // cookie 名称前缀
        'expire' => isset($GLOBALS['cfg']['cookie_expire']) ? $GLOBALS['cfg']['cookie_expire'] : 0, // cookie 保存时间
        'path' => isset($GLOBALS['cfg']['cookie_path']) ? $GLOBALS['cfg']['cookie_path'] : '/', // cookie 保存路径
        'domain' => isset($GLOBALS['cfg']['cookie_domain']) ? $GLOBALS['cfg']['cookie_domain'] : '', // cookie 有效域名
        'secure' => isset($GLOBALS['cfg']['cookie_secure']) ? $GLOBALS['cfg']['cookie_secure'] : FALSE, //  cookie 启用安全传输
        'httponly' => isset($GLOBALS['cfg']['cookie_httponly']) ? $GLOBALS['cfg']['cookie_httponly'] : FALSE, // httponly设置
    );
    // 参数处理
    if (!is_null($option)) {
        if (is_numeric($option))
            $option = array('expire' => $option);
        elseif (is_string($option))
            parse_str($option, $option);//解析字符串为数组
        $config = array_merge($config, $option);
    }
	if(!empty($config['httponly'])){
        ini_set('session.cookie_httponly', 1);
    }
	// 清除指定前缀的所有cookie
    if (is_null($name)) {
        if (empty($_COOKIE))
            return null;
        // 要删除的cookie前缀，不指定则删除config设置的指定前缀
        $prefix = empty($value) ? $config['prefix'] : $value;
        if (!empty($prefix)) {// 如果前缀为空字符串将直接清除$_COOKIE
            foreach ($_COOKIE as $key => $val) {
                if (0 === strpos($key, $prefix)) {
                    setcookie($key, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                    unset($_COOKIE[$key]);
                }
            }
        }else{
			unset($_COOKIE);
		}
        return null;
	}
	$encode = substr($name,0,1)=='_' ? false : true; //是否编码 以下划线开头的不编码
	$name = $config['prefix'].'_'.$name;
	if ('' === $value) {//获取cookie值 
        if(isset($_COOKIE[$name])){
            return $encode ? sys_auth($_COOKIE[$name], 'DECODE') : $_COOKIE[$name];
        }else{
            return null;
        }
    } else {
        if (is_null($value)) {//删除cookie值
            setcookie($name, '', time() - 3600, $config['path'], $config['domain'],$config['secure'],$config['httponly']);
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {// 设置cookie
            setcookie($name, ($encode ? sys_auth($value, 'ENCODE') : $value), $config['expire']==0 ? 0 : time()+$config['expire'], $config['path'], $config['domain'], $config['secure'], $config['httponly']);
        }
    }
}

//递归自定方法数组处理 传址
function array_call_func($func, &$data){
	//$r=array();
    foreach ($data as $key => $val) {
        $data[$key] = is_array($val)
         ? array_call_func($func, $val)
         : call_user_func($func, $val);
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
 * @param mixed $defval 变量的默认值
 * @param mixed $datas 要获取的额外数据源
 * @return mixed
 */
/*
string,bool,int,float,arr,date
%s,%b,%d,%f,%a,%date [2014-01-11 13:23:32 | 2014-01-11]
filter:fun1,fun2,/regx/i正则过滤
*/
function Q($name,$defval='',$datas=null) {
	static $_PUT = null;
	$filter = $min = $max = null;
	$type = 's'; // 默认转换为字符串
	if(strpos($name,':')){ // 指定过滤方法
		list($name,$filter) = explode(':',$name,2);  
    }
	if(strpos($name,'{') && substr($name,-1)=='}'){ // 指定长度范围 用于数字及字符串
		list($name,$size) = explode('{',substr($name,0,-1),2);
		if(strpos($size,',')){
			list($min,$max) = explode(',',$size,2);
		}else $max = $size;
    }
	if(strpos($name,'%')){ // 指定修饰符
		list($name,$type) =	explode('%',$name,2);  
    }
    if(strpos($name,'.')) { // 指定参数来源
        list($method,$name) = explode('.',$name,2);
    }else{ // 默认为自动判断
        $method = 'auto';
    }
	//echo $method.'--'.$name.'--'.$type.'--'.$min.'--'.$max.'--'.$filter;
    switch(strtolower($method)) {
        case 'get' : $input = &$_GET; break;
        case 'post': $input = &$_POST; break;
        case 'put' :   
        	if(is_null($_PUT)) parse_str(file_get_contents('php://input'), $_PUT);
        	$input = &$_PUT; break;
        case 'auto':
            switch($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input = &$_POST; break;
                case 'PUT':
                	if(is_null($_PUT)) parse_str(file_get_contents('php://input'), $_PUT);
					$input = &$_PUT; break;
                default:
                    $input = &$_GET;
            }
            break;
        case 'request': $input = &$_REQUEST; break;
        case 'session': $input = &$_SESSION; break;
        case 'cookie' : $input = &$_COOKIE; break;
        case 'server' : $input = &$_SERVER; break;
        case 'globals': $input = &$GLOBALS; break;
        case 'data'   : $input = &$datas; break;
		case 'path'   :   
            $input = array();
            if(!empty($_SERVER['PATH_INFO'])){
                $depr = GetC('url_para_str');
				$depr = $depr==null ? '/' : $depr;
                $input = explode($depr,trim($_SERVER['PATH_INFO'],$depr));            
            }
            break;
        default:
            return $defval;
    }
    if(''==$name) { // 获取全部变量
        $data = $input;
        $filter = isset($filter)?$filter:GetC('def_filter');
        if($filter && $filter!='null') {
			$filters = strpos($filter, ',')===false ? array($filter) : explode(',', $filter);
			foreach($filters as $filter){
				$data = array_call_func($filter, $data); // 参数过滤
			}
        }
    }elseif(isset($input[$name])) { // 取值操作
        $data = $input[$name];
        $filters = isset($filter)?$filter:GetC('def_filter');
        if($filters && $filters!='null') { //过滤处理
            if(is_string($filters)){
                if(0 === strpos($filters,'/')){
                    if(1 !== preg_match($filters,(string)$data)){// 支持正则验证
                        return $defval;
                    }
                }else{ //多个过滤,分隔
                    $filters = explode(',',$filters);                    
                }
            }elseif(is_int($filters)){
                $filters = array($filters);
            }

            if(is_array($filters)){
                foreach($filters as $filter){
                    if(function_exists($filter)) {
                        $data = is_array($data) ? array_call_func($filter,$data) : $filter($data); // 参数过滤
                    }else{ // ?? filter_var
                        $data = filter_var($data, is_int($filter) ? $filter : filter_id($filter));
                        if(false === $data) return $defval;
                    }
                }
            }
        }
/*
(int), (integer) - 转换为整形 integer	(bool), (boolean) - 转换为布尔类型 boolean	(float), (double), (real) - 转换为浮点型 float
(string) - 转换为字符串 string	(array) - 转换为数组 array	(object) - 转换为对象 object	(unset) - 转换为 NULL (PHP 5)
*/
        //类型转换
    	switch($type){
    		case 'a':	// 数组
    			$data =	(array)$data;
				break;
    		case 'd':	// 数字
    		case 'f':	// 浮点
    			if ($data=='' || !is_numeric($data)) {$data = $defval==''?0:$defval; break;}
    			$data =	$type=='d'?(int)$data:(float)$data;
				if($max!=null && $min!=null) $data = $min<=$data && $data<=$max ? $data : $defval;
				elseif($max!=null) $data = $data<=$max ? $data : $defval;
				elseif($min!=null) $data = $min<=$data ? $data : $defval;
				break;
    		case 'b':	// 布尔
    			$data =	(boolean)$data; break;
			case 'date':// 日期
				$data =	IsTime($data) ? $data : $defval;
				break;
            case 's':   // 字符串
            default:
				if($data=='') {$data = $defval; break;}
                $data = (string)$data;
				$len = strlen($data);
				if($max!=null && $min!=null) $data = $min<=$len && $len<=$max ? $data : $defval;
				elseif($max!=null) $data = $len<=$max ? $data : cutstr($data,$max);
				elseif($min!=null) $data = $min<=$len ? $data : $defval;

				//转义
				if (get_magic_quotes_gpc()) $data = stripslashes($data);
				$data = escape_string($data);
    	}
    }else{ // 变量默认值
        $data = $defval;
    }
    is_array($data) && array_walk_recursive($data,'safe_filter');
    return $data;
}
function safe_filter(&$val){
	// TODO 其他安全过滤
	
	// 过滤查询特殊字符
    if(preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOTIN|NOT IN|IN)$/i', $val)){
        $val .= '&nbsp;';
    }
}
//xss 过滤
function remove_xss($val) {
	// remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
	// this prevents some character re-spacing such as <java\0script>
	// note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
	$val = preg_replace('/([\x00-\x08\x0b-\x0c\x0e-\x19])/', '', $val);

	$search = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890
	!@#$%^&*()~`";:?+/={}[]-_|\'\\';
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