<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午3:42
 */
namespace Sunong\StockControl\Facade;

use Illuminate\Support\Facades\DB;
use Sunong\StockControl\Model\Instock;
use Sunong\StockControl\Model\InstockContent;
use Sunong\StockControl\Model\StockBatch;

class InstockFacade extends CommonFacade{
    //入库单相关方法

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $creator_id
     * @param integer $genre 相关单据类型
     * @param integer $related_id 相关单据id
     * @param array $products 相关商品信息列表
     * @param null|integer $supplier_id 供应商id
     * @param null|string $remark 备注
     * @param null|integer $auditor_id 审核人id  传值则为立即审核
     * @param null|string $instock_date 入库时间
     * @param bool  $update_stock_price 是否更新库存价格
     * @return array
     */
    public function new_instock($hq_code,$orgz_id,$creator_id,$genre,$related_id,$products,$supplier_id=null,
                                $remark=null, $auditor_id=null,$instock_date=null,$update_stock_price=false){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info',$creator_id,'入库单新增开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $creator_id=is_numeric($creator_id)?$creator_id:null;
        $genre=is_numeric($genre)?$genre:null;
        $related_id=is_numeric($related_id)?$related_id:null;
        $products=is_array($products) && !empty($products)?$products:null;
        $supplier_id=is_numeric($supplier_id)?$supplier_id:null;
        $instock_date=trim($instock_date)!==''?trim($instock_date):null;
        $remark=trim($remark)!=''?$remark:null;

        if(!$hq_code || !$orgz_id || !$creator_id || !$genre || !$related_id || !$products){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$creator_id,'入库单新增失败:参数缺失',$params);
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
            $instock_model=new Instock();
            $ic_model=new InstockContent();
            $instock_code=$instock_model->create_code($hq_code);
            $instock_data=['code'=>$instock_code,'hq_code'=>$hq_code,'orgz_id'=>$orgz_id, 'total_amount'=>0,
                'genre'=>$genre,'creator_id'=>$creator_id, 'supplier_id'=>$supplier_id,'related_id'=>$related_id,
                'remark'=>$remark,'instock_date'=>$instock_date,'created_at'=>$now_time];

            $instock_id=$instock_model->insertGetId($instock_data);
            $response['instock_id']=$instock_id;
            $amount=0;
            foreach ($products as $product){
                $ic_data=['instock_id'=>$instock_id];
                $ic_data['product_id']=isset($product['product_id']) && is_numeric($product['product_id'])?$product['product_id']:null;
                $ic_data['spec_unit']=isset($product['spec_unit']) && trim($product['spec_unit'])!=''?$product['spec_unit']:null;
                $ic_data['price']=isset($product['price']) && is_numeric($product['price'])?$product['price']:null;
                $ic_data['quantity']=isset($product['quantity']) && is_numeric($product['quantity'])?$product['quantity']:null;
                $ic_data['package']=isset($product['package']) && is_numeric($product['package'])?$product['package']:null;
                $ic_data['remark']=isset($product['remark']) && trim($product['remark'])!=''?$product['remark']:null;
                $ic_data['created_at']=$now_time;
                if(!$ic_data['product_id'] || !$ic_data['spec_unit'] || !is_numeric($ic_data['price']) ||
                    !is_numeric($ic_data['quantity']) || !is_numeric($ic_data['package'])){
                    DB::rollBack();
                    $this->log_record('error',$creator_id,'商品详情参数缺失',$params);
                    return ['code'=>'10000','msg'=>'商品详情参数缺失'];
                }
                if($ic_data['package']==0) $ic_data['package']=1;
                $ic_data['spec_num']=isset($product['spec_num']) && is_numeric($product['spec_num'])
                    ? $product['spec_num'] : number_format($ic_data['quantity']/$ic_data['package'],3,'.','');
                $ic_data['amount']=isset($product['amount']) && is_numeric($product['amount'])
                    ? $product['amount'] : number_format($ic_data['price']*$ic_data['quantity'],0,'.','');

                $content_id=$ic_model->insertGetId($ic_data);
                $response['contents'][]=['product_id'=>$ic_data['product_id'],'instock_content_id'=>$content_id];
                $amount+=$ic_data['amount'];
            }
            $instock_model->where('id',$instock_id)->update(['total_amount'=>$amount]);
            DB::commit();
            $result=['code'=>'0','msg'=>'入库单新增成功','bill_id'=>$instock_id];
            if($is_confirm){
                $instock_response=$this->confirm_instock($hq_code,$orgz_id,$instock_id,$auditor_id,null,$update_stock_price);
            }
            if(isset($instock_response) && $instock_response['code']=='0'){
                $result['amount']=$amount;
                $result['detail']=$instock_response['detail'];
            }elseif(isset($instock_response) && $instock_response['code']!='0'){
                $this->log_record('error',$creator_id,'入库单新增失败,原因:'.json_encode($instock_response['msg']),$params);
                return ['code'=>'10000','msg'=>'入库单新增失败','data'=>$instock_response['msg']];
            }
            $this->log_record('info',$creator_id,'入库单新增成功 id:'.$instock_id.' 耗时:'.($this->get_micro_time()-$start_time),$params);
            return $result;
        }catch (\Exception $exception){
            DB::rollBack();
            $this->log_record('error',$creator_id,'入库单新增失败,原因:'.$exception->getMessage(),$params);
            return ['code'=>'10000','msg'=>'入库单新增失败','data'=>$exception->getMessage()];
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $instock_id 入库单id
     * @param integer $auditor_id 审核人id
     * @param string $instock_date 入库时间
     * @param boolean $update_stock_price 是否更新库存最近一次批次进价
     * @return array 审核入库单
     */
    public function confirm_instock($hq_code,$orgz_id,$instock_id,$auditor_id,$instock_date=null,$update_stock_price=false){
        $start_time=$this->get_micro_time();
        $now_time=date('Y-m-d H:i:s');
        $params=func_get_args();
        $this->log_record('info',$auditor_id,'入库单审核开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $instock_id=is_numeric($instock_id)?$instock_id:null;
        $auditor_id=is_numeric($auditor_id)?$auditor_id:null;
        $instock_date=trim($instock_date)!==''?trim($instock_date):null;

        if(!$hq_code || !$orgz_id || !$instock_id || !$auditor_id){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$auditor_id,'入库单审核失败:参数缺失',$params);
            return $result;
        }

        $instock_model=new Instock();
        $stock_batch_model=new StockBatch();
        $products=$instock_model->get_unconfirmed_list_by_instock_id($hq_code,$orgz_id,$instock_id);
        if($products==null){
            $result=['code'=>'10000','msg'=>'入库单审核失败:状态已变更'];
            $this->log_record('error',$auditor_id,'入库单审核失败:状态已变更',$params);
            return $result;
        }
        $genre=$instock_model->get_genre_by_instock_id($instock_id);
        if($genre!=null){
            $genre=$genre->genre;
        }else{
            $result=['code'=>'10000','msg'=>'入库单审核失败:单据不存在'];
            $this->log_record('error',$auditor_id,'入库单审核失败:单据不存在',$params);
            return $result;
        }

        DB::beginTransaction();
        try{
            $instock_data=['confirmed'=>1,'auditor_id'=>$auditor_id,'confirmed_date'=>$now_time];
            if($instock_date!==null) $instock_data['instock_date']=$instock_date;
            $instock_model->where('id',$instock_id)
                ->update($instock_data);
            $result=$stock_batch_model->add_stock_batch($hq_code,$orgz_id,$instock_id,$genre,$products,1,$update_stock_price);
            if($result){
                DB::commit();
                $this->log_record('info',$auditor_id,'入库单审核成功 耗时:'.($this->get_micro_time()-$start_time).' 更新明细:'.json_encode($result),$params);
                return ['code'=>'0','msg'=>'入库单审核成功','bill_id'=>$instock_id,'detail'=>$result['details'],'amount'=>$result['amount']];
            }else{
                DB::rollBack();
                $this->log_record('info',$auditor_id,'入库单审核失败 耗时:'.($this->get_micro_time()-$start_time).' 库存变动失败或库存信息不存在',$params);
                return ['code'=>'10000','msg'=>'入库单审核失败 库存变动失败或库存信息不存在','data'=>null];
            }
        }catch (\Exception $e){
            DB::rollBack();
            $this->log_record('info',$auditor_id,'入库单审核失败 原因:'.json_encode($e->getMessage()),$params);
            return ['code'=>'10000','msg'=>'入库单审核失败','data'=>$e->getMessage()];
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param array $date_range 时间范围
     * @param integer $limit 长度
     * @param integer $offset 偏移量
     * @param null|integer $genre 类型
     * @param null|integer $confirmed 审核状态
     * @param null|array $or_where 查询条件
     * @return array 获取入库单列表
     */
    public function instock_list($hq_code,$orgz_id,$date_range,$limit=20,$offset=0,$genre=null,$confirmed=null,$or_where=null){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','入库单列表获取开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $date_range=is_array($date_range) && !empty($date_range)?$date_range:null;
        $limit=is_numeric($limit)?$limit:20;
        $offset=is_numeric($offset)?$offset:0;
        $genre=is_numeric($genre)?$genre:null;
        $confirmed=is_numeric($confirmed)?$confirmed:null;
        $or_where=is_array($or_where) && !empty($or_where)?$or_where:null;

        if(!$hq_code || !$orgz_id || !$date_range){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','入库单列表获取失败:参数缺失',$params);
            return $result;
        }

        $instock_model=new Instock();
        $instock_list=$instock_model->get_instock_list($hq_code,$orgz_id,$date_range,$limit,$offset,$genre,$confirmed,$or_where);
        if($instock_list==null){
            $result=['code'=>'0','msg'=>'入库单列表获取成功:但没有符合筛选条件的数据','total'=>0,'data'=>[]];
            $this->log_record('info','null','入库单列表获取成功:但没有符合筛选条件的数据 耗时:'.($this->get_micro_time()-$start_time),$params);
            return $result;
        }
        $result=['code'=>'0','msg'=>'入库单列表获取成功','total'=>$instock_list['total'],'data'=>$instock_list['data']];
        $this->log_record('info','null','入库单列表获取成功 耗时:'.($this->get_micro_time()-$start_time),$params);
        return $result;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $instock_id 入库单id
     * @return array 通过入库单id获取单据详情
     */
    public function instock_detail($hq_code,$orgz_id,$instock_id){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','入库单列表获取开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $instock_id=is_numeric($instock_id)?$instock_id:null;

        if(!$hq_code || !$orgz_id || !$instock_id){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','入库单详情获取失败:参数缺失',$params);
            return $result;
        }

        $instock_model=new Instock();
        $ic_model=new InstockContent();
        $instock_data=$instock_model->get_instock_by_id($hq_code,$orgz_id,$instock_id);
        $instock_content=$ic_model->get_detail_by_instock_id($instock_id);
        if($instock_data==null || $instock_content==null){
            $result=['code'=>'10000','msg'=>'入库单详情获取失败:状态已变更,或单据不存在'];
            $this->log_record('error','null','入库单详情获取失败:状态已变更,或单据不存在',$params);
            return $result;
        }
        $instock_data=$instock_data->toArray();
        $instock_content=$instock_content->toArray();
        $result=['code'=>'0','msg'=>'入库单详情获取成功','data'=>['instock_data'=>$instock_data,'content_data'=>$instock_content]];
        $this->log_record('info','null','入库单详情获取成功 耗时:'.($this->get_micro_time()-$start_time),$params);
        return $result;
    }
}