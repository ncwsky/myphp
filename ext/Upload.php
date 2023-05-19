<?php
//上传类
class Upload {
    public $fileMd5 = false; //文件名md5加密
    public $fileName = ''; //指定保存文件名
    public $fileZero = false; //文件零字节开关
	private static $instance = null; //内部实例对象
    //上传配置
    public $uploadPath = 'up/'; //相对的上传路径
    public $realPath = 'up/'; //绝对路径
    public $fileType = 'rar,zip,doc,docx,pdf,txt,swf,flv,wmv,png,jpg,jpeg,bmp,gif'; //文件允许格式
    public $notFileType = ''; //不允许上传的格式 asp,asa,aspx,php,jsp
    public $fileSize = 10; //文件大小限制，单位MB
    //图片大小处理配置
    private $imgType = ',png,jpg,jpeg,bmp,gif,';
    private $width = 0;
    private $height = 0;

    private $defData = ['state' => '1', 'title' => null, 'url' => null, 'fileType' => null, 'fileSize' => null];
	// 构造函数
    public function __construct() {}
    //静态方法，返回实例
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
	//设置上传路径
	public function setUpPath($val){
		$this->uploadPath = $val;
	}
	//设置真实上传路径
	public function setRealPath($val){
        $this->realPath = realpath($val);
	}
	//设置允许格式 格式使用,分隔
	public function setFileType($val){
		$val = trim($val);
		if(empty($val)) return false;
		$this->fileType = $val;
	}
	//设置图片宽高 @w int @h int 用于生成指定像素范围的图片
	public function setWH($w, $h){
		$this->width = $w;
		$this->height = $h;
	}
	//设置文件大小限制 ,单位MB
	public function setFileSize($val){
		//$val = floatval($val);
		if(empty($val)) return false;
		$this->fileSize = $val;
	}
    //递归创建目录 createPath("./up/img/ap")
    public function createPath( $path, $mode=0755 ) {
        if ( !is_dir($path) && !@mkdir( $path, $mode, true)) {
            throw new \Exception('创建目录 '. $path .' 失败');
        }
        return true;
    }

    /**
     * 远端文件保存
     * @param string $url
     * @param string $path eg. /ymd/
     * @return array
     * @throws \Exception
     */
    public function remote($url, $path=''){
        $this->fileName = md5($url);
        $this->fileMd5 = false;
        if ($pos = strpos($url, '?')) {
            $url = substr($url, 0, $pos);
        }
        $clientFile = [
            "tmp_name" => "",
            "name" => basename($url),
            "size" => 0,
            "error" => UPLOAD_ERR_OK,
            "type" => "raw_string",
        ];
        $f_name = $this->fileName . strrchr($clientFile['name'], '.');
        if ($path === '') $path = '/' . substr($this->fileName, 0, 1) . '/' . substr($this->fileName, 1, 2) . '/';
        $this->uploadPath = rtrim($this->uploadPath, '/') . $path;
        $this->realPath = rtrim($this->realPath, '/') . $path;

        $realFile = $this->realPath . $f_name;

        if (file_exists($realFile)) {
            $data = $this->defData;
            $data['title'] = $clientFile['name'];
            $data['url'] = $this->uploadPath.$f_name;
            $data['fileType'] = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
            $data['fileSize'] = filesize($realFile);
            return $data;
        } else {
            $clientFile['tmp_name'] = Http::doGet($url);
            if ($clientFile['tmp_name']) {
                //上传目录创建
                $this->createPath($this->realPath);
                $clientFile['size'] = strlen($clientFile['tmp_name']);
            } else {
                $clientFile['error'] = UPLOAD_ERR_NO_FILE;
            }
        }
        return $this->doupload($clientFile);
    }
    /**
     * 返回数据格式
     * {
	 *   'state'    :'1'  //上传状态，成功时返回1,其他任何值将原样返回至图片上传框中
	 *   'title'    :'hello.jpg',   //文件描述，对图片来说在前端会添加到title属性上
     *   'url'      :'a.jpg',   //保存后的文件路径
     *   'fileType' :'jpg',   //文件类型
     *   'fileSize' :'100',   //文件大小
     * }
     */	
	//文件上传表单元素名称
	public function upload($name='upfile') {
        if (!isset($_FILES[$name])) {
            $data = $this->defData;
            $data['state'] = '未选择上传文件！';
            return $data;
        }
        //上传目录创建
        $this->createPath($this->realPath);
        //文件句柄
        $clientFile = $_FILES[$name];
        $data = array();
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
	private function doupload(&$clientFile){
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
        //无效文件名字符验证 '\\', '/', ':', '*', '?', '"', '<', '>', '|'
        if ($clientFile["name"] === '' || strlen($clientFile["name"]) > 250 || preg_match('/[\/:*?"<>|\\\\]/', $clientFile["name"])) {
            $data['state'] = '无效的文件名！';
            return $data;
        }

        //上传框中的描述表单名称，描述内容
        $data['title'] = $clientFile["name"];
        $data['fileType'] = strtolower(pathinfo($clientFile["name"], PATHINFO_EXTENSION));
        //$pos = strrpos($clientFile["name"], '.');
        //$data['fileType'] = $pos ? strtolower(substr($clientFile["name"], $pos + 1)) : '';
        $data['fileSize'] = $clientFile["size"];

        //类型格式验证
        if ($this->fileType != '') {
            $fileType = ',' . $this->fileType . ',';
            if (strpos($fileType, ',' . $data['fileType'] . ',') === false) {
                $data['state'] = "不支持的文件类型！";
                return $data;
            }
        } elseif ($this->notFileType != '') {
            //不允许的类型格式
            $notFileType = ',' . $this->notFileType . ',';
            if (strpos($notFileType, ',' . $data['fileType'] . ',') !== false) {
                $data['state'] = "不支持上传的文件类型！";
                return $data;
            }
        }

        //大小验证
        $file_size = 1024 * 1024 * $this->fileSize;
        if ($data['fileSize'] > $file_size) {
            $data['state'] = "文件大小超出限制！";
            return $data;
        }
        if (!$this->fileZero && $data['fileSize'] == 0) {
            $data['state'] = "文件大小为0字节";
            return $data;
        }

        //保存文件
        $fileUrl = "";
        //是否文件流
        $raw_string = $clientFile['type']=='raw_string';
        $cli = PHP_SAPI == 'cli';
        //cli模式is_uploaded_file无效
        if ($cli || $raw_string || is_uploaded_file($clientFile['tmp_name'])) {//判断文件是上传文件
            $tmp_file = $clientFile["name"];
            $f_name = $this->fileName!=='' ? $this->fileName : str_replace('.', '', uniqid('', true));
            $f_name = $this->fileMd5 ? md5($f_name) : $f_name;//md5文件名加密
            $f_name .= strrchr($tmp_file, '.');
            $fileUrl = $this->uploadPath . $f_name;
            $realFile = $this->realPath . $f_name;

            $save = true;
            if (strpos($this->imgType, ',' . $data['fileType'] . ',') !== false && $this->width > 0 && $this->height > 0) { //指定图片大小处理 使用第三方 Image类 方式一
                Image::$raw_string = $raw_string;
                $result = Image::thumb($clientFile["tmp_name"], $realFile, $this->width, $this->height);
                $save = (0 === $result);
            }
            if ($save) {
                if ($raw_string) {
                    $result = file_put_contents($realFile, $clientFile["tmp_name"]);
                } else {
                    $result = $cli ? rename($clientFile["tmp_name"], $realFile) : move_uploaded_file($clientFile["tmp_name"], $realFile);
                }
            }
            if (!$result) {
                $data['state'] = "文件保存失败";
            }
		} else {
			$data['state'] = '上传文件 '. $clientFile['tmp_name'] .' 不是一个合法文件';
		}

        if ($data['state'] === '1') {
            $data['url'] = $fileUrl;
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
require('Upload.php');
if($_POST['dofile']=='1'){
	echo $_POST['des'].'<br>';
	//上传框中的描述表单名称，描述内容
   	//$title = $des!='' ? htmlspecialchars($_POST[$des], ENT_QUOTES) : '';//编码双引号和单引号
	$upload = Upload::getInstance();
	$upload->setUpPath('app/up/'); //设置文件相对的上传路径
	$upload->setRealPath('app/up/'); //设置文件的绝对路径
	
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