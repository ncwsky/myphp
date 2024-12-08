<?php

declare(strict_types=1);

namespace myphp\session;

//redis会话类
class Redis implements \SessionHandlerInterface
{
    private $handler;
    //配置
    private $options = [
        'prefix' => 'ses:', //前缀
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'select' => 0, //选择库
        'timeout' => 0, //连接超时时间（秒）
        'pconnect' => true, //持续连接
        'expire' => 1440 //有效期 为0使用php默认配置
    ];

    /**
     * Redis constructor.
     * @param null $options = [
     *
     * ]
     */
    public function __construct($options = null)
    {
        if (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        }
        if (empty($this->options['expire'])) {
            $this->options['expire'] = (int)ini_get('session.gc_maxlifetime');
        }

        if (extension_loaded('redis')) {
            $func = $this->options['pconnect'] ? 'pconnect' : 'connect';
            $this->handler = new \Redis();
            $this->options['timeout'] == 0 ? $this->handler->$func($this->options['host'], $this->options['port']) : $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);
            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }
            $this->handler->select($this->options['select']);
        } else {
            $this->options['database'] = $this->options['select'];
            $this->options['retries'] = 1;
            $this->handler = new \myphp\driver\Redis($this->options);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function open($save_path, $name): bool
    {
        return $this->handler ? true : false;
    }
    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function read($id)
    {
        return (string)$this->handler->get($this->options['prefix'] . $id);
    }
    /**
     * {@inheritdoc}
     */
    public function write($id, $data): bool
    {
        $expire = $this->options['expire'];
        $name = $this->options['prefix'].$id;

        if ($expire > 0) {
            $result = $this->handler->setex($name, $expire, $data);
        } else {
            $result = $this->handler->set($name, $data);
        }
        return (bool)$result;

    }
    /**
     * {@inheritdoc}
     */
    public function destroy($id): bool
    {
        //\myphp\Log::trace('destroy:'.$id);
        return (bool)$this->handler->del($this->options['prefix'].$id) > 0;
    }
    /**
     * {@inheritdoc}
     */
    public function gc($max_lifetime): bool
    {
        return true;
    }

    /**
     * Update sesstion modify time.
     *
     * @see https://www.php.net/manual/en/class.sessionupdatetimestamphandlerinterface.php
     */
    public function updateTimestamp($id, $data = ''): bool
    {
        return (bool)$this->handler->expire($this->options['prefix'].$id, $this->options['expire']);
    }
}
