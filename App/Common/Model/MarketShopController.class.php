<?php
/**
 * Created by PhpStorm.
 * User: Ydr
 * Date: 2019/12/18
 * Time: 10:54
 */

namespace Home\Controller;


use Think\Controller;

class MarketShopController extends Controller
{
    public function __construct()
    {
        header("Content-Type: text/html;charset=utf-8");
        parent::__construct();
    }


    public function test(){
        $code = randorderno(6);
        var_dump($code);;
    }

    /**
     * index
     * params  int $cloud_market_type 市场类型
     * params  tinyint $is_try 是否试用 0非试用 1-试用
     * params  int $cloud_product_id 套餐编号
     * params  int $age_limit 使用年限
     * params  string $qcloud_openid 腾讯云openId
     * params  string $remark 备注
     * params  string $vip_time 套餐时长
     */
    public function index(){
        $openId = I('qcloud_openid');
        $center = M('operate_center');
        $market_type = I('cloud_market_type');
        $marketConfig = $this->getMarketConfig($market_type);
        if(empty($marketConfig) || empty($marketConfig['operate_sn'])){
            $this->output_error(-1,'未找到相应的配置');
        }
        $operate_sn = $marketConfig['operate_sn'];
        $operate_info = $center->where(array($operate_sn=>'operate_sn'))->find();
        $cloud_product_id = I('cloud_product_id');
        $product = M('cloud_product_list')->where(array('cloud_product_id'=>$cloud_product_id,'status'=>1))->find();
        $product_id = $product['product_id'];
        $product_spec = [];
        $is_try = I('is_try');
        if($is_try == 1){
            $vip = 2;
            $vip_time = 0;
            $try_day = $product['try_day'];
            $try_time = (int)$try_day * 84000;
        }else{
            $spec_name = I('spec_name');
            $product_spec = M('cloud_product_spec')->where(array('product_id'=>$product_id,'spec_name'=>$spec_name))->find();
            $vip = $product_spec('up_level');
            $vip_time = I('vip_time');
            $try_time = 0;
            $cost_price = $product_spec['market_price'];
            if($cost_price > $operate_info['money']){
                $this->output_error(-1,'该运营商预存资金为￥'.$operate_info['money'].',购买套餐成本为￥'.$cost_price.',预存资金不足');
            }
        }
        $age_limit = 0;
        $xunXin_num = '';
        $xunxin_num = $this->getXunXinNum($vip);
        if(empty($xunXin_num)){
            $this->output_error(-2,'迅信帐号为空');
        }
        $passWard = $xunXin_num;
        $params = array();
        $params['is_naming'] = 1;
        $params['qcloud_openid'] = $openId;
        $params['shopName'] = 'cloud-'.TIMESTAMP;
        $params['account_membertel'] = '';
        $params['store_parenttype_id'] = 1;
        $params['store_childtype_id'] = 2;
        $params['vip'] = $vip;
        $params['xunxin_num'] = $xunxin_num;
        $params['password'] = $passWard;
        $params['is_try'] = $is_try;
        $params['try_time'] = $try_time;
        $params['age_limit'] = $age_limit;
        $remark  = I('remark');
        if(!empty($remark)){
            $params['remark'] = $remark;
        }
        $params['operate_num'] = $operate_sn;
        $params['operate_id'] = $operate_info['id'];
        $sync_url = M('system_config')->where(array('status'=>1))->getField('sync_url');
        $url = "http://".$sync_url."/xxapi/index.php?act=cloud_openaccount&op=applyStore";
        $json =  $this->postCurl($url,$params);
        $rt = json_decode($json,true);
        if($rt['result'] == -1){
            $this->output_error(-3,$rt['error']);
        }else {
            $account_id = $rt['datas'];
            $order_id = $this->create_package_order($product_spec,$account_id,$xunxin_num,$is_try,$product_id,$age_limit,$operate_sn,$params['account_membertel'],1,0);
            if (empty($order_id)) {
                $this->output_error(-4,'创建订单失败');
            } else {
                $pay_check = $this->pay_package_order($order_id);
                if ($pay_check['status'] == -1) {
                    $this->output_error(-5,$pay_check['desc']);
                } else {
                    $params2 = array();
                    $params2['account_id'] = $account_id;
                    $params2['opentype'] = 4;
                    $params2['platform_type'] = 2;
                    $params2['vip_time'] = $vip_time;
                    $url2 = "http://" . $sync_url . "/xxapi/index.php?act=operate_openaccount&op=openStore";
                    $json2 = $this->postCurl($url2, $params2);
                    $rt2 = json_decode($json2, true);
                    if ($rt2['result'] == '0' || $rt2['result'] == '-10' || $rt2['result'] == '1001') {
                        $order_sn = M('vip_orders')->where(array('id' => $order_id))->getField('orderSn');
                        M('data_record')->where(array('order_sn' => $order_sn))->save(array('open_result' => 'SUCCESS'));
                        $newdata = array();
                        $newdata['rechargetime'] = mktime();
                        $newdata['is_create'] = 1;
                        $newdata['store_id'] = $rt2['datas'];
                        M('vip_orders')->where(array('id' => $order_id))->save($newdata);
                        $this->settlement_package_order($order_id);
                        $this->checkOperateUplevel($operate_info['id']);
                        $auth_code = M('system_config')->where(array('status' => 1))->getField('auth_code');
                        $sync_url = ADMIN_URL.'/index.php?m=api&c=Store&a=index&auth_code=' . $auth_code;
                        $x = 1;
                        do {
                            $json = file_get_contents($sync_url);
                            $result = json_decode($json, true);
                        } while (++$x <= 3 && $result['result'] != '0' && $result['result'] != '1');
                        $rs = [];
                        $rs['account_id'] = $account_id;
                        $rs['xunxin_num'] = $xunxin_num;
                        $rs['password'] = $passWard;
                        $rs['order_id'] = $operate_sn;
                        $this->output_data($rs);
                    } else {
                        $this->output_error(-6,$rt2['error']);
                    }
                }
            }
        }
    }

    public function index2(){
        $account_id = I('account_id');
        $store_info = M('stores')->where(array('account_id'=>$account_id,'isdelete'=>0))->find();
        if(empty($store_info)){
            $this->output_error(-1,'该店铺不存在');
        }
        $market_type = I('cloud_market_type');
        $marketConfig = $this->getMarketConfig($market_type);
        if(empty($marketConfig) || empty($marketConfig['operate_sn'])){
            $this->output_error(-3,'未找到相应的配置');
        }
        $center = M('operate_center');
        $operate_sn = $marketConfig['operate_sn'];
        $operate_info = $center->where(array($operate_sn=>'operate_sn'))->find();
        $cloud_product_id = I('cloud_product_id');
        $product = M('cloud_product_list')->where(array('cloud_product_id'=>$cloud_product_id,'status'=>1))->find();
        $product_id = $product['product_id'];
        $spec_name = I('spec_name');
        $product_spec = M('cloud_product_spec')->where(array('product_id'=>$product_id,'spec_name'=>$spec_name))->find();
        $cost_price = $product_spec['market_price'];
        if($cost_price > $operate_info['money']){
            $this->output_error(-4,'该运营商预存资金为￥'.$operate_info['money'].',购买套餐成本为￥'.$cost_price.',预存资金不足');
        }
        $vip = $product_spec('up_level');
        $vip_time = I('vip_time');
        $xunxin_num = $store_info['member_name'];
        $order_id = $this->create_package_order($product_spec,$account_id,$xunxin_num,0,$product_id,0,$operate_sn,0,1,0);
        if (empty($order_id)) {
            $this->output_error(-5,'创建订单失败');
        } else {
            $pay_check = $this->pay_package_order($order_id);
            if ($pay_check['status'] == -1) {
                $this->output_error(-5,$pay_check['desc']);
            }else{
                $sync_url = M('system_config')->where(array('status'=>1))->getField('sync_url');
                $params3 = array();
                $params3['vip'] = $vip;
                $params3['vip_time'] = $vip_time;
                $url3 = "http://".$sync_url."/xxapi/index.php?act=operate_openaccount&op=updateApplyStore";
                $json3 =  $this->postCurl($url3,$params3);
                $rt3 = json_decode($json3,true);
                if($rt3['result'] == -1){
                    $this->error($rt3['error']);
                }else{
                    $order_sn = M('vip_orders')->where(array('id' => $order_id))->getField('orderSn');
                    M('data_record')->where(array('order_sn' => $order_sn))->save(array('open_result' => 'SUCCESS'));
                    $newdata = array();
                    $newdata['rechargetime'] = mktime();
                    $newdata['is_create'] = 1;
                    $newdata['store_id'] = $store_info['store_id'];
                    M('vip_orders')->where(array('id' => $order_id))->save($newdata);
                    $this->settlement_package_order($order_id);
                    $this->checkOperateUplevel($operate_info['id']);
                    $auth_code = M('system_config')->where(array('status' => 1))->getField('auth_code');
                    $sync_url = ADMIN_URL.'/index.php?m=api&c=Store&a=index&auth_code=' . $auth_code;
                    $x = 1;
                    do {
                        $json = file_get_contents($sync_url);
                        $result = json_decode($json, true);
                    } while (++$x <= 3 && $result['result'] != '0' && $result['result'] != '1');
                    $rs = [];
                    $this->output_data($rs);
                }
            }
        }
    }

    public function index3(){
        $account_id = I('account_id');
        $store_info = M('stores')->where(array('account_id'=>$account_id,'isdelete'=>0))->find();
        if(empty($store_info)){
            $this->output_error(-1,'该店铺不存在');
        }
        $is_try = $store_info['is_try'];
        if($is_try == 1){
            $openId = I('qcloud_openid');
            $market_type = I('cloud_market_type');
            $marketConfig = $this->getMarketConfig($market_type);
            if(empty($marketConfig) || empty($marketConfig['operate_sn'])){
                $this->output_error(-3,'未找到相应的配置');
            }
            $center = M('operate_center');
            $operate_sn = $marketConfig['operate_sn'];
            $operate_info = $center->where(array($operate_sn=>'operate_sn'))->find();
            $cloud_product_id = I('cloud_product_id');
            $product = M('cloud_product_list')->where(array('cloud_product_id'=>$cloud_product_id,'status'=>1))->find();
            $product_id = $product['product_id'];
            $spec_name = I('spec_name');
            $product_spec = M('cloud_product_spec')->where(array('product_id'=>$product_id,'spec_name'=>$spec_name))->find();
            $cost_price = $product_spec['market_price'];
            if($cost_price > $operate_info['money']){
                $this->output_error(-4,'该运营商预存资金为￥'.$operate_info['money'].',购买套餐成本为￥'.$cost_price.',预存资金不足');
            }
            $vip = $product_spec('up_level');
            $vip_time = I('vip_time');
            $xunxin_num = $store_info['member_name'];
            $order_id = $this->create_package_order($product_spec,$account_id,$xunxin_num,0,$product_id,0,$operate_sn,0,1,0);
            if (empty($order_id)) {
                $this->output_error(-5,'创建订单失败');
            } else {
                $pay_check = $this->pay_package_order($order_id);
                if ($pay_check['status'] == -1) {
                    $this->output_error(-5,$pay_check['desc']);
                }else{
                    $sync_url = M('system_config')->where(array('status'=>1))->getField('sync_url');
                    $params3 = array();
                    $params3['vip'] = $vip;
                    $params3['vip_time'] = $vip_time;
                    $params3['is_try'] = 0;
                    $url3 = "http://".$sync_url."/xxapi/index.php?act=operate_openaccount&op=updateApplyStore";
                    $json3 =  $this->postCurl($url3,$params3);
                    $rt3 = json_decode($json3,true);
                    if($rt3['result'] == -1){
                        $this->error($rt3['error']);
                    }else{
                        $order_sn = M('vip_orders')->where(array('id' => $order_id))->getField('orderSn');
                        M('data_record')->where(array('order_sn' => $order_sn))->save(array('open_result' => 'SUCCESS'));
                        $newdata = array();
                        $newdata['rechargetime'] = mktime();
                        $newdata['is_create'] = 1;
                        $newdata['store_id'] = $store_info['store_id'];
                        M('vip_orders')->where(array('id' => $order_id))->save($newdata);
                        $this->settlement_package_order($order_id);
                        $this->checkOperateUplevel($operate_info['id']);
                        $auth_code = M('system_config')->where(array('status' => 1))->getField('auth_code');
                        $sync_url = ADMIN_URL.'/index.php?m=api&c=Store&a=index&auth_code=' . $auth_code;
                        $x = 1;
                        do {
                            $json = file_get_contents($sync_url);
                            $result = json_decode($json, true);
                        } while (++$x <= 3 && $result['result'] != '0' && $result['result'] != '1');
                        $rs = [];
                        $this->output_data($rs);
                    }
                }
            }
        }else{
            $this->output_error(-2,'不是试用商铺');
        }
    }

    /**
     * 创建套餐订单
     * params  int $account_id 申请编号
     * params  string $member_name 用户账号
     * params  tinyint $is_try 是否试用 0非试用 1-试用
     * params  int $package_id 套餐编号
     * params  int $age_limit 使用年限
     * params  string $recommend_code 业务编号
     * params  string $tel 联系方式
     * params  tinyint $paytype 支付方式 1-线下支付 2-微信支付 3-余额支付 4-云支付
     * params  tinyint $type 下单方式 0会员下单 1-管理员添加订单
     * params  tinyint $store_id  店铺编号
     * return  int $order_id  订单编号
     */
    public function create_package_order($product_spec,$account_id,$member_name,$is_try,$product_id,$age_limit,$recommend_code,$tel,$paytype,$type){

        $shareholder_info = M('operate_shareholder')->where(array('shareholder_sn'=>$recommend_code))->find();
        if(!empty($shareholder_info)){
            $operate_id = $shareholder_info['operate_id'];
            $holder_id = $shareholder_info['id'];
        }else{
            $operate_id = M('operate_center')->where(array('operate_sn'=>$recommend_code))->getField('id');
        }
        $up_level = 2;
        $datas = array();
        $datas['member_name'] = $member_name;
        $datas['orderSn'] = 'v'.mktime().$account_id;
        $datas['cloud_product_id'] = $product_id;
        $datas['account_id'] = $account_id;

        /*获取用户套餐购买价格*/
        if($is_try == 1){
            $cost_price = 0;
            $market_price = 0;
            $actual_price = 0;
        }else{
            $market_price = $product_spec['market_price'];
            $actual_price = $market_price;
            $cost_price = $market_price;
            $up_level = $product_spec['up_level'];
        }
        $datas['cost_price'] = $cost_price;
        $datas['age_limit'] = $age_limit;
        $datas['sale_price'] = $market_price;

        /*获取用户套餐购买价格结束*/
        $datas['actual_price'] = $actual_price;
        if(!empty($shareholder_info)){
            $recommend_profit = ($actual_price-$cost_price)* $shareholder_info['recommend_rate']/100;
        }else{
            $recommend_profit = 0;
        }
        $datas['recommend_profit'] = $recommend_profit;
        $datas['operate_profit'] = $actual_price-$cost_price-$recommend_profit;
        $datas['tel'] = $tel;
        $datas['recommend_code'] = $recommend_code;
        $datas['holder_id'] = $holder_id;
        $datas['operate_id'] = $operate_id;
        $datas['up_level'] = $up_level;
        $datas['paytype'] = $paytype;
        $datas['type'] = $type;
        $datas['status'] = 0;
        $datas['applytime'] = mktime();
        $datas['is_create'] = 0;
        $datas['issettlement'] = 0;
        $order_id = M('vip_orders')->add($datas);
        $add_data = array();
        $add_data['ad_id'] = $recommend_code;
        $add_data['event'] = 'click';
        $add_data['member_name'] = $member_name;
        $add_data['member_tel'] = $tel;
        $add_data['order_sn'] = $datas['orderSn'];
        DataRecoed($add_data);
        return $order_id;
    }

    /**
     * 支付套餐订单
     * param int $order_id 订单id
     * param array $rt 结果 -1:失败 ; 1:成功
     * 支付套餐订单
     */
    public function pay_package_order($order_id = ''){
        $order = M('vip_orders');
        $order_info = $order->where(array('id'=>$order_id))->find();
        $rt = array();
        if(empty($order_info)){
            $rt['status'] = -1;
            $rt['desc'] = '该订单不存在';
        }else if($rt['status'] == 1){
            $rt['status'] = -1;
            $rt['desc'] = '该订单已经支付';
        }else{
            $w = array();
            $w['id'] = $order_info['operate_id'];
            $w['status'] = 1;
            $money = M('operate_center')->where($w)->getField('money');
            if($money < $order_info['cost_price']){
                $rt['status'] = -1;
                $rt['desc'] = '运营商预存金额不足';
            }else{
                $da = array();
                $da['value'] = 0 -$order_info['cost_price'];
                $da['final_value'] = $money - $order_info['cost_price'];
                $da['operate_id'] = $order_info['operate_id'];
                $da['type'] = 2;
                $da['order_sn'] = $order_info['ordersn'];
                $da['periods'] = date('Y-m-d');
                $da['addtime'] = mktime();
                $da['editor'] =  session('loginname');
                $da['status'] = 1;
                if(M('operate_trade_record')->add($da)){
                    $check = M('operate_center')->where($w)->setDec('money',$order_info['cost_price']);
                    if($check !== false){
                        $new = array();
                        $new['status'] = 1;
                        $order->where(array('id'=>$order_id))->save($new);
                        $rt['status'] = 1;
                    }else{
                        $rt['status'] = -1;
                        $rt['desc'] = '更改运营商预存金额失败';
                    }
                }else{
                    $rt['status'] = -1;
                    $rt['desc'] = '插入运营商交易记录失败';
                }
            }
        }
        return $rt;

    }

    /**
     * 结算套餐订单
     * params  int $order_id 申请编号
     */
    public function settlement_package_order($order_id){
        $order = M('vip_orders');
        $operate_shareholder_total_price = M('operate_shareholder_total_price');
        $operate_total_price = M('operate_total_price');
        $shareholder = M('operate_shareholder');
        $where = array();
        $where['id'] = $order_id;
        $order_info = $order->where($where)->find();
        $month = date("Y-m",mktime());
        $package_name = M('package_list')->where(array('packageid'=>$order_info['packageid']))->getField('name');
        $operate_name = M('operate_center')->where(array('id'=>$order_info['operate_id']))->getField('operate_name');
        $pay_name = '';
        if($order_info['paytype'] == 1){
            $pay_name = '线下支付';
        }else if($order_info['paytype'] == 2){
            $pay_name = '微信支付';
        }else if($order_info['paytype'] == 3){
            $pay_name = '余额支付';
        }else if ($order_info['paytype'] == 4){
            $pay_name = '腾讯云支付';
        }
        if($order_info['issettlement'] == 0){
            /*运营商成员推荐结算*/
            if(!empty($order_info['holder_id']) && $order_info['recommend_profit'] > 0){

                $shareholder_info = $shareholder->where(array('id'=>$order_info['holder_id'],'status'=>1))->find();
                $holderdata = array();
                $holderdata['operate_id'] = $shareholder_info['operate_id'];
                $holderdata['operate_name'] = $operate_name;
                $holderdata['shareholder_id'] = $shareholder_info['id'];
                $holderdata['shareholder_name'] = $shareholder_info['shareholder_name'];
                $holderdata['type'] = 1;
                $holderdata['link_orderid'] = $order_id;
                $holderdata['pay_name'] = $pay_name;
                $holderdata['value'] = $order_info['recommend_profit'];
                $holderdata['desc'] = $package_name.'推广收益';
                $holderdata['periods'] = date('Y-m-d',mktime());
                $holderdata['addtime'] = mktime();
                M('operate_shareholder_price_record')->add($holderdata);

                $w = array();
                $w['shareholder_id'] = $shareholder_info['id'];
                $w['month'] = $month;
                $check = $operate_shareholder_total_price->where($w)->find();
                if(!empty($check)){
                    $operate_shareholder_total_price->where($w)->setInc('value', $order_info['recommend_profit']);
                }else{
                    $totaldata1 = array();
                    $totaldata1['operate_id'] = $shareholder_info['operate_id'];
                    $totaldata1['operate_name'] = $operate_name;
                    $totaldata1['shareholder_id'] = $shareholder_info['id'];
                    $totaldata1['shareholder_name'] = $shareholder_info['shareholder_name'];
                    $totaldata1['value'] = $order_info['recommend_profit'];
                    $totaldata1['month'] = $month;
                    $totaldata1['month_time'] = strtotime($month);
                    $totaldata1['is_get'] = 0;
                    $operate_shareholder_total_price->add($totaldata1);
                }
            }
            /*运营商结算*/
            if(!empty($order_info['operate_id']) && $order_info['operate_profit'] > 0){
                $operatedata = array();
                $operatedata['operate_id'] = $order_info['operate_id'];
                $operatedata['link_orderid'] = $order_id;
                $operatedata['type'] = 1;
                $operatedata['pay_name'] = $pay_name;
                $operatedata['operate_name'] = $operate_name;
                $operatedata['value'] = $order_info['operate_profit'];
                $operatedata['desc'] =$package_name.'佣金收益';
                $operatedata['periods'] =date('Y-m-d',mktime());
                $operatedata['addtime'] = mktime();
                M('operate_price_record')->add($operatedata);
                $w2 = array();
                $w2['operate_id'] = $order_info['operate_id'];
                $w2['month'] = $month;
                $check2 = $operate_total_price->where($w2)->find();
                if(!empty($check2)){
                    $operate_total_price->where($w2)->setInc('value', $order_info['operate_profit']);
                }else{
                    $totaldata2 = array();
                    $totaldata2['operate_id'] = $order_info['operate_id'];
                    $totaldata2['operate_name'] = $operate_name;
                    $totaldata2['value'] = $order_info['operate_profit'];
                    $totaldata2['month'] = $month;
                    $totaldata2['month_time'] = strtotime($month);
                    $totaldata2['is_get'] = 0;
                    $operate_total_price->add($totaldata2);
                }

                $holder_list = M('operate_shareholder')->where(array('operate_id'=>$order_info['operate_id'],'status'=>1))->field('id')->select();
                foreach($holder_list as $list){
                    $holderinfo = array();
                    $holderinfo = $shareholder->where(array('id'=>$list['id']))->find();
                    $profit = $order_info['operate_profit']*$holderinfo['share_rate']/100;
                    if($profit > 0){
                        $data = array();
                        $data['operate_id'] = $holderinfo['operate_id'];
                        $data['operate_name'] = $operate_name;
                        $data['shareholder_id'] = $holderinfo['id'];
                        $data['shareholder_name'] = $holderinfo['shareholder_name'];
                        $data['type'] = 2;
                        $data['link_orderid'] = $order_id;
                        $data['pay_name'] = $pay_name;
                        $data['value'] = $profit;
                        $data['desc'] = $package_name.'股东分红';
                        $data['periods'] = date('Y-m-d',mktime());
                        $data['addtime'] = mktime();
                        M('operate_shareholder_price_record')->add($data);

                        $w = array();
                        $w['shareholder_id'] = $holderinfo['id'];
                        $w['month'] = $month;
                        $check = array();
                        $check = $operate_shareholder_total_price->where($w)->find();
                        if(!empty($check)){
                            $operate_shareholder_total_price->where($w)->setInc('value', $profit);
                        }else{
                            $totaldata1 = array();
                            $totaldata1['operate_id'] = $holderinfo['operate_id'];
                            $totaldata1['operate_name'] = $operate_name;
                            $totaldata1['shareholder_id'] = $holderinfo['id'];
                            $totaldata1['shareholder_name'] = $holderinfo['shareholder_name'];
                            $totaldata1['value'] = $profit;
                            $totaldata1['month'] = $month;
                            $totaldata1['month_time'] = strtotime($month);
                            $totaldata1['is_get'] = 0;
                            $operate_shareholder_total_price->add($totaldata1);
                        }
                    }
                }

            }

            $order->where($where)->save(array('issettlement'=>1));
        }
    }

    /**
     * 检验运营商能否升级
     */
    public function checkOperateUplevel($operate_id=''){
        $config = M('operate_config')->where(array('status'=>1))->find();
        $operate = M('operate_center');
        $order = M('vip_orders');
        $w = array();
        $w['id'] = $operate_id;
        $w['status'] = 1;
        $operate_info = $operate->where($w)->find();
        if(!empty($operate_info)){
            if($operate_info['level'] < 6){
                $w2 = array();
                $w2['operate_id'] = $operate_id;
                $w2['status'] = 1;
                $w2['is_create'] = 1;
                $total_price = $order->where($w2)->sum('actual_price');
                $total_price = empty($total_price) ? 0 : $total_price;
                $up_level = 0;
                if(($total_price >= $config['first_upprice']) && ($total_price < $config['secend_upprice'])){
                    $up_level = 2;
                }else if(($total_price >= $config['secend_upprice']) && ($total_price < $config['third_upprice'])){
                    $up_level = 3;
                }else if(($total_price >= $config['third_upprice']) && ($total_price < $config['fourth_upprice'])){
                    $up_level = 4;
                }else if(($total_price >= $config['fourth_upprice']) && ($total_price < $config['fifth_discount'])){
                    $up_level = 5;
                }else if($total_price >= $config['fifth_discount']){
                    $up_level = 6;
                }
                if($up_level > $operate_info['level']){
                    $operate->where($w)->save(array('level'=>$up_level));
                    M('shareholder_package_edit')->where(array('operate_id'=>$operate_id))->save(array('status'=>0));
                }

            }
        }
    }

    /*获取账号列表*/
    public function getXunXinNum($vip = 0){
        $num = NULL;
        $param = array();
        $param['vip'] = $vip;
        $param['channel_id'] = 0;
        $sync_url = M('system_config')->where(array('status'=>1))->getField('sync_url');
        $url = "http://".$sync_url."/xxapi/index.php?act=operate_openaccount&op=selectXunxinnum";
        $member_name_list = $this->postCurl($url,$param);
        $member_name_list = json_decode($member_name_list,true);
        $rand = rand(0,count($member_name_list)-1);
        $num = $member_name_list[$rand]['xunxin_num_name'];
        return $num;
    }

    public function getMarketConfig($marketType = 0,$condition = []){
        $config = NULL;
        $m = M('cloud_market_config');
        $where = [];
        $where['is_delete'] = 0;
        $where['status'] = 1;
        $where['cloud_market_type'] = $marketType;
        if(!empty($condition)){
            $where['_complex'] = $condition;
        }
        $config = $m->where($where)->find();
        return $config;
    }

    public function output_error($result = 0,$error = '')
    {
        $data = array();
        $data['result'] = $result;
        $data['code'] = 500;
        $data['error_msg'] = $error;
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die;
    }

    public function output_data($datas = array(), $msg = '')
    {
        $data = array();
        $data['result'] = 0;
        $data['code'] = 200;
        $data['msg'] = $msg;
        $data['data'] = $datas;
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die;
    }

    public function postCurl($url='',$data = array()) {

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

}