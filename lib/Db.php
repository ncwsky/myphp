<?php
namespace myphp;

/**
 * Class Db 数据db类
 * @method Db group($val)
 * @method Db having($val)
 * @method Db idx($val)
 * @method Db limit($val)
 * @method Db order($val)
 * @method Db table($val)
 * @property string group
 * @property string having
 * @property string idx
 * @property string limit
 * @property string lock
 * @property string order
 * @property string table
 */
class Db {
	public static $sql = ''; //完整的Sql
    public static $times = 0; //执行次数
    private static $log_type = 0; //是否记录sql
    private static $instance = [];
    /**
     * @var \myphp\db\db_pdo|\myphp\db\db_mysqli
     */
    private $db;
    /**
     * @var \myphp\cache\File 缓存
     */
    private $cache;
    /**
     * @var array 配置
     */
    private $config = [
        'type' => 'pdo',   //数据库连接类型 仅pdo、mysqli
        'dbms' => 'mysql', //数据库
        'server' => '',    //数据库主机
        'name' => '',   //数据库名称
        'user' => '',   //数据库用户
        'pwd' => '',    //数据库密码
        'port' => '',   // 端口
        'char' => 'utf8', //数据库编码
        'prefix' => '',  //数据库表前缀
        'prod' => false  //生产环境
    ];
    /**
     * @var array 链操作方法信息
     */
    private $options;
    public $resetOption = true;
	//链操作方法列表
    private $methods = ',group,having,idx,limit,order,table,';
    //表字段信息
    private $tbFields = [];
    //特殊符 默认mysql
    private $startSpec = '`';
    private $endSpec = '`';

    /**
     * Db constructor.
     * @param string|array $conf
     * @param bool $force 是否强制生成新实例
     * @throws \Exception
     */
    public function __construct($conf='db', $force=false) {
        $key = '';
        if (is_string($conf)) {
            if (!isset(\myphp::$cfg[$conf])) throw new \Exception($conf . 'DB连接配置不存在');

            $key = $conf;
            $this->config = array_merge($this->config, \myphp::$cfg[$conf]);
        } else {
            $this->config = array_merge($this->config, $conf);
            $key = $this->config['dbms'] . $this->config['server'] . $this->config['name'] . $this->config['port'];
        }

        if ($force || !isset(self::$instance[$key])) {
            $db_type = '\myphp\db\db_' . $this->config['type'];
            $this->db = new $db_type($this->config);//连接数据库
            self::$instance[$key] = $this->db;//if (false === $force)
        } else {
            $this->db = self::$instance[$key];
        }

        switch ($this->config['dbms']) {
            case 'mssql':
            case 'oracle':
            case 'pgsql':
            case 'sqlite':
                $this->startSpec = '"';
                $this->endSpec = '"';
                break;
        }
	}
	//释放资源
	public static function free($name='db'){
        unset(self::$instance[$name]);
        \myphp::free('__db_'.$name);
    }
	//启用或关闭SQL记录 依赖Log类 0不记录 1仅execute的sql 2全部sql
	public static function log_on($bool=2){
		self::$log_type=$bool;
	}
	// 获取数据对象的值
    public function __get($name) {
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }
    //销毁数据对象的值
    public function __unset($name) {
        unset($this->options[$name]);
    }
	//连贯操作
	public function __call($method, $args) {
		if(strpos($this->methods, $method)!==false && isset($args[0])){
			$this->options[$method] = $args[0];
            return $this;
		}else{
            throw new \Exception(__CLASS__.':'.$method.'方法无效');
        }
	}
	//获取最后执行的Sql
	public function getSql(){
	    return self::$sql;
    }
    //取得数据表的字段信息
    public function getFields($tb, &$prikey='', &$fields='*', &$rule=array(), &$autoKey=''){
        if (!isset($this->tbFields[$tb])) {
            if ($this->config['prod']) {
                $this->initCache();
                $fieldInfo = $this->cache->get($tb);
            }
            if (!isset($fieldInfo) || $fieldInfo === false) {
                $fieldInfo = array('fields' => '*', 'prikey' => '', 'auto_increment'=>'', 'rule' => array());
                $tb_type = '\myphp\db\tb_' . $this->config['dbms'];
                if (class_exists($tb_type)) {
                    $tbName = $tb;
                    /**
                     * @var \myphp\db\tb_mysql|\myphp\db\tb_sqlite $tbType
                     */
                    $tbType = new $tb_type();
                    $this->_table($tbName, false);
                    $fieldInfo = $tbType->getFields($this->db, $tbName);
                }
                if ($this->config['prod']) { #生成模式下提前解析
                    foreach ($fieldInfo['rule'] as $k=>$rule){
                        $type = 's'; $min = $max = null;
                        Value::parseType($rule['rule'], $type, $min, $max);
                        $fieldInfo['rule'][$k]['rule'] = [$type, 'min' => $min, 'max' => $max];
                    }
                    $this->cache->set($tb, $fieldInfo);
                }
            }
            $this->tbFields[$tb] = $fieldInfo;
        }
        $prikey = $this->tbFields[$tb]['prikey'];
        $fields = $this->tbFields[$tb]['fields'];
        $rule = $this->tbFields[$tb]['rule'];
        $autoKey = $this->tbFields[$tb]['auto_increment']; //自增键
        return $this->tbFields[$tb];
    }
    //取得当前数据库的表信息
    public function getTables(){
        $tables = array();
        $tb_type = '\myphp\db\tb_' . $this->config['dbms'];
        if (class_exists($tb_type)) {
            $tbType = new $tb_type();
            $tables = $tbType->getTables($this->db);
        }
        return $tables;
    }
	//安全转义
	public function quote($val){
		return $this->db->quote($val);
	}
    final public function parseValue($val) {
        if ($val instanceof Expr) { //表达式
            return $val;
        }
        if(is_string($val)) {
            $val = $this->quote($val);
        }
        elseif(is_array($val)){
            $val = array_map(array($this, 'parseValue'), $val);
        }
        elseif(is_null($val)){
            $val = 'null';
        }
        return $val;
    }
    //根据绑定参数组装SQL语句 不允许子类覆盖  sql绑定编译
    final public function get_real_sql($sql, $bind = null){
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
        return $sql;
    }
    //特殊符转换
    public function specTransfer(&$sql){
        switch($this->config['dbms']){
            case 'mssql':
            case 'oracle':
            case 'pgsql':
            case 'sqlite':
                $sql = str_replace('`', '"', $sql);
                break;
        }
    }
    /**
     * @param string $sql
     * @return bool 是否读取数据的sql
     */
    public function isReadSql($sql)
    {
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE)\b/i';
        return preg_match($pattern, $sql) > 0;
    }
    public function resetOptions(){
        $this->options = null;
    }
    public function conn(){
        return $this->db;
    }
    //对sql部分语句进行转换
    final public function chkSql(&$sql, $curd=false) {
        $isMysql = $this->config['dbms'] == 'mysql';
        $sql = trim($sql);
        if(!$curd){ #非execute
            //([0-9]+(,[0-9]+)?) | ([0-9]+)
            if (stripos($sql, 'select top')!==false && preg_match('/^(select top )([0-9]+(,[0-9]+)?)/i', $sql, $topArr)) {
                $pos = strpos($topArr[2],',');
                $limit = 0;
                if($pos!==false) {
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
                switch($this->config['dbms']){
                    case 'mysql':
                        $sql = str_replace($topArr[0], 'SELECT ', $sql);
                        $sql .= ' LIMIT ' . $topArr[2];
                        break;
                    case 'mssql'://mssql2005及以上版本  //支持top
                        if($pos!==false){
                            $sql = str_replace($topArr[0], 'SELECT ', $sql);
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
                        if($pos!==false){
                            $sql = str_replace($topArr[0], 'SELECT ', $sql);
                            $sql = 'SELECT * FROM ( SELECT "tmp".*, ROWNUM AS "row_num" FROM ('.$sql.') "tmp" WHERE ROWNUM<='.$max.') WHERE "row_num">'.$min;
                        }
                        break;
                    case 'pgsql':
                    case 'sqlite':
                        $sql = str_replace($topArr[0], 'SELECT ', $sql);
                        if($min>0)
                            $sql .= ' LIMIT ' .$max.' OFFSET '.$min;
                        else
                            $sql .= ' LIMIT ' .$max;
                        break;
                }
                unset($topArr);
            }

            if (isset($this->options['lock'])) { #&& stripos($sql, 'select')===0
                switch($this->config['dbms']){
                    case 'mssql'://mssql2005及以上版本
                        $lock = $this->options['lock'] == 'FOR UPDATE' ? 'WITH (UPDLOCK, ROWLOCK)' : $this->options['lock'];
                        $sql = preg_replace('/from\s{1,}([\w\.]{1,})/i', 'FROM $1 '.$lock, $sql);
                        break;
                    case 'mysql':
                    case 'oracle':
                    case 'pgsql':
                        $sql .= ' '.$this->options['lock'];
                        break;
                    case 'sqlite':
                        break;
                }
            }
        }

        if (!$isMysql && stripos($sql, 'CONCAT(')!==false) { //连接处理
            switch($this->config['dbms']){
                case 'mssql'://mssql2005及以上版本
                case 'oracle':
                case 'pgsql':
                case 'sqlite':
                    $sql = preg_replace_callback('/CONCAT(\([^\)]*?\))/i',function($matches){return str_replace(',','+',$matches[1]);},$sql);
                    break;
            }
        }

        //Log::trace($sql);
    }
	//用于select find getCount联合查询
	public function join($tb, $on, $joinWay='inner'){
        $where = '';
        if(is_array($on)){
            foreach($on as $k=>$v){
                $field = is_int($k) ? $v : ($k . '=' . $this->parseValue($v));
                $where .= $where==''?$field:' and '.$field;
            }
        }elseif(is_string($on)){
            $where = $on;
        }
        $this->_table($tb);
        if(!isset($this->options['fields'])){
            $this->options['fields'] = '*'; //联合查询 直接使用星号显示所有字段
        }
        /*
        $joinTb = $tb;
        if($pos=strpos($tb,' ')){
            $joinTb = substr($tb,0, $pos);
        }
        $prikey = '';
        $this->getFields($joinTb, $prikey, $joinFields);
        */
	    $join = $joinWay.' join '.$tb.' on '.$where;
        $this->options['join'] = isset($this->options['join']) ? $this->options['join'] . ' ' . $join : ' ' . $join;
        return $this;
    }
    /**
     * 指定显示列
     * @param $val
     * @return $this
     */
	public function fields($val){
        $this->options['fields'] = is_array($val) ? implode(',',$val) : $val;
        return $this;
    }
    /**
     * where处理
     * @param string|array $case (string:条件语句可绑定参数[$bind设参数数组], array:条件数组[$bind可设or|and])
     * @param string|array $bind (string:or|and, array:要解析的参数)
     * @return $this
    */
	public function where($case, $bind=null){ //and
		$this->_where($case, $bind, (is_string($case) && is_string($bind) && $bind=='or')?false:true);
		return $this;
	}
	public function whereOr($case, $bind=null){ //or
		$this->_where($case, $bind, false);
		return $this;
	}
    private function _where($case, $bind=null, $and=true){ //and[true:and,false:or]
	    $where = $this->makeWhere($case, $bind);
        if($where!==''){
            if (isset($this->options['where'])) {
                $where = '(' . $where . ')';
                $_where = '(' . $this->options['where'] . ')';
                //排除重复条件
                if (strpos($_where, $where) === false) {
                    $this->options['where'] = $_where . ($and ? ' and ' : ' or ') . $where;
                    //'('.$this->options['where'].') and ('.$where.')' : '('.$this->options['where'].') or ('.$where.')';
                }
            } else {
                $this->options['where'] = $where;
            }
            //$this->options['where'] = empty($this->options['where']) ? $where : ($and ? '('.$this->options['where'].') and ('.$where.')' : '('.$this->options['where'].') or ('.$where.')');
        }
    }

    /**
     * @param array|string $case
     * @param null|array|string $args
     * @return string|string[]
     */
    public function makeWhere($case, $args=null){
		$where = '';
		if(is_array($case)){ //数组组合条件
			$args = (string)$args=='or'?'or':'and';
			foreach($case as $k=>$v){
			    if(is_int($k)){ // '1=1'
                    $field = $v;
                }else{  // ['a'=>1] || ['a::like'=>'%s%']
                    $operator = '=';
                    if ($pos = strpos($k, '::')) {
                        $operator = trim(substr($k, $pos + 2));
                        $operator = $operator == '' ? '=' : ' ' . $operator . ' ';
                        $k = substr($k, 0, $pos);
                    }elseif(is_array($v)){
                        $operator = ' in ';
                    }
                    switch ($operator){
                        case ' between ':
                        case ' not between ':
                            $v = is_array($v) ? $v : explode(',', $v);
                            $v = $this->parseValue($v);
                            $field = $k . $operator . $v[0] . ' and ' . $v[1];
                            break;
                        case ' in ':
                        case ' not in ':
                            if (is_array($v)) {
                                $field = empty($v) ? '1=0' : $k . $operator . '(' . implode(',', $this->parseValue($v)) . ')';
                            } else {
                                $field = $v === '' ? '1=0' : $k . $operator . '(' . $v . ')';
                            }
                            break;
                        case ' exists ':
                        case ' not exists ':
                            $field = $k . $operator . '(' . $v . ')';
                            break;
                        default:
                            $field = $k . $operator . $this->parseValue($v);
                            break;
                    }
                }
                $where .= ($where == '' ? $field : ' ' . $args . ' ' . $field);
			}
		}elseif(is_string($case)){ //参数绑定方式条件
			$where = is_array($args)?$this->get_real_sql($case, $args):$case;
		}
		return $where;
	}
	//sql处理 记数
	private function _run_init(&$sql, $bind=null, $curd=false){
        $this->chkSql($sql, $curd);
        self::$sql = $sql = $this->get_real_sql($sql, $bind); //解析绑定参数
		if($this->resetOption) $this->options = null; //重置
		if(self::$log_type==2 || (self::$log_type==1 && $curd)) Log::write($sql,'SQL');
		self::$times++;
	}

    /**
     * todo:有主从时 默认都走主库
     * 返回预处理对象 $stmt -> 调用 $stmt->execute($params=null) 处理sql数据
     * @param $sql
     * @param array $options
     * @return false|\mysqli_stmt|\PDOStatement
     */
    public function prepare($sql, $options = [])
    {
        //$this->specTransfer($sql);
        $this->_run_init($sql);
        return $this->db->conn->prepare($sql, $options);
    }

    /**
     * @var \Closure function($db, $sql){}
     */
    public static $execCustom = null;

    /**
     * 执行sql  todo:有主从时 默认都走主库
     * @param $sql
     * @param null $bind
     * @return bool|int|mixed
     * @throws \Exception
     */
	public function execute($sql, $bind=null) {
		$this->_run_init($sql, $bind, true);
		if(self::$execCustom instanceof \Closure) { //自定义exec处理
            return call_user_func(self::$execCustom, $this->db, $sql);
        }else{
            return $this->db->exec($sql);
        }
	}
	//执行查询 返回数据 $bind[array:绑定数据, true:直接返回查询数据],$isArr $bind为array时才有效
	public function query($sql, $bind=null, $isArr=false) {
        !is_array($bind) && $isArr = $bind;
        $idx = $isArr && isset($this->options['idx']) ? $this->options['idx'] : null; //指定键名
        // 替换前缀
        if(!isset($this->options['table']) && strpos($sql, '{prefix}')){
            $sql = str_replace('{prefix}', $this->config['prefix'], $sql);
        }

        $this->_run_init($sql, $bind);
        if(!$isArr) return $this->db->query($sql);

        $rs = $this->db->queryAll($sql);
        if(!$idx) return $rs;
        $data = array();
        foreach ($rs as $row){
            $data[$row[$idx]] = $row;
        }
        return $data;
        /*
        $rs = $this->db->query($sql);
        $data = array();//可以 读取缓存
        while($row = $this->db->fetch_array($rs)) {
            if($idx && isset($row[$idx])){
                $data[$row[$idx]] = $row;
            }else{
                $data[] = $row;
            }
        }
        //可以 设置缓存
        return $data;*/
	}
	//获取记录 简单单表查询
    public function select_sql($table='', $where = '', $order='', $fields = '*', $limit=''){
        if($where) $this->_where($where);
        if(isset($this->options['table'])) $table=$this->options['table'];
        if(isset($this->options['fields'])) $fields=$this->options['fields'];
        if(isset($this->options['limit'])) $limit=$this->options['limit'];
        if(isset($this->options['order'])) $order=$this->options['order'];
        if(isset($this->options['where'])) $where=$this->options['where'];
        if($limit!='') $limit = 'TOP '.$limit.' ';
        $this->_table($table);
        $sql = 'SELECT '.$limit.$fields.' FROM '.$table.(isset($this->options['join'])?$this->options['join']:'');

        if($where!='') $sql .= ' WHERE '.$where;
        if(isset($this->options['group'])) $sql .= ' GROUP BY '.$this->options['group'];
        if(isset($this->options['having'])) $sql .= ' HAVING '.$this->options['having'];
        if($order!= '') $sql .= ' ORDER BY '.$order;

        return $sql;
    }
    public function select($table='', $where = '', $order='', $fields = '*', $limit=''){
		return $this->query($this->select_sql($table, $where, $order, $fields, $limit),$table===false?false:true);
	}
    public function all($table='', $where = '', $order='', $fields = '*', $limit=''){
        return $this->query($this->select_sql($table, $where, $order, $fields, $limit),$table===false?false:true);
    }
	public function find_sql($table='', $where='', $order='', $fields = '*'){
        if($where!='') $this->_where($where);
        if(isset($this->options['table'])) $table=$this->options['table'];
        if(isset($this->options['fields'])) $fields=$this->options['fields'];
        if(isset($this->options['order'])) $order=$this->options['order'];
        if(isset($this->options['where'])) $where=$this->options['where'];

        $this->_table($table);
        $this->options['limit'] = 1;
        $sql = 'SELECT TOP 1 '.$fields.' FROM '.$table.(isset($this->options['join'])?$this->options['join']:'');
        //$sql = 'SELECT '.$fields.' FROM '.$table.(isset($this->options['join'])?$this->options['join']:'');

        if($where!='') $sql .= ' WHERE '.$where;
        if(isset($this->options['group'])) $sql .= ' GROUP BY '.$this->options['group'];
        if(isset($this->options['having'])) $sql .= ' HAVING '.$this->options['having'];
        if($order!= '') $sql .= ' ORDER BY '.$order;
        return $sql;
    }
    public function find($table='', $where='', $order='', $fields = '*'){
		return $this->getOne($this->find_sql($table, $where, $order, $fields));
	}
    public function one($table='', $where='', $order='', $fields = '*'){
        return $this->getOne($this->find_sql($table, $where, $order, $fields));
    }
	public function lock($mode = 'FOR UPDATE'){
        $this->options['lock'] = $mode;
        return $this;
    }

    /**
     * 获取指定字段的值
     * @param $name
     * @return mixed|null
     */
    public function val($name){
	    $row = $this->find();
        return isset($row[$name]) ? $row[$name] : null;
    }
	//[批量]添加记录
    public function add_sql($post, $table='') {
		$field=''; $value=''; $values = '';
		if(isset($post[0])){ //批量
            foreach ($post as $n=>$data){
                $value='';
                foreach ($data as $k=>$v) {
                    if ($n == 0) $field .= ',' . $this->startSpec . $k . $this->endSpec;
                    //val值得预先过滤处理
                    $value .= ','. $this->parseValue($v);
                }
                $value=substr($value, 1);
                $values .= ',('.$value.')';
            }
            $field=substr($field, 1); $values = substr($values, 1);
        }else{
            foreach ($post as $k=>$v) {
                $field .= ',' . $this->startSpec . $k . $this->endSpec;
                //val值得预先过滤处理
                $value .= ','. $this->parseValue($v);
            }
            $field=substr($field, 1); $values='('.substr($value, 1).')';
        }
        if($table=='' && isset($this->options['table'])) $table=$this->options['table'];
        $this->_table($table, false);
        $sql = 'INSERT INTO '.$table.'('.$field.') VALUES '.$values;
		return $sql;//返回执行sql
	}

    /**
     * @param array|array[] $post 可批量
     * @param string $table
     * @return mixed|string 自动自增获取最后插入记录的id
     * @throws \Exception
     */
    public function add($post, $table='') {
		$this->execute($this->add_sql($post, $table));
		return $this->db->insert_id();
    }
	//更新记录 $where[str|arr]
    public function update_sql($post, $table='', $where = '') {
        $value = '';
        if (is_array($post)) {
            foreach ($post as $k => $v) {
                //val值得预先过滤处理
                $value .= $this->startSpec . $k . $this->endSpec . ' = ' . $this->parseValue($v) . ',';
            }
            $value = substr($value, 0, -1);
        } else {
            $value = $post;
        }
		
		if($table=='' && isset($this->options['table'])) $table=$this->options['table'];
		$this->_table($table, false);
		$sql = 'UPDATE '.$table.' SET '.$value;
		
		if($where!='') $this->_where($where);
		if(isset($this->options['where'])) $sql .= ' WHERE '.$this->options['where'];
		return $sql;
	}

    /**
     * 返回更新成功修改记录的行数
     * @param $post
     * @param string $table
     * @param string $where
     * @return bool|int|mixed
     * @throws \Exception
     */
    public function update($post, $table='', $where = '') {
		return $this->execute($this->update_sql($post, $table, $where));
	}
    public function del_sql($table='', $where = ''){
        if($table=='' && isset($this->options['table'])) $table=$this->options['table'];
        $this->_table($table, false);
        $sql = 'DELETE FROM '.$table;

        if($where!='') $this->_where($where);
        if(isset($this->options['where'])) $sql .= ' WHERE '.$this->options['where'];

        return $sql;
    }
	//删除记录
    public function del($table='', $where = '') {
		return $this->execute($this->del_sql($table, $where)); //返回删除记录数
	}
	public function count($table='', $where = '', $field='*'){
        return $this->getCount($table, $where, $field);
    }

    /**
     * 获取指定查询表的行数
     * @param string $table
     * @param string $where
     * @param string $field
     * @return int
     */
	public function getCount($table='', $where = '', $field='*') {
        $join = '';
        if (isset($this->options['join'])) { //联合统计时处理
            $field = '*'; //strpos($field, '.') ? $field :
            $join = $this->options['join'];
        }elseif ($field != '*') { //非联合统计
            if (strpos($field, '.')) {
                $field = $this->startSpec . str_replace('.', $this->endSpec.'.'.$this->startSpec, $field) . $this->endSpec;
            } elseif (!strpos($field, ' ')) {
                $field = $this->startSpec . $field . $this->endSpec;
            }
        }
        if ($table=='' && isset($this->options['table'])) $table = $this->options['table'];
        $this->_table($table);
        $sql = 'SELECT COUNT(' . $field . ') FROM ' . $table . $join;

        if ($where != '') $this->_where($where);
        if (isset($this->options['where'])) $sql .= ' WHERE ' . $this->options['where'];
        if (isset($this->options['group'])) $sql .= ' GROUP BY ' . $this->options['group'];
        if (isset($this->options['having'])) $sql .= ' HAVING ' . $this->options['having'];

        $row = $this->getOne($sql, null, 'num');
        return (int)$row[0];
	}
	//获取指定字段最新值
	public function getLastId($table, $idName, $where = '', $orderByName = ''){
		if ($orderByName == '') $orderByName = $idName;

		return $this->getCustomId($table, $idName, $where, $orderByName.' DESC');
	}

    /**
     * 获取自定字段值
     * @param $table
     * @param $idName
     * @param string $where
     * @param string $orderBy
     * @return bool|mixed
     */
	public function getCustomId($table, $idName, $where = '', $orderBy = ''){
		$this->_table($table);
        $sql = 'SELECT TOP 1 ' . $this->startSpec . $idName . $this->endSpec . ' FROM ' . $table;
		
		if($where!='') $this->_where($where);
		if(isset($this->options['where'])) $sql .= ' WHERE '.$this->options['where'];
		
		if ($orderBy != '') $sql .= ' ORDER BY '. $orderBy;
		
		$row = $this->getOne($sql, null, 'num');
		return is_array($row)?$row[0]:false;
	}

    /**
     * 执行一个SQL语句,仅返回一条记录 $bind[array:绑定数据],$type $bind为array时才有效
     * @param $sql
     * @param null $bind
     * @param string $type
     * @return array|false
     */
	public function getOne($sql, $bind=null, $type = 'assoc') {
		if(is_array($bind)){
			$result = $this->query($sql, $bind);
		}else{
			$bind && $type = $bind;
			$result = $this->query($sql);
		}
		return $this->db->fetch_array($result, $type);//无记录返回false
	}

    /**
     * 返回当前的一条记录并把游标移向下一记录
     * @param null $rs
     * @param string $type
     * @return mixed
     */
    public function fetch($rs=null, $type = 'assoc') {
        if($rs===null) $rs=$this->db->rs;
        return $this->db->fetch_array($rs, $type);
    }

    /**
     * @param null $rs
     * @param string $type
     * @return mixed
     */
	public function fetch_array($rs=null, $type = 'assoc') {
        return $this->fetch($rs, $type);
	}
	public function isTrans(){
        return $this->db->inTrans();
    }

    /**
     * 设置事务隔离等级
     * @param $level
     * @return $this
     * @throws \Exception
     */
    public function setTransactionLevel($level){
        $sql = 'transaction isolationLevel '.$level;
        $this->_run_init($sql, null, true);
        $this->db->setTransactionLevel($level);
        return $this;
    }
	//开始一个事务，关闭自动提交 todo:有主从时 默认都走主库
	public function beginTrans(){
		$sql = 'beginTransaction';
		$this->_run_init($sql, null, true);
        $this->db->beginTrans();
        return $this;
	}
	//提交事务 返回到自动提交模式
	public function commit($force=false){
		$sql = 'commit';
		if($force) $sql .= ' force';
		$this->_run_init($sql, null, true);
		$this->db->commit($force);
	}
	//回滚当前事务
	public function rollBack($force=false){
		$sql = 'rollback';
        if($force) $sql .= ' force';
		$this->_run_init($sql, null, true);
		$this->db->rollback($force);
	}
	//获取影响行数
	public function getRows() {
		return $this->db->num_rows();
	}
	//初始化缓存类，如果开启缓存，则加载缓存类并实例化
	public function initCache() {
        if (!$this->cache) {
            $this->cache = Cache::getInstance();
            $this->cache->setCacheDir(RUNTIME . DS . $this->config['dbms']);
            $this->cache->setCachePrefix($this->config['name'].'.');
            $this->cache->suffix = '.bin';
        }
	}
	//格式名称-关键字冲突处理
	public function formatName($val){
        $val = trim(str_replace('`', '', $val));
        if(strpos($val,'.')){
            $val = str_replace('.',$this->endSpec.'.'.$this->startSpec, $val);
        }
        if(strpos($val,',')) {
            $val = str_replace(',',$this->endSpec.','.$this->startSpec, $val);
        }
        if(strpos($val,' ')){ //有别名
            $val = str_replace(' ',$this->endSpec.' '.$this->startSpec, $val);
        }
        return $this->startSpec . $val . $this->endSpec;
    }

	//是否给表名增加关键字冲突处理符号
	private function _table(&$tb, $more=true){
        if ($tb instanceof Expr) { //表达式
            return $tb;
        }

        if(strpos($tb,'{prefix}')!==false){ //表名前缀处理
            $tb = str_replace('{prefix}', $this->config['prefix'], $tb);
        }

        if($more){
            $tb = trim($tb);
            if ($tb[0]=='(' || strpos($tb, '.') || strpos($tb, ',')) return $tb; //子查询|联合查询[.,]
            if ($pos = strpos($tb, ' ')) { //有别名
                if(strpos($tb, ' ', $pos+1)){ //多个空格 可能非别名
                    return $tb;
                }
                $tb = str_replace(' ', $this->endSpec . ' ' . $this->startSpec, $tb); //, str_replace('`', '', $tb)
            }
        }elseif (strpos($tb, '.')) { //指定库名
            $tb = str_replace('.', $this->endSpec . '.' . $this->startSpec, $tb);
        }

        $tb = $this->startSpec . $tb . $this->endSpec;
        return $tb;
	}
}
//数据库表
abstract class TbBase{
    //字段类型对应规则
    protected $fieldType = array(
        'tinyint'=>'%d{-128,127}',
        'untinyint'=>'%d{0,255}',
        'smallint'=>'%d{-32768,32767}',
        'unsmallint'=>'%d{0,65535}',
        'mediumint'=>'%d{-8388608,8388607}',
        'unmediumint'=>'%d{0,16777215}',
        'int'=>'%d{-2147483648,2147483647}',
        'unint'=>'%d{0,4294967295}',
        'bigint'=>'%d{-9233372036854775808,9223372036854775807}',
        'unbigint'=>'%d{0,18446744073709551615}',
        'float'=>'%f',
        'unfloat'=>'%f{0,}',
        'double'=>'%f',
        'undouble'=>'%f{0,}',
        'decimal'=>'%f',
        'undecimal'=>'%f{0,}',
        'date'=>'%ymd',
        'time'=>'%his',
        'datetime'=>'%date',
        'timestamp'=>'%date',
        'char'=>'%s{}', //通过字段设置获取长度
        'varchar'=>'%s{}', //通过字段设置获取长度
        'tinyblob'=>'%s{255}',
        'tinytext'=>'%s{255}',
        'blob'=>'%s{65535}',
        'text'=>'%s{65535}',
        'mediumblob'=>'%s', //{16777215}
        'mediumtext'=>'%s',
        'longblob'=>'%s', //{4294967295}
        'longtext'=>'%s',
        'json'=>'%json',
        'bit'=>'%b'
    );

    protected function toType($type){
        if(strpos($type,'int')){
            $unsigned = strpos($type, 'un') === 0;
            switch ($type){
                case 'int':
                case 'unint':
                    return PHP_INT_SIZE === 4 && $unsigned ? 'string' : 'int';
                case 'integer':
                case 'bigint':
                case 'unbigint':
                    return PHP_INT_SIZE === 8 && $unsigned ? 'string' : 'int';
            }
            return 'int';
        }
        switch ($type){
            case 'real':
            case 'float':
            case 'double':
            case 'unfloat':
            case 'undouble':
            case 'decimal':
            case 'undecimal':
                return 'double';
        }
        return $type;
    }

    /** 按字段类型生成规则
     * @param string $type 数据库取得的类型
     * @param string $vType 返回给php的类型
     * @return mixed
     */
    abstract public function fieldToRule($type, &$vType);

    /**
     * 取得数据表的字段信息
     * @param \myphp\db\db_pdo $db
     * @param string $tableName
     * @return mixed array('fields'=>string,'prikey'=>string,'rule'=>array)
     */
    abstract public function getFields($db, $tableName);
    //取得数据库的表信息
    abstract public function getTables($db, $dbName);
}

/**
 * Class DbBase
 * @property \PDO|\mysqli $conn
 */
abstract class DbBase{
    //隔离级别
    const READ_UNCOMMITTED = 'READ UNCOMMITTED'; //读未提交：脏读、不可重复读、幻读
    const READ_COMMITTED = 'READ COMMITTED'; //读已提交：不可重复读、幻读 如:mssql oracle
    const REPEATABLE_READ = 'REPEATABLE READ'; //可重复读：幻读 如:mysql
    const SERIALIZABLE = 'SERIALIZABLE'; //串行化

	public $conn; //连接实例
	public $rs; //数据集
    public $config;
    public $transCounter = 0;
	
	public function __construct($config) {
        $this->config = $config;
        $this->connect();
	}
	//释放结果集
	public function free() {
		$this->rs = null;
	}
	//是否在事务内
	public function inTrans(){
        if($this->config['type']=='pdo'){
            return $this->conn->inTransaction();
        }else{
            return $this->transCounter > 0;
        }
    }
	//开始一个事务，关闭自动提交
	public function beginTrans(){
		if (!$this->transCounter++) {
            if($this->config['type']=='pdo'){
                $this->conn->beginTransaction();
            }else{
                $this->exec('START TRANSACTION');
            }
            return;
        }
        if($this->config['dbms']=='mssql'){
            $this->exec('SAVE TRANSACTION trans'.$this->transCounter);
        }else{
            $this->exec('SAVEPOINT trans'.$this->transCounter);
        }
	}
    //回滚当前事务
    public function rollBack($force=false){
        if(!--$this->transCounter){
            if($this->config['type']=='pdo'){
                $this->conn->rollback();
            }else{
                $this->exec('ROLLBACK');
            }
            return;
        }
        if($this->config['dbms']=='mssql'){
            $this->exec('ROLLBACK TRANSACTION trans'.($this->transCounter+1));
        }else{
            $this->exec('ROLLBACK TO trans'.($this->transCounter+1));
        }
        if($force && $this->transCounter) $this->rollBack($force);
    }
	//提交事务 返回到自动提交模式
	public function commit($force=false){
		if (!--$this->transCounter) {
		    if($this->config['type']=='pdo'){
                $this->conn->commit();
            }else{
                $this->exec('COMMIT');
            }
            return;
		}
        if($force && $this->transCounter>0) $this->commit($force);
	}

    /**
     * 设置事务隔离等级 isolation
     * @param $level
     * @throws \Exception
     */
	public function setTransactionLevel($level){
        if ($this->config['dbms'] == 'sqlite') {
            switch ($level) {
                case self::SERIALIZABLE:
                    $this->exec('PRAGMA read_uncommitted = False;');
                    break;
                case self::READ_UNCOMMITTED:
                    $this->exec('PRAGMA read_uncommitted = True;');
                    break;
                default:
                    throw new \Exception(get_class($this) . ' only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.');
            }
        } else {
            $this->exec("SET TRANSACTION ISOLATION LEVEL $level");
        }
    }
	// 连接数据库 $cfg_db array数据库配置
	abstract public function connect();
	//安全过滤 是字符串时在格式后需要使用单引号包括返回
	abstract protected function quote($str);
	//执行sql 返回影响的行数
	abstract public function exec($sql);
    /** 返回所有行的数组
     * @param $sql
     * @param string $type
     * @return mixed
     */
    abstract function queryAll($sql, $type = 'assoc');
	//执行查询
	abstract public function query($sql);
    /**
     * 从结果集中取得一行作为关联数组/数字索引数组
     * @param \PDOStatement|\mysqli_result $query 数据集
     * @param string $type 默认MYSQL_ASSOC 关联，MYSQL_NUM 数字，MYSQL_BOTH 两者
     * @return mixed
     */
	abstract public function fetch_array($query, $type = 'assoc');
	//取得结果集行的数目
	abstract public function num_rows();
	//取得上一步 INSERT 操作产生的AUTO_INCREMENT的ID
	abstract public function insert_id($sequenceName);
}
//表达式
class Expr{
    private $expr;
    public function __construct($expr) {
        $this->expr = (string)$expr;
    }
    public function __toString() {
        return $this->expr;
    }
}