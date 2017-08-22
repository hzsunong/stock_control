<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use SuNong\StockControl\Core;

class StockBatch extends Core{
    protected $table;

    public function __construct(){
        parent::__construct();
        $this->table=$this->_stock_batch;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $product_id 商品信息
     * @param integer $left_quantity 回填数量
     * @return integer 回填后剩余数量
     */
    public function backfill_negative_inventory($hq_code,$orgz_id,$product_id,$left_quantity){
        $sbc_model=new StockBatchContent();
        $data=$this->select('stock_batch_content.id as content_id','stock_batch_content.inventory',
            'stock_batch_content.product_id','stock_batch_content.price')
            ->join('stock_batch_content','stock_batch_content.stock_batch_id','=','stock_batch.id')
            ->where('stock_batch.hq_code',$hq_code)->where('stock_batch.orgz_id',$orgz_id)->where('stock_batch.status',1)
            ->where('stock_batch_content.product_id',$product_id)->where('stock_batch_content.inventory','<',0)
            ->where('stock_batch_content.status',1)->get();
        if(empty($data)) return $left_quantity;
        $data=$data->toArray();

        foreach ($data as $item){
            $diff=$left_quantity+$item['inventory'];
            if($diff<=0){
                $sbc_model->where('id',$item['content_id'])->increment('inventory',$left_quantity);
                $left_quantity=0;
                break;
            }else{
                $sbc_model->where('id',$item['content_id'])->increment('inventory',abs($item['inventory']));
                $left_quantity=$diff;
            }
        }
        return $left_quantity;
    }
}