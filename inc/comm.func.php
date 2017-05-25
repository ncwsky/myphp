<?php
/**
 *  comm.func.php 功能函数库
 */

/**
 * 数字转换为中文
 * @param  string|integer|float  $num  目标数字
 * @param  integer $mode 模式[true:金额（默认）,false:普通数字表示]
 * @param  boolean $sim 使用小写（默认）
 * @return string
 */
function num2ch($num,$mode = true,$sim = true){
    if(!is_numeric($num)) return '含有非数字非小数点字符！';
    $char = $sim ? array('零','一','二','三','四','五','六','七','八','九') : array('零','壹','贰','叁','肆','伍','陆','柒','捌','玖');
    $unit = $sim ? array('','十','百','千','','万','亿','兆') : array('','拾','佰','仟','','萬','億','兆');
    $retval = $mode ? '元':'点';
 
    //小数部分
    if(strpos($num, '.')){
        list($num,$dec) = explode('.', $num);
        $dec = strval(round($dec,2));
        if($mode){
            $retval .= "{$char[$dec['0']]}角{$char[$dec['1']]}分";
        }else{
            for($i = 0,$c = strlen($dec);$i < $c;$i++) {
                $retval .= $char[$dec[$i]];
            }
        }
    }else{
		$retval = '';
	}
    //整数部分
    $str = $mode ? strrev(intval($num)) : strrev($num);
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
    $retval = join('',array_reverse($out)) . $retval;
    return $retval;
}

function num2char($num,$mode=true){
	$char = array('零','一','二','三','四','五','六','七','八','九');
	//$char = array('零','壹','贰','叁','肆','伍','陆','柒','捌','玖);
	$dw = array('','十','百','千','','万','亿','兆');
	//$dw = array('','拾','佰','仟','','萬','億','兆');
	$dec = '点';  //$dec = '點';
	$retval = '';
	if($mode){
		preg_match_all('/^0*(\d*)\.?(\d*)/',$num, $ar);
	}else{
		preg_match_all('/(\d*)\.?(\d*)/',$num, $ar);
	}
	if($ar[2][0] != ''){
		$retval = $dec . num2char($ar[2][0],false); //如果有小数，先递归处理小数
	}
	if($ar[1][0] != ''){
		$str = strrev($ar[1][0]);
		for($i=0;$i<strlen($str);$i++) {
			$out[$i] = $char[$str[$i]];
			if($mode){
				$out[$i] .= $str[$i] != '0'? $dw[$i%4] : '';
				if(($i>1) && ($str[$i]+$str[$i-1] == 0)){
					$out[$i] = '';
				}
				if($i%4 == 0){
					$out[$i] .= $dw[4+floor($i/4)];
				}
			}
		}
		$retval = join('',array_reverse($out)) . $retval;
	}
	return $retval;
}

/* ini内容 解析
 * string str 	ini配置内容 
 * string find 	ini配置项  [name]
 * string attr 	查找配置项的属性
 */
function ReadIni($str, $find, $attr=''){
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
 * string str 	ini配置内容 
 * string find 	ini配置项 [name]
 * string attr_val 	配置项属性=属性值 attr=val
 * bool $ismult 批量直接替换写入 $attr_val\n$attr_val
 */
function WriteIni($str, $find, $attr_val,$ismult=false){
	$str = trim($str);
	$attr_val = trim($attr_val);
	if(strpos($attr_val,'=')===false && !$ismult) return $str;

	$br = chr(13).chr(10);
	$wz = strpos($str, $find.$br);

	if($ismult){ //多项直接替换
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
		if($attr=='') return $str; //$val=='' || 
	}
	
	if($wz!==false){ //无此配置项
		$wz += strlen($find.$br);

		!$ismult && $wz_a = strpos($str, $attr.'=', $wz);
		if(!$ismult && $wz_a!==false && strpos(substr($str,$wz,$wz_a-$wz),'[')===false){ //存在此属性
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
				$str = substr($str,0, $ismult?$wz:$wz_end). $br.$attr_val . substr($str,$wz_end);
			else //配置项在最尾
				$str = ($ismult?substr($str,0, $wz):$str).$br.$attr_val;
		}
	}else //直接追加
		$str .= $br.$find.$br.$attr_val;
	return $str;
}

/*
 * rc4加密算法
 * $pkey 密钥
 * $pdata 要加密的数据
 */
function rc4($pdata, $pkey)//$pwd密钥　$data需加密字符串
{
    $sbox = array(); $keys = array();
    $keylen = strlen($pkey); $datalen = strlen($pdata);
    $cipher = '';
 
    for ($i = 0; $i < 256; $i++){
    	$sbox[$i] = $i;
        $keys[$i] = ord($pkey[$i % $keylen]);
    }

    for ($j = $i = 0; $i < 256; $i++){
        $j = ($j + $sbox[$i] + $keys[$i]) % 256;
        $tmp = $sbox[$i];
        $sbox[$i] = $sbox[$j];
        $sbox[$j] = $tmp;
    }

 	$j = $l = $k = 0;
    for ($i = 0; $i < $datalen; $i++){
        $j = ($j + 1) % 256;
        $l = ($l + $sbox[$j]) % 256;
 
        $tmp = $sbox[$j];
        $sbox[$j] = $sbox[$l];
        $sbox[$l] = $tmp;
 
        $k = ($sbox[$j] + $sbox[$l]) % 256;
        $cipher .= chr(ord($pdata[$i]) ^ $sbox[$k]);
    }
    return $cipher;
}
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {		
	$ckey_length = 4;
	$key = md5($key != '' ? $key : GetC('authcode_key'));
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