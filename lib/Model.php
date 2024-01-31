<?php
namespace myphp;

/**
 * Class Model 数据模型类
 * @method null|static fields(string|array $val)
 * @method null|static order(string $val)
 * @method null|static group(string $val)
 * @method null|static having(string $val)
 * @method null|static lock(string $val = 'FOR UPDATE')
 * @method null|static limit(string|int $val) example 30 or 2,5
 * @method null|static table(string $val)
 * @method null|static idx(string $val)
 * @method null|static join(string $tb, string|array $on, $joinWay='inner')
 * @method null|static leftJoin(string $tb, string|array $on)
 * @method null|static rightJoin(string $tb, string|array $on)
 * @method null|static where(string|array $val, $bind=null)
 * @method null|static whereOr(string|array $val, $bind=null)
 * @method int update(array $post, string $table='', string|array $where = '')
 * @method array|false|\PDOStatement|static[] select(bool|string $table='');
 * @method array|false|\PDOStatement|static[] all(bool|string $table='');
 * @method array|false|static find();
 * @method array|false|static one()
 * @method string select_sql();
 * @method string find_sql();
 * @method mixed|null val($name);
 * @method string get_real_sql($sql, $bind = null);
 * @method null|static setTransactionLevel($level)
 * @method null|static beginTrans();
 * @method bool commit($force=false);
 * @method bool rollBack($force=false);
 */
class Model implements \ArrayAccess
{
    use \MyMsg;
    // 当前操作数据表名
    protected $tbName; //不指定自动获取
    protected $aliasName; //别名
    /**
     * @var Db
     */
    protected $db; //db操作类
    // 表数据信息
    private $_data = [];
    private $_oldData = []; //单条查询记录数据
    // 是否返回实例
    private $_asObj = false;
    //主键
    protected $prikey;
    //自增键
    protected $autoIncrement;
    //表查询的字段
    protected $fields = '*';
    //表字段规则
    protected $fieldRule = [];//'id'=>['rule'=>'%d{1,10}','def'=>0]
    /**
     * 字段扩展过滤规则
     * 示例
     $extRule = [
        'id'=>['rule'=>'%d{1,10}','err'=>'请输入数字','err2'=>'数字范围无效'], // rule=>['d','min'=>1,'max'=>10] ;'def'=>0, 有默认值不做提示
        'name'=>['rule'=>'%s{10}','err'=>'请输入名称','err2'=>'名称最多不能超过10个字符'],
        'des'=>['rule'=>'%s{250}','def'=>''], // ['rule'=> ['d','max'=>250],'def'=>'']
        'his'=>['rule'=>'%his{250}','err'=>'时间无效'], // 无err2项时 默认使用err
     ];
     * @var array
     */
    protected $extRule = [];

    protected static $dbName = 'db'; //db配置名
    protected static $tableName = null; //表配置名
    /**
     * @var bool 查询后是否重置sql-options
     */
    public static $resetOption = true;

    /**
     * 数据库实例
     * @param bool $newInstance
     * @return Db
     * @throws \Exception
     */
    public static function getDb($newInstance = true)
    {
        if ($newInstance) return new Db(static::$dbName);
        return \myphp::db(static::$dbName);
    }

    /**
     * 表名
     * @return string|null
     */
    public static function tableName()
    {
        if (static::$tableName === null) { //未指定表名且未传递表名时 自动获取【前缀+表名】
            $tbName = get_called_class(); //static::class
            if ($pos = strrpos($tbName, '\\')) { //命名空间
                $tbName = substr($tbName, $pos + 1);
            }
            return \myphp::get(static::$dbName . '.prefix') . strtolower($tbName);
        }
        return static::$tableName;
    }

    /**
     * 构造函数  $tbName 不定义表模型 直接指定, $dbName 指定db配置名称
     * @param null $tbName
     * @param null|string|Db $dbName
     * @throws \Exception
     */
    public function __construct($tbName = null, $dbName = null)
    {
        if ($dbName === null) {
            $this->db = static::getDb();
        } else {
            if ($dbName instanceof Db) {
                $this->db = $dbName;
            } else {
                $this->db = new Db($dbName);
            }
        }
        $this->db->resetOption = static::$resetOption; //sql组合项执行后是否重置

        if (!$this->tbName) {
            $this->tbName = $tbName === null ? static::tableName() : $tbName;
        }

        if ($this->tbName && empty($this->fieldRule)) { //获取表字段
            $this->db->getFields($this->tbName, $this->prikey, $this->fields, $this->fieldRule, $this->autoIncrement);
        }
        if ($this->extRule) {
            array_cover_merge($this->fieldRule, $this->extRule);
            //$this->fieldRule = array_merge($this->fieldRule, $this->extRule);
        }
    }
    public function __clone(){
        $this->db = clone $this->db; //用于复制隔离db->options
    }
    /**
     * 设置扩展字段过滤规则
     * @param string|array $name id|[id'=>['rule'=>'%d{1,10}','def'=>0]]
     * @param null|array $rule ['rule'=>'%d{1,10}','def'=>0]|null
     */
    public function setRule($name, $rule=null){
        if (is_array($name)) {
            array_cover_merge($this->fieldRule, $name);
            //$this->fieldRule = array_merge($this->fieldRule, $name);
        } else {
            array_cover_merge($this->fieldRule[$name], $rule);
            //$this->fieldRule[$name] = $rule;
        }
    }
    public function rules(){
        return $this->fieldRule;
    }
    //设置字段数据
    public function setData($data, $reset = true)
    {
        if (is_array($data)) {
            if ($reset) {
                $this->_data = $data;
            } else {
                $this->_data = $this->_data ? array_merge($this->_data, $data) : $data;
            }
        } elseif ($data === null) {
            $this->_data = [];
        }
    }
    //设置字段旧数据
    public function setOldData($data, $reset = true)
    {
        if (is_array($data)) {
            if ($reset) {
                $this->_oldData = $data;
            } else {
                $this->_oldData = $this->_oldData ? array_merge($this->_oldData, $data) : $data;
            }
            if (!$this->_data) {
                $this->formatData($this->_oldData);
                $this->_data = $this->_oldData;
            }
        } elseif ($data === null) {
            $this->_oldData = [];
        }
    }
    //获取字段数据
    public function getData($name = null)
    {
        if ($name !== null) return isset($this->_data[$name]) ? $this->_data[$name] : null;
        return $this->_data ?: [];
    }
    //获取字段旧数据
    public function getOldData($name = null)
    {
        if ($name !== null) return isset($this->_oldData[$name]) ? $this->_oldData[$name] : null;
        return $this->_oldData ?: [];
    }
    //格式数据
    public function formatData(&$data){
        if(!is_array($data) || empty($this->fieldRule)) return;
        foreach ($data as $k=>$val){
            if(isset($this->fieldRule[$k]['type'])) { //转换到指定类型
                $type = $this->fieldRule[$k]['type'];
                if($type=='double') $data[$k] = (float) $val;
                elseif($type=='bit') $data[$k] = (bool) $val;
                elseif($type=='int' && (PHP_INT_SIZE === 8 || ($val>=-2147483648 && $val<=2147483647))) {
                    $data[$k] = (int) $val;
                }
            }
        }
    }

    /**
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        return true;
    }
    /**
     * @param bool $insert
     * @param array $changed 变动的数据
     */
    public function afterSave($insert, $changed = []) {}

    /**
     * @return bool
     */
    public function beforeDel(){
        return true;
    }
    public function afterDel(){}

    /**
     * @return int|false
     * @throws \Throwable
     */
    public function del()
    {
        //$trans = clone $this->db;
        $trans = $this->db;
        $this->db->resetOption = false; //sql组合项执行后是否重置
        $trans->beginTrans();
        try {
            if (!$this->beforeDel()) {
                $trans->rollBack();
                return false;
            }

            // 删除条件处理
            if ($this->prikey && $this->_oldData) { //有主键 有单条查询
                if (isset($this->_oldData[$this->prikey])) {
                    $this->db->where([$this->prikey => $this->_oldData[$this->prikey]]);
                } else {
                    $this->db->where('1=0'); //没有主键值
                }
            }
            if(!$this->db->where){
                throw new \Exception("请指定删除条件");
            }

            $result = $this->db->del($this->tbName);
            $this->afterDel();
            $trans->commit();
            return $result;
        } catch (\Exception $e) {
            $trans->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $trans->rollBack();
            throw $e;
        }
    }

    /**
     * 保存数据
     * $def:0 禁用默认值处理 用于添加或更新部分数据,不对未设置字段验证及默认值处理
     * $def:false 对未设置字段not null验证(不为空验证)
     * $def:true 对未设置字段not null验证,同时有默认值时设默认值
     * @param null $data
     * @param null|false|true $def 是否给有默认值但未设置的字段设置默认值 0不验证|true设置默认值|false不设置
     * @return bool|int|mixed|string
     * @throws \Exception
     */
    public function save($data = null, $def=false)
    {
        if (is_array($data)) {
            $this->_data = $this->_data ? array_merge($this->_data, $data) : $data;
        }
        //有单条查询且数据有主键 则识别为更新
        $isUpdate = $this->db->where ? true : false;
        //主键值为[null 0 空]时可insert记录
        if($this->prikey && $this->_oldData && !empty($this->_data[$this->prikey])){ //有主键[非null 0 空] 有单条查询
            if(isset($this->_oldData[$this->prikey])){
                $this->db->where([$this->prikey => $this->_oldData[$this->prikey]]);
            }else{
                $this->db->where('1=0'); //没有主键值
            }
            $isUpdate = true;
        }
        if($this->autoIncrement && empty($this->_data[$this->autoIncrement])){ //自增键[null 0 空]时排除验证规则
            unset($this->_data[$this->autoIncrement], $this->fieldRule[$this->autoIncrement]);
        }
        //验证数据
        if (!self::validate($this->_data, $this->fieldRule, true, $def, !$isUpdate)) {
            //throw new \RuntimeException('验证失败');
            $this->db->resetOptions();
            return false;
        }
        //未指定表名时指定表名
        if(!$this->db->table) $this->db->table($this->tbName);

        if(!$this->beforeSave(!$isUpdate)){
            return false;
        }
        //指定了条件时必定是更新
        if ($isUpdate) {
            $changed = [];
            //未变动的数据不更新
            if ($this->_oldData) {
                foreach ($this->_oldData as $k => $v) {
                    if ((isset($this->_data[$k]) || array_key_exists($k, $this->_data))
                        && ((is_numeric($this->_data[$k]) && $this->_data[$k] == $v) || $this->_data[$k] === $v)) {
                        unset($this->_data[$k]);
                    } else {
                        $changed[$k] = $v;
                    }
                }
                if (empty($this->_data)) {
                    $this->_data = $this->_oldData;
                    $this->db->resetOptions(); //清除未执行的条件 防条件被附加到下次执行的条件中
                    $this->afterSave(false, []);
                    return 0;
                }
            } else {
                $changed = $this->_data;
            }
            $result = $this->db->update($this->_data);  //返回影响行数
            if($this->_oldData){
                $this->_data = array_merge($this->_oldData,$this->_data);
            }
            $this->afterSave(false, $changed);
        } else {
            $result = $this->db->add($this->_data); //返回新增id
            if ($this->autoIncrement) $this->_data[$this->autoIncrement] = $result;
            $this->afterSave(true, $this->_data);
        }
        $this->_oldData = $this->_data;
        return $result;
    }

    /**
     * 设置数据对象属性
     * @param mixed $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if($name==$this->prikey && $value!==0 && isset($this->_oldData[$name])){ //有单条且主键有值时 不能指定主键值
            return;
        }
        $this->_data[$name] = $value;
    }

    /**
     * 获取数据对象的值
     * @param mixed $name
     * @return mixed|null
     */
    public function __get($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    /**
     * 检测数据对象的值
     * @param mixed $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    /**
     * 销毁数据对象的值
     * @param mixed $name
     */
    public function __unset($name)
    {
        unset($this->_data[$name]);
    }

    /**
     * @param mixed $name
     * @param mixed $value
     */
    #[\ReturnTypeWillChange] // 用于>=8.1抑制错误提示
    public function offsetSet($name, $value)
    {
        $this->__set($name, $value);
    }

    /**
     * @param mixed $name
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        return $this->__isset($name);
    }

    /**
     * @param mixed $name
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        $this->__unset($name);
    }

    /**
     * @param mixed $name
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        return $this->__get($name);
    }

    //执行db方法的前置处理
    protected function _beforeDbMethod($method){
        if ($this->tbName && (!$this->db->table || strpos($this->db->table,$this->tbName)!==0)) $this->db->table($this->tbName.($this->aliasName ? ' ' . $this->aliasName : ''));
        if ($method == 'one' || $method == 'all' || $method == 'find' || $method == 'select') {
            if(!$this->tbName && $this->db->table){ //未取得表名及字段时
                $this->tbName = $this->db->table;
                $this->db->getFields($this->tbName, $this->prikey, $this->fields, $this->fieldRule, $this->autoIncrement);
            }
            //有指定fields时 且db->fields为空0null或='*' 且db->join[因为这里联合查询fields会指定为*不适合使用自动获取表字段的方式]为空0null 设置db->fields
            if ($this->fields != '*' && (!$this->db->fields || $this->db->fields == '*') && !$this->db->join) {
                $this->db->fields($this->db->formatName($this->fields));
            }
        }
    }
    //执行db方法的后置处理
    protected function _afterDbMethod($method, &$result){
        if ($method == 'one' || $method == 'find') { //单条记录   || $method == 'getOne'
            if (false === $result) return;
            $this->formatData($result);
            $this->_data = $this->_oldData = $result;
            if ($this->_asObj) {
                $result = $this;
            }
        }
        if ($this->_asObj && ($method == 'all' || $method == 'select')) {
            foreach ($result as $k => $row) {
                $result[$k] = self::clone($this, $row);
                //$result[$k] = static::create($row, $this->tbName, clone $this->db);
            }
        }
    }
    //表别名 一般用于联合查询
    public function alias($name){
        $this->aliasName = $name;
        $this->db->table($this->tbName.' '.$name);
        return $this;
    }
    public function asArray(){
        $this->_asObj = false;
        return $this;
    }
    public function asObj(){
        $this->_asObj = true;
        return $this;
    }
    public function db(){
        return $this->db;
    }

    /**
     * @param $num
     * @return \Generator|\SplFixedArray[][]|static[][]|array[][]
     */
    public function batch($num){
        $result = $this->db->table($this->tbName.($this->aliasName ? ' ' . $this->aliasName : ''))->batch($num);
        if (!$this->_asObj) {
            return $result;
        }
        $generator = function ($result) { //替换成带对象的新生成器
            foreach ($result as $rows) {
                $data = $rows instanceof \SplFixedArray ? new \SplFixedArray(count($rows)): [];
                foreach ($rows as $k => $row) {
                    $data[$k] = self::clone($this, $row);
                }
                yield $data;
            }
        };
        return $generator($result);
    }
    /**
     * @param string $field
     * @return int
     * @throws \Exception
     */
    public function count($field='*'){
        return $this->db->getCount($this->tbName.($this->aliasName ? ' ' . $this->aliasName : ''), '', $field);
    }
    protected static function runCall(Model $model, $method, $args){
        if (method_exists($model, $method)) {
            call_user_func_array([$model, $method], $args);
            return $model;
        } else { //调用db方法
            $model->_beforeDbMethod($method);
            $result = call_user_func_array([$model->db, $method], $args);
            if($result instanceof Db){
                return $model;
            }
            $model->_afterDbMethod($method, $result);
            return $result;
        }
    }
    //调用一个不可访问方法
    public function __call($method, $args)
    {
        return self::runCall($this, $method, $args);
    }
    //静态上下文中调用一个不可访问方法
    public static function __callStatic($method, $args)
    {
        return self::runCall(new static(), $method, $args);
    }

    /** 数据有效性处理
     * @param array $data 数据
     * @param array $rules 规则
     * @param bool $exclude 是否排除非rule规则键名的数据
     * @param bool $setDef 是否给有默认值但未设置的字段设置默认值
     * @param bool $all 验证所有字段
     * @return bool
     * @throws \RuntimeException
     */
    public static function validate(&$data, $rules, $exclude=false, $setDef=false, $all=true){
        try{
            foreach($data as $name=>$v){ //数据验证及是否多余数据处理
                if (isset($rules[$name])) {
                    //非禁用默认值及验证处理或表达式
                    if (!($setDef === 0 || $v instanceof Expr)) {
                        $hasDef = true;
                        $err1 = $err2 = '';
                        if (is_array($rules[$name])) { // 'name'=>['rule'=>'%s{25}'|['s','max'=>25,'err','err2'],'err','err2'
                            //是否有默认值 无默认值时则不能为空
                            $hasDef = isset($rules[$name]['def']) || array_key_exists('def', $rules[$name]);
                            $rule = isset($rules[$name]['rule']) ? $rules[$name]['rule'] : $rules[$name];
                            if (isset($rules[$name]['err'])) $err1 = $rules[$name]['err'];
                            if (isset($rules[$name]['err2'])) $err2 = $rules[$name]['err2'];
                        } else { // 'name'=>'%s{25}'
                            $rule = $rules[$name];
                        }
                        Value::type2val($v, $rule, '', !$hasDef, $name, $err1, $err2);

                        $data[$name] = $v;
                    }
                    unset($rules[$name]);
                } elseif ($exclude) {
                    unset($data[$name]);
                }
            }

            if($setDef!==0 && $all){ //未指定字段默认值处理
                foreach ($rules as $name=>$rule){ //是否可为空使用默认值
                    //是否有默认值  无默认值时则不能为空
                    if (isset($rule['def']) || array_key_exists('def', $rule)) {
                        if ($setDef) $data[$name] = $rule['def'];
                    } else {
                        $err = $name . ' is invalid';
                        if (is_array($rule)) {
                            if (isset($rule['rule']['err'])) $err = $rule['rule']['err'];
                            elseif (isset($rule['err'])) $err = $rule['err'];
                        }
                        throw new \RuntimeException($err);
                    }
                }
            }
        } catch (\RuntimeException $e) {
            return static::err($e->getMessage());
        }
        return true;
    }

    /**
     * @param Model $self
     * @param array $data
     * @return static
     */
    public static function clone($self, $data=[]){
        $model = clone $self;
        $model->db->resetOptions();
        $model->_oldData = $data;
        $self->formatData($model->_oldData);
        $model->_data = $model->_oldData;
        return $model;
    }

    /**
     * @param null $data
     * @param null $tbName
     * @param null $dbName
     * @return static
     * @throws \Exception
     */
    public static function create($data=null, $tbName=null, $dbName = null){
        $model = new static($tbName, $dbName);
        $model->_asObj = true;
        if ($data) {
            $model->setOldData($data);
        }
        return $model;
    }

    /**
     * @param array|array[] $post 添加数据可批量
     * @return bool|mixed|string
     * @throws \Exception
     */
    public static function insert($post, $validate=true){
        if ($validate && ($model = static::create()) && $model->fieldRule) { //有字段规则
            if (isset($post[0])) { //批量
                if($model->autoIncrement){ //自增键时排除验证规则
                    unset($model->fieldRule[$model->autoIncrement]);
                }
                foreach ($post as &$data) {
                    if (!self::validate($data, $model->fieldRule, true)) {
                        return false;
                    }
                }
            } else {
                if($model->autoIncrement && empty($post[$model->autoIncrement])){ //自增键[null 0 空]时排除验证规则
                    unset($model->fieldRule[$model->autoIncrement]);
                }
                //验证数据
                if (!self::validate($post, $model->fieldRule, true)) {
                    return false;
                }
            }
        }
        return static::getDb(false)->add($post, static::tableName());
    }
    /**
     * @param $data
     * @param string|array $where
     * @return bool|int
     * @throws \Exception
     */
    public static function updateAll($data, $where = '')
    {
        return static::getDb(false)->update($data, static::tableName(), $where);
    }

    /**
     * @param string|array $where
     * @return bool|int
     * @throws \Exception
     */
    public static function delAll($where)
    {
        return static::getDb(false)->del(static::tableName(), $where);
    }
}
