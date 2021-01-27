<?php

/**
 * Class Endian 字节序 大小端
 */
class Endian
{
    /** 入大端
     * @param int $val
     * @param int $byte
     * @return string
     */
    public static function enBig($val, $byte = 1)
    {
        $ret = chr($val & 0xff);
        if ($byte == 1) return $ret;

        for ($i = 1; $i < $byte; $i++) {
            $ret = chr($val >> $i * 8) . $ret;
        }
        return $ret;
    }

    /** 出大端
     * @param string $val
     * @param int|null $len
     * @return int
     */
    public static function deBig($val, $len = null)
    {
        if ($len === null) $len = strlen($val);
        if ($len == 1) return ord($val) & 0xff; #$val[0]

        $j = $len - 1;
        $ret = 0x00;
        for ($i = 0; $i < $len; $i++) {
            $ret |= (ord($val[$i]) & 0xff) << $j * 8;
            $j--;
        }
        return $ret;
    }

    /** 入小端  enLittle(0xffffff, 3)==substr(pack('V', 0xffffff),0,-1): true
     * @param int $val
     * @param int $byte
     * @return string
     */
    public static function enLittle($val, $byte = 1)
    {
        $ret = chr($val & 0xff);
        if ($byte == 1) return $ret;

        for ($i = 1; $i < $byte; $i++) {
            $ret .= chr($val >> $i * 8);
        }
        return $ret;
    }

    /** 出小端
     * @param string $val
     * @param int|null $len
     * @return int
     */
    public static function deLittle($val, $len = null)
    {
        if ($len === null) $len = strlen($val);
        $ret = ord($val) & 0xff; #$val[0]
        if ($len == 1) return $ret;

        for ($i = 1; $i < $len; $i++) {
            $ret |= (ord($val[$i]) & 0xff) << $i * 8;
        }
        return $ret;
    }
}

/*

$a = 0xffafef0a;

var_dump(Endian::deBig(Endian::enBig(0xffafef0a, 8), 8));
var_dump($a, Endian::enBig($a, 8), pack('J', $a), unpack('J', Endian::enBig($a, 8)));
var_dump($a, Endian::enLittle($a, 8), pack('P', $a), unpack('P', Endian::enLittle($a, 8)));

*/