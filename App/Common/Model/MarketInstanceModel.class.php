<?php
/**
 * Created by PhpStorm.
 * User: Ydr
 * Date: 2019/12/17
 * Time: 17:04
 */

namespace Common\Model;


class MarketInstanceModel extends BaseModel
{
    const DELETE = 1;
    const NOT_DELETE = 0;
    protected $tableName = 'market_instance';

    /**获取实例单信息
     * @param $id
     * @return array
     **/
    public function getInstanceByShopId($id = 0){
        $where = [];
        $where['is_delete'] = self::NOT_DELETE;
        $where['shop_account_id'] = $id;
        $data = $this->where(array('shop_account_id'=>$id))->find();
        if($data){
            return $data;
        }
        return NULL;
    }

    /**解析腾讯云开户请求参数
     * @param array $params
     * @return array
     **/
    public function resolveQCloudOpenParams($params = []){
        $data = [];
        $data['cloud_market_type'] = $this->getMarketType();
        $productInfo = $params['productInfo'];
        if($productInfo['isTrial'] === "true"){
            $data['is_try'] = '1';
        }else{
            $timeUnit = $productInfo['timeUnit'];
            $timeSpan = (int)$productInfo['timeSpan'];
            $timeLong = 0;
            if($timeUnit === 'y'){
                $timeLong = (365 * 84600 * $timeSpan);
            }else if($timeUnit === 'm'){
                $timeLong = 30 * 84600 * $timeSpan;
            }else if($timeUnit === 'd'){
                $timeLong = 84600 * $timeSpan;
            }
            $data['is_try'] = '0';
            $data['vip_time'] = $timeLong;
            $data['spec_name'] = $productInfo['spec'];
            $data['age_limit'] = $timeSpan;
        }
        $data['cloud_product_id'] = $params['productId'];
        $data['qcloud_openid'] = $params['openId'];
        if($params['extendInfo']){
            $data['remark'] = $params['extendInfo'];
        }
        return $data;
    }

    /**解析腾讯云续费请求参数
     * @param array $params
     * @param array $instance
     * @return array
     **/
    public function resolveQCloudRenewParams($params = [], $instance = []){
        $data = [];
        $data['cloud_market_type'] = $this->getMarketType();
        $data['account_id'] = $params['signId'];
        $data['vip_time'] =  (int)strtotime($params['instanceExpireTime']);
        $data['cloud_product_id'] = $params['productId'];
        $data['spec_name'] = $instance['cloud_product_spec'];
        return $data;
    }

    /**解析腾讯云测试转正式请求参数
     * @param array $params
     * @return array
     **/
    public function resolveQCloudModifyParams($params = []){
        $data = [];
        $data['cloud_market_type'] = $this->getMarketType();
        $data['account_id'] = $params['signId'];
        $data['vip_time'] =  (int)strtotime($params['instanceExpireTime']);
        $data['spec_name'] = (string)$params['spec'];
        $data['cloud_product_id'] = $params['productId'];
        return $data;
    }

    /**实例创建
     * @param array $params 腾讯云参数
     * @param array $data   迅信参数
     **/
    public function addQCloudInstance($params = [], $data = []){
        $parameters = [];
        $productInfo = $params['productInfo'];
        if($productInfo['isTrial'] === "true"){
            $parameters['is_try'] = '1';
        }else{
            $parameters['is_try'] = '0';
            $parameters['cloud_product_spec'] = $productInfo['spec'];
            $parameters['expire_time'] = time()+(int)$params['vip_time'];
        }
        if(!empty($data['index_url'])){
            $parameters['index_url'] = $data['index_url'];
        }
        if(!empty($data['auth_url'])){
            $parameters['auth_url'] = $data['auth_url'];
        }
        $parameters['shop_account_id'] = $data['account_id'];
        $parameters['shop_member_id'] = $data['user'];
        $parameters['shop_member_password'] = $data['password'];
        $parameters['cloud_qcloud_openid'] = $params['openId'];
        $parameters['cloud_market_type'] = $this->getMarketType();
        $parameters['cloud_account_id'] = $params['accountId'];
        $parameters['cloud_product_id'] = $params['productId'];
        $parameters['cloud_product_name'] = $productInfo['productName'];
        $parameters['cloud_des'] = $params['extendInfo'];
        $parameters['create_time'] = TIMESTAMP;
        $this->add($parameters);
    }

    /**实例续费
     * @param array $params 腾讯参数
     * @param int $id 实例id
     **/
    public function setRenewInstance($params = [], $id = 0){
        $parameters = [];
        $parameters['expire_time'] = time() + (int)strtotime($params['instanceExpireTime']);
        $parameters['edit_time'] = TIMESTAMP;
        $parameters['renew_time'] = TIMESTAMP;
        $this->where(array('shop_account_id'=>$id))->save($parameters);
    }

    /**实例转为正式
     * @param array $params 腾讯参数
     * @param int $id 实例id
     **/
    public function setModifyInstance($params = [], $id = 0){
        $parameters = [];
        $parameters['expire_time'] = time() + (int)strtotime($params['instanceExpireTime']);
        $parameters['cloud_product_spec'] = (string)$params['spec'];
        $parameters['edit_time'] = TIMESTAMP;
        $parameters['modify_time'] = TIMESTAMP;
        $parameters['is_try'] = 0;
        $this->where(array('shop_account_id'=>$id))->save($parameters);
    }
}