<?php


namespace Common\Model;


class RequestRecordModel extends BaseModel
{
    protected $tableName = 'request_record';

    public function addRecord($cloudOrderId = 0,$shopOrderId = 0,$shopStatus = 0,$errorMsg = ''){
        $record = [];
        $record['cloud_market_type'] = $this->getMarketType();
        $record['cloud_request_action'] = $this->getAction();
        $record['cloud_order_id'] = $cloudOrderId;
        $record['shop_order_id'] = $shopOrderId;
        $record['shop_status'] = $shopStatus;
        $record['error_msg'] = $errorMsg;
        $record['create_time'] = TIMESTAMP;
        return $this->add($record);
    }
}