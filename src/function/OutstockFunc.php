<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午3:42
 */
namespace SuNong\StockControl\Func;

use Illuminate\Support\Facades\DB;
use SuNong\StockControl\Model\Instock;
use SuNong\StockControl\Model\InstockContent;
use SuNong\StockControl\Model\Outstock;
use SuNong\StockControl\Model\OutstockContent;
use SuNong\StockControl\Model\StockBatch;

class OutstockFunc extends CommonFunc{
    //出库单相关方法

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-24
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $creator_id 创建者id
     * @param integer $genre 单据类型
     * @param integer $related_id
     * @param array $products
     * @param null $target_orgz_id
     * @param null $delivery_date
     * @param null $remark
     * @return array
     */
    public function new_outstock($hq_code,$orgz_id,$creator_id,$genre,$related_id,$products,$auditor_id=null,
                                 $target_orgz_id=null,$delivery_date=null,$remark=null){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info',$creator_id,'出库单新增开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $creator_id=is_numeric($creator_id)?$creator_id:null;
        $genre=is_numeric($genre)?$genre:null;
        $related_id=is_numeric($related_id)?$related_id:null;
        $products=is_array($products) && !empty($products)?$products:null;
        $target_orgz_id=is_numeric($target_orgz_id)?$target_orgz_id:null;
        $delivery_date=trim($delivery_date)!=''?$delivery_date:null;
        $remark=trim($remark)!=''?$remark:null;

        if(!$hq_code || !$orgz_id || !$creator_id || !$genre || !$related_id || !$products){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$creator_id,'出库单新增失败:参数缺失',$params);
            return $result;
        }

        $auditor_id=is_numeric($auditor_id)?$auditor_id:null;
        if($auditor_id){
            $is_confirm=true;
        }else{
            $is_confirm=false;
        }

        $now_time=date('Y-m-d H:i:s');
        $response=[];
        DB::beginTransaction();
        try{
            //building instock data
            $outstock_model=new Outstock();
            $oc_model=new OutstockContent();
            $outstock_code=$outstock_model->create_code($hq_code);
            $outstock_data=['code'=>$outstock_code,'hq_code'=>$hq_code,'orgz_id'=>$orgz_id, 'genre'=>$genre,
                'creator_id'=>$creator_id,'target_orgz_id'=>$target_orgz_id,'related_id'=>$related_id,
                'delivery_date'=>$delivery_date,'remark'=>$remark,'created_at'=>$now_time];

            $outstock_id=$outstock_model->insertGetId($outstock_data);
            $response['outstock_id']=$outstock_id;
            $amount=0;
            foreach ($products as $product){
                $oc_data=['outstock_id'=>$outstock_id];
                $oc_data['product_id']=isset($product['product_id']) && is_numeric($product['product_id'])?$product['product_id']:null;
                $oc_data['spec_unit']=isset($product['spec_unit']) && trim($product['spec_unit'])!=''?$product['spec_unit']:null;
                $oc_data['price']=isset($product['price']) && is_numeric($product['price'])?$product['price']:null;
                $oc_data['quantity']=isset($product['quantity']) && is_numeric($product['quantity'])?$product['quantity']:null;
                $oc_data['package']=isset($product['package']) && is_numeric($product['package'])?$product['package']:null;
                $oc_data['remark']=isset($product['remark']) && trim($product['remark'])!=''?$product['remark']:null;

                if(!$oc_data['product_id'] || !$oc_data['spec_unit'] || !is_numeric($oc_data['price']) || !$oc_data['quantity'] || !$oc_data['package']){
                    DB::rollBack();
                    $this->log_record('error',$creator_id,'商品详情参数缺失',$params);
                    return ['code'=>'10000','msg'=>'商品详情参数缺失'];
                }
                $oc_data['spec_num']=isset($product['spec_num']) && is_numeric($product['spec_num'])
                    ? $product['spec_num'] : number_format($oc_data['quantity']/$oc_data['package'],3,'.','');
                $oc_data['amount']=isset($product['amount']) && is_numeric($product['amount'])
                    ? $product['amount'] : number_format($oc_data['price']*$oc_data['quantity'],0,'.','');

                $content_id=$oc_model->insertGetId($oc_data);
                $response['contents'][]=['product_id'=>$oc_data['product_id'],'outstock_content_id'=>$content_id];
                $amount+=$oc_data['amount'];
            }
            $outstock_model->where('id',$outstock_id)->update(['total_amount'=>$amount]);
            DB::commit();
            //立即审核
            $this->log_record('info',$creator_id,'出库单新增成功'.' 耗时:'.($this->get_micro_time()-$start_time).' id:'.$outstock_id,$params);

            if($is_confirm){
                $confirm_result=$this->confirm_outstock($hq_code,$orgz_id,$outstock_id,$auditor_id);
            }
            $result=['code'=>'0','msg'=>'出库单新增成功','bill_id'=>$outstock_id];
            if(isset($confirm_result) && $confirm_result['code']=='0'){
                $result['amount']=$confirm_result['amount'];
                $result['detail']=$confirm_result['data'];
            }
            return $result;
        }catch (\Exception $exception){
            DB::rollBack();
            $this->log_record('error',$creator_id,'出库单新增失败,原因:'.$exception->getMessage(),$params);
            return ['code'=>'10000','msg'=>'出库单新增失败','data'=>$exception->getMessage()];
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $outstock_id 出库单id
     * @param integer $auditor_id 审核人id
     * @return array 审核出库单
     */
    public function confirm_outstock($hq_code,$orgz_id,$outstock_id,$auditor_id){
        $start_time=$this->get_micro_time();
        $now_time=date('Y-m-d H:i:s');
        $params=func_get_args();
        $this->log_record('info',$auditor_id,'出库单审核开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $outstock_id=is_numeric($outstock_id)?$outstock_id:null;
        $auditor_id=is_numeric($auditor_id)?$auditor_id:null;

        if(!$hq_code || !$orgz_id || !$outstock_id || !$auditor_id){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$auditor_id,'出库单审核失败:参数缺失',$params);
            return $result;
        }

        $outstock_model=new Outstock();
        $oc_model=new OutstockContent();
        $stock_batch_model=new StockBatch();
        $products=$outstock_model->get_unconfirmed_list_by_outstock_id($hq_code,$orgz_id,$outstock_id);
        if($products==null){
            $result=['code'=>'10000','msg'=>'出库单审核失败:状态已变更'];
            $this->log_record('error',$auditor_id,'出库单审核失败:状态已变更',$params);
            return $result;
        }
        $genre=$outstock_model->get_genre_by_outstock_id($outstock_id);
        if($genre!=null){
            $genre=$genre->genre;
        }else{
            $result=['code'=>'10000','msg'=>'出库单审核失败:单据不存在'];
            $this->log_record('error',$auditor_id,'出库单审核失败:单据不存在',$params);
            return $result;
        }

        $flag=[];
        $products_map=collect($products)->keyBy('product_id')->toArray();
        DB::beginTransaction();
        try{
            $result=$stock_batch_model->deduct_stock_batch($hq_code,$orgz_id,$outstock_id,$genre,$products,2);
            if(!$result){
                $result=['code'=>'10000','msg'=>'出库单审核失败:库存变动失败或库存信息不存在'];
                $this->log_record('error',$auditor_id,'出库单审核失败:库存变动失败或库存信息不存在',$params);
                DB::rollBack();
                return $result;
            }
            $deducted_amount=-$result['amount'];
            $detail=$result['detail'];
            $outstock_model->where('id',$outstock_id)
                ->update(['confirmed'=>1,'auditor_id'=>$auditor_id,'confirmed_date'=>$now_time,'total_amount'=>$deducted_amount]);

            foreach ($detail as $item){
                $stock_batch_content_id=$item['stock_batch_content_id'];
                $product_id=$item['product_id'];
                $quantity=-$item['quantity'];
                $price=$item['price'];
                $amount=$item['amount'];
                $spec_num=$products_map[$product_id]['spec_num'];
                $spec_unit=$products_map[$product_id]['spec_unit'];
                $remark=$products_map[$product_id]['remark'];
                $package=number_format($quantity/$spec_num,3,'.','');
                if(!in_array($product_id,$flag)){
                    $oc_model->where('outstock_id',$outstock_id)->where('product_id',$product_id)
                        ->update(['price'=>$price,'amount'=>$amount,'quantity'=>$quantity,'package'=>$package,
                            'batch_content_id'=>$stock_batch_content_id]);
                    $flag[]=$product_id;
                }else{
                    $oc_data=['outstock_id'=>$outstock_id,'product_id'=>$product_id,'spec_unit'=>$spec_unit,
                        'spec_num'=>$spec_num,'price'=>$price,'amount'=>$amount,'quantity'=>$quantity,'package'=>$package,
                        'batch_content_id'=>$stock_batch_content_id,'remark'=>$remark,'created_at'=>$now_time];
                    $oc_model->insert($oc_data);
                }
            }
            DB::commit();
            $this->log_record('info',$auditor_id,'出库单审核成功 耗时:'.($this->get_micro_time()-$start_time).' 更新明细:'.json_encode($result),$params);
            return ['code'=>'0','msg'=>'出库单审核成功','bill_id'=>$outstock_id,'amount'=>$deducted_amount,'data'=>$detail];
        }catch (\Exception $e){
            DB::rollBack();
            $this->log_record('info',$auditor_id,'出库单审核失败 原因:'.json_encode($e->getMessage()),$params);
            return ['code'=>'10000','msg'=>'出库单审核失败','data'=>$e->getMessage()];
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $limit 长度
     * @param integer $offset 偏移量
     * @param null|integer $genre 类型
     * @param null|integer $confirmed 审核状态
     * @return array 获取出库单列表
     */
    public function outstock_list($hq_code,$orgz_id,$limit=20,$offset=0,$genre=null,$confirmed=null){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','出库单列表获取开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $limit=is_numeric($limit)?$limit:20;
        $offset=is_numeric($offset)?$offset:0;
        $genre=is_numeric($genre)?$genre:null;
        $confirmed=is_numeric($confirmed)?$confirmed:null;

        if(!$hq_code || !$orgz_id){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','出库单列表获取失败:参数缺失',$params);
            return $result;
        }

        $outstock_model=new Outstock();
        $outstock_list=$outstock_model->get_outstock_list($hq_code,$orgz_id,$limit,$offset,$genre,$confirmed);
        if($outstock_list==null){
            $result=['code'=>'0','msg'=>'出库单列表获取成功:但没有符合筛选条件的数据','total'=>0,'data'=>[]];
            $this->log_record('info','null','出库单列表获取成功:但没有符合筛选条件的数据 耗时:'.($this->get_micro_time()-$start_time),$params);
            return $result;
        }
        $result=['code'=>'0','msg'=>'出库单列表获取成功','total'=>$outstock_list['total'],'data'=>$outstock_list['data']];
        $this->log_record('info','null','出库单列表获取成功 耗时:'.($this->get_micro_time()-$start_time),$params);
        return $result;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $outstock_id 出库单id
     * @return array 通过出库单id获取单据详情
     */
    public function outstock_detail($hq_code,$orgz_id,$outstock_id){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','出库单列表获取开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $outstock_id=is_numeric($outstock_id)?$outstock_id:null;

        if(!$hq_code || !$orgz_id || !$outstock_id){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','出库单详情获取失败:参数缺失',$params);
            return $result;
        }

        $outstock_model=new Outstock();
        $oc_model=new OutstockContent();
        $outstock_data=$outstock_model->get_outstock_by_id($hq_code,$orgz_id,$outstock_id);
        $outstock_content=$oc_model->get_detail_by_outstock_id($outstock_id);
        if($outstock_data==null || $outstock_content==null){
            $result=['code'=>'10000','msg'=>'出库单详情获取失败:状态已变更,或单据不存在'];
            $this->log_record('error','null','出库单详情获取失败:状态已变更,或单据不存在',$params);
            return $result;
        }
        $outstock_data=$outstock_data->toArray();
        $outstock_content=$outstock_content->toArray();
        $result=['code'=>'0','msg'=>'出库单详情获取成功','data'=>['outstock_data'=>$outstock_data,'content_data'=>$outstock_content]];
        $this->log_record('info','null','出库单详情获取成功 耗时:'.($this->get_micro_time()-$start_time),$params);
        return $result;
    }
}