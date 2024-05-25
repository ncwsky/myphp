<?php

namespace myphp\middleware;

use myphp\Request;

/**
 * Class Options 浏览器预检options请求输出处理
 * @package myphp\middleware
 */
class Options
{
    protected $allow = 'GET, POST, PATCH, PUT, DELETE, HEAD, OPTIONS';

    /**
     * 处理请求
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function process(Request $request, \Closure $next)
    {
        if (Request::method() == 'OPTIONS') {
            if (!isset(\myphp::$header['Allow'])) {
                \myphp::setHeader('Allow', $this->allow);
            }
            return \myphp::res();
        }
        return $next($request);
    }
}
