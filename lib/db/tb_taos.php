<?php

declare(strict_types=1);

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
    public function fieldToRule(string $type, string &$vType, int $len=0)
    {
        $rule = '%s';
        if (strpos($type, 'unsigned')) { //无符号型
            $type = 'un' . $type;
            [$type, ] = explode(' ', $type, 2);
        }
        if (isset($this->fieldType[$type])) {
            $rule = $len > 0 && strpos($this->fieldType[$type], '{}') ? str_replace('{}', '{' . $len . '}', $this->fieldType[$type]) : $this->fieldType[$type];
        }
        $vType = $type;
        return $rule;
    }

    /** 取得数据表的字段信息
     * @param db_taos $db
     * @param string $tableName
     * @return array
     */
    public function getFields($db, string $tableName): array
    {
        $fields = '';
        $prikey = ''; //taosdata 表的第一个字段必须是 TIMESTAMP，并且系统自动将其设为主键；相同TIMESTAMP的插入时会被覆写
        $autoKey = '';
        $rule = [];
        $sql = 'DESCRIBE ' . $tableName;
        $res = $db->query($sql);
        while ($rs = $db->fetch($res)) {
            $rs = array_change_key_case($rs);
            if ($prikey == '') { //第一个字段 主键
                $prikey = $rs['field'];
            }

            $null = 1;
            $toRule = $this->fieldToRule(strtolower($rs['type']), $vType, $rs['length']);
            //规则
            $rule[$rs['field']] = [
                'type' => $this->toType($vType),
                'rule' => $toRule,
                'null' => $null
            ];
            //字段
            $fields .= $rs['field'].',';
        }
        return ['fields' => $fields == '' ? '*' : substr($fields, 0, -1),'prikey' => $prikey,'auto_increment' => $autoKey,'rule' => $rule];
    }

    /** 取得数据库的表信息
     * @param db_taos $db
     * @param string $dbName
     * @return array
     * @throws \Exception
     */
    public function getTables($db, string $dbName = ''): array
    {
        $tables = [];
        $sql = 'SHOW TABLES';
        $res = $db->query($sql);
        while ($rs = $db->fetch($res, 'num')) {
            $tables[] = $rs[0];
        }
        return $tables;
    }
}
