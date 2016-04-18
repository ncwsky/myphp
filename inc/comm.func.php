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