<?php


namespace Api\Controller;


class TestController extends \Common\Controller\BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function test(){
        echo AK;
    }
}