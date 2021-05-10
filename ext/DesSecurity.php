<?php

/**
 * openssl 实现的 DES 加密类，支持各种 PHP 版本
 * java DES/CBC/PKCS5Padding 源码中 key 实际只取了前8个字节
 */
class DesSecurity
{
    /**
     * @var string $method 加解密方法，可通过 openssl_get_cipher_methods() 获得
     */
    protected $method;

    /**
     * @var string $key 加解密的密钥
     */
    protected $key;

    /**
     * @var string $output 输出格式 无、base64、hex
     */
    protected $output;

    /**
     * @var string $iv 加解密的向量
     */
    protected $iv;

    /**
     * @var string $options
     */
    protected $options;

    // output 的类型
    const OUTPUT_BASE64 = 'base64';
    const OUTPUT_HEX = 'hex';

    /**
     * DES constructor.
     * @param string $key
     * @param string $method
     *      ECB DES-ECB、DES-EDE3 （为 ECB 模式时，$iv 为空即可）
     *      CBC DES-CBC、DES-EDE3-CBC、DESX-CBC
     *      CFB DES-CFB8、DES-EDE3-CFB8
     *      CTR
     *      OFB
     * @param string $iv
     * @param int $options  OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
     * @param string $output
     *      base64、hex
     */
    public function __construct($key, $method = 'DES-ECB', $iv = '', $options = OPENSSL_NO_PADDING, $output = self::OUTPUT_BASE64)
    {
        $this->key = $key;
        $this->method = $method;
        $this->output = $output;
        $this->iv = $iv;
        $this->options = $options;
    }

    /**
     * 加密
     *
     * @param $str
     * @return string
     */
    public function encrypt($str)
    {
        $str = $this->pkcsPadding($str, 8);
        $sign = openssl_encrypt($str, $this->method, $this->key, $this->options, $this->iv);

        if ($this->output == self::OUTPUT_BASE64) {
            $sign = base64_encode($sign);
        } else if ($this->output == self::OUTPUT_HEX) {
            $sign = bin2hex($sign);
        }
        return $sign;
    }

    /**
     * 解密
     *
     * @param $encrypted
     * @return string
     */
    public function decrypt($encrypted)
    {
        if ($this->output == self::OUTPUT_BASE64) {
            $encrypted = base64_decode($encrypted);
        } else if ($this->output == self::OUTPUT_HEX) {
            $encrypted = hex2bin($encrypted);
        }

        $sign = openssl_decrypt($encrypted, $this->method, $this->key, $this->options, $this->iv);
        $sign = $this->unPkcsPadding($sign);
        $sign = rtrim($sign);
        return $sign;
    }

    /**
     * 填充 按64bit一组填充数据
     *
     * @param $str
     * @param $blocksize
     * @return string
     */
    private function pkcsPadding($str, $blocksize)
    {
        $pad = $blocksize - (strlen($str) % $blocksize);
        return $str . str_repeat(chr($pad), $pad);
    }

    /**
     * 去填充
     *
     * @param $str
     * @return string
     */
    private function unPkcsPadding($str)
    {
        //取出最后一个字符串 {15}   ord返回字符的 ASCII 码值
        $pad = ord($str{strlen($str) - 1});//5
        //判断$pad的值是否大于本身字符串
        if ($pad > strlen($str)) {
            //如果大于  则多余
            return false;
        }
        //计算ASCII 码值中全部字符都存在于$str字符集合中的第一段子串的长度是否等于取出的$pad
        if (strspn($str, chr($pad), strlen($str) - $pad) != $pad) {
            //如果不相等  则缺失
            return false;
        }
        //返回字符串的子串
        return substr($str, 0, -1 * $pad);
    }
}

/*
$key = 'key123456';
$iv = 'iv123456';

// DES CBC 加解密
$des = new DesSecurity($key, 'DES-CBC', $iv, DES::OUTPUT_BASE64);
echo $base64Sign = $des->encrypt('Hello DES CBC');
echo "\n";
echo $des->decrypt($base64Sign);
echo "\n";

// DES ECB 加解密
$des = new DES($key, 'DES-ECB', '', DES::OUTPUT_HEX);
echo $base64Sign = $des->encrypt('Hello DES ECB');
echo "\n";
echo $des->decrypt($base64Sign);*/