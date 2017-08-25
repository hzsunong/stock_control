<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午11:41
 */
namespace SuNong\StockControl\Func;

use Illuminate\Support\Facades\DB;
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

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-25
     * @param string $hq_code
     * @param integer $orgz_id
     * @param array $products
     * @return array 初始化库存信息
     */
    public function init_stock($hq_code,$orgz_id,$products){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','库存信息初始化开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $products=is_array($products) && !empty($products) ? $products : null;

        if(!$hq_code || !$orgz_id || !$products){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','库存信息初始化失败:参数缺失',$params);
            return $result;
        }

        $stock_model=new Stock();
        $insert_product_list=[];
        DB::beginTransaction();
        try{
            foreach ($products as $product){
                $product_id=isset($product['product_id']) && is_numeric($product['product_id']) ? $product['product_id'] :null;
                $price=isset($product['price']) && is_numeric($product['price']) ? $product['price'] :null;
                $spec_num=isset($product['spec_num']) && is_numeric($product['spec_num']) ? $product['spec_num'] :null;
                $spec_unit=isset($product['spec_unit']) && trim($product['spec_unit'])!=='' ? $product['spec_unit'] :null;

                if(!$product_id || $price===null || $spec_num===null || !$spec_unit){
                    $result=['code'=>'10000','msg'=>'商品参数缺失'];
                    $this->log_record('error','null','库存信息初始化失败:参数缺失',$params);
                    return $result;
                }

                $stock_id=$stock_model->insertGetId(['hq_code'=>$hq_code,'orgz_id'=>$orgz_id,'product_id'=>$product_id,
                    'price'=>$price,'quantity'=>0,'spec_unit'=>$spec_unit,'spec_num'=>$spec_num]);
                if(is_numeric($stock_id)) $insert_product_list[]=$product_id;
            }
            $this->log_record('info','null','库存信息初始化成功 耗时:'.($this->get_micro_time()-$start_time),$params);
            return ['code'=>'0','msg'=>'库存信息初始化成功','data'=>$insert_product_list];
        }catch (\Exception $exception){
            $result=['code'=>'10000','msg'=>'库存信息初始化失败','data'=>$exception->getMessage()];
            $this->log_record('error','null','库存信息初始化失败'.' 耗时:'.($this->get_micro_time()-$start_time).' 原因:'.json_encode($exception->getMessage()),$params);
            return $result;
        }

    }
}