<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/30
 * Time: 下午4:19
 */
namespace SuNong\StockControl\Test;

use SuNong\StockControl\Func\InstockFunc;

class InstockTest{

    public function add_instock(){
        $instock_func=new InstockFunc();
        $products=[];
        $result=$instock_func->new_instock('000001',1,1,2,2,$products,1,'备注',5);
    }

    public function instock_list(){
        $instock_func=new InstockFunc();
        $result=$instock_func->instock_list('000001',1,20,0,null,null,[['code','like',"%00%"]]);
        print_r($result);die;
    }
}