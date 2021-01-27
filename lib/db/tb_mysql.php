<?php
//获取表信息
class tb_mysql extends TbBase
{
    /**
     * @param string $type 数据库取得的类型
     * @param string $vType 返回给php的类型
     * @return mixed|string
     */
    public function fieldToRule($type, &$vType)
    {
        $rule = '%s';
        $len = 0;
        if (strpos($type, 'unsigned')) { //无符号型
            $type = 'un' . $type;
            list($type,) = explode(' ', $type, 2);
        }
        if ($pos = strpos($type, '(')) {
            list($type, $len) = explode('(', substr($type, 0, -1), 2);
        }
        if (isset($this->fieldType[$type])) {
            $rule = $len > 0 && strpos($this->fieldType[$type], '{}') ? str_replace('{}', '{' . $len . '}', $this->fieldType[$type]) : $this->fieldType[$type];
        }
        $vType = $type;
        return $rule;
    }

    /** 取得数据表的字段信息
     * @param db_pdo $db
     * @param $tableName
     * @return array
     */
    public function getFields($db, $tableName)
    {
        $fields = '';
        $prikey = '';
        $autoKey = '';
        $rule = array();
        $sql = 'SHOW COLUMNS FROM ' . $tableName;
        $res = $db->query($sql);
        while ($rs = $db->fetch_array($res)) {
            $rs = array_change_key_case($rs);
            $null = strtolower($rs['null']) == 'yes' ? 1 : 0;
            $toRule = $this->fieldToRule(strtolower($rs['type']), $vType);
            //规则
            $rule[$rs['field']] = array(
                'type' => $this->toType($vType),
                'rule' => $toRule,
                'null' => $null
            );
            //无def项时表示必需有值
            if ($rs['default'] !== null || $null) { //不是not null 或 非null的有默认值|可为null
                $rule[$rs['field']]['def'] = $rs['default'];
            }
            //主键
            if (strtolower($rs['key']) == 'pri') {
                if($prikey == '') $prikey = $rs['field'];
                if($autoKey!=$prikey && stripos($rs['extra'],'auto_increment')!==false) { //自增主键
                    $autoKey = $rs['field'];
                    $prikey = $rs['field'];
                }
            }
            if($autoKey=='' && stripos($rs['extra'],'auto_increment')!==false) { //自增键
                $autoKey = $rs['field'];
            }
            //字段
            $fields .= $rs['field'].',';
        }
        return array('fields'=>$fields==''?'*':substr($fields,0,-1),'prikey'=>$prikey,'auto_increment'=>$autoKey,'rule'=>$rule);
    }

    /** 取得数据库的表信息
     * @param db_pdo $db
     * @param string $dbName
     * @return array
     */
    public function getTables($db, $dbName = '')
    {
        $tables = array();
        $sql = $dbName != '' ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES';
        $res = $db->query($sql);
        while ($rs = $db->fetch_array($res, 'num')) {
            $tables[] = $rs[0];
        }
        return $tables;
    }
}