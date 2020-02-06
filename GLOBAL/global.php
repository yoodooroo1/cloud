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



// 定义应用模式 用于加载不同的配置文件 不定义默认为common
// common               => 正式环境
// dev                  => 测试环境
// home_dev             => 本地测试环境
// home_common          => 本地正式环境
define('MODE', 'dev');




