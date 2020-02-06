<?php

namespace Api\Controller;

use Common\Common\SendSMS;

class OperateOpenaccountController extends MobileHomeController
{
    /**
     * 迅信号返回数据接口
     * param int $vip vip等级
     * param int $channel_id 渠道编号
     * return 返回迅信号列表
     */
    public function selectXunXinNum()
    {

        $vip = $_REQUEST['vip'];
        $channel_id = $_REQUEST['channel_id'];
        $xunxin_data = Model('mb_xunxinnum')->where(array('xunxin_num_grade' => $vip, 'is_select' => 0, 'channel_id' => $channel_id))->limit(20)->select();
        $return_data = array();
        if (empty($xunxin_data)) {
            $return_data = $xunxin_data;
        } else {
            for ($i = 0; $i < count($xunxin_data); $i++) {
                if ($xunxin_data[$i]['countdown'] == 0 || $xunxin_data[$i]['countdown'] < time()) {
                    $member_data = Model('member')->where(array('member_name' => $xunxin_data[$i]['xunxin_num_name']))->find();
                    if (empty($member_data)) {
                        $return_data[] = $xunxin_data[$i];
                    }

                }
            }
        }
        echo json_encode($return_data);
    }


    /**
     * 全球采购商家资料提交接口
     * param string $shopName 商家名称  必填
     * param int $store_parenttype_id 大行业  必填
     * param int $store_childtype_id 子行业  必填
     * param int $package_id 套餐编号  必填
     * param int $vip 商家等级  必填
     * param string $xunxin_num 迅信号  必填
     * param string $password 密码  必填
     * param int $is_try 是否试用  必填
     * param int $try_time 试用时间  非必填
     * param int $age_limit 套餐年限 必填
     * param string $account_membertel 联系人电话号码  必填
     * param string $operate_num 业务编号  必填
     * param string $operate_id 运营商编号  必填
     * return 返回开户号
     */

    public function applyStore()
    {
        $shopName = $_REQUEST['shopName'];
        $store_parenttype_id = $_REQUEST['store_parenttype_id'];
        $store_childtype_id = $_REQUEST['store_childtype_id'];
        $package_id = $_REQUEST['package_id'];
        $vip = $_REQUEST['vip'];
        $xunxin_num = $_REQUEST['xunxin_num'];
        $password = $_REQUEST['password'];
        $is_try = $_REQUEST['is_try'];
        $try_time = $_REQUEST['try_time'];
        $age_limit = $_REQUEST['age_limit'];
        $account_membertel = $_REQUEST['account_membertel'];
        $operate_num = $_REQUEST['operate_num'];
        $operate_id = $_REQUEST['operate_id'];
//        $is_naming = $_REQUEST['is_naming'];
//        $open_Id = $_REQUEST['qcloud_openid'];


        if (empty($shopName)) {
            output_error(-1, "商家名称不能为空", "商家名称不能为空");
        }

        if (empty($store_parenttype_id)) {
            output_error(-1, "请选择父行业", "请选择父行业");
        }

        if (empty($store_childtype_id)) {
            output_error(-1, "请选择子行业", "请选择子行业");
        }

        if (empty($vip)) {
            output_error(-1, "请选择vip等级", "请选择vip等级");
        }

        if (empty($xunxin_num)) {
            output_error(-1, "请选择迅信号", "请选择迅信号");
        }

        if (empty($password)) {
            output_error(-1, "密码不能为空", "密码不能为空");
        }


        if (empty($is_try)) {
            $is_try = 0;
        }

        $exits_storeinfo = Model('store')->where(array('member_name' => $xunxin_num))->find();
        if (!empty($exits_storeinfo)) {
            output_error(-1, "该号码已被注册", "该号码已被注册");
        }

        $m = Model("mb_account");
        $data = array(
            'account_storename' => $shopName,
            'store_parenttype_id' => $store_parenttype_id,
            'store_childtype_id' => $store_childtype_id,
            'package_id' => $package_id,
            'store_grade' => $vip,
            'xunxin_num' => $xunxin_num,
            'password' => $password,
            'is_try' => $is_try,
            'try_time' => $try_time,
            'age_limit' => $age_limit,
            'account_membertel' => $account_membertel,
            'operate_num' => $operate_num,
            'operate_id' => $operate_id,
            'create_time' => time(),
            'create_day' => date('Y-m-d H:i:s', time()),
            'channel_id' => 0,
//            'qcloud_openid' => $open_Id,
//            'is_naming' => $is_naming,
        );
        $member_data = Model('member')->where(array('member_name' => $xunxin_num))->find();
        if (empty($member_data)) {
            $account_id = $m->insert($data);
        } else {
            output_error(-1, "商家号码已使用,请重新选择号码", "商家号码已使用,请重新选择号码");
        }
        output_data($account_id);

    }

    /**
     * 商家开户接口
     * param int $account_id 商家资料编号  必填
     * param int $opentype 开户类型  必填
     * param string $vip_time vip时长
     * return 返回ture or false
     */
    public function openStore()
    {
        $account_id = $_REQUEST['account_id'];
        $opentype = $_REQUEST['opentype'];
        $vip_time = $_REQUEST['$vip_time'];
        $account_data = Model('mb_account')->where(array('account_id' => $account_id))->find();
        if (empty($account_data)) {
            output_error(-1, "商家资料信息不存在", "商家资料信息不存在");
        }

        $array = array();
        $array['member_name'] = $account_data['xunxin_num'];
        $array['member_passwd'] = md5(trim($account_data['password']));
        $member = Model('member');
        $is_exist = $member->where(array(
            'member_name' => $account_data['xunxin_num']
        ))->select();
        $hahah = false;
        $allmember = Model('member')->field('member_name')->select();
        for ($i = 0; $i < count($allmember); $i++) {
            if (strtolower($account_data['xunxin_num']) == strtolower($allmember[$i]['member_name'])) {
                $hahah = true;
                break;
            }
        }
        if ($is_exist || $hahah) {
            output_error(-1, "注册失败！用户名已被占用！", "注册失败！用户名已被占用！");
        } else {
            $mver = $member->max('version');
            $mver++;
            $array['version'] = $mver;
            $array['member_tel'] = $account_data['account_membertel'];
            $array['member_email'] = $account_data['account_memberemail'];

            $member_id = $member->insert($array);
            if (empty($member_id)) {
                output_error(-2, "注册用户失败!", "插入数据库失败！");
            } else {

                $model_store = Model('store');
                $version_max = $model_store->max('version');
                $version_max = $version_max + 1;

                $store_array = array();
                $store_array['version'] = $version_max;
                $store_array['store_name'] = $account_data['account_storename'];
                $store_array['member_name'] = $account_data['xunxin_num'];
                $store_array['lianxi_member_name'] = $account_data['account_membername'];
                $store_array['lianxi_member_tel'] = $account_data['account_membertel'];
                $store_array['store_address'] = $account_data['bank_provincenae'] . $account_data['bank_cityname'] . $account_data['bank_areaname'] . $account_data['account_storeaddress'];
                $store_array['store_grade'] = $account_data['store_grade'];
                $store_array['channel_type'] = 0;
                $store_array['channel_id'] = 0;
                $store_array['member_id'] = $member_id;
                $store_array['main_store'] = 0;
                $store_array['store_parenttype_id'] = $account_data['store_parenttype_id'];
                $store_array['store_childtype_id'] = $account_data['store_childtype_id'];
                $store_array['is_try'] = $account_data['is_try'];
                $store_array['operate_id'] = $account_data['operate_id'];
                $store_array['package_id'] = $account_data['package_id'];
                $store_array['operate_num'] = $account_data['operate_num'];
                $store_array['opentype'] = $opentype;  //4 腾讯云开户
                $store_array['account_time'] = TIMESTAMP;
                $store_array['platform_type'] = $this->platform_type;
                $store_array['store_platform'] = 1;
//                $store_array['is_naming'] = $account_data['is_naming'];
                if ($account_data['is_try'] == 1) {
                    $store_array['vip_endtime'] = TIMESTAMP + $account_data['try_time'];
                } else {
                    $store_array['vip_endtime'] = TIMESTAMP + $vip_time;
                }

                $store_array['recharge_time'] = TIMESTAMP;


                $second_store = $model_store->where(array('member_name' => $account_data['xunxin_num']))->find();
                if (!empty($second_store)) {
                    output_error(-2, "商家已存在", "商家已存在");
                }
                $store_id = $model_store->insert($store_array);
                if (empty($store_id)) {
                    output_error(-3, "注册商铺失败!", "插入数据库失败！");
                } else {
                    Model('mb_store_info')->insert(array('store_id' => $store_id));
                    $model_seller = Model('seller');
                    $seller_array = array();
                    $seller_array['member_id'] = $member_id;
                    $seller_array['store_id'] = $store_id;
                    $seller_array['is_admin'] = 1;
                    $seller_array['isserver'] = 1;
                    $seller_array['allow_order'] = 1;
                    $seller_array['allow_store'] = 1;
                    $seller_array['is_consult'] = 1;
                    $seller_array['seller_role'] = '管理员';
                    $seller_array['seller_name'] = $account_data['xunxin_num'];

                    $seller_id = $model_seller->insert($seller_array);
                    if (empty($seller_id)) {
                        output_error(-4, "注册管理员失败!", "插入数据库失败！");
                    } else {

                        $model_storechannel = Model('mb_storechannel');
                        $schannel_array = array();
                        $schannel_array['channel_id'] = 0;
                        $schannel_array['store_id'] = $store_id;
                        $storechannel_id = $model_storechannel->insert($schannel_array);
                        if (empty($storechannel_id)) {
                            output_error(-9, "商铺添加到渠道失败!", "插入数据库失败！");
                        } else {
                            $mb_xunxinnum = Model('mb_xunxinnum');
                            $marray = array();
                            $marray['is_select'] = 1;
                            $marray['store_id'] = $store_id;
                            $marray['select_time'] = TIMESTAMP;
                            $tag2 = $mb_xunxinnum->where(array('xunxin_num_name' => $account_data['xunxin_num']))->update($marray);
                            if (empty($tag2)) {
                                output_error(-10, "更新商家号码失败", "插入数据库失败！");
                            }
                            if (!empty($account_id)) {
                                $model_mb_account = Model('mb_account');
                                $marray = array();
                                $marray['is_through'] = 1;
                                $marray['end_time'] = TIMESTAMP;
                                $marray['ispay'] = 1;
                                $marray['store_id'] = $store_id;

                                $tag = $model_mb_account->where(array('account_id' => $account_id))->update($marray);

                                if ($tag) {
                                    output_data($store_id);
                                } else {
                                    output_error(-10, "更新申请表失败!", "插入数据库失败！");
                                }
                            }
                        }
                    }
                }
            }
        }


    }


    /**
 * 将试用转为正式
 * param string $account_id 负责人姓名  必填
 * param int $vip vip 等级 必填
 * param string $vip_time vip到期时间 必填
 * param string $is_try 是否试用  必填
 * return array
 */

    public function modifyTryAccount()
    {
        $account_id = $_REQUEST['account_id'];
        $vip_time = $_REQUEST['vip_time'];
        $is_try = $_REQUEST['is_try'];
        $vip = $_REQUEST['vip'];
        $exits_storeinfo = Model('mb_account')->where(array('account_id' => $account_id))->find();
        if (empty($exits_storeinfo)) {
            output_error(-1, "暂未找到该资料信息", "暂未找到该资料信息");
        }
        $m = Model("mb_account");
        $data = array(
            'is_try' => $is_try,
            'store_grade' => $vip,
        );

        $tag = $m->where(array('account_id' => $account_id))->update($data);
        if ($tag !== false) {
            $version_max = Model('store')->max('version');
            $version_max = $version_max + 1;
            $info = array();
            $info['version'] = $version_max;
            $info['vip'] = $vip;
            $info['vip_endtime'] = $vip_time;
            $info['is_try'] = $is_try;
            Model('store')->where(array('member_name' => $exits_storeinfo['xunxin_num']))->update($info);
            output_data($tag);
        } else {
            output_error(-1, "更改资料失败", "更改资料失败");
        }
    }

    /**
     * 续费
     * param string $account_id 负责人姓名  必填
     * param int $vip vip 等级 必填
     * param string $vip_time vip到期时间 必填
     * param string $is_try 是否试用  必填
     * return array
     */

    public function renewAccount()
    {
        $account_id = $_REQUEST['account_id'];
        $vip_time = $_REQUEST['vip_time'];
        $vip = $_REQUEST['vip'];
        $exits_storeinfo = Model('mb_account')->where(array('account_id' => $account_id))->find();
        if (empty($exits_storeinfo)) {
            output_error(-1, "暂未找到该资料信息", "暂未找到该资料信息");
        }
        $m = Model("mb_account");
        $data = array(
            'store_grade' => $vip,
        );

        $tag = $m->where(array('account_id' => $account_id))->update($data);
        if ($tag !== false) {
            $version_max = Model('store')->max('version');
            $version_max = $version_max + 1;
            $info = array();
            $info['version'] = $version_max;
            $info['vip'] = $vip;
            $info['vip_endtime'] = $vip_time;
            Model('store')->where(array('member_name' => $exits_storeinfo['xunxin_num']))->update($info);
            output_data($tag);
        } else {
            output_error(-1, "更改资料失败", "更改资料失败");
        }
    }
}