<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace Sunong\StockControl\Model;

use Sunong\StockControl\Core;

class OutstockContent extends Core{
    protected $table;

    public function __construct(){
        parent::__construct();
        $this->table=$this->_outstock_content;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param integer $outstock_id
     * @return mixed 获取入库单明细信息
     */
    public function get_detail_by_outstock_id($outstock_id){
        $data=$this->where('outstock_id',$outstock_id)->where('status',1)->get();
        return $data;
    }
}