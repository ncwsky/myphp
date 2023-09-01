<?php
namespace myphp\db;

//获取表信息
class tb_taos extends \myphp\TbBase
{
    public function __construct()
    {
        $this->fieldType['binary'] = '%s{}'; // 16374~65517
        $this->fieldType['bool'] = '%b';
        $this->fieldType['nchar'] = '%s{}'; //每个 NCHAR 字符占用 4 字节
        $this->fieldType['geometry'] = '%s{}'; // 几何类型
    }

    /**
     * @param string $type 数据库取得的类型
     * @param string $vType 返回给php的类型
     * @param int $len
     * @return mixed|string
     */
    public function fieldToRule($type, &$vType, $len=0)
    {
        $rule = '%s';
        if (strpos($type, 'unsigned')) { //无符号型
            $type = 'un' . $type;
            list($type,) = explode(' ', $type, 2);
        }
        if (isset($this->fieldType[$type])) {
            $rule = $len > 0 && strpos($this->fieldType[$type], '{}') ? str_replace('{}', '{' . $len . '}', $this->fieldType[$type]) : $this->fieldType[$type];
        }
        $vType = $type;
        return $rule;
    }

    /** 取得数据表的字段信息
     * @param db_taos $db
     * @param $tableName
     * @return array
     */
    public function getFields($db, $tableName)
    {
        $fields = '';
        $prikey = ''; //taosdata 表的第一个字段必须是 TIMESTAMP，并且系统自动将其设为主键；相同TIMESTAMP的插入时会被覆写
        $autoKey = '';
        $rule = array();
        $sql = 'DESCRIBE ' . $tableName;
        $res = $db->query($sql);
        while ($rs = $db->fetch_array($res)) {
            $rs = array_change_key_case($rs);
            if ($prikey == '') $prikey = $rs['field']; //第一个字段 主键

            $null = 1;
            $toRule = $this->fieldToRule(strtolower($rs['type']), $vType, $rs['length']);
            //规则
            $rule[$rs['field']] = array(
                'type' => $this->toType($vType),
                'rule' => $toRule,
                'null' => $null
            );
            //字段
            $fields .= $rs['field'].',';
        }
        return array('fields'=>$fields==''?'*':substr($fields,0,-1),'prikey'=>$prikey,'auto_increment'=>$autoKey,'rule'=>$rule);
    }

    /** 取得数据库的表信息
     * @param db_taos $db
     * @param string $dbName
     * @return array
     * @throws \Exception
     */
    public function getTables($db, $dbName = '')
    {
        $tables = [];
        $sql = 'SHOW TABLES';
        $res = $db->query($sql);
        while ($rs = $db->fetch_array($res, 'num')) {
            $tables[] = $rs[0];
        }
        return $tables;
    }
}