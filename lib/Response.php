<?php

namespace myphp;

class Response
{
    use \MyBaseObj;

    /**
     * @var mixed
     */
    public $body;

    /**
     * @var string 输出文件
     */
    private $_outFile;
    /**
     * @var array 分片起始结束位置[begin,end]
     */
    private $_range = [];
    /**
     * @var int 限制每秒读取大小 KB 0不限制
     */
    public $secLimitSize = 0;
    /**
     * @var int 默认分块输出大小 MB
     */
    public $chunkSize = 2;
    public $isSent = false;
    /**
     * @var array 发送文件信息 [文件路径,起始位置,读取长度:0为所有]
     */
    public $file = [];

    const CONTENT_TYPE_JSON = 'application/json';
    const CONTENT_TYPE_JSONP = 'application/javascript';
    const CONTENT_TYPE_XML = 'application/xml';
    const CONTENT_TYPE_HTML = 'text/html';

    /**
     * Response constructor.
     * @param int $code
     * @param array $headers
     * @param mixed|string $body
     */
    public function __construct($code=200, $headers=[], $body='')
    {
        $this->setStatusCode($code);
        $this->withHeaders($headers);
        $this->body = $body;
    }

    public function __toString()
    {
        return $this->body;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function withHeaders($headers)
    {
        foreach ($headers as $name => $value) {
            \myphp::setHeader($name, $value);
        }
        return $this;
    }

    /**
     * 添加头
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function withHeader($name, $value=null)
    {
        \myphp::setHeader($name, $value);
        return $this;
    }

    /**
     * 指定头追加
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function withAddedHeader($name, $value){
        \myphp::setHeader($name, $value, true);
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function withoutHeader($name)
    {
        \myphp::setHeader($name, null);
        return $this;
    }

    /**
     * @param string $name
     * @param bool $first
     * @return mixed|null
     */
    public function getHeader($name, $first = true)
    {
        if (!isset(\myphp::$header[$name])) return null;
        if (is_array(\myphp::$header[$name]) && $first) {
            return reset(\myphp::$header[$name]);
        }
        return \myphp::$header[$name];
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getHeaderLine($name)
    {
        return $this->getHeader($name);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return \myphp::$header;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setStatusCode($code)
    {
        \myphp::$statusCode = (int)$code;
        return $this;
    }

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return $this
     */
    public function withStatus(int $code, string $reasonPhrase='') {
        \myphp::$statusCode = (int)$code;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return \myphp::$statusCode;
    }

    /**
     * @param mixed $body
     * @return $this
     */
    public function withBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $contentType
     * @param string $charset
     * @return $this
     */
    public function setContentType($contentType, $charset = '')
    {
        \myphp::conType($contentType, $charset);
        return $this;
    }

    /**
     * @param string|resource $file
     * @param int $offset
     * @param int $size
     * @param bool $inline
     * @param null $attachmentName
     * @return $this
     * @throws \Exception
     */
    public function sendFile($file, $offset=0, $size=0, $inline=false, $attachmentName=null, $mimeType='')
    {
        if (is_resource($file)) {
            $meta = stream_get_meta_data($file); //取文件的实际路径
            //fclose($file);
            if (is_file($meta['uri'])) {
                $file = $meta['uri'];
            } else {
                throw new \InvalidArgumentException('file does not exist', 500);
            }
        } elseif (!is_file($file)) {
            throw new \Exception('file does not exist', 404);
        }

        $this->file = [$file, $offset, $size];
        $this->_outFile = $file;
        if ($inline !== null) { //文件下载 可能分片传输  null时文件直接全部输出
            if ($size == 0) $size = filesize($file);
            if ($mimeType === '') $mimeType = Helper::minMimeType($file);
            if ($attachmentName === null) $attachmentName = basename($file);

            //取分片信息
            if (isset($_SERVER['HTTP_RANGE'])) {
                $this->_range = self::getRange($size);
                if ($this->_range === false) {
                    $this->withHeader('Content-Range', 'bytes */' . $size);
                    throw new \Exception(416);
                }

                list($begin, $end) = $this->_range;
                if ($begin != 0 || $end != $size - 1) {
                    $this->setStatusCode(206)->withHeader('Content-Range', "bytes $begin-$end/$size");
                } else {
                    $this->setStatusCode(200);
                }
                //分片读取长度
                $size = $end - $begin + 1;
                //记录起始和读取长度
                $this->file[1] = $begin;
                $this->file[2] = $size;
            }
            $this->setDownloadHeaders($attachmentName, $mimeType, $inline, $size);
        }
        return $this;
    }

    /**
     * @param string $filename
     * @param null|string $mimeType
     * @param bool $inline 表示在浏览器中直接显示数据
     * @param null|int $contentLength
     * @return $this
     */
    public function setDownloadHeaders($filename, $mimeType = null, $inline = false, $contentLength = null)
    {
        $this->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . ';filename="' . $filename . '"')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withHeader('Content-Type', $mimeType === null ?'application/octet-stream':$mimeType);

        if (\myphp::req()->header('Connection') == 'close') {
            #$this->withHeader('Connection', 'close');
        } else {
            #$this->withHeader('Connection', 'keep-alive');
        }

        if (isset($this->file[0])) {
            if ($mtime = \filemtime($this->file[0])) {
                $this->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
            }
        }

        if ($contentLength !== null) {
            $this->withHeader('Content-Length', $contentLength);
        }

        return $this;
    }

    /**
     * 重定向
     * @param string $url
     * @param int $code
     * @param array $headers
     * @return $this
     */
    public function redirect($url, $code = 302, $headers = [])
    {
        if ($url === '') {
            $url = Request::siteUrl();
        } elseif ($url[0] === '/') {
            $url = Request::siteUrl() . $url;
        } elseif (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
            $url = Request::siteUrl() . '/' . $url;
        }

        //if (IS_CLI || !headers_sent()) { // 如果报头未发送，则发送
            $headers['Location'] = $url;
            $this->setStatusCode($code)->withHeaders($headers);
        /*} else {
            $this->withBody("<meta http-equiv='Refresh' content='0;URL={$url}'>");
        }*/
        return $this;
    }

    public function e404($msg = '')
    {
        $this->setStatusCode(404)->withBody($msg);
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setCookie($name, $value)
    {
        cookie($name, $value);
        return $this;
    }

    public function clear()
    {
        $this->_outFile = null;
        $this->body = null;
        $this->isSent = false;
        $this->file = [];
        $this->_range = [];
    }

    public function send()
    {
        if ($this->isSent) {
            return;
        }
        #Log::write(\myphp::req()->header('Range', 'null'),'req '.$_SERVER['REMOTE_PORT']);
        if (!IS_CLI) {
            #Log::write($this->getHeader('Content-Range').', '.$this->getHeader('Content-Length'), 'res '.\myphp::$statusCode.' '.$_SERVER['REMOTE_PORT']);
            // 发送状态码
            \myphp::httpCode(\myphp::$statusCode);
            $this->sendHeaders();
        }
        $this->sendBody();
        $this->clear();
        $this->isSent = true;
    }

    protected function sendHeaders(){
        // 发送头部信息
        \myphp::sendHeader();
    }
    //输出内容
    protected function sendBody()
    {
        if ($this->_outFile === null) {
            echo is_array($this->body) ? Helper::toJson($this->body) : $this->body;
            return;
        }

        set_time_limit(0); // Reset time limit for big files
        $perLimit = $this->secLimitSize > 0; //每秒限制?
        $chunkSize = $perLimit ? $this->secLimitSize * 1024 : $this->chunkSize * 1024 * 1024; // 2MB per chunk

        $handle = fopen($this->_outFile, 'rb');
        if ($this->_range) {
            list($begin, $end) = $this->_range;
            fseek($handle, $begin);

            #Log::write($begin, 'start '.$_SERVER['REMOTE_PORT']);
            while (!feof($handle) && ($pos = ftell($handle)) <= $end) {
                if ($pos + $chunkSize > $end) {
                    $chunkSize = $end - $pos + 1;
                }
                #Log::write($pos, 'pos '.$_SERVER['REMOTE_PORT']);
                echo fread($handle, $chunkSize);
                flush(); // 释放缓冲内存
                if ($perLimit) {
                    sleep(1); //延时
                }
            }
        } else {
            while (!feof($handle)) {
                echo fread($handle, $chunkSize);
                flush();
                if ($perLimit) {
                    sleep(1); //延时
                }
            }
        }
        fclose($handle);
    }

    /**
     * 分片请求信息
     * @param int $fileSize
     * @return bool|int[] [start,end]
     */
    public static function getRange($fileSize)
    {
        if (!isset($_SERVER['HTTP_RANGE'])) return [0, $fileSize - 1];
        //bytes=0-5读取开头6字节  bytes=-100读取文件尾100字节  bytes=500-读取500字节以后的; 不支持 bytes=500-600,601-999 多个
        if (strpos($_SERVER['HTTP_RANGE'], 'bytes=') !== 0) return false;
        $range = substr($_SERVER['HTTP_RANGE'], 6);

        if (strpos($range, '-') === false) return false;
        $ranges = explode('-', $range);
        if ($ranges[0] === '' && $ranges[1] === '') return [0, $fileSize - 1];

        $start = (int)$ranges[0];
        $end = (int)$ranges[1];
        if ($ranges[0] === '') { // bytes=-100
            $start = $fileSize - $end;
            $end = $fileSize - 1;
        } elseif ($ranges[1] === '') { // bytes=500-
            $end = $fileSize - 1;
        } else { //bytes=0-5

        }

        if ($start < 0 || $start > $end) {
            return false;
        }

        return [$start, $end];
    }

}