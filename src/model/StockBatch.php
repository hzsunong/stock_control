<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use Illuminate\Support\Facades\DB;
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

    /**
     * 扣减批次
     * $product_info示例：
     * array(['product_id'=>1, 'quantity'=>10], ..., [...])
     * @author Robin <huangfeilong@comteck.cn>
     * @date   2017-03-29
     * @param  string    $hq_code               企业编号
     * @param  integer   $orgz_id               组织ID
     * @param  integer   $related_id            相关单据ID
     * @param null|integer $stock_change_genre  库存变动类型见下方
     * @param  array     $product_info          商品信息
     * 库存变动类型 特殊处理 <4:报损 2:盘点>
     *            出库相关 <1:销售出库 10:调拨出库 11:门店退仓出库 3:门店要货出库 8:加工原料 5:门店配送差异  6:网单销售 12:供应商退货 18:配送中心销售>
     *            入库相关 <7:销售退货 13:调拨入库 14:门店退仓入库 0:门店要货入库 9:加工成品 15:配送差异确认 16:配送差异驳回 17:采购>
     * @param integer    $operation               1:入库单,2:出库单,3:销售
     * @return mixed                            根据类型返回扣减明细记录数组或扣减总价
     */
    public function deduct_stock_batch($hq_code, $orgz_id, $related_id,$stock_change_genre=1, $product_info,$operation)
    {
        $stock_model=new Stock();
        // 扣减变动明细
        $changed_detail=[];
        // 扣减批次总价
        $deducted_amount_price=0;

        $join_table = 'stock_batch_content';
        foreach ($product_info as $item)
        {
            $deducted_price=0;
            $left_quantity = $item['quantity'];
            $this->select('stock_batch.id as stock_batch_id', 'stock_batch.hq_code as hq_code',
                'stock_batch.orgz_id as orgz_id',$join_table.'.id as content_id', $join_table.'.inventory',
                $join_table.'.product_id', $join_table.'.price', $join_table.'.spec_num', $join_table.'.spec_unit')
                ->join($join_table, $join_table.'.stock_batch_id', '=', 'stock_batch.id')
                ->where('stock_batch.hq_code', $hq_code)
                ->where('stock_batch.orgz_id', $orgz_id)
                ->where('stock_batch.status', 1)
                ->where($join_table.'.product_id', $item['product_id'])
                ->where($join_table.'.status', 1)
                ->where($join_table.'.inventory', '>', 0)
                ->orderBy('stock_batch.created_at', 'asc')
                ->chunk(5, function($records) use(&$left_quantity,&$changed_detail,&$deducted_price, &$stock_change_genre,$related_id,$stock_model){
                    $om = new StockBatchContent();
                    $fw = new StockBatchFlow;
                    foreach ($records as $record)
                    {

                        $flow = array(
                            'hq_code' => $record->hq_code,
                            'orgz_id' => $record->orgz_id,
                            'stock_batch_id' => $record->stock_batch_id,
                            'stock_batch_content_id' => $record->content_id,
                            'product_id' => $record->product_id,
                            'price' => $record->price,
                            'spec_num' => $record->spec_num,
                            'spec_unit' => $record->spec_unit,
                            'genre' => $stock_change_genre,
                            'related_id' => $related_id
                        );
//                        print_r($left_quantity);die;
                        //待扣减数量大于该批次库存数量
                        if ($left_quantity >= $record->inventory)
                        {
                            $om->where('id', $record->content_id)->update(['inventory'=>0,'outstock_num'=>DB::raw("outstock_num+$record->inventory")]);
                            $left_quantity -= $record->inventory;
                            $changed_detail[]=[
                                'stock_batch_content_id'=>$record->content_id,
                                'quantity'=>-$record->inventory,
                                'product_id'=>$record->product_id,
                                'price'=>$record->price,
                                'spec_num'=>$record->spec_num,
                                'amount'=>number_format(-$record->inventory*$record->price,0,'.','')
                            ];
                            $deducted_price+=($record->inventory*$record->price);
                            $flow['quantity'] = -$record->inventory;
                            if (!is_numeric($flow['spec_num']) or $flow['spec_num'] == 0)
                            {
                                $flow['package'] = 1;
                            }
                            else
                            {
                                $flow['package'] = number_format($flow['quantity']/$flow['spec_num'], 3,'.','');
                            }
                            $flow['total_amount'] = number_format($flow['price'] * $flow['quantity'],0,'.','');
                            $fw->insert($flow);
                        }
                        else
                        {
                            $deducted_price+=($left_quantity*$record->price);
                            $om->where('id', $record->content_id)
                                ->update(['inventory'=>DB::raw("inventory-$left_quantity"),'outstock_num'=>DB::raw("outstock_num+$left_quantity")]);
                            $changed_detail[]=[
                                'stock_batch_content_id'=>$record->content_id,
                                'quantity'=>-$left_quantity,
                                'product_id'=>$record->product_id,
                                'price'=>$record->price,
                                'spec_num'=>$record->spec_num,
                                'amount'=>number_format(-$left_quantity*$record->price,0,'.','')
                            ];
                            $flow['quantity'] = -$left_quantity;
                            $left_quantity=0;
                            if (!is_numeric($flow['spec_num']) or $flow['spec_num'] == 0)
                            {
                                $flow['package'] = 1;
                            }
                            else
                            {
                                $flow['package'] = number_format($flow['quantity']/$flow['spec_num'], 3,'.','');
                            }
                            $flow['total_amount'] = number_format($flow['price'] * $flow['quantity'],0,'.','');
                            $fw->insert($flow);
                            break;
                        }
                    }
                    if ($left_quantity <= 0)
                    {
                        return false;
                    }
                });
            //如果剩余库存不足以扣减数量,新增负批次
            if ($left_quantity > 0) {
                // 查找最近的负批次
                $record = $this->select('stock_batch.id as stock_batch_id', 'stock_batch.hq_code as hq_code',
                    'stock_batch.orgz_id as orgz_id',$join_table.'.id as content_id', $join_table.'.inventory',
                    $join_table.'.product_id', $join_table.'.price', $join_table.'.spec_num', $join_table.'.spec_unit')
                    ->join($join_table, $join_table.'.stock_batch_id', '=', 'stock_batch.id')
                    ->where('stock_batch.hq_code', $hq_code)
                    ->where('stock_batch.orgz_id', $orgz_id)
                    ->where('stock_batch.status', 1)
                    ->where($join_table.'.product_id', $item['product_id'])
                    ->where($join_table.'.status', 1)
                    ->where($join_table.'.inventory', '<', 0)
                    ->orderBy('stock_batch.created_at', 'desc')
                    ->first();
                $fw = new StockBatchFlow;

                if ($record)
                {
                    $flow = array(
                        'hq_code' => $record->hq_code,
                        'orgz_id' => $record->orgz_id,
                        'stock_batch_id' => $record->stock_batch_id,
                        'stock_batch_content_id' => $record->content_id,
                        'product_id' => $record->product_id,
                        'price' => $record->price,
                        'spec_num' => $record->spec_num,
                        'spec_unit' => $record->spec_unit,
                        'genre' => $stock_change_genre,
                        'related_id' => $related_id
                    );
                    // 若负批次存在，在该批次中扣减
                    $om = new StockBatchContent;
                    $om->where('id', $record->content_id)->update(['inventory'=>DB::raw("inventory-$left_quantity"),'outstock_num'=>DB::raw("outstock_num+$left_quantity")]);
                    $changed_detail[]=[
                        'stock_batch_content_id'=>$record->content_id,
                        'quantity'=>-$left_quantity,
                        'product_id'=>$record->product_id,
                        'price'=>$record->price,
                        'spec_num'=>$record->spec_num,
                        'amount'=>number_format(-$left_quantity*$record->price,0,'.','')
                    ];
                    $deducted_price+=($left_quantity*$record->price);
                    $flow['quantity'] = -$left_quantity;
                    if (!is_numeric($flow['spec_num']) or $flow['spec_num'] == 0)
                    {
                        $flow['package'] = 1;
                    }
                    else
                    {
                        $flow['package'] = number_format($flow['quantity']/$flow['spec_num'], 3,'.','');
                    }
                    $flow['total_amount'] = number_format($flow['price'] * $flow['quantity'],0,'.','');
                    $fw->insert($flow);
                }
                else
                {
                    // 新增负批次
                    $arr = ['product_id' => $item['product_id'], 'quantity' => -$left_quantity,'package'=>$item['package']];
                    $product_info=$stock_model->get_product_info_by_product_id($hq_code,$orgz_id,$item['product_id']);
                    if($product_info==null) return false;
                    $arr['spec_num']=$product_info->spec_num;
                    $arr['spec_unit']=$product_info->spec_unit;
                    $arr['price']=$product_info->price;

                    $info = array($arr);

                    $res = $this->add_stock_batch($hq_code, $orgz_id,$related_id,$stock_change_genre ,$info,$operation);
                    if (!isset($res['details']))
                    {
                        return false;
                    }
                    $batch = reset($res['details']);
                    $batch['quantity'] = -$left_quantity;
                    $batch['product_id'] = $item['product_id'];
                    $batch['amount'] = -$left_quantity*$batch['price'];
                    $changed_detail[] = $batch;
                    $deducted_price += ($left_quantity*$batch['price']);
                }
            }

            //更新库存
            $item['amount']=number_format(-$deducted_price,0,'.','');
            $item['quantity']=-$item['quantity'];
            $update=$stock_model->update_product_stock($hq_code,$orgz_id,$related_id,$stock_change_genre,[$item],$operation);
            if($update){
                $deducted_amount_price+=$item['amount'];
            }else{
                return false;
            }
        }

        $result=['amount'=>$deducted_amount_price,'detail'=>$changed_detail];
        return $result;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @param string $hq_code 企业编号
     * @param integer $orgz_id 组织id
     * @param integer $related_id 相关单据id
     * @param integer $genre 类型
     * @param array $product_info 数据示例
     * 商品信息 [ ['product_id'=>1,'quantity'=>5,'package'=>2,'spec_num'=>2.5,'spec_unit'=>'斤','supplier_id'=>null] ]
     * @param integer $operation 1:入库单,2:出库单,3:销售
     * @param bool $update_stock_price 是否更新库存价格
     * @return array|false ['stock_batch_id'=>55,'detail'=>$detail]
     */
    public function add_stock_batch($hq_code,$orgz_id,$related_id,$genre,$product_info,$operation,$update_stock_price=false){
        $stock = new Stock();
        $sbc_model = new StockBatchContent();
        $sbf_model = new StockBatchFlow();

        $result = [];
        $details = [];
        $now_time=date('Y-m-d H:i:s');
        $base = ['hq_code' => $hq_code, 'orgz_id' => $orgz_id, 'related_id' => $related_id, 'genre' => $genre,
            'code' => $this->order_create_code($hq_code,$this->_stock_batch,'PC',4), 'created_at' => $now_time];
        $id = $this->insertGetId($base);
        $result['stock_batch_id'] = $id;
        $amount=0;
        foreach ($product_info as $item)
        {
            $detail=null;
            // 构建批次明细数据
            $detail['stock_batch_id']=$id;
            $detail['product_id']=isset($item['product_id']) && is_numeric($item['product_id'])?$item['product_id']:null;
            $detail['quantity']=isset($item['quantity']) && is_numeric($item['quantity'])?$item['quantity']:null;
            $detail['package']=isset($item['package']) && is_numeric($item['package'])?$item['package']:null;
            $detail['price']=isset($item['price']) && is_numeric($item['price'])?$item['price']:null;
            $detail['spec_num']=isset($item['spec_num']) && is_numeric($item['spec_num'])?$item['spec_num']:null;
            $detail['spec_unit']=isset($item['spec_unit']) && trim($item['spec_unit'])!=null?$item['spec_unit']:null;
            $detail['supplier_id']=isset($item['supplier_id']) && is_numeric($item['supplier_id'])?$item['supplier_id']:null;

            if(!$detail['product_id'] || !$detail['quantity'] || !$detail['package'] || !is_numeric($detail['price'])) return false;

            $detail['total_amount'] = number_format($detail['price'] * $detail['quantity'],0,'.','');
            $amount+=$detail['total_amount'];
            $detail['inventory'] = $item['quantity'];
            $detail['instock_num'] = $item['quantity'];
            $detail['created_at'] = $now_time;

            // 查询批次负库存，若存在则填补
            if($detail['quantity'] > 0){
                $left_quantity=$this->backfill_negative_inventory($hq_code,$orgz_id,$detail['product_id'],$detail['quantity']);
                $detail['quantity']=$left_quantity;
            }

            $detail_id = $sbc_model->insertGetId($detail);
            $detail['stock_batch_content_id']=$detail_id;

            $detail['id'] = $detail_id;
            $details[]=$detail;

            // 更新库存表商品最新批次单价
            if ($update_stock_price && $detail['spec_num'] != 0)
            {
                $stock->where('hq_code', $hq_code)
                    ->where('orgz_id', $orgz_id)
                    ->where('product_id', $detail['product_id'])
                    ->update(['price' => $item['price']]);
            }

            // 插入批次流水
            $flow = array(
                'hq_code' => $hq_code,
                'orgz_id' => $orgz_id,
                'stock_batch_id' => $detail['stock_batch_id'],
                'stock_batch_content_id' => $detail_id,
                'product_id' => $detail['product_id'],
                'spec_num' => $detail['spec_num'],
                'spec_unit' => $detail['spec_unit'],
                'price' => $detail['price'],
                'quantity' => $detail['quantity'],
                'package' => $detail['package'],
                'total_amount' => $detail['total_amount'],
                'genre' => $genre,
                'related_id' => $related_id,
                'created_at'=>$now_time
            );
            $sbf_model->insert($flow);
        }

        $result['details'] = $details;
        $result['amount'] = $amount;
        $update=$stock->update_product_stock($hq_code,$orgz_id,$related_id,$genre,$product_info,$operation);
        if($update){
            return $result;
        }else{
            return false;
        }
    }
}