<?php

declare(strict_types=1);

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
    public function send($passable): Pipeline
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * 设置管道列表 调用栈
     * @param mixed $pipes
     * @return $this
     */
    public function through($pipes): Pipeline
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    /**
     * 设置管道对象调用方法名
     * @param string $method
     * @return $this
     */
    public function via(string $method): Pipeline
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置异常处理器
     * @param callable $handler
     * @return $this
     */
    public function whenException(callable $handler): Pipeline
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    /**
     * 执行管道
     * @param \Closure $destination 处理最终管道结果
     * @return mixed
     * @throws \Throwable
     */
    public function then(\Closure $destination)
    {
        $pipeline = array_reduce( //用回调函数迭代地将数组简化为单一的值
            array_reverse($this->pipes), //array:输入数据取相反数组数据
            function ($carry, $pipe) { //callback:回调函数, $carry上一次迭代的返回值, $pipe本次迭代的值,
                return function ($passable) use ($carry, $pipe) {
                    try {
                        if (is_callable($pipe)) {
                            return $pipe($passable, $carry);
                        } else {
                            if (!is_object($pipe)) {
                                $pipe = new $pipe();
                            }
                            if (method_exists($pipe, $this->method)) {
                                return $pipe->{$this->method}($passable, $carry);
                            } else {
                                return $pipe($passable, $carry); //以__invoke方法的方式调用
                            }
                        }
                    } catch (\Throwable $e) {
                        return $this->handleException($passable, $e);
                    }
                };
            },
            function ($passable) use ($destination) { //initial:初始值
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
     * @throws \Throwable
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
     * @param \Throwable $e
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
