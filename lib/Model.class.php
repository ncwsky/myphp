<?php
//数据模型类
class Model {
	private $sqlCount = 0; //sql执行次数
	public $sql = ''; //完整的Sql
	//内部数据连接对象,记录集,缓存,配置数组
	private $db = null;
	private $cache = null;
	private $config = null;
	private $log_type = 0; //是否记录sql
	// 表名（不包含表前缀）
    //protected $table = NULL;
	// 当前数据库操作对象配置名
    protected $name = null;
	// 数据信息 被重载的保存在此
    protected $data = null;
	protected $options = null;
	// 链操作方法列表
    protected $methods = ',order,group,having,limit,table,fields,'; //where,

	// 构造函数  $name 数据库配置名, $dbcfg name为null时的数据库配置
    public function __construct($name='db',$dbcfg=array()) {
		if (is_null($name)){//单独指定数据库配置
			if (!empty($dbcfg)) exit('请载入数据库连接数组配置信息！');
			$this->config = &$dbcfg;
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
			$this->config = &$GLOBALS['cfg'][$this->name];
		}

		if (empty($this->config['type'])) exit('未正确配置数据库连接信息！');
		
		$db_type = $this->config['type'];
		if(!file_exists(MY_PATH.'/lib/db/db_'.$db_type.'.class.php')) exit($db_type.'数据库类型没有驱动');
		require_once(MY_PATH.'/lib/db/db_'.$db_type.'.class.php');//加载数据库类
		//load_class($db_type,MY_PATH.'/lib/db/db_',false);//加载数据库类

		$db_type = 'db_'.$db_type;
		if(class_exists($db_type)) { // 检查类
			$this->db = new $db_type($this->config);//连接数据库
		}else {
			exit($db_type.'数据库类没有定义');
		}
	}
	//启用或关闭SQL记录 依赖Log类 0不记录 1仅execute的sql 2全部sql
	public function log_on($bool=2){
		$this->log_type=$bool;
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
	//连贯操作
	public function __call($method, $args) {
		if(strpos($this->methods, $method)!==false && isset($args[0])){
			$this->options[$method] = $args[0];
            return $this;
		}else{
            throw new myException(__CLASS__.':'.$method.'方法无效');
            return;
        }
	}
    // 为表设置前缀
    public function prefix($val){
    	$this->config['prefix'] = $val;
    }
	//安全转义
	public function quote($val){
		return $this->db->quote($val);
	}
	/* where处理 
	 * $case string|array (string:条件语句可绑定参数[$bind设参数数组], array:条件数组[$bind可设or|and])
	 * $bind string|array (string:or|and, array:要解析的参数)
	*/
	public function where($case, $bind=null){ //and
		$this->_where($case, $bind, (is_string($case) && (string)$bind=='or')?false:true);
		return $this;
	}
	public function whereOr($case, $bind=null){ //or
		$this->_where($case, $bind, false);
		return $this;
	}
	private function _where($case, $bind=null, $and=true){ //and[true:and,false:or]
		$where = '';
		if(is_array($case)){
			$bind = (string)$bind=='or'?'or':'and';
			foreach($case as $k=>$v){
				$field = $k.'='.$this->db->parseValue($v);;
				$where = $where==''?$field:' '.$bind.' '.$field;
			}
		}elseif(is_string($case)){
			$where = is_array($bind)?$this->get_real_sql($case, $bind):$case;
		}
		if($where!=''){
			$this->options['where'] = empty($this->options['where']) ? $where : ($and ? $this->options['where'].' and '.$where : '('.$this->options['where'].' or '.$where.')');
		}
		return true;
	}
	//sql绑定编译
	public function get_real_sql($sql, $bind = null){
		$this->db->get_real_sql($sql, $bind);
		return $sql;
	}
	//sql处理 记数
	private function _run_init(&$sql, $bind=null, $log_curd=false){
		$this->db->chksql($sql);
		!isset($this->config['prefix']) && $this->config['prefix']='';
		$sql = str_replace('{db_prefix}', $this->config['prefix'], trim($sql)); // 替换表前缀
		$this->db->get_real_sql($sql, $bind); //解析绑定参数
		$this->sqlCount++;
		$this->sql = $sql;
		if($this->log_type==2 || ($this->log_type==1 && $log_curd))
			Log::trace($sql,'SQL');
		N('sql',1);
	}
	//执行查询
	public function execute($sql, $bind=null) {
		$this->_run_init($sql, $bind, true);
		$this->options = null;
		
		return $this->db->exec($sql);
	}
	//执行查询 返回数据 $bind[array:绑定数据, true:直接返回查询数据],$is_arr $bind为array时才有效
	public function query($sql, $bind=null, $is_arr=false) {
		$this->_run_init($sql, $bind);
		$this->options = null;
		
		!is_array($bind) && $is_arr = $bind;
		$rs = $this->db->query($sql);
		if($is_arr){
			$data = array();//可以 读取缓存
			while($row = $this->db->fetch_array($rs)) {
				$data[] = $row;
			}
			//可以 设置缓存
			return $data;
		}else{
			return $rs;
		}
	}
	//获取记录 简单单表查询
	function select($table='', $where = '', $order='', $fields = '*', $limit=''){
		if(isset($this->options['table'])) $table=$this->options['table'];
		if(isset($this->options['fields'])) $fields=$this->options['fields'];
		if(isset($this->options['limit'])) $limit='TOP '.$this->options['limit'].' ';
		if(isset($this->options['order'])) $order=$this->options['order'];
		if(isset($this->options['where'])) $where=$this->options['where'];
		
		$this->_table($table);
		$sql = 'SELECT '.$limit.$fields.' FROM '.$table;
		
		if($where!='') $sql .= ' WHERE '.$where;
		if(isset($this->options['group'])) $sql .= ' GROUP BY '.$this->options['group'];
		if(isset($this->options['having'])) $sql .= ' HAVING '.$this->options['having'];
		if($order!= '') $sql .= ' ORDER BY '.$order;
		
		return $this->query($sql,TRUE);	
	}
	function find($table='', $where='', $order='', $fields = '*'){
		if(isset($this->options['table'])) $table=$this->options['table'];
		if(isset($this->options['fields'])) $fields=$this->options['fields'];
		if(isset($this->options['order'])) $order=$this->options['order'];
		if(isset($this->options['where'])) $where=$this->options['where'];

		$this->_table($table);
		$sql = 'SELECT '.$fields.' FROM '.$table;
		
		if($where!='') $sql .= ' WHERE '.$where;
		if(isset($this->options['group'])) $sql .= ' GROUP BY '.$this->options['group'];
		if(isset($this->options['having'])) $sql .= ' HAVING '.$this->options['having'];
		if($order!= '') $sql .= ' ORDER BY '.$order;

		return $this->getOne($sql);
	}
	//添加记录
	function add_sql($post, $table='') {
		$field=''; $value='';
		foreach ($post as $k=>$v) {
			$field .= ',`'. $k .'`';
			//val值得预先过滤处理
			$value .= ",". $this->db->parseValue($v);
		}
		$field=substr($field, 1); $value=substr($value, 1);
		if(isset($this->options['table'])) $table=$this->options['table'];
		
		$this->_table($table);
		$sql = "INSERT INTO $table($field) VALUES($value)";
		return $sql;//返回执行sql
	}
	function add($post, $table='') {
		$this->execute($this->add_sql($post, $table));
		return $this->getInsertId();//返回插入记录的编号
	}
	//更新记录
	function update_sql($post, $table='', $where = '') {
		$value='';
		foreach ($post as $k=>$v) {
			//val值得预先过滤处理
			$value .= "`$k` = ".$this->db->parseValue($v).",";
		}
		$value=rtrim($value, ",");
		
		if(isset($this->options['table'])) $table=$this->options['table'];
		if(isset($this->options['where'])) $where=$this->options['where'];

		$this->_table($table);
		$sql = 'UPDATE '.$table.' SET '.$value;
		if($where!='') $sql .= ' WHERE '.$where;
		return $sql;//返回修改记录的行数
	}
	function update($post, $table='', $where = '') {
		return $this->execute($this->update_sql($post, $table, $where)); //返回修改记录的行数
	}
	//删除记录
	function del($table='', $where = '') {
		if(isset($this->options['table'])) $table=$this->options['table'];
		if(isset($this->options['where'])) $where=$this->options['where'];
		
		$this->_table($table);
		$sql = 'DELETE FROM '.$table;
		if($where!='') $sql .= ' WHERE '.$where;
		
		return $this->execute($sql); //返回修改记录的行数
	}
	//获取指定查询表的行数
	public function getCount($table='', $where = '', $field='*') {
		if(isset($this->options['table'])) $table=$this->options['table'];
		if(isset($this->options['where'])) $where=$this->options['where'];
		if(isset($this->options['fields'])) $field=$this->options['fields'];

		if($field!='*') $field = strpos($field,'.')===FALSE?"`$field`":'`'.str_replace('.', '`.`', $field).'`';
		
		$this->_table($table);
		$sql = 'SELECT COUNT('.$field.') FROM '.$table;
		if($where!='') $sql .= ' WHERE '.$where;
		
		$row = $this->getOne($sql,'num');
		return $row[0];
	}
	//获取指定字段最新值
	public function getLastId($table, $idName, $caseSql = '', $orderBy = ''){
		$this->_table($table);
		$sql = "SELECT TOP 1 `$idName` FROM $table";
		if ($caseSql != '') $sql .= ' WHERE '.$caseSql;
		if ($orderBy == '') $orderBy = $idName;
		$sql .= ' ORDER BY '. $orderBy .' DESC';
		
		$row = $this->getOne($sql,'num');
		return is_array($row)?$row[0]:FALSE;
	}
	//获取自定字段值
	public function getCustomId($table, $idName, $caseSql = '', $orderBy = ''){
		$this->_table($table);
		$sql = "SELECT TOP 1 `$idName` FROM $table";
		if ($caseSql != '') $sql .= ' WHERE '.$caseSql;
		if ($orderBy != '') $sql .= ' ORDER BY '. $orderBy;
		
		$row = $this->getOne($sql,'num');
		return is_array($row)?$row[0]:FALSE;
	}
	//执行一个SQL语句,仅返回一条记录 $bind[array:绑定数据],$type $bind为array时才有效
	public function getOne($sql, $bind=null, $type = 'assoc') {
		if(is_array($bind)){
			$result = $this->query($sql, $bind);
		}else{
			$bind && $type = $bind;
			$result = $this->query($sql);
		}
		$row = $this->db->fetch_array($result, $type);//无记录返回FALSE
		return $row;
	}
	//返回当前的一条记录并把游标移向下一记录
	public function fetch_array($rs=null, $type = 'assoc') {
		if($rs===null) $rs=$this->db->rs;
		return $this->db->fetch_array($rs, $type);
	}
	//开始一个事务，关闭自动提交
	public function beginTrans(){
		return $this->config['type']=='pdo' && $this->db->conn->beginTransaction();
	}
	//提交事务 返回到自动提交模式
	public function commit(){
		return $this->config['type']=='pdo' && $this->db->conn->commit();
	}
	//回滚当前事务
	public function rollBack(){
		return $this->config['type']=='pdo' && $this->db->conn->rollback();
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
	//是否给表名增加关键字冲突处理符号
	private function _table(&$tb){
		$tb = strpos($tb,'.')||strpos($tb,' ')?$tb:'`'.$tb.'`';
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
	public $conn = null; //连接实例
	public $rs = null; //数据集
	protected $dbms = 'mysql'; //数据库名
	
	public function __construct(&$config = array()) {
		isset($config['dbms']) && $this->dbms = $config['dbms'];
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
	abstract public function connect(&$cfg_db);
	//安全过滤 是字符串时在格式后需要使用单引号包括返回
	abstract protected function quote($str);
	//执行sql 返回影响的行数
	abstract public function exec(&$sql);
	//执行查询
	abstract public function query(&$sql);
	/**
	 * 从结果集中取得一行作为关联数组/数字索引数组
	 * $query 数据集
	 * $type  默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
	 */
	abstract public function fetch_array(&$query, $type = 'assoc');
	//取得结果集行的数目
	abstract public function num_rows();
	//取得结果集中字段的数目
	abstract public function num_fields();
	//取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
	abstract public function insert_id();
	//根据绑定参数组装SQL语句 不允许子类覆盖
	final public function get_real_sql(&$sql, $bind = null){
		if (is_array($bind)) {
			$pos = 0;
            foreach ($bind as $key => $val) {
                $val = $this->parseValue($val);
				$isnum = is_numeric($key);
                // 判断占位符
                $sql = $isnum ? substr_replace($sql, $val, $pos=strpos($sql, '?', $pos), 1) :
                str_replace(
                    array(':' . $key . ')', ':' . $key . ',', ':' . $key . ' '),
                    array($val . ')', $val . ',', $val . ' '),
                    $sql . ' ');
				$isnum && $pos += strlen($val);
            }
        }
	}
	final public function parseValue(&$val) {
        if(is_string($val)) {
            $val = $this->quote($val);
        }elseif(is_null($val)){
            $val = 'null';
        }
        return $val;
    }
	//对sql部分语句进行转换
	public function chksql(&$sql) {
		//$sql = str_replace(array('[', ']'), '`', $sql);
		switch($this->dbms){
			case 'mssql':
			case 'oracle':
			case 'pgsql':
			case 'sqlite':
				$sql = str_replace('`', '"', $sql);break;
		}
		
		if (stripos($sql, 'select top')!==FALSE) {
			//([0-9]+(,[0-9]+)?) | ([0-9]+)
			if (preg_match('/^(select top )([0-9]+(,[0-9]+)?)/i', $sql, $topArr)) {
				//$sql = str_replace($topArr[0], 'SELECT', $sql);
				$pos = strpos($topArr[2],',');
				$limit = 0;
				if($pos!==FALSE) {
					$offset = substr($topArr[2],0,$pos);
					$limit = substr($topArr[2],$pos+1);
				}else {
					$offset = $topArr[2];
				}
				if($limit==0){
					$max = $offset;$min = 0;
				}else{
					$max = $offset+$limit;$min = $offset;	
				}
				switch($this->dbms){
					case 'mysql':
						$sql = str_replace($topArr[0], 'SELECT', $sql);
						$sql .= ' LIMIT ' . $topArr[2];
						break;
					case 'mssql'://mssql2005及以上版本  //支持top
						if($pos!==FALSE){
							$sql = str_replace($topArr[0], 'SELECT', $sql);

							$order = '(order by .* (asc|desc))';
							if(preg_match('/(order by .* (asc|desc))/i', $sql, $order)){
								$sql = str_replace($order[0], '', $sql);
								$sql = 'SELECT T1.* FROM (SELECT myphp.*, ROW_NUMBER() OVER ('.$order[0].') AS ROW_NUMBER FROM ('.$sql.') AS myphp) AS T1 WHERE (T1.ROW_NUMBER BETWEEN '.$min.'+1 AND '.$max.')';
							}else{
								$sql = preg_replace('/^SELECT /', 'SELECT TOP '.$max, $sql);
								if($min>0) 
									$sql = 'SELECT TOP '.$min.' * FROM ('.$sql.') AS T1';
							}
						}
						break;
					case 'oracle': //支持top
						if($pos!==FALSE){
							$sql = str_replace($topArr[0], 'SELECT', $sql);
							$sql = 'SELECT * FROM ( SELECT "tmp".*, ROWNUM AS "row_num" FROM ('.$sql.') "tmp" WHERE ROWNUM<='.$max.') WHERE "row_num">'.$min;
						}
						break;
					case 'pgsql':
					case 'sqlite':
						$sql = str_replace($topArr[0], 'SELECT', $sql);
						if($min>0) 
							$sql .= ' LIMIT ' .$max.' OFFSET '.$min;
						else
							$sql .= ' LIMIT ' .$max;
						break;
				}
			}
			unset($topArr);
		}
		if (stripos($sql, 'CONCAT')!==FALSE) {
			switch($this->dbms){
				case 'mysql':
					break;
				case 'mssql'://mssql2005及以上版本
				case 'oracle':
				case 'pgsql':
				case 'sqlite':
					$sql = preg_replace_callback('/CONCAT(\([^\)]*?\))/i',$this->concat($matches),$sql);
					break;
			}
		}
		//Log::trace($sql);
	}
	protected function concat($matches){return str_replace(',','+',$matches[1]);}
	//析构方法
    public function __destruct() {
        //释放结果集
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