<?php
/**
 * Created by PhpStorm.
 * User: Ydr
 * Date: 2019/12/17
 * Time: 15:16
 */
namespace Common\Model;

use Think\Model;

class BaseModel extends Model
{
    protected $autoCheckFields = false; //虚拟模型关闭自动检测
    private $action;
    private $marketType;

    public function __construct($name = '', $tablePrefix = '', $connection = '')
    {
        parent::__construct($name, $tablePrefix, $connection);
        $this->setAction(C('MARKET_ACTION'));
        $this->setMarketType(C('MARKET_TYPE'));
    }

    public function setAction($action){
        $this->action = $action;
        return $this;
    }

    public function getAction(){
        return $this->action;
    }

    public function setMarketType($marketType){
        $this->marketType = $marketType;
        return $this;
    }

    public function getMarketType(){
        return $this->marketType;
    }


    /**
     * 简单的数据查询，仅能解析成 “=” where的条件
     * @param $table 数据表名
     * @param $param 数组参数，查询条件，非必要参数
     * @param $field 需要查询的参数
     * @return 返回多条数据
     */
    public function think_select_all($table, $param, $field = '*'){
        $where = array();
        $bind = array();
        foreach ($param as $k=>$v){
            $where[$k] = ':'.$k;
            $bind[':'.$k] = $v;
        }

        return $this->table($table)->field($field)->where($where)->bind($bind)->select();
    }

    /**
     * 原生sql数据查询
     * @param $sql sql语句
     * @param $param 绑定参数，可不传，仅支持传入数组
     * @return 返回一条数据
     */
    public function native_select_one($sql, $param){
        try {
            $parseSql = $this->bindParam($sql, $param);
            $this->isSelectSql($parseSql);

            $result = $this->query($parseSql);
            return $result[0];
        } catch (Exception $e) {
            throw_exception($e->getMessage());
        }
    }

    /**
     * @param $table 数据表名
     * @param $param 更新的数据
     * @param $condition 更新的条件
     * @return boolean 成功返回true 失败返回false
     */
    public function think_update($table, $param, $condition){
        try {
            $this->startTrans();

            $where = array();
            $bind = array();
            $update = array();

            foreach ($condition as $k=>$v){
                $where[$k] = ':w_'.$k;
                $bind[':w_'.$k] = $v;
            }

            foreach ($param as $k=>$v){
                $update[$k] = ':u_'.$k;
                $bind[':u_'.$k] = $v;
            }

            $this->table($table)->where($where)->bind($bind)->save($update);

            if(!$this->commit()){
                $this->rollback();
                return false;
            }else {
                return true;
            }
        } catch (Exception $e) {
            $this->rollback();
            throw_exception($e->getMessage());
        }
    }

    /**
     * 注意！注意！注意！使用本方法的数据表，不可再使用thinkphp的add方法，
     * 若使用的thinkphp的add方法或者在其他执行了insert语句，必须更新sequence中维护的数据表的最大id值
     * @param $table 数据表名
     * @param $param 要插入的数据
     * @param $is_auto_increment 数据表主键是否自增
     * @param $is_exist_keyid 数据表主键（或者用于表示数据是否重复的key）是否存在
     * @param string $is_return_id 是否返回当前的主键ID
     * @return boolean 成功返回true或主键ID 失败返回false
     */
    public function think_insert($table, $param, $is_auto_increment=true, $is_exist_keyid=true, $is_return_id=false){
        try {
            $this->startTrans();

            $insert = array();
            $bind = array();

            if(!$is_auto_increment && $is_exist_keyid){
                //如果主键不是自增，并且存在主键或者非重复key，调用本类中last_sequence_id方法来获取最大id+1，用以存入要插入的数据
                $key = isset($param['table_key']) ? $param['table_key'] : 'id'; //如果自增主键不是id在 $param里传入table_key的值即可
                $param[$key] = $this->last_sequence_id($table);
            }

            foreach ($param as $k => $v){
                $insert[$k] = ':i_'.$k;
                $bind[':i_'.$k] = $v;
            }

            $result = $this->table($table)->bind($bind)->add($insert);

            if($is_auto_increment){
                //如果是主键，执行sql函数使sequence表中维护的自增id保持最新
                $this->native_select_one("SELECT SETVAL('" . $table . "',".$result.")");
            }

            if(!$this->commit()){
                $this->rollback();
                return false;
            }else {
                return $is_return_id === true ? ($is_auto_increment ? $result : $param[$key]) : true;
            }
        } catch (Exception $e) {
            $this->rollback();
            throw_exception($e->getMessage());
        }
    }

    /**
     * 获取表最大ID+1
     * @param $table 表名
     */
    public function last_sequence_id($table){
        $sql = "SELECT NEXTVAL('" . $table . "') AS num";
        $result = $this->native_select_one($sql);

        if(!$result['num']) {
            throw_exception('获取主键失败：' . $table, '');
        }
        return $result['num'];
    }

    /**
     * 原生sql绑定参数
     */
    protected function bindParam($sql, $param){
        if(!empty($param) && !is_array($param)){
            return false;
        }

        foreach ($param as $k => $v){
            $bind[] = ":".$k;

            if(strpos($v, "'") === 0 && substr($v, -1) == "'"){
                $value[] = $v;
            }else{
                $value[] = "'".$v."'"; //将参数值强制转为带引号的字符串
            }
        }

        return str_replace($bind, $value, $sql);
    }

}