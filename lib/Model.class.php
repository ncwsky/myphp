<?php
//数据模型类
class Model {
	static private $instance = array(); // 数据库连接实例
    static private $_instance = null; // 当前数据库连接实例
	//sql执行次数
	private $sqlCount = 0;
	//内部数据连接对象,记录集,缓存,内部数据结果集,配置数组
	private $db = null;
	private $rs = null;
	private $cache = null;
	private $config = null;
	// 表名（不包含表前缀）
    //protected $table = NULL;
	// 当前数据库操作对象配置名
    protected $name = null;
	// 数据信息 被重载的保存在此
    protected $data = null;
	protected $options = null;
	// 链操作方法列表
    protected $methods = ',where,strict,order,alias,having,group,distinct,index,force,';

	// 构造函数  $name 数据库配置名, $dbcfg name为null时的数据库配置
    public function __construct($name='db',$dbcfg=array()) {
		if (is_null($name)){//单独指定数据库配置
			if (!empty($dbcfg)) exit('请载入数据库连接数组配置信息！');
			$this->config = $dbcfg;//对配置信息存放config数组中
			/*
			array(
				'type' => 'pdo',	//数据库类型 仅有mysql、pdo
				'dbms' => 'mysql', //数据库
				'server' => 'localhost',	//数据库主机
				'name' => '9ecms',	//数据库名称
				'user' => 'root',	//数据库用户
				'pwd' => '123456',	//数据库密码
				'port' => 3306,     // 端口
				'char' => 'utf8',	//数据库编码
				'prefix' => ''	//数据库表前缀 9e_
			)
			*/
		}else{
			$this->name = 'db'; //默认数据库配置名
			if($name!='') $this->name = $name;
			//if(strpos($name, '.')) list($this->name,$this->table) = explode('.',$name);
			//if(empty($this->table)) $this->table = substr(get_class($this),0,-strlen('Model'));
			if(!isset($GLOBALS['cfg'][$this->name]))
				exit($this->name.'数据库连接数组配置不存在');
			$this->config = $GLOBALS['cfg'][$this->name];
		}
		//$this->db = Db::set_adapter($this->name, $this->config);//数据库实例化或切换
		
		if (empty($this->config['type'])) exit('未正确配置数据库连接信息！');
		
		$db_type = $this->config['type'];
		if(!file_exists(MY_PATH.'/lib/db/db_'.$db_type.'.class.php')) exit($db_type.'数据库类型没有驱动');
		require_once(MY_PATH.'/lib/db/db_'.$db_type.'.class.php');//加载数据库类
		//load_class($db_type,MY_PATH.'/lib/db/db_',false);//加载数据库类
		// 检查驱动类
		$db_type = 'db_'.$db_type;
		if(class_exists($db_type)) {
			$this->db = new $db_type($this->config);//连接数据库
		}else {
			exit($db_type.'数据库类没有定义');
		}
	}
	// 设置数据对象属性
	public function __set($name, $value) {
        $this->data[$name] = $value;
    }
	// 获取数据对象的值
    public function __get($name) {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
	//检测数据对象的值
    public function __isset($name) {
        return isset($this->data[$name]);
    }
    //销毁数据对象的值
    public function __unset($name) {
        unset($this->data[$name]);
    }
	//连贯操作  where,data,table,join,union,cache,field  add,del,update,select([$offset,]$rows)
	public function __call($method_name, $args) {
		$method_name = strtolower($method_name);
		if(strpos($this->methods, $method_name)!==false){
			if($args) $this->options[$method_name] = $args[0];
			echo $method_name;
            return $this;
		}elseif (method_exists($this, $method_name)){
			echo $method_name;
            return $this;
			// return call_user_func_array(array(& $this, $method_name), $args);
		}else{
            throw new myException(__CLASS__.':'.$method_name.'方法不存在！');
            return;
        }
	}
    // 为表设置前缀
    public function prefix($val){
    	$this->config['prefix'] = $val;
    }

    protected function parseValue($value) {
        if(is_string($value)) {
            $value = '\''.$value.'\'';
        }elseif(is_null($value)){
            $value   =  'null';
        }
        return $value;
    }
	//执行查询
	public function execute($sql) {
		$sql = str_replace('{db_prefix}', $this->config['prefix'], $sql); // 替换表前缀
		$this->sqlCount++;
		N('sql',1);
		return $this->db->query($sql);
	}
	//添加记录
	function add($post, $tabName) {
		$field='';
		$value='';
		foreach ($post as $k=>$v) {
			$field .= '`'.$k . '`,';
			//val值得预先过滤处理
			$value .= $this->parseValue($v).",";
		}
		$field=rtrim($field, ",");
		$value=rtrim($value, ",");

		$sql = "INSERT INTO `{$tabName}` ($field) VALUES($value)";
		$this->execute($sql);
		return $this->getInsertId();//返回插入记录的编号
	}
	//更新记录
	function update($post, $tabName, $casesql = '1') {
		$value='';
		foreach ($post as $k=>$v) {
			//val值得预先过滤处理
			$value .= "`$k` = ".$this->parseValue($v).",";
		}
		$value=rtrim($value, ",");

		$sql = "UPDATE `{$tabName}` SET {$value} WHERE $casesql";
		$this->execute($sql);
		return $this->getRows();//返回修改记录的行数
	}
	//删除记录
	function del($tabName, $casesql = '1') {
		$sql = "DELETE FROM `{$tabName}` WHERE $casesql";
		$this->execute($sql);
		return $this->getRows();//返回修改记录的行数
	}
	//获取记录 简单单表查询
	function select($tabName,$casesql = '1',$orderBy='',$fields = '*',$limit=''){
		if ($orderBy != '') $orderBy = 'Order By '.$orderBy;
		$sql = "SELECT $limit $fields FROM `{$tabName}` WHERE $casesql $orderBy";
		return $this->query($sql,TRUE);	
	}
	//执行查询 返回数据 is_arr 是否返回二维数据
	public function query($sql, $is_arr = false) {
		$this->rs = $this->execute($sql);
		if($is_arr){
			$data = array();//可以 读取缓存
			while($row = $this->db->fetch_array($this->rs)) {
				$data[] = $row;
			}
			//可以 设置缓存
			return $data;
		}else{
			return $this->rs;
		}
	}
	//获取指定字段最新值
	public function getLastId($tb, $idName, $caseSql = '', $orderBy = ''){
		if ($orderBy == '') $orderBy = $idName;
		if ($caseSql != '') 
			$sql = "SELECT TOP 1 `$idName` FROM `$tb` WHERE $caseSql ORDER BY `$orderBy` DESC";
		else
			$sql = "SELECT TOP 1 `$idName` FROM `$tb` ORDER BY `$orderBy` DESC";
		//使用getOne获取此行记录
		$row = $this->getOne($sql);
		if (!is_array($row)) {
			return FALSE;
		} else {
			return $row[0];
		}
	}
	//获取自定字段值
	public function getCustomId($tb, $idName, $caseSql = '', $orderBy = ''){
		if ($orderBy != '') $orderBy = 'ORDER BY '. $orderBy;
		if ($caseSql != '') 
			$sql = "SELECT TOP 1 `$idName` FROM `$tb` WHERE $caseSql $orderBy";
		else
			$sql = "SELECT TOP 1 `$idName` FROM `$tb` $orderBy";
		//使用getOne获取此行记录
		$row = $this->getOne($sql);
		if (!is_array($row)) {
			return FALSE;
		} else {
			return $row[0];
		}
	}
	//执行一个SQL语句,仅返回一条记录
	public function getOne($sql,$type = 'both') {
		$result = $this->execute($sql);
		$row = $this->db->fetch_array($result, $type);//无记录返回FALSE
		//unset($result);
		return $row;
	}
	//获取指定查询表的行数
	public function getCount($tb, $caseSql = '', $id='*') {
		if($id!='*') $id = strpos($id,'.')===FALSE?"`$id`":'`'.str_replace('.', '`.`', $id).'`';
		$tb = strpos($tb,' ')===FALSE?"`$tb`":$tb;
		$sql = 'SELECT COUNT('.$id.') FROM '.$tb;
		$sql .= $caseSql == '' ? '' : ' WHERE ' . $caseSql;
		//echo $sql .'<br>';
		$row = $this->getOne($sql);
		return $row[0];
	}
	//获取记录集行数
	public function getNumRows() {
		return $this->db->num_rows();	
	}
	//返回当前的一条记录并把游标移向下一记录
	public function fetch_array($rs = NULL, $type = 'assoc') {
		if($rs == NULL) $rs = $this->rs;
		return $this->db->fetch_array($rs, $type);
	}
	//获取影响行数
	public function getRows() {
		return $this->db->num_rows();
	}
	//获取最后插入记录的 自动id
	public function getInsertId() {
		return $this->db->insert_id();	
	}
	//取得数据库查询次数
	public function getSqlCount() {
		return $this->sqlCount;
	}
	
	//初始化缓存类，如果开启缓存，则加载缓存类并实例化
	public function initCache() {
		$this->cache = Cache::getInstance();
		$this->cache->setCacheDir(ROOT . PUB.'/cache/sql/'); //设置存放缓存文件夹路径
		$this->cache->setCacheMode('2');
	}
	//查询缓存 读取
	private function readCache() {
		
	}
	//查询缓存 写入
	private function writeCache() {
		
	}
}

/* 
 * 数据库
 */
abstract class db_abstract{
	protected $conn = null; //连接实例
	protected $rs = null; //数据集
	
	public function __construct($config = array()) {
		$this->connect($config);
	}
	//关闭数据库连接
	public function close() {
		$this->conn = NULL;
	}
	//释放结果集
	public function free() {
		$this->rs = NULL;
	}
	
	// 连接数据库 $cfg_db array数据库配置
	abstract public function connect($cfg_db=array());
	//对sql部分语句进行转换
	abstract protected function chksql(&$sql);
	//执行查询语句 $sql 执行语句
	abstract public function query($sql);
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $query 数据集
	 * $type  默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
	 */
	abstract public function fetch_array($query, $type = 'assoc');
	// 取得结果集行的数目
	abstract public function num_rows();
	//取得结果集中字段的数目
	abstract public function num_fields();
	//取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
	abstract public function insert_id();

	//析构方法
    public function __destruct() {
        //释放结果集
        if ($this->rs)
            $this->free();
        $this->close();// 关闭连接
    }
}

// 数据查询分页
class cls_query_page{
	var $db;
	var $where=''; //条件语句
	var $where_c=''; //总数条件语句,为空默认使用$where
	var $id = '*'; //总数统计关键字段名 : id或*
	var $fields = '*'; //需要查询的字段列表 联合查询时 指定字段注意带上所属表名（别名）
	var $orderby = ''; //指定排序方式 如 id desc
	var $table = ''; //表名 可多个表联合查询
	
	var $num=20; //每页显示数
	var $page_num = 5; // 数字页码范围*2
	var $page_name = 'page'; //默认分页get变量名
	var $url = ''; //分页链接  页码值替换字符串"{$page}"
	var $page = ''; //分页结果内容
	var $total=0,$curr_page=0;
	var $lng_next='下一页',$lng_pre='上一页',$lng_first='首页',$lng_last='尾页',$lng_total='总';

	// 构造函数
    public function __construct(&$db=null) {
		if(gettype($db)=='object') $this->db=$db;
		//$lng_next,$lng_pre,$lng_first,$lng_last,$lng_total
    }
	//参数说明：TotalResult(记录条数),Page_Size(每页记录条数),CurrentPage(当前记录页),paraUrl(URL参数)
	//数据分页查询 获取相关分页数据  $isData 是否直接返回数据数组  返回查询结果数组或资源
	public function query($isData=true){
		if(!isset($this->db) || gettype($this->db)!='object'){
			//out_msg('0:未指定数据库','err');
			if(GetC('db.name')!='') $this->db = M();	//基类模型;
			else out_msg('0:未指定数据库','err');
		}
		if($this->table=='') out_msg('0:未指定表','err');
		if($this->fields=='') $this->fields='*';

		$this->where_c = $this->where_c==''? $this->where : $this->where_c;
		$orderby = $this->orderby!='' ? ' ORDER BY '.$this->orderby : '';
		
		$this->total = $this->db->getCount($this->table, $this->where_c, $this->id); //获取总行数
		$this->curr_page = isset($_GET[$this->page_name]) ? intval($_GET[$this->page_name]) : 1; //当前页码
		if ($this->curr_page<1) $this->curr_page=1; //对当前页面验证（大于＝1）
		//获取 
		if($this->curr_page==1){
			$limit = 'TOP '.$this->num;
		} else {
			$offset = ($this->curr_page-1)*$this->num;
			$limit = 'TOP '. $offset .','. $this->num;
		}
		$this->page = PageList4($this->total, $this->num, $this->curr_page, $this->url, $this->page_num, $this->page_name);//分页结果
		
		$sql = 'SELECT '. $limit .' '.$this->fields.' FROM '.$this->table.($this->where!='' ? ' WHERE '.$this->where : '').$orderby;
		return $this->db->query($sql, $isData);
	}
}