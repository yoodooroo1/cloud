<?php
/**
 * 复制该文件，重命名为global.php即可
 * Created by PhpStorm.
 * User: hjun
 * Date: 2018/6/9
 * Time: 15:00
 */

// 引入全局函数文件
require_once 'function.php';
//require_once realpath(__DIR__ . '/../') . '/vendor/autoload.php';

// 是否是XDebug模式
$isXDebug = true;
if ($isXDebug) {
    error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
}


// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG', true);

// 定义应用模式 用于加载不同的配置文件 不定义默认为common
// common               => 正式环境
// dev                  => 测试环境
// home_dev             => 本地测试环境
// home_common          => 本地正式环境
define('MODE', 'dev');

define('SIGN_DEBUG', true);


