<?php  
/* 5百万 uid 白名单 之 PHP Bitmap 处理  
 * author: hushuilong 
 * email: hushuilong at gmail dot com 
 * */  
class BitmapFile   
{  
    private $handler = NULL;  
    private $max = 0;  
    public function __construct($file)   
    {  
        clearstatcache(true, $file);      
        if(file_exists($file))  
            $this->handler = @fopen($file , 'r+') OR die('open bitmap file failed');  
        else  
            $this->handler = @fopen($file , 'w+') OR die('open bitmap file failed');  
  
        $this->max = file_exists($file) ? (filesize($file) * 8 - 1) : 0;  
    }  
    public function __destruct()   
    {  
        @fclose($this->handler);  
    }  
      
    private function binary_dump($binary_data)
    {
        return sprintf('%08d',decbin(hexdec(bin2hex($binary_data))));
    }

    private function num_check($num)  
    {  
        ($num > -1) OR die('number must be greater than -1');  
        ($num < 4294967296) or die('number must be less than 4294967296'); // 2^32  
        if ($this->max < $num) {  
            fseek($this->handler, 0, SEEK_END);  
            fwrite($this->handler , str_repeat("\x00",ceil(($num - $this->max)/8))); // fill with 0  
            $this->max = ceil($num/8)*8 - 1;  
        }         
    }  
      
    public function set($num)  
    {  
        $this->num_check($num);
        /*
        SEEK_SET - 设定位置等于 offset 字节。
        SEEK_CUR - 设定位置为当前位置加上 offset。
        SEEK_END - 设定位置为文件尾加上 offset。

        ftell — 返回文件指针读/写的位置   在附加模式（加参数 "a" 打开文件）中 ftell() 会返回未定义错误。
        fflush — 将缓冲内容输出到文件
         */
        fseek($this->handler, floor($num/8), SEEK_SET);  
        $bin = fread($this->handler, 1) | pack('C',0x100 >> fmod($num,8)+1); // mark with 1  
          
        fseek($this->handler, ftell($this->handler)-1, SEEK_SET); // write a new byte  
        fwrite($this->handler, $bin);   
        fflush($this->handler);  
    }  
      
    public function del($num)  
    {  
        $this->num_check($num);  
        fseek($this->handler, floor($num/8), SEEK_SET);  
        $bin = fread($this->handler, 1) & ~pack('C',0x100 >> fmod($num,8)+1); // mark with 0  
          
        fseek($this->handler, ftell($this->handler)-1, SEEK_SET); // write a new byte  
        fwrite($this->handler, $bin);   
        fflush($this->handler);  
    }     
      
    public function find($num)  
    {  
        if (fseek($this->handler, floor($num/8), SEEK_SET) == -1) return FALSE;  
        $bin = fread($this->handler , 1);  
        if ($bin === FALSE || strlen($bin) == 0) return FALSE;  
  
        $bin = $bin & pack('C',0x100 >> fmod($num,8)+1);  
        if($bin === "\x00") return FALSE;  
        return TRUE;  
    }  
}  
  
$b = new BitmapFile('./bitmapdata'); # /dev/shm/bitmapdata 把文件，放在内存里，增加读写速度
  
// 设置白名单  
$b->set(1); $b->set(3); $b->set(5);  
$b->set(7); $b->set(9); $b->set(501);  
  
$uid = 501;  
var_dump($b->find($uid)); // 查找白名单  
  
$b->del($uid); // 删除白名单  
var_dump($b->find($uid)); // 查找白名单  