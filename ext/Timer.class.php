<?php
class Timer{
	private $startTime;
	private $stopTime;
	
	function __construct() {
		$this->startTime == 0;
		$this->stopTime == 0;
	}
	function start() {
		$this->startTime = microtime(TRUE);	
	}
	function stop() {
		$this->stopTime = microtime(TRUE);	
	}
	function spent() {
		return round(($this->stopTime - $this->startTime), 4);	
	}
}
/*
$timer = new Timer();
$timer->start();
usleep(1000);//等待1秒
$timer->stop();
echo '执行该脚本用时<b>'. $timer->spent() .'</b>秒';
unset($timer);
*/
?>