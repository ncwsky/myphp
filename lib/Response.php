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
     * @var resource|array 文件指针资源|[文件指针资源,起始位置,结束位置]
     */
    public $stream;
    public $steamLimit = 0; //限制每秒读取大小 单位KB 0不限制
    public $isSent = false;

    /**
     * @var array 发送文件信息 [文件路径,起始位置,读取长度]
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
        \myphp::$header = \array_merge_recursive(\myphp::$header, $headers);
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
     * @param string $filePath
     * @param null|string $attachmentName
     * @param array $options
     * @return $this
     * @throws \Exception
     */
    public function sendFile($filePath, $attachmentName = null, $options = [])
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException('file does not exist', 404);
        }
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = self::minMimeType($filePath);
        }
        if ($attachmentName === null) {
            $attachmentName = basename($filePath);
        }
        $handle = fopen($filePath, 'rb');
        $this->file = [$filePath, 0, -1];
        $this->sendStreamAsFile($handle, $attachmentName, $options);
        return $this;
    }

    /**
     * @param resource $handle
     * @param string $filename
     * @param array $options
     * @return $this
     * @throws \Exception
     */
    public function sendStreamAsFile($handle, $filename, $options = [])
    {
        if (!is_resource($handle)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }
        if (isset($options['fileSize'])) {
            $fileSize = $options['fileSize'];
        } else {
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);
        }

        $range = self::getRange($fileSize);
        if ($range === false) {
            $this->withHeader('Content-Range', 'bytes */' . $fileSize);
            throw new \Exception(416);
        }

        list($begin, $end) = $range;
        if ($begin != 0 || $end != $fileSize - 1) {
            $this->setStatusCode(206)->withHeader('Content-Range', "bytes $begin-$end/$fileSize");
        } else {
            $this->setStatusCode(200);
        }

        $length = $end - $begin + 1;
        $mimeType = isset($options['mimeType']) ? $options['mimeType'] : 'application/octet-stream';
        $this->setDownloadHeaders($filename, $mimeType, !empty($options['inline']), $length);

        $this->stream = [$handle, $begin, $end];
        if ($this->file) { //是文件 记录起始和读取长度
            $this->file[1] = $begin;
            $this->file[2] = $length;
        }
        return $this;
    }

    /**
     * @param string $filename
     * @param null|string $mimeType
     * @param bool $inline
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
        } elseif (strpos($url, 'http://') === false || strpos($url, 'https://') === false) {
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
        $this->stream = null;
        $this->body = null;
        $this->isSent = false;
        $this->file = [];
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
        if ($this->stream === null) {
            echo is_array($this->body) ? Helper::toJson($this->body) : $this->body;
            return;
        }

        ob_end_flush();//冲刷出（送出）输出缓冲区内容并关闭缓冲区

        set_time_limit(0); // Reset time limit for big files
        $perLimit = $this->steamLimit > 0; //每秒限制?
        $chunkSize = $perLimit ? $this->steamLimit * 1024 : 2 * 1024 * 1024; // 2MB per chunk

        if (is_array($this->stream)) {
            list($handle, $begin, $end) = $this->stream;
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
            fclose($handle);
        } else {
            while (!feof($this->stream)) {
                echo fread($this->stream, $chunkSize);
                flush();
                if ($perLimit) {
                    sleep(1); //延时
                }
            }
            fclose($this->stream);
        }
    }

    /**
     * @param int $fileSize
     * @return bool|int[]
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

    /**
     * 简要的mime_type类型
     * @param string $filename
     * @return string
     */
    public static function minMimeType($filename)
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filename);
        }

        static $mimeType = array(
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',

            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/x-wav',
            'ogg' => 'audio/ogg',

            'avi' => 'video/x-msvideo',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'ogv' => 'video/ogg',
            'webm' => 'video/webm',
            'flv' => 'video/x-flv',

            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'ppt' => 'application/vnd.ms-powerpoint',
            'xls' => 'application/vnd.ms-excel',
            'xlt' => 'application/vnd.ms-excel',

            'tar' => 'application/x-tar',
            '7z' => 'application/x-7z-compressed',
            'rar' => 'application/x-rar-compressed',
            'zip' => 'application/zip',
        );
        $mime = 'application/octet-stream';
        $pos = strrpos($filename, '.');
        if (!$pos) return $mime;
        $type = strtolower(substr($filename, $pos + 1));
        if (isset($mimeType[$type])) {
            $mime = $mimeType[$type];
        }
        return $mime;
    }
}