<?php
//获取表信息
class tb_sqlite extends TbBase
{
    //字段类型对应规则
    protected $fieldType = array(
        'integer'=>'%d',
        'real'=>'%f',
        'text'=>'%s{}',
        'blob'=>'%s'
    );

    /**
     * @param string $type 数据库取得的类型
     * @param string $vType 返回给php的类型
     * @return mixed|string
     */
    public function fieldToRule($type, &$vType)
    {
        $rule = '%s';
        $len = 0;
        if ($pos = strpos($type, '(')) {
            list($type, $len) = explode('(', substr($type, 0, -1), 2);
        }
        if (isset($this->fieldType[$type])) {
            $rule = strpos($this->fieldType[$type], '{}') ? str_replace('{}', $len>0?'{' . $len . '}':'', $this->fieldType[$type]) : $this->fieldType[$type];
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
        $sql = 'PRAGMA table_info(' . $tableName . ')';
        $res = $db->query($sql);
        while ($rs = $db->fetch_array($res)) {
            $rs = array_change_key_case($rs);
            $null = strtolower($rs['notnull']) == 0 ? 1 : 0;
            $toRule = $this->fieldToRule(strtolower($rs['type']), $vType);
            //规则
            $rule[$rs['name']] = array(
                'type' => $this->toType($vType),
                'rule' => $toRule,
                'null' => $null
            );
            //无def项时表示必需有值
            if ($rs['dflt_value'] !== null || $null) { //不是not null 或 非null的有默认值|可为null
                $rule[$rs['name']]['def'] = $rs['dflt_value'];
            }
            //主键
            if ($prikey == '' && (strtolower($rs['pk']) == 1)) { //sqlite只允许一个主键
                $prikey = $rs['name'];
                if($autoKey=='' && stripos($rs['type'],'int')!==false) $autoKey = $rs['name']; //自增主键
            }

            //字段
            $fields .= $rs['name'].',';
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
        $sql = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"; // UNION ALL SELECT name FROM sqlite_temp_master WHERE type='table'
        $res = $db->query($sql);
        while ($rs = $db->fetch_array($res, 'num')) {
            $tables[] = $rs[0];
        }
        return $tables;
    }
}