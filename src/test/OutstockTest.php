<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/31
 * Time: 下午1:47
 */
namespace SuNong\StockControl\Test;

use SuNong\StockControl\Func\OutstockFunc;

class OutstockTest{
    public function new_outstock(){
        $outstock_func=new OutstockFunc();
        $products=[];
        $result=$outstock_func->new_outstock('000001',1,1,2,1,$products,5);
    }
}