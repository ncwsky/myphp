���������Ŀ¼

PHPRPC��һ�����͵ġ���ȫ�ġ������ʵġ������Եġ���ƽ̨�ġ��绷���ġ�����ġ�֧�ָ��Ӷ�����ġ�֧�����ò������ݵġ�֧����������ض���ġ�֧�ַּ�������ġ�֧�ֻỰ�ġ��������ĸ�����Զ�̹��̵���Э�顣
http://www.phprpc.org


Hprose��High Performance Remote Object Service Engine����һ���Ƚ����������������ԡ���ƽ̨��������ʽ�������ܶ�̬Զ�̶����������⡣�����������ã����ҹ���ǿ��
������ר��ѧϰ��ֻ�迴�ϼ��ۣ������������ɹ����ֲ�ʽӦ��ϵͳ��
http://www.hprose.com/


Spyc PHP ��һ��������ȡ YAML ��ʽ�ļ���PHP�⣬YAMLһ�����ڱ��������ļ�, ��������XML,Ҳ��ֱ��
ʹ�÷�����
include('spyc.php');
 
// ��ȡYAML�ļ�,��������
$yaml = Spyc::YAMLLoad('spyc.yaml');
 
// ������ת����YAML�ļ�
$array['name']  = 'andy';
$array['site'] = '21andy.com';
$yaml = Spyc::YAMLDump($array);

http://www.oschina.net/p/spyc+php


smarty��һ������PHP������PHPģ�����档���ṩ���߼����������ݵķ��룬�򵥵Ľ���Ŀ�ľ���Ҫʹ ��PHP����Աͬ��������,ʹ�õĳ���Ա�ı������߼����ݲ���Ӱ�쵽������ҳ����ƣ����������޸�ҳ�治��Ӱ�쵽����ĳ����߼������ڶ��˺�������Ŀ�� �Ե���Ϊ��Ҫ��
http://www.smarty.net/


phpqrcode	PHP QR Code �� PHP ���������ά������Ŀ����������� C ���Ե� libqrencode �⿪�����ṩ���ɶ�ά�����빦�ܣ����� PNG��JPG ��ʽ��ʹ�ô� PHP ʵ�֣����������������������� GD2 ���⡣
ʾ�����룺

QRcode::png('code data text', 'filename.png'); // creates file 
QRcode::png('some othertext 1234'); // creates code image and outputs it directly into browser