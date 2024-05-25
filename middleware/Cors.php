<?php

namespace myphp\middleware;

use myphp\Request;

/**
 * Class Cors 跨域header头输出中间件
 * @package myphp\middleware
 */
class Cors
{
    protected $origin = '*';
    protected $headers = '*';
    protected $methods = 'GET, POST, PATCH, PUT, DELETE, HEAD, OPTIONS';
    protected $credentials = null;
    protected $maxAge = 86400;

    /**
     * 处理请求
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function process(Request $request, \Closure $next)
    {
        $response = \myphp::res();
        $response->withHeader('Access-Control-Allow-Origin', $this->origin)
        ->withHeader('Access-Control-Allow-Headers', $this->headers)
        ->withHeader('Access-Control-Allow-Methods', $this->methods);
        if ($this->credentials !== null) {
            $response->withHeader('Access-Control-Allow-Credentials', $this->credentials ? 'true' : 'false');
        }
        if (Request::method() == 'OPTIONS') {
            $response->withHeader('Access-Control-Max-Age', $this->maxAge);
        }

        return $next($request);
    }
}
