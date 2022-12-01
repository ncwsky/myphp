<?php
namespace __package__;

/**
 * Class __name__ auto make
 * @package __package__
 *
 * @property int $id 属性
 */
class __name__ extends \myphp\Model {
    // 当前操作数据库名表名
    protected static $dbName = '__db__';
    protected static $tableName = '__table__';
    //主键
    protected $prikey = '';
    //自增键
    protected $autoIncrement = '';
    //表查询的字段
    protected $fields = '*';

    /**
     * @var array 字段规则 未配置时将自动获取 $fieldRule $prikey $autoIncrement $fields
     */
    public $fieldRule = [];

}