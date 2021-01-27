<?php
class BitMap{
    private $n = 0; // # of entries
    private $m; // # of bits in array
    public $bitData;
    private $bitSize;
    const NULL_BYTE = "\x00"; #空字符
    public function __construct($m, $data=null, $n=null){
        $this->m = $m;
        $this->bitSize = ($m>>3)+1;#ceil($m / 8); # $m>>3
        # https://www.php.net/manual/zh/language.types.string.php
        $this->bitData = $data ?: str_repeat(self::NULL_BYTE, $this->bitSize); #这里是复制会使用双倍内存
        if($data){ #纯遍历统计数量
            if($n!==null){
                $this->n = $n;
            }else{
                for ($i=0;$i<$this->bitSize;$i++) {
                    $byte = $this->bitData[$i];
                    if ($byte == 0) continue;
                    while ($byte) {
                        if ($byte & 1) $this->n++;
                        $byte = $byte >> 1;
                    }
                }
            }
        }
    }
    /**
     * 返回bit数据
     *
     * @return string
     */
    public function __toString() {
        return pack('N*', $this->m, $this->n) . $this->bitData;
    }

    public static function load($data) {
        $nRet = unpack('N*', substr($data, 0, 8));
        if(!$nRet) throw new Exception('解析数据[m,n]失败');
        $bitMap = new self($nRet[1], substr($data, 8), $nRet[2]);
        return $bitMap;
    }
    public function save($file){
        return file_put_contents($file, $this->__toString());
    }

    public function getInfo(){
        $units = array('','K','M','G','T','P','E','Z','Y');
        $M = $this->bitSize;
        $magnitude = intval(floor(log($M,1024)));
        $unit = $units[$magnitude];
        $M /= pow(1024,$magnitude);

        return 'Allocated '.$this->m.' bits ('.$M.$unit.'Bytes)'.PHP_EOL.'Contains '.$this->n.' elements'.PHP_EOL;
    }
    public function sort(){
        $out = '';
        for($i=0;$i<$this->bitSize;$i++){
            $byte = $this->bitData[$i];
            for($j=0;$j<8;$j++){
                $bit = $byte & self::$posMap[$j];
                if($bit!==self::NULL_BYTE){
                    $num = $i*8+$j;
                    $out .= ' '.$num;
                }
            }
        }
        return $out ? substr($out,1):$out;
    }
    //位码值 对应的ascii         [1,    2,    4,    8,    16,   32,   64,   128];
    protected static $posMap = ["\x01", "\x02", "\x04", "\x08", "\x10", "\x20", "\x40", "\x80"];
    /**
     *
     * 返回所在字节及位码值
     * 示例: pos 9 -> (1, "01000 000") (在第2字节, 第2个bit[bit位值2])
     *
     * @param int $pos The $pos'th bit in the bit field.
     * @param $index
     * @param $byte
     */
    protected function toIndexByte($pos, &$index, &$byte) {
        $index = $pos>>3; # 2^3=8;
        $byte = self::$posMap[$pos & 7]; #0x01<<($pos & 7); #
    }
    public function setBit($pos) {
        $this->toIndexByte($pos, $idx, $byte);
        if(($this->bitData[$idx] & $byte) !== $byte){ #判断存在
            $this->bitData[$idx] = $this->bitData[$idx] | $byte;
            $this->n++;
        }
    }
    public function hasBit($pos) {
        $this->toIndexByte($pos, $idx, $byte);
        return ($this->bitData[$idx] & $byte) === $byte;
    }
    public function delBit($pos) {
        $this->toIndexByte($pos, $idx, $byte);
        if(($this->bitData[$idx] & $byte) === $byte){ #判断存在
            $this->bitData[$idx] = $this->bitData[$idx] ^ $byte;
            $this->n--;
        }
    }
}
/* 示例
$bitMap = new BitMap(100);
$bitMap->setBit(9);
var_dump($bitMap->hasBit(9));
$bitMap->setBit(13);
$bitMap->setBit(0);
$bitMap->setBit(7);
#$bitMap->delBit(5);
file_put_contents('./bit', $bitMap->bitData); #原始内容

var_dump($bitMap->save('./bit2')); #带有长度及元素个数
$bitMap = BitMap::load(file_get_contents('./bit2'));
var_dump($bitMap->getInfo());
 */