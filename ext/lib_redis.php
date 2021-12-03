<?php
// lib_redis类
class lib_redis{
    private static $instance = array();
    protected $handler;
    private $isExRedis = false;
    //配置
    protected $options = array(
        //'name'=>'', //创建对象名
        'prefix' => '',
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'select' => 0, //选择库
        'timeout' => 0,
        'pconnect' => false, //持续连接
        'server' => array() //从服务器 待实现
    );
    /**
     * @param array $options
     * @return lib_redis
     */
    public static function getInstance($options = array()){
        $name = isset($options['name']) ? $options['name'] : 'redis';
        if (!isset(self::$instance[$name])) { //|| (PHP_SAPI=='cli' && self::$instance[$name]->handler->ping() === false)
            self::$instance[$name] = new self($options);
        } else {
            if (isset($options['pconnect']) && $options['pconnect'] && isset($options['select'])) { //持久连接处理
                try{
                    self::$instance[$name]->handler->select($options['select']);
                }catch (\Exception $e){ #重新初始一次
                    Log::write($e->getMessage(), 'redis-warn');
                    self::$instance[$name] = new self($options);
                }
            }
        }
        return self::$instance[$name];
    }
    public static function free(){
        #self::$instance = null; #取消
    }
    //构造函数
    public function __construct($options = array()){
		if (!empty($options)) {
			$this->options = array_merge($this->options, $options);
		}
        if ( extension_loaded('redis') ) {
            $this->isExRedis = true;
           
            $func = $this->options['pconnect'] ? 'pconnect' : 'connect';
            $this->handler = new Redis();
            $this->options['timeout'] == 0 ? $this->handler->$func($this->options['host'], $this->options['port']) : $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);
            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }
            $this->handler->select($this->options['select']);
        }else{
            $this->options['database'] = $this->options['select'];
            $this->handler = new MyRedis($this->options);
        }
    }
    public function __call($method_name, $method_args){
        return call_user_func_array(array($this->handler, $method_name), $method_args);
    }
    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param $json
     * @return mixed
     */
    public function get($name, $json=true){
        $val = $this->handler->get($this->options['prefix'] . $name);
        if(!$json) return $val;
        $jsonData = json_decode($val, true);
        return ($jsonData === null) ? $val : $jsonData;    //检测是否为JSON数据 true 返回JSON解析数组, false返回源数据
    }
    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $data 存储数据
     * @param int|string $expire 有效时间（秒） 0表示永久缓存
     * @return boolean
     */
    public function set($name, $data, $expire = 0){
        $name = $this->options['prefix'] . $name;
        if(func_num_args()>3){ //直接走原生操作
            $args = func_get_args(); $args[0] = $name;
            return call_user_func_array(array($this->handler, 'set'), $args);
        }
        //对数组/对象数据进行缓存处理，保证数据完整性
        $option = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
        $data = (is_object($data) || is_array($data)) ? json_encode($data, $option) : $data;
        if (is_int($expire) && $expire) {
            $result = $this->handler->setex($name, $expire, $data);
        } else {
            $result = $this->handler->set($name, $data);
        }
        return $result;
    }
    //删除缓存
    public function del($name){
        return $this->handler->del($this->options['prefix'] . $name);
        # redis扩展的delete已弃用
        #return $this->isExRedis ? $this->handler->del($this->options['prefix'] . $name) : $this->handler->del($this->options['prefix'] . $name);
    }
    //清除缓存
    public function clear(){
        return $this->handler->flushDB();
    }
}