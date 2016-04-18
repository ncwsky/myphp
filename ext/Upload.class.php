<?php
//上传类
class Upload {
	//内部实例对象
	var $filemd5=FALSE; //文件名md5加密
	private static $instance = null;
	//上传配置
	//".gif,.png,.jpg,.jpeg,.bmp"
	private $config = array(
			"uploadPath" => 'up/', //保存路径
			"realPath" => 'up/',
			"max_filename_len" => 250,//文件名长度
			"valid_chars_regex" => '.A-Z0-9_ !@#$%^&()+={}\[\]\',~`-',//有效的文件名字符正则
			"fileType" => "rar,zip,doc,docx,pdf,txt,swf,flv,wmv,png,jpg,jpeg,bmp,gif" , //文件允许格式
			"notFileType" => "asp,asa,aspx,php,jsp",//不允许上传的格式
			"fileSize" => 10 //文件大小限制，单位MB
		);
	private $data = array('state'=>'1', 'title'=>'null', 'url'=>'null', 'fileType'=>'null', 'fileSize'=>'null');
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
	//设置文件大小限制 ,单位MB
	public function setFileSize($val){
		//$val = floatval($val);
		if(empty($val)) return FALSE;
		$this->config['fileSize'] = $val;
	}
	//递归创建目录 createPath ("./up/img/ap")
	private function createPath( $folderPath, $mode=0777 ) {
		$sParent = dirname( $folderPath );
		$result = 1;
		//Check if the parent exists, or create it.
		if (!is_dir($sParent)) createPath( $sParent, $mode );
		if (!is_dir($folderPath)) mkdir($folderPath, $mode) or exit("创建目录 $realpath 失败");
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
	public function upload($name='upfile'){
		//上传目录创建
		$realpath = $this->config['realPath'];
		$this->createPath($realpath);
		//文件句柄
		$clientFile = isset($_FILES[$name]) ? $_FILES[$name] : NULL;
		if(!isset($clientFile)){
			$this->data['state'] = '未选择上传文件！';
			return $this->data;
		}
		$this->data = array();//清空
		//批量上传判断
		if ( is_array($clientFile['name']) ){
			$tmpFile = array();
			for ($i = 0; $i < count($clientFile['name']); $i++) {
				$tmpFile['name'] = $clientFile['name'][$i];
				$tmpFile['type'] = $clientFile['type'][$i];
				$tmpFile['tmp_name'] = $clientFile['tmp_name'][$i];
				$tmpFile['error'] = $clientFile['error'][$i];
				$tmpFile['size'] = $clientFile['size'][$i];
				//传递上传文件信息
				$this->data[] = $this->doupload($tmpFile);
				/*
				$tmpData = $this->doupload($tmpFile);
				$this->data['state'][] = $tmpData['state'];
				$this->data['title'][] = $tmpData['title'];
				$this->data['url'][] = $tmpData['url'];
				$this->data['fileType'][] = $tmpData['fileType'];
				$this->data['fileSize'][] = $tmpData['fileSize'];
				*/
			}
		} else {
			$this->data = $this->doupload($clientFile);
		}
		return $this->data;
	}
	//执行上传保存
	public function doupload($clientFile){
		//上传配置
		$config = $this->config;
		//返回数组初始 文件上传状态,当成功时返回1，其余值将直接返回对应字符窜  $state = "1";
		$data = array('state'=>'1', 'title'=>'null', 'url'=>'null', 'fileType'=>'null', 'fileSize'=>'null');
		//判断文件上传是否出错
		if($clientFile['error']>0){
			switch($clientFile['error']){
				case 1:
					$data['state'] = "上传文件大小超出了PHP配置文件中的约定值:upload_max_filesize";
					break;
				case 2:
					$data['state'] = "上传文件大小超出了表单中的约定值:MAX_FILE_SIZE";
					break;
				case 3:
					$data['state'] = "文件只被部分上载";
					break;
				case 4:
					$data['state'] = "没有上传任何文件";
					break;
				default:
					$data['state'] = "未知错误";
			}
			return $data;
		}
		//有效文件名字符验证
		$file_name = preg_replace('/[^'.$config['valid_chars_regex'].']|\.+$/i', "", basename($clientFile[ "name" ]));
		if (strlen($file_name) == 0 || strlen($file_name) > $config['max_filename_len']) {
			$data['state'] = '无效的文件名！';
			return $data;
		}
		//上传框中的描述表单名称，描述内容
   		$data['title'] = htmlspecialchars($clientFile[ "name" ], ENT_QUOTES);
		$data['fileType'] = strtolower( substr($clientFile[ "name" ], strrpos($clientFile[ "name" ], '.')+1) );
		$data['fileSize'] = $clientFile[ "size" ];

		//类型格式验证
		$fileType = explode(',', $config[ 'fileType' ]);
		if ( !in_array( $data['fileType'] , $fileType ) ) {
			$data['state'] = "不支持的文件类型！";
			return $data;
		}
		$notFileType = explode(',', $config[ 'notFileType' ]);
		//不允许的类型格式
		if ( in_array( $data['fileType'] , $notFileType ) ) {
			$data['state'] = "不支持上传的文件类型！";
			return $data;
		}
		//大小验证
		$file_size = 1024 * 1024 * $config[ 'fileSize' ];
		if ( $data['fileSize'] > $file_size ) {
			$data['state'] = "文件大小超出限制！";
			return $data;
		}
		//保存文件
		$path = $config[ 'uploadPath' ];
		$realpath = $config[ 'realPath' ];
		$fileurl = "";
		if(is_uploaded_file($clientFile['tmp_name'])){//判断文件是否是上传文件
			$tmp_file = $clientFile[ "name" ];
			$f_name = rand( 1 , 10000 ) . time();
			$f_name = $this->filemd5 ? md5($f_name) : $f_name;//md5文件名加密
			$f_name .= strrchr( $tmp_file , '.' );
			$fileurl = $path . $f_name;
			$realfile = $realpath . $f_name;
			$result = move_uploaded_file( $clientFile[ "tmp_name" ] , $realfile );
			if ( !$result ) {
				$data['state'] = "文件保存失败！";
			}
		} else {
			$data['state'] = '上传文件 '. $clientFile['tmp_name'] .' 不是一个合法文件！';
		}

	 	if ($data['state']=='1') {
			//$data['title'] = $title;
			$data['url'] = $fileurl;
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
	if (is_array($data['state'])){//多个文件上传
		for($i=0;$i<count($data['state']);$i++){
			if ($data['state'][$i]=='1'){
				echo '上传成功：'. $data['url'][$i] .'，类型：'. $data['fileType'][$i] .'，大小：'. $data['fileSize'][$i] .'<br>';
			} else {
				echo '上传失败：'. $data['url'][$i] .'，类型：'. $data['fileType'][$i] .'，大小：'. $data['fileSize'][$i] .'<br>';
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
?>