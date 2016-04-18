第三方类库目录

PHPRPC是一个轻型的、安全的、跨网际的、跨语言的、跨平台的、跨环境的、跨域的、支持复杂对象传输的、支持引用参数传递的、支持内容输出重定向的、支持分级错误处理的、支持会话的、面向服务的高性能远程过程调用协议。
http://www.phprpc.org


Hprose（High Performance Remote Object Service Engine）是一款先进的轻量级、跨语言、跨平台、无侵入式、高性能动态远程对象调用引擎库。它不仅简单易用，而且功能强大。
你无需专门学习，只需看上几眼，就能用它轻松构建分布式应用系统。
http://www.hprose.com/


Spyc PHP 是一个用来读取 YAML 格式文件的PHP库，YAML一般用于保存配置文件, 性能优于XML,也更直观
使用方法：
include('spyc.php');
 
// 读取YAML文件,生成数组
$yaml = Spyc::YAMLLoad('spyc.yaml');
 
// 将数组转换成YAML文件
$array['name']  = 'andy';
$array['site'] = '21andy.com';
$yaml = Spyc::YAMLDump($array);

http://www.oschina.net/p/spyc+php


smarty是一个基于PHP开发的PHP模板引擎。它提供了逻辑与外在内容的分离，简单的讲，目的就是要使 用PHP程序员同美工分离,使用的程序员改变程序的逻辑内容不会影响到美工的页面设计，美工重新修改页面不会影响到程序的程序逻辑，这在多人合作的项目中 显的尤为重要。
http://www.smarty.net/


phpqrcode	PHP QR Code 是 PHP 用来处理二维条形码的开发包。基于 C 语言的 libqrencode 库开发，提供生成二维条形码功能，包括 PNG、JPG 格式。使用纯 PHP 实现，无需依赖第三方包，除了 GD2 除外。
示例代码：

QRcode::png('code data text', 'filename.png'); // creates file 
QRcode::png('some othertext 1234'); // creates code image and outputs it directly into browser