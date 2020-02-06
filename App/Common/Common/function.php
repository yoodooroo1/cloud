<?php
defined('TIMESTAMP') or define('TIMESTAMP', $_SERVER['REQUEST_TIME']);

//
defined('CODE_SUCCESS') or define('CODE_SUCCESS',200);
defined('CODE_ERROR') or define('CODE_ERROR',500);

//日志定义
defined('QCLOUD_LOG_PATH') or define('QCLOUD_LOG_PATH',LOG_PATH. 'QCloud/');
defined('ERR_LOG_PATH') or define('ERR_LOG_PATH',LOG_PATH. 'Err/');

/**
 * Created by PhpStorm.
 * User: Ydr
 * Date: 2019/12/5
 * Time: 10:06
 */


/**
 * json序列化
 * @param $param
 * @return string
 * User: hjun
 * Date: 2018-08-22 15:05:55
 * Update: 2018-08-22 15:05:55
 * Version: 1.00
 */
function jsonEncode($param)
{
    if (empty($param)) return '';
    $data = json_encode($param, 256);
    if ($data === 'null' || $data === '[]' || $data === '{}') {
        return '';
    }
    return $data;
}

/**
 * 成功返回数据
 * @access public
 * @param object $datas 返回数据
 * @param bool $log 是否需要打印日志
 * @param string $fag 日志标记
 * @return string
 */
function jsonResponse($data = [])
{
    header("Content-type:text/html;charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    die;
}


function postCurl($url='',$data = array()) {

    $ch = curl_init ();
    curl_setopt ( $ch, CURLOPT_URL, $url );
    curl_setopt ( $ch, CURLOPT_POST, 1 );
    curl_setopt ( $ch, CURLOPT_HEADER, 0 );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data);
    $datas = curl_exec ($ch);

    $curl_errno = curl_errno($ch);
    if($curl_errno=='0'){
        curl_close($ch);
        return $datas;
    }else{
        curl_close($ch);
        $resultdata = array();
        $resultdata['result'] = -1;
        $resultdata['desc'] = "curl出错，错误码:".$curl_errno;
        return json_encode($resultdata, JSON_UNESCAPED_UNICODE);
    }
}


/**
 * 记录日志
 * 默认是信息等级
 * @param string $message
 * @param string $level
 * @param string $method
 * @param boolean $record
 * @param string $type
 * @param string $path
 * User: hj
 * Date: 2017-09-14 00:40:00
 */
function logWrite($message = '', $level = 'INFO', $method = 'record', $record = false, $type = '', $path = '')
{
    switch ($method) {
        case 'write':
            if (empty($path)) {
                $path = C('LOG_PATH');
            }
            \Think\Log::write($message, $level, $type, $path . date('y_m_d') . '.log');
            break;
        default:
            \Think\Log::record($message, $level, $record);
            break;
    }
}

/**
 * 返回配置项数组或对应值(快速缓存)
 * @param string|integer $key 标识名,标识为空则返回所有配置项数组
 * @param string|integer $name 缓存名称
 * @return mixed
 */
function get_cfg_value($key = '', $name = 'site')
{
    if (empty($name)) {
        return array();
    }
    $sname = 'config/' . $name;
    $array = F($sname);
    if (!$array) {
        $data = M('config')->field('name,value,typeid')->select();
        $array = array();
        if ($data) {
            foreach ($data as $value) {
                $array[$value['name']] = $value['value'];
            }
        }
        F($sname, $array);
    }
    if ($key == '') {
        return $array;
    } else {
        $value = isset($array[$key]) ? $array[$key] : '';
        return $value;
    }

}
function checkSignature($signature, $token, $timestamp, $eventId)
{
    $currentTimestamp = time();
    if ($currentTimestamp - $timestamp > 30) {
        return false;
    }
    $timestamp = (string)$timestamp;
    $eventId = (string)$eventId;
    $params = array($token, $timestamp, $eventId);
    sort($params, SORT_STRING);
    $str = implode('', $params);
    $requestSignature = hash('sha256', $str);
    return $signature === $requestSignature;
}

/*日志*/
function writeLogs($message = '',$path = '',$method = 'write'){
    if(empty($path)){
        $path = LOG_PATH;
    }
    \Think\Log::$method($message, \Think\Log::INFO, "",$path.date('y_m_d').'.log');
}