<?php
namespace myphp;

class Tool
{
    use \MyMsg;

    public static function run(){
        //todo 解析脚本命令执行本类方法
    }

    /**
     * 生成表model
     * @param $tbName
     * @param string $namespace
     * @param string $baseClass
     * @param string $dbName
     * @return bool
     */
    public static function initModel($tbName, $namespace='common\model', $baseClass='\myphp\Model', $dbName='db'){
        $className = str_replace(' ', '', ucwords(str_replace(['-','_'], ' ', $tbName), ' '));

        $classFile = ROOT . '/' . ($namespace ? strtr($namespace, '\\', '/') . '/' : '') . $className . '.php';

        try {
            $content = is_file($classFile) ? file_get_contents($classFile) : file_get_contents(__DIR__ . '/../tpl/TableModel.php');

            if(!$content){
                throw new \Exception('model文件读取失败');
            }

            db($dbName)->getFields($tbName, $prikey, $fields, $fieldRule, $autoIncrement);

            $classDir = dirname($classFile);
            if(!is_dir($classDir)){
                mkdir($classDir, 0755, true);
            }
        } catch (\Throwable $e) {
            self::err($e->getMessage());
            return false;
        }

        //print_r($fieldRule);

        //namespace __package__;
        $content = str_replace('namespace '.substr_cut($content, 'namespace ', ';', 0, false).';', "namespace $namespace;", $content);

        // 注释 /**  property  */
        $notes = "/**\n* Class $className\n* @package $namespace\n*";
        foreach ($fieldRule as $k=>$v){
            $type = 'string';
            $rule = substr($v['rule'],1,1);
            if ($rule == 'd') {
                $type = 'int';
            } elseif ($rule == 'b') {
                $type = 'bool';
            } elseif ($rule == 'f') {
                $type = 'float';
            }
            $notes .="\n* @property $type \$$k";
            //解析规则
            $type = 's'; $min = $max = null;
            CheckValue::parseType($v['rule'], $type, $min, $max);
            unset($fieldRule[$k]['null']);
            $fieldRule[$k]['rule'] = [$type, 'min' => $min, 'max' => $max];
            if(isset($fieldRule[$k]['def'])){
                if($fieldRule[$k]['type']=='int'){
                    $fieldRule[$k]['def'] = (int)$fieldRule[$k]['def'];
                }elseif($fieldRule[$k]['type']=='double'){
                    $fieldRule[$k]['def'] = (float)$fieldRule[$k]['def'];
                }
            }
        }
        $notes .="\n*/";
        $noteFlag = substr_cut($content, "/**", "*/\nclass", 0, false);
        $classHead = "class " . substr_cut($content, "class ", "{", 0, false) . "{";
        if($noteFlag){
            $content = str_replace("/**".$noteFlag."*/", $notes, $content);
        }else{
            $content = str_replace($classHead, $notes."\n".$classHead, $content);
        }

        //class __name__ extends __parent__ {
        $content = str_replace($classHead, "class $className extends $baseClass {", $content);

        //protected static $dbName = '__db__';
        $content = str_replace('static $dbName '.substr_cut($content, 'static $dbName ', ';', 0, false).';', "static \$dbName = '$dbName';", $content);

        //protected static $tableName = '__table__';
        $content = str_replace('static $tableName '.substr_cut($content, 'static $tableName ', ';', 0, false).';', "static \$tableName = '$tbName';", $content);

        //protected $prikey = '';
        $content = str_replace('protected $prikey '.substr_cut($content, 'protected $prikey ', ';', 0, false).';', "protected \$prikey = '$prikey';", $content);

        //protected $autoIncrement = ''; //自增键
        $content = str_replace('protected $autoIncrement '.substr_cut($content, 'protected $autoIncrement ', ';', 0, false).';', "protected \$autoIncrement = '$autoIncrement';", $content);

        //protected $fields = '*';
        $content = str_replace('protected $fields '.substr_cut($content, 'protected $fields ', ';', 0, false).';', "protected \$fields = '$fields';", $content);

        $fieldRule = strtr(var_export($fieldRule, true), ["=> \n  " => "=> ", "array (" => "[", "  )" => "        ]", "  '" => "        '", ")" => "    ]","NULL"=>"null","\n      0 => "=>"","\n      'min'"=>"'min'","\n      'max'"=>"'max'",",\n    )"=>"]"]);
        $fieldRule = str_replace("'rule' =>   ","'rule' => ", $fieldRule);
        //$fieldRule = var_export($fieldRule, true);
        //public $fieldRule = [];
        $content = str_replace('public $fieldRule '.substr_cut($content, 'public $fieldRule ', ';', 0, false).';', "public \$fieldRule = $fieldRule;", $content);
        $content = str_replace('protected $fieldRule '.substr_cut($content, 'protected $fieldRule ', ';', 0, false).';', "protected \$fieldRule = $fieldRule;", $content);

        file_put_contents($classFile, $content);
        return true;
    }
}
