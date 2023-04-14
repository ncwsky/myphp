<?php
namespace myphp;

class Pipeline
{
    /**
     * @var mixed 通过管道传递的数据对象
     */
    protected $passable;

    /**
     * @var array
     */
    protected $pipes = [];

    /**
     * @var string 管道对象默认的调用方法
     */
    protected $method = 'process';

    protected $exceptionHandler;

    /**
     * 设置管道发送的数据
     * @param mixed $passable
     * @return $this
     */
    public function send($passable)
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * 设置管道列表 调用栈
     * @param mixed $pipes
     * @return $this
     */
    public function through($pipes)
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    /**
     * 设置管道对象调用方法名
     * @param string $method
     * @return $this
     */
    public function via($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置异常处理器
     * @param callable $handler
     * @return $this
     */
    public function whenException($handler)
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    /**
     * 执行管道
     * @param \Closure $destination 处理最终管道结果
     * @return mixed
     */
    public function then(\Closure $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            function ($carry, $pipe) { //$carry上一次迭代的返回值, $pipe本次迭代的值,
                return function ($passable) use ($carry, $pipe) {
                    try {
                        if (is_callable($pipe)) {
                            return $pipe($passable, $carry);
                        } else {
                            if (!is_object($pipe)) {
                                $pipe = new $pipe();
                            }
                            return $pipe->{$this->method}($passable, $carry);
                        }
                    } catch (\Throwable $e) {
                        return $this->handleException($passable, $e);
                    }
                };
            },
            function ($passable) use ($destination) {
                //所有管道处理汇总后到这里
                try {
                    return $destination($passable);
                } catch (\Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            }
        );

        return $pipeline($this->passable);
    }

    /**
     * 执行管道返回结果
     * @return mixed
     */
    public function thenReturn()
    {
        return $this->then(function ($passable) {
            return $passable;
        });
    }

    /**
     * 异常处理
     * @param $passable
     * @param $e
     * @return mixed
     * @throws \Throwable
     */
    protected function handleException($passable, \Throwable $e)
    {
        if ($this->exceptionHandler) {
            return call_user_func($this->exceptionHandler, $passable, $e);
        }
        throw $e;
    }
}