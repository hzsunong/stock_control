<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午3:42
 */
namespace Sunong\StockControl\Facade;

use Illuminate\Support\Facades\DB;
use Sunong\StockControl\Model\Outstock;
use Sunong\StockControl\Model\OutstockContent;
use Sunong\StockControl\Model\StockBatch;

class OutstockFacade extends CommonFacade{
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
                $oc_data['created_at']=$now_time;

                if(!$oc_data['product_id'] || !$oc_data['spec_unit'] || !is_numeric($oc_data['price']) ||
                    !is_numeric($oc_data['quantity']) || !is_numeric($oc_data['package'])){
                    DB::rollBack();
                    $this->log_record('error',$creator_id,'商品详情参数缺失',$params);
                    return ['code'=>'10000','msg'=>'商品详情参数缺失'];
                }
                if($oc_data['package']==0) $oc_data['package']=1;
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
            }elseif(isset($confirm_result) && $confirm_result['code']!='0'){
                $this->log_record('error',$creator_id,'出库单新增失败,原因:'.json_encode($confirm_result['msg']),$params);
                return ['code'=>'10000','msg'=>'出库单新增失败','data'=>$confirm_result['msg']];
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
     * @date 2017-08-24
     * @param string $hqCode
     * @param integer $orgzId
     * @param integer $creatorId 创建者id
     * @param string $creatorName 创建者姓名
     * @param integer $genre 单据类型
     * @param integer $relatedId 关联单据id
     * @param string  $relatedCode 关联单据编码
     * @param array $products
     * 以下为商品参数
     *
     * @param array $optional 可选参数
     * 以下为可选参数
     * $optional['targetOrgzId'] 目标组织id
     * $optional['deliveryData'] 出库日期
     * $optional['auditorId'] 审核人id
     * $optional['auditorName'] 审核人姓名
     * $optional['UpdateFromId'] 更新人姓名
     * $optional['UpdateFromName'] 更新人姓名
     * $optional['remark'] 备注
     * @return array
     */
    public function newOutstock($hqCode,$orgzId,$creatorId,$creatorName,$genre,$relatedId,$relatedCode,$products,
                                $optional=[]){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info',$creatorId,'出库单新增开始',$params);
        $hqCode=trim($hqCode)!=''?$hqCode:null;
        $orgzId=is_numeric($orgzId)?$orgzId:null;
        $creatorId=is_numeric($creatorId)?$creatorId:null;
        $creatorName=trim($creatorName)!=''?trim($creatorName):null;
        $genre=is_numeric($genre)?$genre:null;
        $relatedId=is_numeric($relatedId)?$relatedId:null;
        $relatedCode=trim($relatedCode)!=''?trim($relatedCode):null;
        $products=is_array($products) && !empty($products)?$products:null;
        $targetOrgzId=isset($optional['targetOrgzId']) && is_numeric($optional['targetOrgzId'])?$optional['targetOrgzId']:null;
        $deliveryDate=isset($optional['deliveryData'])&&trim($optional['deliveryData'])!=''?$optional['deliveryData']:null;
        $remark=isset($optional['remark'])&&trim($optional['remark'])!=''?$optional['remark']:null;

        if(!$hqCode || !$orgzId || !$creatorId || !$genre || !$relatedId || !$products){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$creatorId,'出库单新增失败:参数缺失',$params);
            return $result;
        }

        $auditorId=isset($optional['auditorId']) && is_numeric($optional['auditorId'])?$optional['auditorId']:null;
        $auditorName=isset($optional['auditorName']) && trim($optional['auditorName'])!=''?trim($optional['auditorName']):null;
        if($auditorId){
            $is_confirm=true;
        }else{
            $is_confirm=false;
        }
        $UpdateFromId=isset($optional['UpdateFromId']) && is_numeric($optional['UpdateFromId'])?$optional['UpdateFromId']:null;
        $UpdateFromName=isset($optional['UpdateFromName']) && trim($optional['UpdateFromName'])!=''?trim($optional['UpdateFromName']):null;


        $now_time=date('Y-m-d H:i:s');
        $response=[];
        DB::beginTransaction();
        try{
            //building instock data
            $outstock_model=new Outstock();
            $oc_model=new OutstockContent();
            $outstock_code=$outstock_model->create_code($hqCode);
            $outstockData=['code'=>$outstock_code,'hq_code'=>$hqCode,'orgz_id'=>$orgzId, 'genre'=>$genre,
                'creator_id'=>$creatorId,'creator_name'=>$creatorName,
                'target_orgz_id'=>$targetOrgzId,'related_id'=>$relatedId,'related_code'=>$relatedCode,
                'delivery_date'=>$deliveryDate,'remark'=>$remark,'created_at'=>$now_time];

            $outstockId=$outstock_model->insertGetId($outstockData);
            $response['outstockId']=$outstockId;
            $amount=0;
            foreach ($products as $product){
                $oc_data=['outstock_id'=>$outstockId];
                $oc_data['product_id']=isset($product['product_id']) && is_numeric($product['product_id'])?$product['product_id']:null;
                $oc_data['product_code']=isset($product['product_code']) && is_numeric($product['product_code'])?$product['product_code']:null;
                $oc_data['product_name']=isset($product['product_name']) && trim($product['product_name'])!=''?trim($product['product_name']):null;
                $oc_data['spec_unit']=isset($product['spec_unit']) && trim($product['spec_unit'])!=''?$product['spec_unit']:null;
                $oc_data['sale_type']=isset($product['sale_type']) && is_numeric($product['sale_type'])?$product['sale_type']:null;
                $oc_data['price']=isset($product['price']) && is_numeric($product['price'])?$product['price']:null;
                $oc_data['quantity']=isset($product['quantity']) && is_numeric($product['quantity'])?$product['quantity']:null;
                $oc_data['package']=isset($product['package']) && is_numeric($product['package'])?$product['package']:null;
                $oc_data['remark']=isset($product['remark']) && trim($product['remark'])!=''?$product['remark']:null;
                $oc_data['created_at']=$now_time;

                if(!$oc_data['product_id'] || !$oc_data['spec_unit'] || !is_numeric($oc_data['price']) ||
                    !is_numeric($oc_data['quantity']) || !is_numeric($oc_data['package'])){
                    DB::rollBack();
                    $this->log_record('error',$creatorId,'商品详情参数缺失',$params);
                    return ['code'=>'10000','msg'=>'商品详情参数缺失'];
                }
                if($oc_data['package']==0) $oc_data['package']=1;
                $oc_data['spec_num']=isset($product['spec_num']) && is_numeric($product['spec_num'])
                    ? $product['spec_num'] : number_format($oc_data['quantity']/$oc_data['package'],3,'.','');
                $oc_data['amount']=isset($product['amount']) && is_numeric($product['amount'])
                    ? $product['amount'] : number_format($oc_data['price']*$oc_data['quantity'],0,'.','');

                $content_id=$oc_model->insertGetId($oc_data);
                $response['contents'][]=['product_id'=>$oc_data['product_id'],'outstock_content_id'=>$content_id];
                $amount+=$oc_data['amount'];
            }
            $outstock_model->where('id',$outstockId)->update(['total_amount'=>$amount]);
            DB::commit();
            //立即审核
            $this->log_record('info',$creatorId,'出库单新增成功'.' 耗时:'.($this->get_micro_time()-$start_time).' id:'.$outstockId,$params);

            if($is_confirm){
                $confirm_result=$this->confirm_outstock($hqCode,$orgzId,$outstockId,$auditorId);
            }
            $result=['code'=>'0','msg'=>'出库单新增成功','bill_id'=>$outstockId];
            if(isset($confirm_result) && $confirm_result['code']=='0'){
                $result['amount']=$confirm_result['amount'];
                $result['detail']=$confirm_result['data'];
            }elseif(isset($confirm_result) && $confirm_result['code']!='0'){
                $this->log_record('error',$creatorId,'出库单新增失败,原因:'.json_encode($confirm_result['msg']),$params);
                return ['code'=>'10000','msg'=>'出库单新增失败','data'=>$confirm_result['msg']];
            }
            return $result;
        }catch (\Exception $exception){
            DB::rollBack();
            $this->log_record('error',$creatorId,'出库单新增失败,原因:'.$exception->getMessage(),$params);
            return ['code'=>'10000','msg'=>'出库单新增失败','data'=>$exception->getMessage()];
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hqCode
     * @param integer $orgzId
     * @param integer $outstockId 出库单id
     * @param integer $userId 操作人id
     * @param string $userName 操作人名称
     * @param string $deliveryDate 出库时间
     * @param bool $isConfirm 审核操作
     * @return array 出库指定商品
     */
    public function deliveryOutstock($hqCode,$orgzId,$outstockId,$userId,$userName,$deliveryDate=null,$isConfirm=false){
        $startTime=$this->get_micro_time();
        $nowTime=date('Y-m-d H:i:s');
        $params=func_get_args();
        $this->log_record('info',$userId,'出库单审核开始',$params);
        $hqCode=trim($hqCode)!=''?$hqCode:null;
        $orgzId=is_numeric($orgzId)?$orgzId:null;
        $outstockId=is_numeric($outstockId)?$outstockId:null;
        $userId=is_numeric($userId)?$userId:null;
        $userName=trim($userName)!=''?trim($userName):null;
        $deliveryDate=trim($deliveryDate)!=''?$deliveryDate:null;

        if(!$hqCode || !$orgzId || !$outstockId || !$userId){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$userId,'出库单审核失败:参数缺失',$params);
            return $result;
        }

        $outstock_model=new Outstock();
        $oc_model=new OutstockContent();
        $stock_batch_model=new StockBatch();
        $products=$outstock_model->get_list_by_outstock_id($hqCode,$orgzId,$outstockId,$isConfirm);
        if($products==null){
            $result=['code'=>'10000','msg'=>'出库单审核失败:状态已变更'];
            $this->log_record('error',$userId,'出库单审核失败:状态已变更',$params);
            return $result;
        }
        $genre=$outstock_model->get_genre_by_outstock_id($outstockId);
        if($genre!=null){
            $genre=$genre->genre;
        }else{
            $result=['code'=>'10000','msg'=>'出库单审核失败:单据不存在'];
            $this->log_record('error',$userId,'出库单审核失败:单据不存在',$params);
            return $result;
        }

        $flag=[];
        $products_map=collect($products)->keyBy('product_id')->toArray();
        DB::beginTransaction();
        try{
            $result=$stock_batch_model->deduct_stock_batch($hqCode,$orgzId,$outstockId,$genre,$products,2);
            if(!$result){
                $result=['code'=>'10000','msg'=>'出库单审核失败:库存变动失败或库存信息不存在'];
                $this->log_record('error',$userId,'出库单审核失败:库存变动失败或库存信息不存在',$params);
                DB::rollBack();
                return $result;
            }
            $detail=$result['detail'];
            $stock_data=['outstock_status'=>1,'update_from_id'=>$userId,'update_from_name'=>$userName,'total_amount'=>-$result['amount']];
            if($isConfirm){
                $stock_data['confirmed']=1;
                $stock_data['confirmed_date']=$nowTime;
                $stock_data['auditor_id']=$userId;
                $stock_data['auditor_name']=$userName;
            }
            if($deliveryDate!==null) $stock_data['delivery_date']=$deliveryDate;
            $outstock_model->where('id',$outstockId)
                ->update($stock_data);

            foreach ($detail as $item){
                $stock_batch_content_id=$item['stock_batch_content_id'];
                $product_id=$item['product_id'];
                $quantity=-$item['quantity'];
                $price=$item['price'];
                $amount=-$item['amount'];
                $spec_num=$products_map[$product_id]['spec_num'];
                $spec_unit=$products_map[$product_id]['spec_unit'];
                $remark=$products_map[$product_id]['remark'];
                $package=number_format($quantity/$spec_num,3,'.','');
                if(!in_array($product_id,$flag)){
                    $oc_model->where('outstock_id',$outstockId)->where('product_id',$product_id)
                        ->update(['price'=>$price,'amount'=>$amount,'quantity'=>$quantity,'package'=>$package,
                            'batch_content_id'=>$stock_batch_content_id]);
                    $flag[]=$product_id;
                }else{
                    $oc_data=['outstock_id'=>$outstockId,'product_id'=>$product_id,'spec_unit'=>$spec_unit,
                        'spec_num'=>$spec_num,'price'=>$price,'amount'=>$amount,'quantity'=>$quantity,'package'=>$package,
                        'batch_content_id'=>$stock_batch_content_id,'remark'=>$remark,'created_at'=>$nowTime];
                    $oc_model->insert($oc_data);
                }
            }
            DB::commit();
            $this->log_record('info',$userId,'出库单审核成功 耗时:'.($this->get_micro_time()-$startTime).' 更新明细:'.json_encode($result),$params);
            return ['code'=>'0','msg'=>'出库单审核成功','bill_id'=>$outstockId,'amount'=>$result['amount'],'data'=>$detail];
        }catch (\Exception $e){
            DB::rollBack();
            $this->log_record('info',$userId,'出库单审核失败 原因:'.json_encode($e->getMessage()),$params);
            return ['code'=>'10000','msg'=>'出库单审核失败','data'=>$e->getMessage()];
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $outstock_id 出库单id
     * @param integer $auditor_id 审核人id
     * @param string $delivery_date 出库时间
     * @return array 审核出库单
     */
    public function confirm_outstock($hq_code,$orgz_id,$outstock_id,$auditor_id,$delivery_date=null){
        $start_time=$this->get_micro_time();
        $now_time=date('Y-m-d H:i:s');
        $params=func_get_args();
        $this->log_record('info',$auditor_id,'出库单审核开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $outstock_id=is_numeric($outstock_id)?$outstock_id:null;
        $auditor_id=is_numeric($auditor_id)?$auditor_id:null;
        $delivery_date=trim($delivery_date)!=''?$delivery_date:null;

        if(!$hq_code || !$orgz_id || !$outstock_id || !$auditor_id){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error',$auditor_id,'出库单审核失败:参数缺失',$params);
            return $result;
        }

        $outstock_model=new Outstock();
        $oc_model=new OutstockContent();
        $stock_batch_model=new StockBatch();
        $products=$outstock_model->get_list_by_outstock_id($hq_code,$orgz_id,$outstock_id,1);
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
            $detail=$result['detail'];
            $stock_data=['confirmed'=>1,'outstock_status'=>1,'auditor_id'=>$auditor_id,'confirmed_date'=>$now_time,'total_amount'=>-$result['amount']];
            if($delivery_date!==null) $stock_data['delivery_date']=$delivery_date;
            $outstock_model->where('id',$outstock_id)
                ->update($stock_data);

            foreach ($detail as $item){
                $stock_batch_content_id=$item['stock_batch_content_id'];
                $product_id=$item['product_id'];
                $quantity=-$item['quantity'];
                $price=$item['price'];
                $amount=-$item['amount'];
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
            return ['code'=>'0','msg'=>'出库单审核成功','bill_id'=>$outstock_id,'amount'=>$result['amount'],'data'=>$detail];
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
     * @param array $date_range 日期范围
     * @param integer $limit 长度
     * @param integer $offset 偏移量
     * @param null|integer $genre 类型
     * @param null|integer $confirmed 审核状态
     * @param null|array $or_where 查询条件
     * @return array 获取出库单列表
     */
    public function outstock_list($hq_code,$orgz_id,$date_range,$limit=20,$offset=0,$genre=null,$confirmed=null,$or_where=null){
        $start_time=$this->get_micro_time();
        $params=func_get_args();
        $this->log_record('info','null','出库单列表获取开始',$params);
        $hq_code=trim($hq_code)!=''?$hq_code:null;
        $orgz_id=is_numeric($orgz_id)?$orgz_id:null;
        $limit=is_numeric($limit)?$limit:20;
        $date_range=is_array($date_range) && !empty($date_range)?$date_range:null;
        $offset=is_numeric($offset)?$offset:0;
        $genre=is_numeric($genre)?$genre:null;
        $confirmed=is_numeric($confirmed)?$confirmed:null;
        $or_where=is_array($or_where) && !empty($or_where)?$or_where:null;

        if(!$hq_code || !$orgz_id || !$date_range){
            $result=['code'=>'10000','msg'=>'参数缺失'];
            $this->log_record('error','null','出库单列表获取失败:参数缺失',$params);
            return $result;
        }

        $outstock_model=new Outstock();
        $outstock_list=$outstock_model->get_outstock_list($hq_code,$orgz_id,$date_range,$limit,$offset,$genre,$confirmed,$or_where);
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