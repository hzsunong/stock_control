<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午11:41
 */
namespace Sunong\StockControl\Facade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sunong\StockControl\Model\Stock;
use Sunong\StockControl\Model\StockBatch;

class StockFacade extends CommonFacade{

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
        Log::info('stock_control:库存信息初始化开始 参数:'.json_encode($params));
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $products=is_array($products) && !empty($products) ? $products : null;

        if(!$hq_code || !$orgz_id || !$products){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            Log::error('stock_control:库存信息初始化失败:参数缺失:'.json_encode($params));
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
                    Log::error('stock_control:库存信息初始化失败:商品参数缺失:'.json_encode($params));
                    return $result;
                }

                $stock_id=$stock_model->insertGetId(['hq_code'=>$hq_code,'orgz_id'=>$orgz_id,'product_id'=>$product_id,
                    'price'=>$price,'quantity'=>0,'spec_unit'=>$spec_unit,'spec_num'=>$spec_num]);
                if(is_numeric($stock_id)) $insert_product_list[]=$product_id;
            }
            Log::info('stock_control:库存信息初始化成功 耗时:'.($this->get_micro_time()-$start_time).' 参数:'.json_encode($params));
            DB::commit();
            return ['code'=>'0','msg'=>'库存信息初始化成功','data'=>$insert_product_list];
        }catch (\Exception $exception){
            $result=['code'=>'10000','msg'=>'库存信息初始化失败','data'=>$exception->getMessage()];
            Log::error('stock_control:库存信息初始化失败:原因:'.json_encode($exception->getMessage()).' 耗时:'.($this->get_micro_time()-$start_time));
            return $result;
        }

    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-25
     * @param string $hq_code
     * @param integer $orgz_id 组织id
     * @param integer $related_id 相关单据id
     * @param integer $genre 单据类型
     * @param array $products 商品信息
     * @return array|mixed 销售扣减库存
     */
    public function sales_deduct_stock($hq_code,$orgz_id,$related_id,$genre,$products){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','销售扣减库存开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $related_id=is_numeric($related_id)?$related_id:null;
        $genre=is_numeric($genre)?$genre:null;
        $products=is_array($products) && !empty($products) ? $products : null;

        if(!$hq_code || !$orgz_id || !$products || !$related_id || !$genre){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','销售扣减库存失败:参数缺失',$params);
            return $result;
        }

        $stock_batch_model=new StockBatch();
        DB::beginTransaction();
        try{
            $result=$stock_batch_model->deduct_stock_batch($hq_code,$orgz_id,$related_id,$genre,$products,3);
            if(!$result){
                DB::rollBack();
                $result=['code'=>'10000','msg'=>'销售扣减库存失败:库存变动失败或库存信息不存在'];
                $this->log_record('error','null','销售扣减库存失败:库存变动失败或库存信息不存在',$params);
                return $result;
            }
            DB::commit();
            $result=['code'=>'0','msg'=>'销售扣减库存成功','amount'=>$result['amount'],'data'=>$result['detail']];
            $this->log_record('error','null','销售扣减库存成功 耗时:'.($this->get_micro_time()-$start_time),$params);
            return $result;
        }catch (\Exception $e){
            DB::rollBack();
            $result=['code'=>'10000','msg'=>'销售扣减库存失败','data'=>$e->getMessage()];
            $this->log_record('error','null','销售扣减库存失败:原因:'.json_encode($e->getMessage()),$params);
            return $result;
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-30
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $inventory_id 相关盘点id
     * @param integer $operator 操作员id
     * @param array $products 商品列表
     * $products=[ ['product_id=>1,'quantity'=>5.20,'package'=>2.33],...,...,[...] ];
     * @return array 盘点相关商品
     */
    public function stock_inventory($hq_code,$orgz_id,$inventory_id,$operator,$products){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','盘点库存开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $inventory_id=is_numeric($inventory_id)?$inventory_id:null;
        $operator=is_numeric($operator)?$operator:null;
        $products=is_array($products) && !empty($products) ? $products : null;

        if(!$hq_code || !$orgz_id || !$inventory_id || !$operator || !$products){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','盘点库存失败:参数缺失',$params);
            return $result;
        }

        $stock_model=new Stock();
        $instock_facade=new InstockFacade();
        $outstock_facade=new OutstockFacade();

        //对比库存数据,筛选盘亏盘盈商品
        $instock_list=[];
        $outstock_list=[];
        $product_ids=collect($products)->pluck('product_id')->toArray();
        $product_info=$stock_model->get_inventory_by_products($hq_code,$orgz_id,$product_ids);
        if($product_info==null){
            $result=['code'=>'10000','msg'=>'盘点库存失败:所选择商品没有库存信息'];
            $this->log_record('error','null','盘点库存失败:所选择商品没有库存信息',$params);
            return $result;
        }
        $product_map=$product_info->keyBy('product_id')->toArray();
        foreach ($products as $product){
            $product_diff=['product_id'=>$product['product_id'],'after_quantity'=>$product['quantity'],
                'before_quantity'=>$product_map[$product['product_id']]['quantity'],
                'package'=>$product['package']];
            $product_diff['change_quantity']=$product_diff['after_quantity']-$product_diff['before_quantity'];

            if($product_diff['change_quantity']>0){
                $instock_list[]=$product_diff;
            }elseif($product_diff['change_quantity']<0){
                $outstock_list[]=$product_diff;
            }
        }

        //构建入库单或出库单
        $instock_id=null;
        $outstock_id=null;
        $instock_amount=null;
        $instock_detail=null;
        $outstock_amount=null;
        $outstock_detail=null;
        $content=[];

        DB::beginTransaction();
        try{
            //处理入库单
            if(!empty($instock_list)){

                //构建入库单相关商品数据
                $instock_collect=collect($instock_list)->keyBy('product_id')->toArray();
                $product_info=[];
                foreach ($instock_collect as $product_id=>$info){
                    $product_info[]=['product_id'=>$product_id,'quantity'=>$info['change_quantity'],'package'=>$info['package'],
                        'price'=>$product_map[$product_id]['price'],'spec_unit'=>$product_map[$product_id]['spec_unit']];
                }

                $instock_response=$instock_facade->new_instock($hq_code,$orgz_id,$operator,18,$inventory_id,$product_info,null,
                    '盘点生成入库单',$operator,date('Y-m-d H:i:s'));
                if($instock_response['code']!='0'){
                    DB::rollBack();
                    $result=['code'=>'10000','msg'=>'盘点生成入库单失败','data'=>$instock_response];
                    $this->log_record('error','null','盘点生成入库单失败 原因:'.json_encode($instock_response),$params);
                    return $result;
                }
                $instock_id=$instock_response['bill_id'];
                $instock_amount=$instock_response['amount'];
                $instock_detail=$instock_response['detail'];
                foreach ($instock_detail as $item){
                    $product_id=$item['product_id'];
                    $content[]=['product_id'=>$product_id,'before_quantity'=>$instock_collect[$product_id]['before_quantity'],
                        'change_quantity'=>$instock_collect[$product_id]['change_quantity'],
                        'after_quantity'=>$instock_collect[$product_id]['after_quantity'],
                        'amount'=>$item['total_amount'],'batch_info'=>[$item]];
                }
            }
            $temp_content=[];
            if(!empty($outstock_list)){
                $outstock_collect=collect($outstock_list)->keyBy('product_id')->toArray();
                $product_info=[];
                foreach ($outstock_collect as $product_id=>$info){
                    $product_info[]=['product_id'=>$product_id,'quantity'=>-$info['change_quantity'],'package'=>$info['package'],
                        'price'=>$product_map[$product_id]['price'],'spec_unit'=>$product_map[$product_id]['spec_unit']];
                }

                $outstock_response=$outstock_facade->new_outstock($hq_code,$orgz_id,$operator,19,$inventory_id,$product_info,
                    $operator,null,null,'盘点生成出库单');

                if($outstock_response['code']!='0'){
                    DB::rollBack();
                    $result=['code'=>'10000','msg'=>'盘点生成出库单失败','data'=>$outstock_response];
                    $this->log_record('error','null','盘点生成出库单失败 原因:'.json_encode($outstock_response),$params);
                    return $result;
                }
                $outstock_id=$outstock_response['bill_id'];
                $outstock_amount=$outstock_response['amount'];
                $outstock_detail=$outstock_response['detail'];
                foreach ($outstock_detail as $item){
                    $product_id=$item['product_id'];
                    if(isset($temp_content[$product_id])){
                        $temp_content[$product_id]['batch_info'][]=$item;
                    }else{
                        $temp_content[$product_id]=['product_id'=>$product_id,'before_quantity'=>$outstock_collect[$product_id]['before_quantity'],
                            'change_quantity'=>$outstock_collect[$product_id]['change_quantity'],
                            'after_quantity'=>$outstock_collect[$product_id]['after_quantity'],
                            'amount'=>$outstock_amount,'batch_info'=>[$item]];
                    }
                }
            }
            $content=array_merge($content,$temp_content);

            $this->log_record('info','null','盘点库存成功 耗时:'.($this->get_micro_time()-$start_time),$params);
            DB::commit();
            return ['code'=>'0','msg'=>'盘点库存成功','instock_id'=>$instock_id,'outstock_id'=>$outstock_id,'data'=>$content];
        }catch (\Exception $e){
            DB::rollBack();
            $this->log_record('info','null','盘点库存失败 原因:'.json_encode($e->getMessage()),$params);
            return ['code'=>'10000','msg'=>'盘点库存失败','data'=>$e->getMessage()];
        }
    }
}