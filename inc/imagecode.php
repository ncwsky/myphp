<?php
$w = (int)($_GET['w']) ? (int)($_GET['w']) : 80;
$h = (int)($_GET['h']) ? (int)($_GET['h']) : 36;
$size = (int)($_GET['size']) ? (int)($_GET['size']) : 18; 
$len = (int)($_GET['len']) ? (int)($_GET['len']) : 4; 
$type = 'png';

imageCode($w, $h, $size, $type, $len);

function imageCode($w=80, $h=36, $fontsize=18, $type='png', $len = 4, $codename = 'code') {
	//生成随机字符
	$chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ0123456789';//0123456789
	
	$number = isset($_GET['number']) ? (int)($_GET['number']) : 0;
	if($number==1) $chars = '0123456789';
	
	$randval = '';
	for ( $i = 0; $i < $len; $i++ ){
		$randval .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
	}
	if(!isset($_SESSION)) session_start();//如果没有开启，session，则开启session

	$_SESSION[$codename] = strtolower($randval);//小写

	//imagecreate($w, $h) 返回一个白色图像的标识符
	$img = imagecreatetruecolor($w, $h);//创建指定wh的黑色图像并返回一个图像标识符
	
	$r = array(225,255,223);
	$g = array(225,236,255);
	$b = array(225,236,125);
	$key = mt_rand(0,2);

	$bgColor = imagecolorallocate($img, $r[$key],$g[$key],$b[$key]);   //背景色（随机）
	$bgColor = imagecolorallocate($img, 237, 247, 255);   //背景色（随机）
	$borderColor = imagecolorallocate($img, 170, 212, 240);            //边框色
	
	imagefilledrectangle($img, 0, 0, $w, $h, $bgColor);//画一矩形并填充
	//imagefill($img, 0, 0, $bgColor);//区域填充
	imagerectangle($img, 0, 0, $w-1, $h-1, $borderColor);//画一个矩形
	
	//imagefill ( resource image, int x, int y, int color ) 区域填充
	//imagefill($img, 0, 0, $bgcol);//设置背景
	
	// mt_rand ( [int min, int max] ) -- 生成更好的随机数
	$txt_color = imagecolorallocate($img, mt_rand(0, 166), mt_rand(0, 166), mt_rand(0, 166));
	//$txt_color = imagecolorallocate($img,mt_rand(0,200),mt_rand(0,120),mt_rand(0,120));
	
	//imagestring  水平地画一行字符串 imagestring ( resource image, int font[1-5], int x, int y, string s, int col )
	//imagechar -- 水平地画一个字符   imagechar ( resource image, int font[1-5], int x, int y, string c, int color )

	// 干扰
	for($i=0;$i<8;$i++){//画椭圆弧
		//$fontcolor=imagecolorallocate($img,mt_rand(0,156),mt_rand(0,156),mt_rand(0,156));
		imagearc($img,mt_rand(-10,$w),mt_rand(-10,$h),mt_rand(20,250),mt_rand(20,250),55,54,$txt_color);
	}/*
	for($i=0;$i<10;$i++){//画一个单一像素
		$fontcolor=imagecolorallocate($img,mt_rand(0,255),mt_rand(0,255),mt_rand(0,255));
		imagesetpixel($img,mt_rand(0,$w),mt_rand(0,$h),$fontcolor);
	}*/
	$font = dirname(__FILE__).'/ggbi.ttf';
	for($i=0;$i<$len;$i++) {
		//imagechar($img,5,$i*10+5,mt_rand(1,8),$randval{$i}, $txt_color);
		imagefttext($img, $fontsize, mt_rand(-30,30), $i*$fontsize+4, $h/2+8, $txt_color, $font, $randval{$i});
	}

	//输出图像
    header("Pragma:no-cache\r\n");
    header("Cache-Control:no-cache\r\n");
    header("Expires:0\r\n");
	
	header('Content-type: image/png');
	imagepng($img);
	imagedestroy($img);
}
?>