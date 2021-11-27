<?php

class HttpCode
{
    const STATUS_CODE_100 = 100;
    const STATUS_CODE_102 = 102;

    const STATUS_CODE_200 = 200;
    const STATUS_CODE_201 = 201;
    const STATUS_CODE_202 = 202;
    const STATUS_CODE_203 = 203;
    const STATUS_CODE_204 = 204;
    const STATUS_CODE_205 = 205;
    const STATUS_CODE_206 = 206;
    const STATUS_CODE_207 = 207;

    const STATUS_CODE_300 = 300;
    const STATUS_CODE_301 = 301;
    const STATUS_CODE_302 = 302;
    const STATUS_CODE_303 = 303;
    const STATUS_CODE_304 = 304;
    const STATUS_CODE_305 = 305;
    const STATUS_CODE_307 = 307;

    const STATUS_CODE_400 = 400;
    const STATUS_CODE_401 = 401;
    const STATUS_CODE_402 = 402;
    const STATUS_CODE_403 = 403;
    const STATUS_CODE_404 = 404;
    const STATUS_CODE_405 = 405;
    const STATUS_CODE_406 = 406;
    const STATUS_CODE_407 = 407;
    const STATUS_CODE_408 = 408;
    const STATUS_CODE_409 = 409;
    const STATUS_CODE_410 = 410;
    const STATUS_CODE_412 = 412;
    const STATUS_CODE_413 = 413;
    const STATUS_CODE_414 = 414;
    const STATUS_CODE_415 = 415;
    const STATUS_CODE_416 = 416;
    const STATUS_CODE_417 = 417;
    const STATUS_CODE_422 = 422;
    const STATUS_CODE_423 = 423; // Locked (WebDAV) (RFC 4918)
    const STATUS_CODE_424 = 424;
    const STATUS_CODE_425 = 425;
    const STATUS_CODE_460 = 460;

    const STATUS_CODE_500 = 500;
    const STATUS_CODE_501 = 501;
    const STATUS_CODE_502 = 502;
    const STATUS_CODE_503 = 503;
    const STATUS_CODE_505 = 505;
    const STATUS_CODE_507 = 507;

    public static $httpCodeStatus = [
        // INFORMATIONAL CODES
        self::STATUS_CODE_100 => 'Continue',
        self::STATUS_CODE_102 => 'Processing', // RFC2518
        // SUCCESS CODES
        self::STATUS_CODE_200 => 'OK',
        self::STATUS_CODE_201 => 'Created',
        self::STATUS_CODE_202 => 'Accepted',
        self::STATUS_CODE_203 => 'Non-Authoritative Information',
        self::STATUS_CODE_204 => 'No Content',
        self::STATUS_CODE_205 => 'Reset Content',
        self::STATUS_CODE_206 => 'Partial Content',
        self::STATUS_CODE_207 => 'Multi-Status',          // RFC4918

        self::STATUS_CODE_300 => 'Multiple Choices',
        self::STATUS_CODE_301 => 'Moved Permanently',
        self::STATUS_CODE_302 => 'Found',
        self::STATUS_CODE_303 => 'See Other',
        self::STATUS_CODE_304 => 'Not Modified',
        self::STATUS_CODE_305 => 'Use Proxy',
        self::STATUS_CODE_307 => 'Temporary Redirect',
        // CLIENT ERROR
        self::STATUS_CODE_400 => 'Bad Request',
        self::STATUS_CODE_401 => 'Unauthorized',
        self::STATUS_CODE_402 => 'Payment Required',
        self::STATUS_CODE_403 => 'Forbidden',
        self::STATUS_CODE_404 => 'Not Found',
        self::STATUS_CODE_405 => 'Method Not Allowed',
        self::STATUS_CODE_406 => 'Not Acceptable',
        self::STATUS_CODE_407 => 'Proxy Authentication Required',
        self::STATUS_CODE_408 => 'Request Timeout',
        self::STATUS_CODE_409 => 'Conflict',
        self::STATUS_CODE_410 => 'Gone',
        self::STATUS_CODE_412 => 'Precondition Failed',
        self::STATUS_CODE_413 => 'Request Entity Too Large',
        self::STATUS_CODE_415 => 'Unsupported Media Type',
        self::STATUS_CODE_416 => 'Range Not Satisfiable',
        self::STATUS_CODE_417 => 'Expectation Failed',
        self::STATUS_CODE_422 => 'Unprocessable Entity', // RFC4918
        self::STATUS_CODE_423 => 'Locked', // RFC4918
        self::STATUS_CODE_424 => 'Failed Dependency', // RFC4918
        self::STATUS_CODE_425 => 'Too Early',
        self::STATUS_CODE_460 => 'Checksum Mismatch',
        // SERVER ERROR
        self::STATUS_CODE_500 => 'Internal Server Error',
        self::STATUS_CODE_501 => 'Not Implemented',
        self::STATUS_CODE_502 => 'Bad Gateway',
        self::STATUS_CODE_503 => 'Service Unavailable',
        self::STATUS_CODE_505 => 'HTTP Version Not Supported',
        self::STATUS_CODE_507 => 'Insufficient Storage', // RFC4918
    ];
}