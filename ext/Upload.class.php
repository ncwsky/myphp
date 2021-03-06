<?php
//上传类
class Upload {
	//内部实例对象
    public $fileMd5 = false; //文件名md5加密
    public $fileName = null; //指定保存文件名
    public $fileZero = false; //文件零字节开关
	private static $instance = null;
	private $imgType = ',png,jpg,jpeg,bmp,gif,';
	//上传配置
	//".gif,.png,.jpg,.jpeg,.bmp"
	private $config = array(
			'uploadPath' => 'up/', //保存路径
			'realPath' => 'up/',
			'max_filename_len' => 250, //文件名长度
			'valid_chars_regex' => '.A-Z0-9_ !@#$%^&()+={}\[\]\',~`-', //有效的文件名字符正则
			'fileType' => 'rar,zip,doc,docx,pdf,txt,swf,flv,wmv,png,jpg,jpeg,bmp,gif' , //文件允许格式
			'notFileType' => 'asp,asa,aspx,php,jsp', //不允许上传的格式
			'fileSize' => 10, //文件大小限制，单位MB
			'width'=>false,
			'height'=>false
		);
	private $defData = array('state'=>'1', 'title'=>'null', 'url'=>'null', 'fileType'=>'null', 'fileSize'=>'null');
	// 构造函数
    public function __construct() {

    }
    //静态方法，返回实例
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Upload();
        }
        return self::$instance;
    }
	//设置上传路径  默认realPath一起设置  一般这种情况可用于相对目录
	public function setUpPath($val){
		$this->config['uploadPath'] = $val;
		$this->config['realPath'] = $val;
	}
	//设置真实上传路径
	public function setRealPath($val){
		$this->config['realPath'] = $val;
	}
	//设置允许格式 格式使用,分隔
	public function setFileType($val){
		$val = trim($val);
		if(empty($val)) return FALSE;
		$this->config['fileType'] = $val;
	}
	//设置图片宽高 @w int @h int 用于生成指定像素范围的图片
	public function setWH($w, $h){
		$this->config['width'] = $w;
		$this->config['height'] = $h;
	}
	//设置文件大小限制 ,单位MB
	public function setFileSize($val){
		//$val = floatval($val);
		if(empty($val)) return FALSE;
		$this->config['fileSize'] = $val;
	}
    //递归创建目录 createPath("./up/img/ap")
    public function createPath( $path, $mode=0755 ) {
        if ( !is_dir($path) && !@mkdir( $path, $mode, true)) {
            throw new Exception('创建目录 '. $path .' 失败');
        }
        return true;
    }
    /**
     * 返回数据格式
     * {
	 *   'state'    :'1'  //上传状态，成功时返回1,其他任何值将原样返回至图片上传框中
	 *   'title'    :'hello',   //文件描述，对图片来说在前端会添加到title属性上
     *   'url'      :'a.jpg',   //保存后的文件路径
     *   'fileType' :'jpg',   //文件类型
     *   'fileSize' :'100',   //文件大小
     * }
     */	
	//文件上传表单元素名称
	public function upload($name='upfile') {
        //上传目录创建
        $realPath = $this->config['realPath'];
        $this->createPath($realPath);
        //文件句柄
        $clientFile = isset($_FILES[$name]) ? $_FILES[$name] : NULL;
        if (!isset($clientFile)) {
            $data = $this->defData;
            $data['state'] = '未选择上传文件！';
            return $data;
        }
        $data = array();//清空
        //批量上传判断
        if (is_array($clientFile['name'])) {
            $tmpFile = array();
            for ($i = 0; $i < count($clientFile['name']); $i++) {
                $tmpFile['name'] = $clientFile['name'][$i];
                $tmpFile['type'] = $clientFile['type'][$i];
                $tmpFile['tmp_name'] = $clientFile['tmp_name'][$i];
                $tmpFile['error'] = $clientFile['error'][$i];
                $tmpFile['size'] = $clientFile['size'][$i];
                //传递上传文件信息
                $data[] = $this->doupload($tmpFile);
            }
        } else {
            $data = $this->doupload($clientFile);
        }
        return $data;
    }
	//执行上传保存
	public function doupload($clientFile){
		//上传配置
		$config = $this->config;
		//返回数组初始 文件上传状态,当成功时返回1，其余值将直接返回对应字符窜  $state = "1";
		$data = $this->defData;
		//判断文件上传是否出错
		if($clientFile['error']>0){
			switch($clientFile['error']){
				case UPLOAD_ERR_INI_SIZE:
					$data['state'] = "上传文件大小超出了PHP配置文件中的约定值:upload_max_filesize";
					break;
				case UPLOAD_ERR_FORM_SIZE:
					$data['state'] = "上传文件大小超出了表单中的约定值:MAX_FILE_SIZE";
					break;
				case UPLOAD_ERR_PARTIAL:
					$data['state'] = "文件只有部分被上传";
					break;
				case UPLOAD_ERR_NO_FILE:
					$data['state'] = "没有文件被上传";
					break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $data['state'] = "找不到临时文件夹";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $data['state'] = "文件写入失败";
                    break;
				default:
					$data['state'] = "未知错误";
			}
			return $data;
		}
        //有效文件名字符验证
        $file_name = preg_replace('/[^' . $config['valid_chars_regex'] . ']|\.+$/i', "", basename($clientFile["name"]));
        if (strlen($file_name) == 0 || strlen($file_name) > $config['max_filename_len']) {
            $data['state'] = '无效的文件名！';
            return $data;
        }
        //上传框中的描述表单名称，描述内容
        $data['title'] = htmlspecialchars($clientFile["name"], ENT_QUOTES);
        $data['fileType'] = strtolower(substr($clientFile["name"], strrpos($clientFile["name"], '.') + 1));
        $data['fileSize'] = $clientFile["size"];

		//类型格式验证
        if ($config['fileType'] != '*') {
            $fileType = explode(',', $config['fileType']);
            if (!in_array($data['fileType'], $fileType)) {
                $data['state'] = "不支持的文件类型！";
                return $data;
            }
        }
        $notFileType = explode(',', $config['notFileType']);
        //不允许的类型格式
        if (in_array($data['fileType'], $notFileType)) {
            $data['state'] = "不支持上传的文件类型！";
            return $data;
        }
        //大小验证
        $file_size = 1024 * 1024 * $config['fileSize'];
        if ($data['fileSize'] > $file_size) {
            $data['state'] = "文件大小超出限制！";
            return $data;
        }

        if (!$this->fileZero && $data['fileSize'] == 0) {
            $data['state'] = "文件大小为0字节";
            return $data;
        }
        //保存文件
        $path = $config['uploadPath'];
        $realPath = $config['realPath'];
        $fileUrl = "";
        if (is_uploaded_file($clientFile['tmp_name'])) {//判断文件是否是上传文件
            $tmp_file = $clientFile["name"];
            $f_name = $this->fileName ? $this->fileName : str_replace('.', '', microtime(true)) . mt_rand(0, 99);
            $f_name = $this->fileMd5 ? md5($f_name) : $f_name;//md5文件名加密
            $f_name .= strrchr($tmp_file, '.');
            $fileUrl = $path . $f_name;
            $realFile = $realPath . $f_name;

            if (strpos($this->imgType, ',' . $data['fileType'] . ',') !== false && $config['width'] && $config['height']) { //指定图片大小处理 使用第三方 Image类 方式一
                $result = Image::thumb($clientFile["tmp_name"], $realFile, '', $config['width'], $config['height']);
                if ($result === 0) {
                    $result = move_uploaded_file($clientFile["tmp_name"], $realFile);
                }
            } else {
                $result = move_uploaded_file($clientFile["tmp_name"], $realFile);
            }
            if (!$result) {
                $data['state'] = "文件保存失败！";
            }/*else{ //指定图片大小处理 使用第三方 Image类 方式二
				if($config['width'] && $config['height'])
					Image::thumb($realFile, $realFile, '', $config['width'], $config['height']);
			}*/
		} else {
			$data['state'] = '上传文件 '. $clientFile['tmp_name'] .' 不是一个合法文件！';
		}

	 	if ($data['state']=='1') {
			//$data['title'] = $title;
			$data['url'] = $fileUrl;
			//$data['fileType'] = $current_type;
			//$data['fileSize'] = $current_size;
		}
	 	return $data;		
	}
}
//范例
/*
<form action="" method="post" enctype="multipart/form-data">
<input name="dofile" type="hidden" value="1" />
<input type="file" name="file[]" id="file[]" /><br />
<input type="file" name="file[]" id="file[]" /><br />
<input type="text" name="des" id="des" /><br />
<input type="submit" name="button" id="button" value="提交" />
</form>

<?php
require('cls/Upload.class.php');
if($_POST['dofile']=='1'){
	echo $_POST['des'].'<br>';
	//上传框中的描述表单名称，描述内容
   	//$title = $des!='' ? htmlspecialchars($_POST[$des], ENT_QUOTES) : '';//编码双引号和单引号
	$upload = Upload::getInstance();
	$upload->setUpPath('app/up/'); //同时将设置文件的绝对路径
	//$upload->setRealPath('app/up/'); //单独设置文件的绝对路径
	
	$data = $upload->upload('file');
	if (isset($data[0]['state'])){//多个文件上传
		for($i=0;$i<count($data);$i++){
			if ($data[$i]['state']=='1'){
				echo '上传成功：'. $data[$i]['url'] .'，类型：'. $data[$i]['fileType'] .'，大小：'. $data[$i]['fileSize'] .'<br>';
			} else {
				echo '上传失败：'. $data[$i]['url'] .'，类型：'. $data[$i]['fileType'] .'，大小：'. $data[$i]['fileSize'] .'<br>';
			}	
		}
	} else {//单个文件上传
		if ($data['state']=='1'){
			echo '上传成功：'. $data['url'] .'，类型：'. $data['fileType'] .'，大小：'. $data['fileSize'].'<br>';
		} else {
			echo '上传失败：'. $data['url'] .'，类型：'. $data['fileType'] .'，大小：'. $data['fileSize'] .'<br>';
		}	
	}
}
?>
*/