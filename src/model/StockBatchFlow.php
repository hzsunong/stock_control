<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use SuNong\StockControl\Core;

class StockBatchFlow extends Core{
    protected $table;

    public function __construct(){
        parent::__construct();
        $this->table=$this->_stock_batch_flow;
    }
}