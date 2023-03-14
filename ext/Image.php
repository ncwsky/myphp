<?php

//GD库 - 生成图像缩略图和生成验证码
class Image
{
    public static $raw_string = false;
    /**
     * 取得图像信息
     * @param string $img 图像文件名
     * @param bool $raw_string 是否原始内容
     * @return bool|array
     */
    public static function getImageInfo($img, $raw_string=null)
    {
        if ($raw_string === null) $raw_string = self::$raw_string;
        $imageInfo = $raw_string ? getimagesizefromstring($img) : getimagesize($img);
        if ($imageInfo) {
            $imageSize = $raw_string ? strlen($img) : filesize($img);
            if (!$imageSize) return false;
            $imageType = strtolower(image_type_to_extension($imageInfo[2], false));
            return array(
                "width" => $imageInfo[0],
                "height" => $imageInfo[1],
                "type" => $imageType,
                "size" => $imageSize,
                "mime" => $imageInfo['mime']
            );
        }
        return false;
    }

    /**
     * 生成缩略图
     * @param string $image 原图路径
     * @param string $thumbName 缩略图生成路径
     * @param int $maxWidth 宽度
     * @param int $maxHeight 高度
     * @param boolean $fixed 固定缩略图大小
     * @param boolean $interlace 启用隔行扫描
     * @param int $quality jpg质量1-100
     * @return false|int|string false失败 0原图未缩放 string原图类型
     */
    public static function thumb($image, $thumbName, $maxWidth = 200, $maxHeight = 50, $fixed = false, $interlace = false, $quality = 80)
    {
        $info = self::getImageInfo($image); // 获取原图信息
        if ($info === false) return false;

        $srcWidth = $info['width'];
        $srcHeight = $info['height'];
        if ($maxWidth == 0 && $maxHeight == 0) {
            return 0;//直接返回
        } elseif ($maxWidth == 0) { //固定高度
            $maxWidth = intval($srcWidth * $maxHeight / $srcHeight);
            $fixed = false;
        } elseif ($maxHeight == 0) { //固定宽度
            $maxHeight = intval($srcWidth * $maxWidth / $srcWidth);
            $fixed = false;
        }

        $type = $info['type'];
        unset($info);
        $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight); // 计算缩放比例
        if ($scale >= 1) { //超出原图大小不缩略直接返回
            return 0;
        } else { // 缩略图尺寸
            $width = (int)($srcWidth * $scale);
            $height = (int)($srcHeight * $scale);
        }
        //固定图像大小值处理
        $dst_x = 0;
        $dst_y = 0;//目标xy坐标
        if ($fixed) {
            $w = $maxWidth;
            $h = $maxHeight;
            $dst_x = ($maxWidth - $width) / 2;
            $dst_y = ($maxHeight - $height) / 2;
        } else {
            $w = $width;
            $h = $height;
        }

        //载入原图
        if (self::$raw_string) {
            self::$raw_string = false; //Reset
            $srcImg = imagecreatefromstring($image); //从字符串的图像流中新建图像
        } else {
            $imagecreatefrom = 'imagecreatefrom' . $type;
            $srcImg = function_exists($imagecreatefrom) ? $imagecreatefrom($image) : imagecreatefromjpeg($image);
        }

        //创建缩略图
        $thumbImg = imagecreatetruecolor($w, $h);
        $background_color = imagecolorallocate($thumbImg, 255, 255, 255);

        //透明及背景处理
        if ($type == 'png') {
            //imagealphablending($thumbImg, false);//关闭图像的混色模式, 可用于保持透明;
            imagefill($thumbImg, 0, 0, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127)); //填充透明
            imagesavealpha($thumbImg, true); //保持完整的 alpha 通道信息
        } elseif ($type == 'gif') {
            $trnprt_indx = imagecolortransparent($srcImg); //透明色的标识符
            if ($trnprt_indx !== false && $trnprt_indx >= 0) {
                if ($trnprt_indx > 0) $trnprt_indx--;
                $trnprt_color = imagecolorsforindex($srcImg, $trnprt_indx);
                $trnprt_indx = imagecolorallocate($thumbImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                imagefill($thumbImg, 0, 0, $trnprt_indx);
                imagecolortransparent($thumbImg, $trnprt_indx);
            } else imagefill($thumbImg, 0, 0, $background_color);
        } else {
            imagefill($thumbImg, 0, 0, $background_color);
        }
        // 复制图片
        if (function_exists("imagecopyresampled"))
            imagecopyresampled($thumbImg, $srcImg, $dst_x, $dst_y, 0, 0, $width, $height, $srcWidth, $srcHeight);
        else
            imagecopyresized($thumbImg, $srcImg, $dst_x, $dst_y, 0, 0, $width, $height, $srcWidth, $srcHeight);

        $interlace && imageinterlace($thumbImg, 1); //图形设置隔行扫描
        // 生成图片
        $imageFun = 'image' . $type;
        if ($type == 'jpeg' || $type == 'jpg') {
            imagejpeg($thumbImg, $thumbName, $quality);
        } else {
            if (function_exists($imageFun)) {
                $imageFun($thumbImg, $thumbName);
            } else {
                imagejpeg($thumbImg, $thumbName); //默认质量为75
            }
        }

        imagedestroy($thumbImg);
        imagedestroy($srcImg);
        return $type;
    }

    /**
     * 图片水印
     * @param string $image 原图
     * @param string $water 水印图片|['text'=>'文字','font'=>'字体文件','size'=>14] //size默认14
     * @param string $newImage 新图片地址 '':在原图上合并
     * @param int $waterPos 水印位置(0-9) 0为随机，其他代表上中下9个部分位置
     * @param int $padding 以水印图片的几分之x计算边距
     * @param int $alpha 透明度 0:完全透明 ~ 100:完全不透明
     * @return bool
     */
    public static function water($image, $water, $newImage='', $waterPos = 9, $padding=5, $alpha=75)
    {
        $isText = false;
        //水印
        if (is_array($water)) {
            $text = isset($water['text']) ? $water['text'] : '';
            $font = isset($water['font']) ? $water['font'] : '';
            if ($text === '' || !$font) return false;
            $size = isset($water['size']) ? max(12, (int)$water['size']) : 18;
            $angle = isset($water['angle']) ? max(180, (int)$water['angle']) : mt_rand(-180,180);
            $color = isset($water['color']) ? explode(',', $water['color']) : [];

            $color[0] = isset($color[0]) ? min(255, (int)$color[0]) : mt_rand(0, 166); //r
            $color[1] = isset($color[1]) ? min(255, (int)$color[1]) : mt_rand(0, 166); //g
            $color[2] = isset($color[2]) ? min(255, (int)$color[2]) : mt_rand(0, 166); //b
            //计算长宽
            $box = imagettfbbox($size, $angle, $font, $text);
            if (!$box) return false;
            $min_x = min(array($box[0], $box[2], $box[4], $box[6]));
            $max_x = max(array($box[0], $box[2], $box[4], $box[6]));
            $min_y = min(array($box[1], $box[3], $box[5], $box[7]));
            $max_y = max(array($box[1], $box[3], $box[5], $box[7]));
            $w = ($max_x - $min_x);
            $h = ($max_y - $min_y);
            $left = abs($min_x);
            $top = abs($min_y);
            //var_dump($w, $h, $left, $top);
            $water_im = imagecreatetruecolor($w, $h);
            $txt_color = imagecolorallocate($water_im, $color[0], $color[1], $color[2]);

            //imagealphablending($water_im, false);//关闭图像的混色模式, 可用于保持透明;
            imagefill($water_im, 0, 0, imagecolorallocatealpha($water_im, 0, 0, 0, 127)); //填充透明
            //imagesavealpha($water_im, true);

            imagefttext($water_im, $size, $angle, $left, $top, $txt_color, $font, $text);
            //imagefttext($water_im, $size, $angle, 0, ceil($h/2)+8, $txt_color, $font, $text);
            $isText = true;
        } else {
            if (!file_exists($water)) return false;
            $waterInfo = self::getImageInfo($water);
            if (!$waterInfo) return false;

            $w = $waterInfo['width'];
            $h = $waterInfo['height'];
            $imagecreatefrom = 'imagecreatefrom' . $waterInfo['type'];
            $water_im = function_exists($imagecreatefrom) ? $imagecreatefrom($water) : imagecreatefromjpeg($water);
            unset($waterInfo);
        }
        if (!$water_im) return false;

        //检查图片是否存在
        if (!file_exists($image)) return false;
        //原图像
        $imageInfo = self::getImageInfo($image);
        if (!$imageInfo) return false;
        $type = $imageInfo['type'];
        $image_w = $imageInfo['width'];
        $image_h = $imageInfo['height'];
        $imagecreatefrom = 'imagecreatefrom' . $type;
        $image_im = function_exists($imagecreatefrom) ? $imagecreatefrom($image) : imagecreatefromjpeg($image);
        unset($imageInfo);

        $offset_w = $padding < 1 ? 0 : ceil($w / $padding);
        $offset_h = $padding < 1 ? 0 : ceil($h / $padding);
        switch ($waterPos) {
            case 1: //1为顶端居左
                $posX = 0 + $offset_w;
                $posY = 0 + $offset_h;
                break;
            case 2: //2为顶端居中
                $posX = ($image_w - $w) / 2;
                $posY = 0 + $offset_h;
                break;
            case 3: //3为顶端居右
                $posX = $image_w - $w - $offset_w;;
                $posY = 0 + $offset_h;
                break;
            case 4: //4为中部居左
                $posX = 0 + $offset_w;;
                $posY = ($image_h - $h) / 2;
                break;
            case 5: //5为中部居中
                $posX = ($image_w - $w) / 2;
                $posY = ($image_h - $h) / 2;
                break;
            case 6: //6为中部居右
                $posX = $image_w - $w - $offset_w;
                $posY = ($image_h - $h) / 2;
                break;
            case 7: //7为底端居左
                $posX = 0 + $offset_w;
                $posY = $image_h - $h - $offset_h;
                break;
            case 8: //8为底端居中
                $posX = ($image_w - $w) / 2;
                $posY = $image_h - $h - $offset_h;
                break;
            case 9: //9为底端居右
                $posX = $image_w - $w - $offset_w;
                $posY = $image_h - $h - $offset_h;
                break;
            default: //随机
                $posX = mt_rand(0, ($image_w - $w));
                $posY = mt_rand(0, ($image_h - $h));
                break;
        }

        if ($type == 'png') {
            //imagealphablending($image_im, false);
            imagesavealpha($image_im, true); //保持完整的 alpha 通道信息
        }
        //设定图像的混色模式
        //imagealphablending($image_im, true);
        if($isText){
            imagecopy($image_im, $water_im, $posX, $posY, 0, 0, $w, $h); //合并图片
        }else{
            imagecopymerge($image_im, $water_im, $posX, $posY, 0, 0, $w, $h, $alpha);
        }

        //生成水印后的图片
        $imageFun = 'image' . $type;
        if($newImage!=='') $image = $newImage;
        $result = function_exists($imageFun) ? $imageFun($image_im, $image) : imagejpeg($image_im, $image);
        imagedestroy($image_im);
        return $result;
    }

    /**
     * 生成验证码
     * @param int $w
     * @param int $h
     * @param int $fontsize
     * @param int $len
     * @param int $type  0数字大小字母 1数字 2大小字母 3小字母 4大字母 5汉字
     * @param string $codename
     * @param string $code
     * @return false|string|null
     */
    public static function code($w=80, $h=36, $fontsize=18, $len = 4, $type=0, $codename = 'code', &$code='') {
        //生成随机字符
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ0123456789';//0123456789
        $font = __DIR__ . '/../inc/ggbi.ttf';
        if ($type > 0) {
            switch ($type) {
                case 1:
                    $chars = '0123456789';
                    break;
                case 2:
                    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ';
                    break;
                case 3:
                    $chars = 'abcdefghijkmnpqrstuvwxyz';
                    break;
                case 4:
                    $chars = 'ABCDEFGHIJKLMNPRSTUVWXYZ';
                    break;
                case 5:
                    $chars = "们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借";
                    $font = __DIR__ . '/../inc/fzxbsjw.ttf';
                    break;
            }
        }

        $rndChars = [];
        if(empty($code)){
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $char = $type == 5 ? mb_substr($chars, mt_rand(0, mb_strlen($chars) - 1), 1) : substr($chars, mt_rand(0, strlen($chars) - 1), 1);
                $code .= $char;
                $rndChars[] = $char;
            }
        }else{
            preg_match_all('/./u', $code, $matches);
            $rndChars = $matches[0];
        }

        if(!isset($_SESSION)) session_start();//如果没有开启，session，则开启session
        $_SESSION[$codename] = strtolower($code);//小写

        //imagecreate($w, $h) 返回一个白色图像的标识符
        $img = imagecreatetruecolor($w, $h);//创建指定wh的黑色图像并返回一个图像标识符

        $r = array(225,255,223);
        $g = array(225,236,255);
        $b = array(225,236,125);
        $key = mt_rand(0,2);

        $bgColor = imagecolorallocate($img, $r[$key],$g[$key],$b[$key]);   //背景色（随机）
        //$bgColor = imagecolorallocate($img, 237, 247, 255);   //背景色（随机）
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
        }
        /*
        for($i=0;$i<10;$i++){//画一个单一像素
            $fontcolor=imagecolorallocate($img,mt_rand(0,255),mt_rand(0,255),mt_rand(0,255));
            imagesetpixel($img,mt_rand(0,$w),mt_rand(0,$h),$fontcolor);
        }*/
        for ($i = 0; $i < $len; $i++) {
            //imagechar($img,5,$i*10+5,mt_rand(1,8), $rndChars[$i], $txt_color);
            $x = $type == 5 ? $i * ($fontsize + 4) : $i * $fontsize + 4;
            imagefttext($img, $fontsize, mt_rand(-30, 30), $x, $h / 2 + 8, $txt_color, $font, $rndChars[$i]);
        }

        //输出图像
        return self::output($img);
    }


    public static $outputString = false;

    /**
     * @param resource $im
     * @param string $type
     * @param string $filename
     * @return false|string|null
     */
    public static function output($im, $type = 'png', $filename = '')
    {
        $imageFun = 'image' . $type;
        $result = null;
        if ($filename === '') {
            if (self::$outputString) {
                ob_start();
                $imageFun($im);
                $result = ob_get_clean();
            } else {
                header("Pragma: public");
                header("Expires: 0");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Content-Transfer-Encoding: binary");
                header("Content-type: image/" . $type);
                $imageFun($im);
            }
        } else {
            $result = $imageFun($im, $filename);
        }
        imagedestroy($im);
        return $result;
    }
}