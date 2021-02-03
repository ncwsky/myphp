<?php
/**
 * Class Model 数据模型类
 * @method Model fields(string|array $val)
 * @method Model order(string $val)
 * @method Model group(string $val)
 * @method Model having(string $val)
 * @method Model lock(string $val = 'FOR UPDATE')
 * @method Model limit(string|int $val) example 30 or 2,5
 * @method Model table(string $val)
 * @method Model idx(string $val)
 * @method Model join(string $tb, string|array $on, $joinWay='inner')
 * @method Model where(string|array $val, $bind=null)
 * @method int update(array $post, string $table='', string|array $where = '')
 * @method int add(array $post, string $table='')
 * @method int del(string $table='', string|array $where = '')
 * @method mixed select(bool|string $table='');
 * @method mixed all(bool|string $table='');
 * @method mixed find();
 * @method mixed one();
 * @method mixed select_sql();
 * @method mixed find_sql();
 * @method mixed val($name);
 * @method string get_real_sql($sql, $bind = null);
 * @method bool beginTrans();
 * @method bool commit($force=false);
 * @method bool rollBack($force=false);
 * @property Db $db
 */
class Model extends ArrayObject
{
    use MyMsg;
    // 当前操作数据表名
    protected $tbName = null; //不指定自动获取
    protected $aliasName = null; //别名
    protected $db = null; //db操作类
    protected $dbName = 'db'; //db配置名
    // 表数据信息
    protected $data = null;
    protected $oldData = null; //单条查询记录数据
    //字段扩展过滤规则
    public $extRule = array();
    /* 示例
    $extRule = [
        'id'=>['rule'=>'%d{1,10}','err'=>'请输入数字','err2'=>'数字范围无效'], //'def'=>0, 有默认值不做提示
        'name'=>['rule'=>'%s{10}','err'=>'请输入名称','err2'=>'名称最多不能超过10个字符'],
        'des'=>['rule'=>'%s{250}','def'=>''], //
        'his'=>['rule'=>'%his{250}','err'=>'时间无效'], // 无err2项时 默认使用err
    ];
     */
    //主键
    protected $prikey = null;
    protected $autoIncrement = ''; //自增键
    //表查询的字段
    protected $fields = '*';
    //表字段验证规则无效时设默认值开关
    protected $setDef = false;
    //表字段列表过滤规则
    public $fieldRule = array();//'id'=>array('rule'=>'%d{1,10}','def'=>0)

    /**
     * 构造函数  $tbName 不定义表模型 直接指定, $db 指定db配置名称
     * @param null $tbName
     * @param null $dbName
     * @throws Exception
     */
    public function __construct($tbName = null, $dbName = null)
    {
        if(!$this->tbName){
            /*
            if(is_subclass_of($this, 'Model')){
                $this->tbName = strtolower(get_class($this));
            }*/
            $this->tbName = $tbName!==null ? $tbName : strtolower(get_class($this)); //get_called_class()
        }

        $this->init();
        $this->db = new Db($dbName ? $dbName : $this->dbName);
        if ($this->tbName && empty($this->fieldRule)) { //获取表字段
            $this->db->getFields($this->tbName, $this->prikey, $this->fields, $this->fieldRule, $this->autoIncrement);
            //$this->fields = implode(',', array_keys($this->fieldRule));
            //print_r($this->fields);
        }
    }
    //初始操作处理
    protected function init()
    {
        //$this->dbName = 'db';
    }
    //设置字段规则  [id'=>array('rule'=>'%d{1,10}','def'=>0)]|id,array('rule'=>'%d{1,10}','def'=>0)
    public function setRule($name, $rule=null){
        if(is_array($name)){
            $this->fieldRule = array_merge($this->fieldRule,$name);
        }else{
            $this->fieldRule[$name] = $rule;
        }
    }
    //设置字段数据
    public function setData($data, $isRest = true)
    {
        if (is_array($data) || is_null($data)) {
            if($isRest){
                $this->data = $data;
            }else{
                $this->data = $this->data ? array_merge($this->data, $data) : $data;
            }
        }
        else {
            throw new Exception('data not is array');
        }
    }

    //设置字段旧数据
    public function setOldData($data, $isRest = true)
    {
        if (is_array($data) || is_null($data)) {
            if($isRest){
                $this->oldData = $data;
            }else{
                $this->oldData = $this->oldData ? array_merge($this->oldData, $data) : $data;
            }
            if(!$this->data) $this->data = $this->oldData;
        }
        else {
            throw new Exception('old data not is array');
        }
    }
    //获取字段数据
    public function getData()
    {
        return $this->data ? $this->data : array();
    }
    //获取字段数据
    public function getOldData()
    {
        return $this->oldData ? $this->oldData : array();
    }
    //格式数据
    private function _formatData(&$data){
        if(!is_array($data)) return;
        foreach ($data as $k=>$val){
            if(isset($this->fieldRule[$k])) { //转换到指定类型
                $type = $this->fieldRule[$k]['type'];
                if($type=='double') $data[$k] = (float) $val;
                elseif($type=='bit') $data[$k] = (bool) $val;
                elseif($type=='int' && (PHP_INT_SIZE === 8 || ($val>=-2147483648 && $val<=2147483647))) {
                    $data[$k] = (int) $val;
                }
            }
        }
    }
    /** 保存数据
     * $def:0 用于添加或更新部分数据,不对未设置字段验证及默认值处理 禁用默认值处理
     * $def:false 对未设置字段not null验证(不为空验证)
     * $def:true 对未设置字段not null验证,同时有默认值时设默认值
     * @param null $data
     * @param null $where
     * @param int|bool $def
     * @return bool|int
     */
    public function save($data = null, $where = null, $def=null)
    {
        $fieldRule = $this->extRule ? array_merge($this->fieldRule, $this->extRule) : $this->fieldRule;
        if (is_array($data)) {
            $this->data = is_array($this->data) ? array_merge($this->data, $data) : $data;
        }
        if ($where) $this->db->where($where);
        //有单条查询且数据有主键 则识别为更新
        $isWhere = $this->db->where ? true : false;
        /*if($this->prikey){ //有主键
            if (!empty($this->data[$this->prikey]) && $this->oldData) { //有单条查询且主键非null 0 空
                $this->db->where(array($this->prikey => $this->data[$this->prikey]));
                $isWhere = true;
            }
            $hasPriKey = isset($this->data[$this->prikey]);
            //禁止更新主键 或 新增主键值为0或未指定时删除主键值及规则
            if($isWhere || !$hasPriKey || ($hasPriKey && $this->data[$this->prikey]===0)){
                unset($this->data[$this->prikey]);
                unset($fieldRule[$this->prikey]);
            }
        }*/
        //主键值为[null 0 空]时可insert记录
        if($this->prikey && $this->oldData && !empty($this->data[$this->prikey])){ //有主键[非null 0 空] 有单条查询
            if(isset($this->oldData[$this->prikey])){
                $this->db->where([$this->prikey => $this->oldData[$this->prikey]]);
            }else{
                $this->db->where('1=0'); //没有主键值
            }
            $isWhere = true;
        }
        if($this->autoIncrement && empty($this->data[$this->autoIncrement])){ //自增键[null 0 空]时排除验证规则
            unset($this->data[$this->autoIncrement], $fieldRule[$this->autoIncrement]);
        }
        //验证数据
        if (!Helper::validAll($this->data, $fieldRule, true, $def === null ? $this->setDef : $def)) {
            //throw new RuntimeException('验证失败');
            $this->db->options = null;
            return false;
        }
        //未指定表名时指定表名
        if(!$this->db->table) $this->db->table($this->tbName);
        //指定了条件时必定是更新
        if ($isWhere) {
            //未变动的数据不更新
            if ($this->oldData) {
                foreach ($this->oldData as $k => $v) {
                    if ((isset($this->data[$k]) || array_key_exists($k, $this->data))
                        && ((is_numeric($this->data[$k]) && $this->data[$k] == $v) || $this->data[$k] === $v)) {
                        unset($this->data[$k]);
                    }
                }
                if (empty($this->data)) {
                    $this->data = $this->oldData;
                    $this->db->options = null; //清除未执行的条件 防条件被附加到下次执行的条件中
                    return 0;
                }
            }
            $result = $this->db->update($this->data);  //返回影响行数
            if($this->oldData){
                $this->data = array_merge($this->oldData,$this->data);
            }
        } else {
            $result = $this->db->add($this->data); //返回新增id
            if ($this->prikey && $this->prikey==$this->autoIncrement) $this->data[$this->prikey] = $result;
            $this->oldData = $this->data;
        }
        return $result;
    }

    // 设置数据对象属性
    public function __set($name, $value)
    {
        if($name==$this->prikey && $value!==0 && isset($this->oldData[$name])){ //有单条且主键有值时 不能指定主键值
            return;
        }
        $this->data[$name] = $value;
    }

    // 获取数据对象的值
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    //检测数据对象的值
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    //销毁数据对象的值
    public function __unset($name)
    {
        unset($this->data[$name]);
    }
    // ArrayAccess
    public function offsetSet($name, $value)
    {
        $this->__set($name, $value);
    }
    public function offsetExists($name)
    {
        return $this->__isset($name);
    }
    public function offsetUnset($name)
    {
        $this->__unset($name);
    }
    public function offsetGet($name)
    {
        return $this->__get($name);
    }

    //执行db方法的前置处理
    private function _preDbMethod($method){
        //$methods = 'find,select,getCount,add,update,del';
        //if (strpos($methods, $method) === false) return;
        if ($method == 'del') {
            if(!$this->db->where){
                throw new Exception("请指定删除条件");
            }
            $this->data = $this->oldData = null;
        }
        if ($this->tbName && strpos($this->db->table,$this->tbName)!==0) $this->db->table($this->tbName.($this->aliasName ? ' ' . $this->aliasName : ''));
        if ($method == 'find' || $method == 'select') {
            if(!$this->tbName && $this->db->table){ //未取得表名及字段时
                $this->tbName = $this->db->table;
                $this->db->getFields($this->tbName, $this->prikey, $this->fields, $this->fieldRule, $this->autoIncrement);
            }
            //有指定fields时 且db->fields为空0null或='*' 且db->join[因为这里联合查询fields会指定为*不适合使用自动获取表字段的方式]为空0null 设置db->fields
            if ($this->fields != '*' && (!$this->db->fields || $this->db->fields == '*') && !$this->db->join) {
                $this->db->fields($this->fields);
            }
        }
    }
    //执行db方法的后置处理
    private function _sufDbMethod($method, &$result){
        if ($method == 'getOne' || $method == 'find') { //单条记录   || $method == 'select'
            $this->_formatData($result);
            $this->data = $this->oldData = $result;
        }/*
        if($method == 'select'){
            $this->_formatData($result);
            $this->data = $this->oldData = null;
        }*/
    }
    //表别名 一般用于联合查询
    public function alias($name){
        $this->aliasName = $name;
        $this->db->table($this->tbName.' '.$name);
        return $this;
    }

    /** 合计行数
     * @param string $fields
     * @return int
     */
    public function count($fields='*'){
        return $this->db->getCount($this->tbName.($this->aliasName ? ' ' . $this->aliasName : ''), '', $fields=='*' && $this->prikey?$this->prikey:$fields);
    }
    protected static function runCall(Model $model, $method, &$args){
        if (method_exists($model, $method)) {
            call_user_func_array([$model, $method], $args);
            return $model;
        } else { //调用db方法
            try{
                $model->_preDbMethod($method);
                $result = call_user_func_array([$model->db, $method], $args);
                $model->_sufDbMethod($method, $result);
                return $result instanceof Db ? $model : $result;
            }catch (Exception $e){
                myphp::err($e->getMessage());
                #Log::WARN($e->getFile().', '.$e->getLine().', '.$e->getMessage());
                return false;
            }
        }
    }
    //连贯操作
    public function __call($method, $args)
    {
        return self::runCall($this, $method, $args);
    }
    //连贯操作
    public static function __callStatic($method, $args)
    {
        return self::runCall(new static(), $method, $args);
    }
}
