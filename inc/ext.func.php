<?php
/**
 *  ext.func.php 扩展函数库
 */

//参数说明：TotalResult(记录条数),Page_Size(每页记录条数),CurrentPage(当前记录页),paraUrl(URL参数)
//分页函数1：PageList1
function PageList1($TotalResult, $Page_Size, $CurrentPage, $paraUrl, $pageName = 'page') {
	$Page_Count=$TotalResult / $Page_Size;
	if ($Page_Count>floor($Page_Count)) $Page_Count = floor($Page_Count)+1;
	$outstr = '共有 <span id="totalresult">'. $TotalResult .'</span> 条信息&nbsp;&nbsp';
	if ($CurrentPage <= 1) {
		$outstr .= '首页&nbsp;&nbsp;上一页&nbsp;&nbsp;';
	} else {
		$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',1,$paraUrl) .'">首页</a>&nbsp;&nbsp;<a href="'. str_replace('{$'.$pageName.'}',($CurrentPage-1),$paraUrl) .'">上一页</a>&nbsp;&nbsp;';
	}
	if ($CurrentPage >= $Page_Count) {
		$outstr .= '下一页&nbsp;&nbsp;尾页&nbsp;';
	} else {
		$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',($CurrentPage+1),$paraUrl) .'">下一页</a>&nbsp;&nbsp;<a href="'. str_replace('{$'.$pageName.'}',$Page_Count,$paraUrl) .'">尾页</a>&nbsp;';
	}
	$outstr .= '&nbsp;页次:'. $CurrentPage .'/'. $Page_Count .'页&nbsp;&nbsp;'. $Page_Size .'条信息/页&nbsp;&nbsp;转到<select name="select" onChange="javascript:var url=\''. $paraUrl .'\';url=url.replace(\'{$'. $pageName .'}\',this.options[this.selectedIndex].value);window.location.href=url;">';
	for ($ipg = 1; $ipg <= $Page_Count; $ipg++) {
		if ($ipg == $CurrentPage) {
			$outstr .= '<option value='. $ipg .' selected>'. $ipg .'</option>"';
		} else {
			$outstr .= '<option value='. $ipg .'>'. $ipg .'</option>';
		}
	}
	$outstr .= '</select>';
	return $outstr;
}
//分页函数2：PageList2 , 参数同上
function PageList2($TotalResult, $Page_Size, $CurrentPage, $paraUrl, $pageName = 'page') {
	$Page_Count=$TotalResult / $Page_Size;
	if ($Page_Count>floor($Page_Count)) $Page_Count = floor($Page_Count)+1;
	$outstr = '第 '. $CurrentPage .'页/总 '. $Page_Count .'页 | 总 <span id="totalresult">'. $TotalResult .'</span>条 | ';
	if ($CurrentPage <= 1) 
		$outstr .= '首页 | 上页 | ';
	else
		$outstr .= '<A href="'. str_replace('{$'.$pageName.'}',1,$paraUrl) .'">首页</A> | <A href="'. str_replace('{$'.$pageName.'}',($CurrentPage-1),$paraUrl) .'">上页</A> | ';

	if ($CurrentPage >= $Page_Count)
		$outstr .= '下页 | 尾页';
	else
		$outstr .= '<A href="'. str_replace('{$'.$pageName.'}',($CurrentPage+1),$paraUrl) .'">下页</A> | <A href="'. str_replace('{$'.$pageName.'}',$Page_Count,$paraUrl) .'">尾页</A>';
	return $outstr;
}
//分页函数3：PageList3 ,参数同上,InitPageNum初始显示数*2
function PageList3($TotalResult, $Page_Size, $CurrentPage, $paraUrl, $InitPageNum, $pageName = 'page') {
	$Page_Count=ceil($TotalResult / $Page_Size);

	$outstr = '<a class="page_total">'. $Page_Count .'页/<span id="totalresult">'. $TotalResult .'</span>条</a>';
	if ($TotalResult <= $Page_Size) return $outstr;
	if ($CurrentPage>$InitPageNum) {
		$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',1,$paraUrl) .'">首页</a>';// <a href="'. str_replace('{$'.$pageName.'}',($CurrentPage-1),$paraUrl) .'">上一页</a>
	}
	//获取页码范围
	if ($CurrentPage <= 1) {
		$TmpPageNo = 1;
		$TmpPageNum = $InitPageNum;
	} elseif ($CurrentPage <= $InitPageNum) {
		$TmpPageNo = 1;
		$TmpPageNum = $InitPageNum + $CurrentPage-1;
	} elseif ($CurrentPage > $InitPageNum && $CurrentPage < $Page_Count) {
		$TmpPageNo = $CurrentPage - $InitPageNum;
		$TmpPageNum = $InitPageNum + $CurrentPage-1;
	} elseif ($CurrentPage >= $Page_Count) {
		$TmpPageNo = $CurrentPage - $InitPageNum;
		$TmpPageNum = $Page_Count;
	}
	//页码超出范围
	if ($TmpPageNum > $Page_Count) $TmpPageNum = $Page_Count;
	
	for ($PageNo = $TmpPageNo; $PageNo <= $TmpPageNum; $PageNo++) {
		if ($CurrentPage == $PageNo)
			$outstr .= '<strong>'.$PageNo .'</strong>';
		else
			$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',$PageNo,$paraUrl) .'">'. $PageNo .'</a>';
	}
	
	if ($CurrentPage <= $Page_Count-$InitPageNum)
		$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',$Page_Count,$paraUrl) .'">末页</a>';//<a href="'. str_replace('{$'.$pageName.'}',($CurrentPage+1),$paraUrl) .'">下一页</a> 
	
	return $outstr;
}
function PageList4($TotalResult, $Page_Size, $CurrentPage, $paraUrl, $InitPageNum, $pageName = 'page') {
	$Page_Count=ceil($TotalResult / $Page_Size);

	$outstr = '';//'<a class="page_total">'. $Page_Count .'页/<span id="totalresult">'. $TotalResult .'</span>条</a>';
	if ($TotalResult <= $Page_Size) return $outstr;
	if ($CurrentPage>1) {
		$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',($CurrentPage-1),$paraUrl) .'">上一页</a>';
	}else{
		$outstr .= '<a>上一页</a>';
	}
	//获取页码范围
	if ($CurrentPage <= 1) {
		$TmpPageNo = 1;
		$TmpPageNum = $InitPageNum;
	} elseif ($CurrentPage <= $InitPageNum) {
		$TmpPageNo = 1;
		$TmpPageNum = $InitPageNum + $CurrentPage-1;
	} elseif ($CurrentPage > $InitPageNum && $CurrentPage < $Page_Count) {
		$TmpPageNo = $CurrentPage - $InitPageNum;
		$TmpPageNum = $InitPageNum + $CurrentPage-1;
	} elseif ($CurrentPage >= $Page_Count) {
		$TmpPageNo = $CurrentPage - $InitPageNum;
		$TmpPageNum = $Page_Count;
	}
	//页码超出范围
	if ($TmpPageNum > $Page_Count) $TmpPageNum = $Page_Count;
	
	for ($PageNo = $TmpPageNo; $PageNo <= $TmpPageNum; $PageNo++) {
		if ($CurrentPage == $PageNo)
			$outstr .= '<strong>'.$PageNo .'</strong>';
		else
			$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',$PageNo,$paraUrl) .'">'. $PageNo .'</a>';
	}
	
	if ($CurrentPage < $Page_Count){
		$outstr .= '<a href="'. str_replace('{$'.$pageName.'}',($CurrentPage+1),$paraUrl) .'">下一页</a>';
	}else{
		$outstr .= '<a>下一页</a>';
	}
	
	return $outstr;
}

/**
 * 格式化商品价格
 * @param   float   $price  商品价格
 * @return  string
 */
function price_format($price, $price_format=0, $currency_format='￥%s元') {
	switch ($price_format){
	case 0:// 四舍五入，不保留小数
        $price = round($price);
		break;
    case 1: // 不四舍五入，保留 1 位
		$price = number_format($price, 1, '.', '');
		break;
    case 2:// 
		$price = number_format($price, 2, '.', '');
		break;
	case 3: // 保留不为 0 的尾数
		$price = preg_replace('/(.*)(\\.)([0-9]*?)0+$/', '\1\2\3', number_format($price, 2, '.', ''));
		if (substr($price, -1) == '.')
		{
			$price = substr($price, 0, -1);
		}
		break;
	}
    return sprintf($currency_format, $price);
}
/**
*数字金额转换成中文大写金额的函数
*String Int  $num  要转换的小写数字或小写字符串
*return 大写字母
*小数位为两位
**/
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
//2次方和反解析
function sum2pow($num){
	$bin = decbin($num);
    $pow = [];
    while($num){
        if($num==1){
            $pow[]=$num; break;
        }
        $bin = substr($bin,1);
        //if($bin===false) break;
        $v = bindec($bin); //剩余数
        if(($num-$v)>0)
        	$pow[]=$num - $v;
        $num = $v;
    }
    return $pow;
}
//输入2位小数的数字
function num2fixed($number){
	return sprintf('%.2f', $number);
}
/**
 * 表单重复提交限制 默认10秒内
 * @param url 跳转网址, time 限制时间 单位秒
 * @return NULL
 */
function form_token($url='',$time=10,$stime=3){
 	//表单重复提交限制 10秒内
	//if(!isset($_SESSION)) session_start();
	$token = isset($_SESSION['token']) ? $_SESSION['token'] : 0;
	if(!empty($token)){
		$token = time()-$token;
		$token = $token<=$time ? $token : 0;
	}
	if(!empty($token)) ShowMsg('表单 '. ($time-$token) .' 秒内限制提交',$url,'',$stime);
	$_SESSION['token'] = time();
}
//AES 128位加密
function aes($string, $operation = 'ENCODE', $key = ''){
	$key = $key != '' ? $key : $GLOBALS['cfg']['encode_key'];//md5($key != '' ? $key : $GLOBALS['cfg']['encode_key'])
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
    $len = strlen($str)/2;
    return substr_replace($str,str_repeat('*',$len),ceil(($len)/2),$len);
}
//替换字符串中间位置字符为星号  支持中文
function cn_half_replace($str){
	preg_match_all('/./u', $str, $arr);	
	//var_dump($arr);
    $len = count($arr[0])/2;
	$offset = ceil(($len)/2);
	$a = implode('', array_slice($arr[0], 0, $offset));
	$b = implode('', array_slice($arr[0], $offset+$len));
    return $a.str_repeat('*',$len).$b;
}
// Returns true if $string is valid UTF-8 and false otherwise.
function is_utf8($word){
	if (preg_match("/^([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}/",$word) == true || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}$/",$word) == true || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){2,}/",$word) == true){
		return true;
	}else{
		return false;
	}
} // function is_utf8
//采集数据 缓存文件路径 采集url 超时设置  返回采集内容
function get_data($cache,$url,$timeout=10){
	if(!file_exists($cache)){
		$data = Http::doGet($url,$timeout);
		if(!$data) Log::write('ext.func.get_data fail: '.$url);//err
		else file_put_contents($cache, $data);
		//echo '0<br>';
	}else{
		$data = file_get_contents($cache);
		//echo '1<br>';
	}
	return $data;
}
//检测是否手机 return bool true|false
function is_mobile() {
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
function is_weixin(){
	if ( isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
		return true;
    }  
	return false;
}