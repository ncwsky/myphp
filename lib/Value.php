<?php
namespace myphp;

class Value{
    public static $before = null; //验证的前置操作 流程处理完请重置为null
    public static $after = null; //验证的后置操作 流程处理完请重置为null
    public static $defaultFilter = 'html_encode'; #默认过滤
    /**
     * $val值过滤
     * @param mixed $val
     * @param bool|int|string $filter 过滤方式 指定的过滤函数不存在时使用filter_var
     * @return bool
     */
    public static function filter(&$val, $filter=true){
        if ($filter===true) {
            self::$defaultFilter && $val = is_array($val) ? array_call_func(self::$defaultFilter, $val) : call_user_func(self::$defaultFilter, $val);
        }
        elseif(is_string($filter)){
            if ($filter[0] == '/') { // 支持正则验证
                if (1 !== preg_match($filter, (string)$val)) {
                    return false;
                }
                return true;
            }
            if(strpos($filter, ',')){ //多个过滤,分隔
                $filters = explode(',', $filter);
                foreach($filters as $filter){
                    if(function_exists($filter)) {
                        $val = is_array($val) ? array_call_func($filter, $val) : call_user_func($filter, $val);
                    }else{
                        $val = filter_var($val, is_numeric($filter) ? (int)$filter : filter_id($filter));
                    }
                    if(false === $val) break;
                }
            }else{
                if(function_exists($filter)) {
                    $val = is_array($val) ? array_call_func($filter, $val) : call_user_func($filter, $val);
                }else{
                    $val = filter_var($val, is_numeric($filter) ? (int)$filter : filter_id($filter));
                }
            }
        }
        elseif(is_int($filter)){
            $val = filter_var($val, $filter);
        }
        if(false === $val) {
            return false;
        }
        return true;
    }

    /**
     * @param array $data
     * @param string $name
     * @param string|array $rule array['rule','def'] | string %s{}:fun   %s,%b,%d,%f,%a,%date[2014-01-11 13:23:32],%his[13:23:32]  {1,20}取值范围 filter:fun1,fun2,/regx/i正则过滤
     * @param mixed $default $rule是string时可指定默认值
     * @param bool $strict 是否严格验证
     * @return array|bool|float|int|string
     * @throws \RuntimeException
     */
    public static function get(&$data, $name, $rule=null, $default=null, $strict=false){
        if (strpos($name, '.')) { //多维数组
            $val = Helper::getValue($data, $name);
        } else {
            $val = isset($data[$name]) ? $data[$name] : null;
        }

        if (is_array($rule)) {
            if (isset($rule['def'])) $default = $rule['def'];
            $rule = $rule['rule'];
        }

        self::type2val($val, $rule, $default, $strict, $name);
        return $val;
    }

    /**
     * 规则解析
     * @param string $rule
     * @param string $type
     * @param null $min
     * @param null $max
     * @param null $filter
     * @param int $digit
     *
     * eg.: %s{1,20}:filter  {1,20}取值范围
     * string,bool,int,float,arr,date
     * %s,%b,%d,%f,%a,%date [2014-01-11 13:23:32 | 2014-01-11]
     * filter:fun1,fun2,/regx/i正则过滤
     */
    public static function parseType(&$rule, &$type='s', &$min=null, &$max=null, &$filter=null, &$digit=0){
        if(strpos($rule,':')!==false){ // 指定过滤方法
            list($rule,$filter) = explode(':',$rule,2);
        }
        if(strpos($rule,'{')!==false && substr($rule,-1)=='}'){ // 指定长度范围 用于数字及字符串
            list($rule, $size) = explode('{', substr($rule, 0, -1), 2);
            if (strpos($size, ',')) {
                list($min, $max) = explode(',', $size, 2);
            } else {
                $max = $size;
            }
            #非64位的max min将会是数字字符串
            if(PHP_INT_SIZE === 8){
                if ($type == 'f') {
                    if($max!==null) $max = $max === '' ? null : (float)$max;
                    if($min!==null) $min = $min === '' ? null : (float)$min;
                } else {
                    if($max!==null) $max = $max === '' ? null : (int)$max;
                    if($min!==null) $min = $min === '' ? null : (int)$min;
                }
            } else {
                if ($max === '') $max = null;
                if ($min === '') $min = null;
            }
        }
        if(strpos($rule,'%')!==false){ // 指定修饰符
            list($rule,$type) = explode('%',$rule,2);
            if($type && $type[0]=='.'){ // %.2f
                $digit=(int)substr($type,-2,1);
                $type=substr($type,-1);
            }
        } else {
            $type = 's';
        }
    }

    /**
     * 指定类型取值处理
     * @param mixed $val
     * @param string|array $rule string:%s{10,20},%s{20}|array:['s','min'=>10,'max'=>20, filter, digit, err, err2],['s','max'=>20]  取值规则
     * @param null $default 默认值
     * @param bool $strict 强验证 失败抛出异常
     * @param string $name 提示名称
     * @return int $errCode
     */
    public static function type2val(&$val, $rule, $default=null, $strict=false, $name='value'){
        $type = 's'; // 默认转换为字符串
        $digit = 0; //小数位处理 四舍五入
        $errCode = 0;
        $filter = $max = $min = null;
        $err2 = $err1 = $name . ' is invalid';

        if($val==='' || $val===null) $errCode = 1;
        elseif(is_string($val)) $val = trim($val);

        if(is_array($rule)){
            if(isset($rule[0])) $type = $rule[0];
            if(isset($rule['min'])) $min = $rule['min'];
            if(isset($rule['max'])) $max = $rule['max'];
            if(isset($rule['digit'])) $digit = (int)$rule['digit']; #小数位
            if(isset($rule['err'])) $err2 = $err1 = $rule['err'];
            if(isset($rule['err2'])) $err2 = $rule['err2'];
            if(isset($rule['filter'])) $filter = $rule['filter'];
        }
        else{
            //规则处理
            self::parseType($rule, $type, $min, $max, $filter, $digit);
        }

        //filter过滤验证
        if($errCode==0 && $filter && !self::filter($val, $filter)) $errCode = 2;

        if($errCode==0){
            if(self::$before){ //验证的前置操作
                $val = call_user_func(self::$before, $val);
            }
            /*
            (int), (integer) - 转换为整形 integer (bool), (boolean) - 转换为布尔类型 boolean (float), (double), (real) - 转换为浮点型 float
            (string) - 转换为字符串 string (array) - 转换为数组 array (object) - 转换为对象 object (unset) - 转换为 NULL (PHP 5)
            */
            //类型转换
            switch($type){
                case 'a': // 数组
                    $val = (array)$val;
                    break;
                case 'd': // 数字
                case 'f': // 浮点
                    if ($val==='' || !is_numeric($val)) {
                        $errCode = 1;
                        //$val = $default;
                        break;
                    }
                    if($type=='d'){ //php_32位或32位系统有溢出bug
                        if(PHP_INT_SIZE === 8 || ($val>=-2147483648 && $val<=2147483647)) $val = (int)$val;
                    }else{
                        $val = (float)$val;
                        if($digit>0) $val = round($val, $digit);
                    }
                    if ($max !== null && $min !== null) {
                        if ($min > $val || $val > $max) $errCode = 2;
                        //$val = $min<=$val && $val<=$max ? $val : $default;
                    } elseif ($max !== null) {
                        if ($val > $max) $errCode = 2;
                        //$val = $val<=$max ? $val : $default;
                    } elseif ($min !== null) {
                        if ($min > $val) $errCode = 2;
                        //$val = $min<=$val ? $val : $default;
                    }
                    break;
                case 'b': // 布尔
                    $val = (bool)$val; break;
                case 'date'://Y-m-d[ H:i:s]
                    if(!Helper::is_date($val)) $errCode = 2;
                    //$val = Helper::is_date($val) ? $val : $default;
                    break;
                case 'ymd'://Y-m[-d]
                    if(!Helper::is_ymd($val)) $errCode = 2;
                    //$val = Helper::is_ymd($val) ? $val : $default;
                    break;
                case 'his':// h:i[:s]
                    if(!Helper::is_his($val)) $errCode = 2;
                    //$val = Helper::is_his($val) ? $val : $default;
                    break;
                case 'json':
                    if(!Helper::is_json($val)) $errCode = 2;
                    //$val = Helper::is_json($val) ? $val : $default;
                    break;
                case 's':   // 字符串
                default:
                    if($val==='') {
                        $errCode = 1;
                        //$val = $default;
                        break;
                    }
                    $val = (string)$val;
                    $len = strlen($val);
                    if($max!==null && $min!==null) {
                        if ($min > $len || $len > $max) $errCode = 2;
                        //$val = $min<=$len && $len<=$max ? $val : $default;
                    }elseif($max!==null) {
                        if ($len > $max) {
                            $val = cutstr($val,$max);
                        }
                        //$val = $len<=$max ? $val : cutstr($val,$max);
                    }elseif($min!==null) {
                        if ($min > $len) $errCode = 2;
                        //$val = $min<=$len ? $val : $default;
                    }
            }
            if(self::$after){ //验证的后置操作
                $val = call_user_func(self::$after, $val);
            }
        }

        //验证失败使用默认值
        if($errCode){
            if($strict){ //严格验证 无默认值异常
                # 1:值为空字符或null 2:值与对应类型设定不匹配
                throw new \RuntimeException($errCode==1?$err1:$err2);
            }
            if($type=='d' || $type=='f') {
                $default = $type == 'd' ? (int)$default : (float)$default;
            }/*elseif($type=='s'){
                $default = (string)$default;
            }*/elseif($type=='a'){
                $default = is_array($default) ? $default : [];
            }elseif($type=='b'){
                $default = (bool)$default;
            }
            $val = $default; #值验证未通过用默认值
        }
        return $errCode;
    }
}