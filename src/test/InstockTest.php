<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/30
 * Time: 下午4:19
 */
namespace Sunong\StockControl\Test;

use Sunong\StockControl\Facade\InstockFacade;

class InstockTest{

    public function add_instock(){
        $instock_facade=new InstockFacade();
        $products=[];
        $result=$instock_facade->new_instock('000001',1,1,2,2,$products,1,'备注',5);
    }

    public function instock_list(){
        $instock_facade=new InstockFacade();
        $result=$instock_facade->instock_list('000001',1,20,0,null,null,[['code','like',"%00%"]]);
        print_r($result);die;
    }
}