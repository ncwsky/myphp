<?php declare(strict_types=1);

/**
 * Copyright © 2024 cclilshy
 * Email: jingnigg@gmail.com
 *
 * This software is licensed under the MIT License.
 * For full license details, please visit: https://opensource.org/licenses/MIT
 *
 * By using this software, you agree to the terms of the license.
 * Contributions, suggestions, and feedback are always welcome!
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in(__DIR__)
    ->exclude(['vendor','tpl']) // 排除 vendor 目录
    ->notPath('def_config.php')
    ->notPath('tpl/config.php')
    ->name('*.php');
    //->exclude('def_config.php')
    //->notName(['*config.php']);

$config = new Config();

$config->setFinder($finder);
$config->setRiskyAllowed(true);

return $config->setRules([
    '@PSR12' => true, // 使用 PSR-2 规则集
    '@PHP74Migration' => true,
    //'@PHP74Migration:risky' => true,
    //'@PHP73Migration' => true,

    'array_syntax' => ['syntax' => 'short'], // 使用短数组语法
    //'blank_line_after_opening_tag' => true, // PHP 标签后必须换行
    //'blank_line_after_namespace'=>true,
    'no_trailing_whitespace' => true, // 去除行尾的空白字符
    'trailing_comma_in_multiline'=> false, //不添加尾行逗号
    'no_trailing_whitespace_in_comment' => true, // 移除注释中的尾随空白字符
    'no_extra_blank_lines' => true, // 移除多余的空行
	'nullable_type_declaration_for_default_null_value' => true, //强制在默认值为 null 的属性或方法参数上使用可空类型声明
/*    'return_type_declaration' => [
        'space_before' => 'none', // 返回类型声明前不加空格
    ],*/
    //让方法或函数应该有一个明确的返回类型PHP-CS-Fixer应该怎么配置
    //'phpdoc_to_param_type'=>true,
    //'phpdoc_to_property_type'=>true,
    //'phpdoc_to_return_type' => true, //启用phpdoc到返回类型声明的转换（如果你有这样的需求）
    'declare_strict_types'  => true, //确保所有文件都包含 declare(strict_types=1); 声明
    //'strict_param' => true, // 强制函数的参数必须是严格模式

    //'void_return' => true, // 使用有效的返回类型声明
    //'no_unused_imports'            => true, //移除未使用的 use 语句
]);
