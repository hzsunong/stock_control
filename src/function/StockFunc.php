<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午11:41
 */
namespace SuNong\StockControl\Func;

use SuNong\StockControl\Model\Stock;

class StockFunc extends CommonFunc{

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param array $product_ids
     * @return array 获取库存信息列表
     */
    public function stock_list($hq_code,$orgz_id,$product_ids){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','库存列表信息查询开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $product_ids=is_array($product_ids) && !empty($product_ids) ? $product_ids : null;

        if(!$hq_code || !$orgz_id || !$product_ids){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','库存信息查询失败:参数缺失',$params);
            return $result;
        }

        $stock_model=new Stock();
        $stock_list=$stock_model->get_list_by_products($hq_code,$orgz_id,$product_ids);
        if($stock_list!=null) $stock_list=$stock_list->toArray();
        $result=['code'=>'0','msg'=>'库存信息查询成功','data'=>$stock_list];
        $this->log_record('info','null','库存信息查询成功 耗时:'.($this->get_micro_time()-$start_time),$params);
        return $result;
    }
}