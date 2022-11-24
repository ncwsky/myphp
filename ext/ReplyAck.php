<?php
/**
 * Class ReplyAck 应答确认 一对一
 */
class ReplyAck{
    private static $time = 5; #多少秒超时 放入重试队列
    public static $redisName = 'redis'; #缓存配置名
    public static $prefix = 'RA'; #缓存名前缀 防与其他的冲突

    const RETRY_QUEUE = '_retry_queue';
    const MSG_LIST = '_msg_list';
    const MSG_LIST_HASH = '_msg_list_hash';
    const MSG_QUEUE = '_msg_queue';
    const MSG_QUEUE_HASH = '_msg_queue_hash';
    const MSG_ID = '_msg_id';

    public static function redisKey($name){
        return self::$prefix . $name;
    }

    /**
     * @return lib_redis|\myphp\MyRedis|\Redis
     */
    public static function redis(){
        return lib_redis::getInstance(myphp::get(self::$redisName));
    }
    /** 初始并生成定时器
     * @param string $redisName
     * @param string $prefix 前缀
     * @param int $timeout
     * @param null $queueTimes
     */
    public static function init($redisName=null, $prefix='RA', $timeout=5)
    {
        self::$redisName = $redisName?:'redis';
        self::$prefix = $prefix;
        self::$time = $timeout; #自定义超时重试时间

        #重发未应答消息
        if(SrvBase::$instance->server->worker_id==0){ #仅在其中一个进程初始定时器，不要运行到多个进程中去，会造成数据重复处理
            #定时验证应答
            SrvBase::$instance->server->tick(self::$time*1000, function () {
                $redis = self::redis();
                $page = 1;
                $size = 100;
                while (true) {
                    $start = ($page-1)*$size;
                    $end = $page*$size-1;
                    #echo 'start:'.$start.', end:'.$end,PHP_EOL;
                    $arr = $redis->zRange(self::$prefix . self::MSG_LIST, $start, $end, true);
                    #print_r($arr);
                    if(!$arr){
                        break;
                    }
                    foreach ($arr as $message_id=>$time){
                        #echo $message_id.'--->'.$time,PHP_EOL;
                        if(!$message_id) {
                            #print_r($arr);
                            \myphp\Log::write($arr, 'xxxxxxx');
                            continue;
                        };
                        if($time>time()) { //未到应答超时时间
                            break 2;
                        }

                        $msg = $redis->hget(self::$prefix . self::MSG_LIST_HASH, $message_id);
                        self::puback($message_id, true); #清除
                        $redis->rPush(self::$prefix . self::RETRY_QUEUE, (time()+30).':'.$msg); //放入第一重试队列 尾部压入
                    }
                    if(count($arr)<$size) break;
                }
                #定时队列超时处理
                $page = 1;
                $size = 100;
                while (true) {
                    $start = ($page-1)*$size;
                    $end = $page*$size-1;
                    #echo 'start:'.$start.', end:'.$end,PHP_EOL;
                    $arr = $redis->zRange(self::$prefix . self::MSG_QUEUE, $start, $end, true);
                    #print_r($arr);
                    if(!$arr){
                        break;
                    }
                    #$page++;
                    #for ($i = 0; $i < $count; $i++) { $message_id = $arr[$i]; $time = (int)$arr[++$i];
                    #$count = count($arr);
                    foreach ($arr as $message_id=>$time){
                        #echo $message_id.'--->'.$time,PHP_EOL;
                        if(!$message_id) {
                            continue;
                        };
                        if($time>time()) { //未到应答超时时间
                            break 2;
                        }
                        $msg = $redis->hget(self::$prefix . self::MSG_QUEUE_HASH, $message_id);
                        list($nextIdx, $data) = explode('>', $msg, 2);
                        self::puback($message_id, true); #清除
                        #echo $nextIdx,'====>', $data,PHP_EOL;
                        $redis->rPush(self::$prefix . self::RETRY_QUEUE.$nextIdx, $data); #加入下一重试队列
                    }
                    if(count($arr)<$size) break;
                }
            });

            #队列重发未应答消息
            myphp::class_dir(SrvBase::$instance->getConfig('timer_dir', APP_PATH.'/timer')); //定时处理载入
            $replyAckQueue = new ReplyAckQueue();
            SwooleSrv::$instance->server->tick(2*1000, function () use($replyAckQueue) {
                $replyAckQueue->run();
            });
            if (SrvBase::$isConsole) {
                echo "应答验证、队列定时器创建成功", PHP_EOL;
            }
            \myphp\Log::INFO('应答验证、队列定时器创建成功');
            ReplyAck::incrId();
        }
    }
    #队列 把重试的消息放到队列
    public static function queue($message_id, $msg){ #msg = [$message_id > $nextIdx > $data[time:data]]
        self::redis()->zAdd(self::$prefix . self::MSG_QUEUE, time()+self::$time, $message_id); #时间 值
        self::redis()->hset(self::$prefix . self::MSG_QUEUE_HASH, $message_id, $msg);
    }

    /** 消息id
     * @return int
     */
    public static function incrId(){
        $message_id = self::redis()->incr(self::redisKey(self::MSG_ID));
        if ($message_id >= 0xFFFFFFFF) { #0xffffff
            self::redis()->set(self::redisKey(self::MSG_ID), 0);
        }
        return $message_id;
    }
    #发布消息 缓存到消息列表
    public static function publish($msg, $message_id=0){
        $message_id = $message_id?:self::incrId();

        self::redis()->zAdd(self::$prefix . self::MSG_LIST, time()+self::$time, $message_id); #时间 值
        self::redis()->hset(self::$prefix . self::MSG_LIST_HASH, $message_id, $msg);

        return $message_id;
    }
    #发布确认 消除缓存的消息或队列
    public static function puback($message_id, $retry=false){
        \myphp\Log::DEBUG("<- ".($retry?'Retry':'Recv')." PUBACK package, message_id:$message_id");
        if (SrvBase::$isConsole) {
            echo "<- ".($retry?'Retry':'Recv')." PUBACK package, message_id:$message_id", PHP_EOL;
        }
        $redis = self::redis();
        if($redis->hExists(self::$prefix . self::MSG_QUEUE_HASH, $message_id)){  #释放消息
            $redis->hdel(self::$prefix . self::MSG_QUEUE_HASH, $message_id);
            $redis->zRem(self::$prefix . self::MSG_QUEUE, $message_id);
        }
        if($redis->hExists(self::$prefix . self::MSG_LIST_HASH, $message_id)){
            $redis->hdel(self::$prefix . self::MSG_LIST_HASH, $message_id);
            $redis->zRem(self::$prefix . self::MSG_LIST, $message_id);
        }
    }
}