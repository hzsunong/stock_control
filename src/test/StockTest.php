<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/31
 * Time: 上午10:22
 */
namespace Sunong\StockControl\Test;

use Sunong\StockControl\Facade\StockFacade;

class StockTest{
    /**
     * @author Javen <w@juyii.com>
     * 销售扣减库存
     */
    public function sales_deduct_stock(){
        $stock_facade=new StockFacade();

        $products=[
            [
                'product_id'=>113,
                'quantity'=>10,
                'package'=>1
            ]
        ];
        $result=$stock_facade->sales_deduct_stock('000001',1,5,2,$products);
        print_r($result);die;
    }

    public function stock_inventory(){
        $stock_facade=new StockFacade();
        $products=[
            [
                'product_id'=>116,
                'quantity'=>0,
                'package'=>2
            ]
        ];
        $result=$stock_facade->stock_inventory('000001',1,5,2,$products);
        print_r($result);die;
    }
}