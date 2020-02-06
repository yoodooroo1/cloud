<?php
$mode = defined('MODE') ? MODE : 'common';
return array(
    // =======================================    根据环境的而不同配置不同的配置    =======================================
    'LOAD_EXT_CONFIG' => "config.{$mode}", // 加载扩展配置文件
);