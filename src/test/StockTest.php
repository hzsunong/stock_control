<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/31
 * Time: 上午10:22
 */
namespace SuNong\StockControl\Test;

use SuNong\StockControl\Func\StockFunc;

class StockTest{
    /**
     * @author Javen <w@juyii.com>
     * 销售扣减库存
     */
    public function sales_deduct_stock(){
        $stock_func=new StockFunc();

        $products=[
            [
                'product_id'=>113,
                'quantity'=>10,
                'package'=>1
            ]
        ];
        $result=$stock_func->sales_deduct_stock('000001',1,5,2,$products);
        print_r($result);die;
    }

    public function stock_inventory(){
        $stock_func=new StockFunc();
        $products=[
            [
                'product_id'=>116,
                'quantity'=>0,
                'package'=>2
            ]
        ];
        $result=$stock_func->stock_inventory('000001',1,5,2,$products);
        print_r($result);die;
    }
}