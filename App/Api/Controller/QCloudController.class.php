<?php
/**
 * Created by PhpStorm.
 * User: Ydr
 * Date: 2019/12/5
 * Time: 17:25
 */

namespace Api\Controller;


class QCloudController extends BaseController
{
    const MARKET_TYPE = 1;  //市场类型
    const SHOP_STATUS_SUCCESS = 1;
    const SHOP_STATUS_ERROR = 2;

    protected $response = [];
    protected $record = [];

    public function __construct()
    {
        parent::__construct();
        C('MARKET_TYPE',self::MARKET_TYPE);
        writeLogs(jsonEncode($this->req,256),QCLOUD_LOG_PATH);
    }

    public function index(){
        switch ($this->getAction()){
            case 'verifyInterface' :
                $this->verifyInterface();
                break;
            case 'createInstance' :
                $this->createInstance();
                break;
            case 'renewInstance' :
                $this->renewInstance();
                break;
            case 'modifyInstance' :
                $this->modifyInstance();
                break;
            case 'expireInstance' :
                $this->expireInstance();
                break;
            case 'destroyInstance' :
                $this->destroyInstance();
                break;
            case 'test' :
                $this->test();
                break;
            default : die();
        }
    }

    public function verifyInterface(){
        $this->response['echoback'] = $this->req['echoback'];
        jsonResponse($this->response);
    }

    /**实例创建**/
    public function createInstance(){
        $XXShopOpenData = D('MarketInstance')->resolveQCloudOpenParams($this->req);
        $log_str = '[QCloud -> createInstance] post : '.json_encode($XXShopOpenData,256)."\n\r";
        writeLogs($log_str,QCLOUD_LOG_PATH);
        $rs = postCurl($this->getYysUrl('openShop'),$XXShopOpenData);
        $log_str = '[QCloud -> createInstance] rs : '.$rs."\n\r";
        writeLogs($log_str,QCLOUD_LOG_PATH);
        $rs = json_decode($rs,256);
        if($rs && $rs['code'] == CODE_SUCCESS){
            $data = $rs['data'];
            D('MarketInstance')->addQCloudInstance($this->req,$data);
            $this->record['shop_order_id'] = $data['order_id'];
            $this->record['shop_status'] = self::SHOP_STATUS_SUCCESS;
            $this->response['signId'] = $data['account_id'];
            $appInfo = [];
            $appInfo['website'] = $data['index_url'];
            $additionalInfo = [];
            $this->response['appInfo'] = $appInfo;
            $this->response['additionalInfo'] = $additionalInfo;
            $this->response['additionalInfo']['user'] = $data['user'];
            $this->response['additionalInfo']['password'] = $data['password'];
        }else if($rs['code'] == CODE_ERROR){
            $this->record['shop_status'] = self::SHOP_STATUS_ERROR;
            $this->record['error_msg'] = $rs['error_msg'];
        }
        $this->record['cloud_request_action'] = $this->getAction();
        $this->record['cloud_market_type'] = self::MARKET_TYPE;
        $this->record['cloud_order_id'] = $this->req['orderId'];
        $this->record['creat_time'] = TIMESTAMP;
        M('request_record')->add($this->record);
        jsonResponse($this->response);
    }

    /**续费**/
    public function renewInstance(){
        $this->response['success'] = "true";
        jsonResponse($this->response);
        die();
        $shop_account_id = $this->req['singId'];
        $cloudInstance = D('MarketInstance')->getInstanceByShopId($shop_account_id);
        if(!empty($cloudInstance)){
            $XXRenewData = D('MarketInstance')->resolveQCloudRenewParams($this->req,$cloudInstance);
            $log_str = '[QCloud -> renewInstance] post : '.json_encode($XXRenewData,256);
            writeLogs($log_str,QCLOUD_LOG_PATH);
            $rs = postCurl('',$XXRenewData);
            $log_str = '[QCloud -> renewInstance] rs : '.$rs;
            writeLogs($log_str,$rs);
            $rs = json_decode($rs,256);
            if($rs['code'] == 200){
                $this->response['success'] = 'true';
                $this->record['shop_status'] = 1;
                D('MarketInstance')->setRenewInstance($this->req,$cloudInstance['shop_account_id']);
            }else if($rs['code'] == 500){
                $this->record['shop_status'] = 2;
                $this->record['error_msg'] = $rs['error_msg'];
            }else{
                $this->record['shop_status'] = -1;
            }
            $this->record['cloud_order_id'] = $this->req['orderId'];
            $this->record['cloud_request_id'] = $this->req['requestId'];
            D('MarketRequestRecord')->addRequestRecord($this->record);
            jsonResponse($this->response);
        }else{
            $log_str = '[QCloud -> renewInstance] err : 未找到实例';
            writeLogs($log_str,QCLOUD_LOG_PATH);
            jsonResponse($this->response);
        }
    }

    /**试用转正式**/
    public function modifyInstance(){
        $shop_account_id = $this->req['singId'];
        $cloudInstance = D('MarketInstance')->getInstanceByShopId($shop_account_id);
        if(!empty($cloudInstance)){
            if($cloudInstance['is_try'] == 1){
                $XXModifyData = D('MarketInstance')->resolveQCloudModifyParams($this->req);
                $log_str = '[QCloud -> modifyInstance] post : '.json_encode($XXModifyData,256);
                writeLogs($log_str,QCLOUD_LOG_PATH);
                $rs = postCurl('',$XXModifyData);
                $log_str = '[QCloud -> modifyInstance] rs : '.$rs;
                writeLogs($log_str,$rs);
                $rs = json_decode($rs,256);
                if($rs['code'] == CODE_SUCCESS){
                    D('MarketInstance')->setModifyInstance($this->req,$cloudInstance['shop_account_id']);
                    $this->response['success'] = 'true';
                    $this->record['shop_status'] = self::SHOP_STATUS_SUCCESS;
                }else if($rs['code'] == CODE_ERROR){
                    $this->record['shop_status'] = self::SHOP_STATUS_ERROR;
                    $this->record['error_msg'] = $rs['error_msg'];
                }
                $this->record['cloud_request_action'] = $this->getAction();
                $this->record['cloud_market_type'] = self::MARKET_TYPE;
                $this->record['cloud_order_id'] = $this->req['orderId'];
                $this->record['creat_time'] = TIMESTAMP;
                M('request_record')->add($this->record);
            }else{
                $log_str = '[QCloud -> modifyInstance] err : 商品非试用';
                writeLogs($log_str,QCLOUD_LOG_PATH);
                jsonResponse($this->response);
            }
        }else{
            $log_str = '[QCloud -> modifyInstance] err : 未找到实例';
            writeLogs($log_str,QCLOUD_LOG_PATH);
            jsonResponse($this->response);
        }
    }

    /**过期**/
    public function expireInstance(){
        $this->response['success'] = "true";
        jsonResponse($this->response);
    }

    /**销毁**/
    public function destroyInstance(){
        $this->response['success'] = "true";
        jsonResponse($this->response);
    }

}