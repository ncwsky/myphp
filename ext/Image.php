<?php
//生成图像缩略图和生成验证码
class Image
{
    /**
     * 取得图像信息
     * @param string $img 图像文件名
     * @return bool|array
     */
    static function getImageInfo($img) {
        if(filesize($img) && false!==($imageInfo = getimagesize($img))) {
            $imageType = strtolower(substr(image_type_to_extension($imageInfo[2]),1));
            $imageSize = filesize($img);
            $info = array(
                "width"=>$imageInfo[0],
                "height"=>$imageInfo[1],
                "type"=>$imageType,
                "size"=>$imageSize,
                "mime"=>$imageInfo['mime']
            );
            return $info;
        }else {
            return false;
        }
    }

    /**
     * 生成缩略图
     * @param string $image  原图
     * @param string $thumbName 缩略图文件名
     * @param string $type 图像格式
     * @param int $maxWidth  宽度
     * @param int $maxHeight  高度
     * @param boolean $interlace 启用隔行扫描
	 * @param boolean $fixed 固定缩略图大小
     * @return bool|int|string
     */
    static function thumb($image,$thumbName,$type='',$maxWidth=200,$maxHeight=50,$interlace=TRUE,$fixed=FALSE)
    {
        $info  = Image::getImageInfo($image); // 获取原图信息
        if($info === false) return false;

		$srcWidth  = $info['width'];
		$srcHeight = $info['height'];
		if($maxWidth==0 && $maxHeight==0){
            return 0;//直接返回
        }
		elseif($maxWidth==0){ //固定高度
            $maxWidth = intval($srcWidth*$maxHeight/$srcHeight);
            $fixed = false;
        }
        elseif($maxHeight==0){ //固定宽度
            $maxHeight = intval($srcWidth*$maxWidth/$srcWidth);
            $fixed = false;
        }

		$type = empty($type)?$info['type']:$type;
		$type = strtolower($type);
		$interlace  =  $interlace? 1:0;
		unset($info);
		$scale = min($maxWidth/$srcWidth, $maxHeight/$srcHeight); // 计算缩放比例
		if($scale>=1) { // 超过原图大小不再缩略
			$width   =  $srcWidth;
			$height  =  $srcHeight;
			return 0;//超出原图大小不缩略直接返回
		} else { // 缩略图尺寸
			$width  = (int)($srcWidth*$scale);
			$height = (int)($srcHeight*$scale);
		}
		//固定图像大小值处理
		$dst_x = 0; $dst_y = 0;//目标xy坐标
		if($fixed){
			$w = $maxWidth; $h = $maxHeight;
			$dst_x = ($maxWidth-$width)/2;
			$dst_y = ($maxHeight-$height)/2;
		}
		else{$w = $width; $h = $height;}

		//载入原图
		if($type=='png') $srcImg = imagecreatefrompng($image);
		elseif($type=='gif') $srcImg = imagecreatefromgif($image);
		else $srcImg = imagecreatefromjpeg($image);
		//创建缩略图
		$thumbImg = imagecreatetruecolor($w, $h);
		$background_color = imagecolorallocate($thumbImg,  255,255,255);

		//透明及背景处理
		if($type=='png'){ 
			imagealphablending($thumbImg,false);//关闭图像的混色模式, 可用于保持透明;
			imagefill($thumbImg, 0, 0, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127)); //填充透明
			imagesavealpha($thumbImg, true); //保持完整的 alpha 通道信息
		}elseif($type=='gif'){
			$trnprt_indx = imagecolortransparent($srcImg); //透明色的标识符
			if($trnprt_indx>=0){
				$trnprt_color = imagecolorsforindex($srcImg, $trnprt_indx);
				$trnprt_indx = imagecolorallocate($thumbImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
				imagefill($thumbImg, 0, 0, $trnprt_indx);
				imagecolortransparent($thumbImg, $trnprt_indx);
			}else imagefill($thumbImg, 0, 0, $background_color);
		}else{
			imagefill($thumbImg, 0, 0, $background_color);
		}
		// 复制图片
		if(function_exists("imagecopyresampled"))
			imagecopyresampled($thumbImg, $srcImg, $dst_x, $dst_y, 0, 0, $width, $height, $srcWidth,$srcHeight);
		else
			imagecopyresized($thumbImg, $srcImg, $dst_x, $dst_y, 0, 0, $width, $height, $srcWidth,$srcHeight);
		// 生成图片
		if($type=='jpeg' || $type=='jpg'){
			imageinterlace($thumbImg, $interlace); //图形设置隔行扫描
			imagejpeg($thumbImg, $thumbName, 80);
		} elseif($type=='png'){
			imagepng($thumbImg, $thumbName);
		} elseif($type=='gif') {
			imagegif($thumbImg, $thumbName);
		} else {
			imagejpeg($thumbImg, $thumbName);
		}

		imagedestroy($thumbImg); imagedestroy($srcImg);
		return $thumbName;
    }

    /**
     * 生成图像验证码 中文需字体支持
     * @param int $length  位数
     * @param int $mode  类型
     * @param int $width  宽度
     * @param int $height  高度
     * @param string|null $randVal  验证码
     * @param string $verifyName  验证标识
     * @return void
     */
    static function buildImageVerify($length=4,$mode=1,$width=48,$height=22,$randVal=NULL,$verifyName='verify')
    {
        if(!isset($_SESSION)) session_start();//如果没有开启，session，则开启session

		$randVal =empty($randVal)? String::rand_string($length, $mode):$randVal;

        $_SESSION[$verifyName]= $randVal;
        $width = ($length*10+10)>$width?$length*10+10:$width;
        $im = imagecreate($width,$height);
  
        $r = array(225,255,255,223);
        $g = array(225,236,237,255);
        $b = array(225,236,166,125);
        $key = mt_rand(0,3);

        $backColor = imagecolorallocate($im, $r[$key],$g[$key],$b[$key]);    //背景色（随机）
		$borderColor = imagecolorallocate($im, 100, 100, 100);                    //边框色
        $pointColor = imagecolorallocate($im,mt_rand(0,255),mt_rand(0,255),mt_rand(0,255));                 //点颜色

        @imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $backColor);
        @imagerectangle($im, 0, 0, $width-1, $height-1, $borderColor);
        $stringColor = imagecolorallocate($im,mt_rand(0,200),mt_rand(0,120),mt_rand(0,120));
		// 干扰
		for($i=0;$i<10;$i++){
			$fontcolor=imagecolorallocate($im,mt_rand(0,255),mt_rand(0,255),mt_rand(0,255));
			imagearc($im,mt_rand(-10,$width),mt_rand(-10,$height),mt_rand(30,300),mt_rand(20,200),55,44,$fontcolor);
		}
		for($i=0;$i<25;$i++){
			$fontcolor=imagecolorallocate($im,mt_rand(0,255),mt_rand(0,255),mt_rand(0,255));
			imagesetpixel($im,mt_rand(0,$width),mt_rand(0,$height),$pointColor);
		}
		if($mode==4){
			$fontface = 'simhei.ttf';
			if (!is_file($fontface)) {
				$fontface = MY_PATH.'/inc/'.$fontface;
			}
			for ($i = 0; $i < $length; $i++) {
				$fontcolor = imagecolorallocate($im, mt_rand(0, 120), mt_rand(0, 120), mt_rand(0, 120)); //这样保证随机出来的颜色较深。
				$codex = String::msubstr($randVal, $i, 1);
				imagettftext($im, mt_rand(16, 18), mt_rand(-40, 45), 30 * $i + 10, mt_rand(20, 25), $fontcolor, $fontface, $codex);
			}
		}else{
			for($i=0;$i<$length;$i++) {
				imagestring($im,5,$i*10+5,mt_rand(1,8),$randVal{$i}, $stringColor);
			}
		}

        Image::output($im,'png');
    }
    /**
     * 生成UPC-A条形码
     * @param string $code 11位数字
     * @param string $type 图像格式
     * @param int $lw  单元宽度
     * @param int $hi   条码高度
     * @return void
     */
    static function UPCA($code, $type='png', $lw=2, $hi=100) {
        static $Lencode = array('0001101', '0011001', '0010011', '0111101', '0100011',
    '0110001', '0101111', '0111011', '0110111', '0001011');
        static $Rencode = array('1110010', '1100110', '1101100', '1000010', '1011100',
    '1001110', '1010000', '1000100', '1001000', '1110100');
        $ends = '101';
        $center = '01010';
        /* UPC-A Must be 11 digits, we compute the checksum. */
        if (strlen($code) != 11) {
            die("UPC-A Must be 11 digits.");
        }
        /* Compute the EAN-13 Checksum digit */
        $ncode = '0' . $code;
        $even = 0;
        $odd = 0;
        for ($x = 0; $x < 12; $x++) {
            if ($x % 2) {
                $odd += $ncode[$x];
            } else {
                $even += $ncode[$x];
            }
        }
        $code.= ( 10 - (($odd * 3 + $even) % 10)) % 10;
        /* Create the bar encoding using a binary string */
        $bars = $ends;
        $bars.=$Lencode[$code[0]];
        for ($x = 1; $x < 6; $x++) {
            $bars.=$Lencode[$code[$x]];
        }
        $bars.=$center;
        for ($x = 6; $x < 12; $x++) {
            $bars.=$Rencode[$code[$x]];
        }
        $bars.=$ends;
        /* Generate the Barcode Image */
        if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor($lw * 95 + 30, $hi + 30);
        } else {
            $im = imagecreate($lw * 95 + 30, $hi + 30);
        }
        $fg = ImageColorAllocate($im, 0, 0, 0);
        $bg = ImageColorAllocate($im, 255, 255, 255);
        ImageFilledRectangle($im, 0, 0, $lw * 95 + 30, $hi + 30, $bg);
        $shift = 10;
        for ($x = 0; $x < strlen($bars); $x++) {
            if (($x < 10) || ($x >= 45 && $x < 50) || ($x >= 85)) {
                $sh = 10;
            } else {
                $sh = 0;
            }
            if ($bars[$x] == '1') {
                $color = $fg;
            } else {
                $color = $bg;
            }
            ImageFilledRectangle($im, ($x * $lw) + 15, 5, ($x + 1) * $lw + 14, $hi + 5 + $sh, $color);
        }
        /* Add the Human Readable Label */
        ImageString($im, 4, 5, $hi - 5, $code[0], $fg);
        for ($x = 0; $x < 5; $x++) {
            ImageString($im, 5, $lw * (13 + $x * 6) + 15, $hi + 5, $code[$x + 1], $fg);
            ImageString($im, 5, $lw * (53 + $x * 6) + 15, $hi + 5, $code[$x + 6], $fg);
        }
        ImageString($im, 4, $lw * 95 + 17, $hi - 5, $code[11], $fg);
        /* Output the Header and Content. */
        Image::output($im, $type);
    }
	
    static function output($im,$type='png',$filename='')
    {
        header("Content-type: image/".$type);
        $ImageFun='image'.$type;
		if(empty($filename)) {
	        $ImageFun($im);
		}else{
	        $ImageFun($im,$filename);
		}
        imagedestroy($im);
		exit;
    }
	
     /**
     * 图片水印
     * @$image  原图
     * @$water 水印图片
     * @$$waterPos 水印位置(0-9) 0为随机，其他代表上中下9个部分位置
      *@return void
     */
    /**
     * 图片水印
     * @param string $image 原图
     * @param string $water 水印图片
     * @param int $waterPos   水印位置(0-9) 0为随机，其他代表上中下9个部分位置
     * @return void
     */
    static function water($image, $water, $waterPos =9)
    {
	    //检查图片是否存在
        if (!is_file($image) || !is_file($water))
            return false;
	   //读取原图像文件
        $imageInfo = self::getImageInfo($image);
        $image_w = $imageInfo['width']; //取得水印图片的宽
        $image_h = $imageInfo['height']; //取得水印图片的高
        $imageFun = "imagecreatefrom" . $imageInfo['type'];
        $image_im = $imageFun($image);
        
        //读取水印文件
        $waterInfo = self::getImageInfo($water);
        $w = $water_w = $waterInfo['width']; //取得水印图片的宽
        $h = $water_h = $waterInfo['height']; //取得水印图片的高
        $waterFun = "imagecreatefrom" . $waterInfo['type'];
        $water_im = $waterFun($water);

        switch ($waterPos) {
            case 0: //随机
                $posX = rand(0, ($image_w - $w));
                $posY = rand(0, ($image_h - $h));
                break;
            case 1: //1为顶端居左
                $posX = 0;
                $posY = 0;
                break;
            case 2: //2为顶端居中
                $posX = ($image_w - $w) / 2;
                $posY = 0;
                break;
            case 3: //3为顶端居右
                $posX = $image_w - $w;
                $posY = 0;
                break;
            case 4: //4为中部居左
                $posX = 0;
                $posY = ($image_h - $h) / 2;
                break;
            case 5: //5为中部居中
                $posX = ($image_w - $w) / 2;
                $posY = ($image_h - $h) / 2;
                break;
            case 6: //6为中部居右
                $posX = $image_w - $w;
                $posY = ($image_h - $h) / 2;
                break;
            case 7: //7为底端居左
                $posX = 0;
                $posY = $image_h - $h;
                break;
            case 8: //8为底端居中
                $posX = ($image_w - $w) / 2;
                $posY = $image_h - $h;
                break;
            case 9: //9为底端居右
                $posX = $image_w - $w;
                $posY = $image_h - $h;
                break;
            default: //随机
                $posX = rand(0, ($image_w - $w));
                $posY = rand(0, ($image_h - $h));
                break;
        }

        //设定图像的混色模式
        
        imagealphablending($image_im, true);

        imagecopy($image_im, $water_im, $posX, $posY, 0, 0, $water_w, $water_h); //拷贝水印到目标文件

        //生成水印后的图片
        $bulitImg = "image" . $imageInfo['type'];
        $bulitImg($image_im, $image);
        //释放内存
        $waterInfo = $imageInfo = null;
        imagedestroy($image_im);
    }

}