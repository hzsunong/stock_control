<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/31
 * Time: 下午1:47
 */
namespace Sunong\StockControl\Test;

use Sunong\StockControl\Facade\OutstockFacade;

class OutstockTest{
    public function new_outstock(){
        $outstock_facade=new OutstockFacade();
        $products=[];
        $result=$outstock_facade->new_outstock('000001',1,1,2,1,$products,5);
    }
}