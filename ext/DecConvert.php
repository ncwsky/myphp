<?php

/**
 * Class DecConvert 十进制转换 2-62
 */
class DecConvert
{
    private $dict = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    /*private $dict = [
        '0','1','2','3','4','5','6','7','8','9',
        'a','b','c','d','e','f','g','h','i','j',
        'k','l','m','n','o','p','q','r','s','t',
        'u','v','w','x','y','z','A','B','C','D',
        'E','F','G','H','I','J','K','L','M','N',
        'O','P','Q','R','S','T','U','V','W','X',
        'Y','Z',
    ];*/
    private $deDict = [
        '0'=>0,'1'=>1,'2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,
        'a'=>10,'b'=>11,'c'=>12,'d'=>13,'e'=>14,'f'=>15,'g'=>16,'h'=>17,'i'=>18,'j'=>19,
        'k'=>20,'l'=>21,'m'=>22,'n'=>23,'o'=>24,'p'=>25,'q'=>26,'r'=>27,'s'=>28,'t'=>29,
        'u'=>30,'v'=>31,'w'=>32,'x'=>33,'y'=>34,'z'=>35,'A'=>36,'B'=>37,'C'=>38,'D'=>39,
        'E'=>40,'F'=>41,'G'=>42,'H'=>43,'I'=>44,'J'=>45,'K'=>46,'L'=>47,'M'=>48,'N'=>49,
        'O'=>50,'P'=>51,'Q'=>52,'R'=>53,'S'=>54,'T'=>55,'U'=>56,'V'=>57,'W'=>58,'X'=>59,
        'Y'=>60,'Z'=>61
    ];
    private $custom = false;
    private static $instance = null;
    #自定义映射表
    public function setDict($dict){
        if(strlen($dict)!=62) {
            throw new \Exception('设置的字符映射表长度不符');
        }

        $deDict = [];
        for($i=0;$i<62;$i++){
            $deDict[$dict[$i]] = $i;
        }
        if(count($deDict)!=62){
            throw new \Exception('设置的字符映射表存在重复字符');
        }
        $this->dict = $dict;
        $this->deDict = $deDict;
        $this->custom = true;
    }
    /** 十进制数转换成其它(2-62)进制
     * @param int|string $number
     * @param int $toBase
     * @return string
     * @throws \Exception
     */
    public function to($number, $toBase) {
        /*if ($toBase > 62 || $toBase < 2) {
            throw new \Exception('Invalid to base('.$toBase.')');
        }elseif($toBase==10){
            return $number;
        }elseif(!$this->custom && $toBase<=36 && $number<=0xa7c5ac471b5f){
            return base_convert($number, 10, $toBase);
        }*/
        $ret = '';
        do {
            #$ret = $dict[$number%$toBase] . $ret;
            #$number = (int)($number/$toBase);
            $ret = $this->dict[bcmod($number, $toBase)] . $ret;
            $number = bcdiv($number, $toBase);
        } while ($number > 0);
        return $ret;
    }

    /** 其它(2-62)进制数转换成十进制数
     * @param string $number
     * @param int $fromBase
     * @return int|string
     * @throws \Exception
     */
    public function from($number, $fromBase) {
        /*if ($fromBase > 62 || $fromBase < 2) {
            throw new \Exception('Invalid from base('.$fromBase.')');
        }elseif($fromBase==10){
            return $number;
        }elseif(!$this->custom && $fromBase<=36 && $number<=0xa7c5ac471b5f){
            return base_convert($number, $fromBase, 10); #最大支持 184467440737119
        }*/
        $number = (string)$number;
        $len = strlen($number);
        $dec = 0;
        for($i = 0; $i < $len; $i++) {
            #$pos = strpos($dict, $number[$i]);
            $pos = $this->deDict[$number[$i]];
            if ($pos >= $fromBase) {
                continue; // 如果出现非法字符，会忽略掉。比如16进制中出现w、x、y、z等
            }
            #超大数会丢失精度 使用精度函数
            $dec = bcadd(bcmul(bcpow($fromBase, $len - $i - 1), $pos), $dec);
            #$dec += pow($fromBase, $len - $i - 1)*$pos;
        }
        return $dec;
    }

    public static function instance(){
        if(!self::$instance){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function en24($number){
        return self::instance()->to($number, 24);
    }
    public static function de24($number){
        return self::instance()->from($number, 24);
    }
    public static function en26($number){
        return self::instance()->to($number, 26);
    }
    public static function de26($number){
        return self::instance()->from($number, 26);
    }
    public static function en32($number){
        return self::instance()->to($number, 32);
    }
    public static function de32($number){
        return self::instance()->from($number, 32);
    }
    public static function en36($number){
        return self::instance()->to($number, 36);
    }
    public static function de36($number){
        return self::instance()->from($number, 36);
    }
    public static function en58($number){
        return self::instance()->to($number, 58);
    }
    public static function de58($number){
        return self::instance()->from($number, 58);
    }
    public static function en62($number){
        return self::instance()->to($number, 62);
    }
    public static function de62($number){
        return self::instance()->from($number, 62);
    }
}

/*

$number = '18446744073709551615';
#$number = 184467440737119;

echo DecConvert::en26($number),PHP_EOL;
echo DecConvert::en32($number),PHP_EOL;
echo DecConvert::en36($number),PHP_EOL;
echo DecConvert::en62($number),PHP_EOL;

echo DecConvert::de26(DecConvert::en26($number)),PHP_EOL;
echo DecConvert::de32(DecConvert::en32($number)),PHP_EOL;
echo DecConvert::de36(DecConvert::en36($number)),PHP_EOL;
echo DecConvert::de62(DecConvert::en62($number)),PHP_EOL;

*/