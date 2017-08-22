<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use SuNong\StockControl\Core;

class Stock extends Core{
    protected $table = 'stock';

    public function __construct(){
        parent::__construct();
        $this->table=$this->_stock;
    }

    // 判断组织商品ID是否存在
    public function product_isexist_by_orgz_product($hq_code,$orgz_id,$product_id)
    {
        $data = $this->where('hq_code', $hq_code)->where('orgz_id', $orgz_id)
            ->where('product_id', $product_id)->first();
        if (!$data) return false;
        return $data;
    }
}