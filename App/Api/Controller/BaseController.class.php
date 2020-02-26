<?php
/**
 * Created by PhpStorm.
 * User: Ydr
 * Date: 2019/12/15
 * Time: 17:17
 */
namespace Api\Controller;

defined('QCLOUD_TOKEN') or define('QCLOUD_TOKEN','yk9LZyOiCxrTz8h6');  //腾讯云Token

class BaseController extends \Common\Controller\BaseController
{

    private $action = NULL;

    private $allowAction = array(
        'verifyInterface',
        'createInstance',
        'renewInstance',
        'modifyInstance',
        'expireInstance',
        'destroyInstance',
    );

    public function __construct()
    {
        parent::__construct();

        $body = file_get_contents('php://input');
        $this->req = array_merge($this->req,json_decode($body,true));
        $checkSign = checkSignature($this->req['signature'],QCLOUD_TOKEN,$this->req['timestamp'],$this->req['eventId']);

        if(false === $checkSign){
            writeLogs('签名错误,'.jsonEncode($this->req),ERR_LOG_PATH);
            die('error');
        }

        if(false === $this->setAction($this->req['action'])){
            writeLogs('方法错误,'.jsonEncode($this->req),ERR_LOG_PATH);
            die('error');
        }


    }

    protected function setAction($action){
        if(!empty($action)){
            if(in_array($action,$this->allowAction)){
                $this->action = $action;
                C('MARKET_ACTION', $this->action);
                return $this;
            }else{
                return false;
            }
        }
        return false;
    }

    protected function getAction(){
        return $this->action;
    }

    /**
     * 代理商接口
     * @param string $action
     * @param string $control
     * @return string
     * User: Ydr
     */
    protected function getShopApi($action = '',$control ='Market'){
        return YYS_URL.'index.php?m=api&c='.$control.'&a='.$action;
    }

}