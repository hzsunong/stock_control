<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use Illuminate\Support\Facades\DB;
use SuNong\StockControl\Core;

class Stock extends Core{
    protected $table;

    public function __construct(){
        parent::__construct();
        $this->table=$this->_stock;
    }

    // 判断组织商品ID是否存在
    public function product_isexist_by_orgz_product($hq_code,$orgz_id,$product_id)
    {
        $data = $this->where('hq_code', $hq_code)->where('orgz_id', $orgz_id)
            ->where('product_id', $product_id)->first();
        if (!$data) return false;
        return $data;
    }

    /**
     * @date 2017-04-01
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $related_id 相关来源单据id
     * @param integer $genre 库存变动类型
     * @param array $product_info 商品信息
     * @param integer $operation 1:入库单,2:出库单,3:销售
     * @return bool 更新商品库存统一方法
     * 对该方法不解可咨询龙哥,佳霖,甲文
     */
    public function update_product_stock($hq_code, $orgz_id,$related_id ,$genre, $product_info,$operation)
    {
        $now_time=date('Y-m-d H:i:s');
        $stock_model=new Stock();
        $scr_model=new StockChangeRecord();
//        print_r($product_info);die;
        foreach ($product_info as $product){
            //验证数据有效性
            $product_id=isset($product['product_id']) && is_numeric($product['product_id']) ? $product['product_id'] :null;
            $quantity=isset($product['quantity']) && is_numeric($product['quantity']) ? $product['quantity'] :null;
            $price=isset($product['price']) && is_numeric($product['price']) ? $product['price'] :null;
            $amount_price=isset($product['amount']) && is_numeric($product['amount']) ? $product['amount'] :null;
            if(!$product_id || !$quantity || (!$price && !$amount_price)) return false;
            if($price===null) $price=number_format($amount_price/$quantity,0,'.','');
            if($amount_price===null) $amount_price=number_format($price*$quantity,0,'.','');
            $stock_info=$stock_model->product_isexist_by_orgz_product($hq_code,$orgz_id,$product_id);
            //对应商品库存信息不存在,则新建
            if(!$stock_info){
                $stock_arr = [
                    'hq_code' => $hq_code,
                    'orgz_id' => $orgz_id,
                    'product_id' => $product_id,
                    'price' => $price,
                    'quantity' => $quantity,
                    'total_amount' => $amount_price,
                    'sales_num' => 0,
                    'instock_num'=>0,
                    'outstock_num'=>0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if($operation==1){
                    $stock_arr['instock_num']=$quantity;
                    $stock_arr['last_instock_date'] = $now_time;
                }elseif($operation==2){
                    $stock_arr['outstock_num']=$quantity;
                    $stock_arr['last_outstock_date'] = $now_time;
                }else{
                    $stock_arr['sales_num']=$quantity;
                    $stock_arr['last_sales_date'] = $now_time;
                }
                $stock_id=$this->insertGetId($stock_arr);

                $changed_inventory=$quantity;
            }else{
                $stock_id=$stock_info->id;
                $stock_arr['total_amount']=DB::raw("total_amount+$amount_price");
                $stock_arr['quantity']=DB::raw("quantity+$quantity");
                if ($operation==1)
                {
                    $stock_arr['instock_num'] = DB::raw("instock_num+$quantity");
                    $stock_arr['last_instock_date'] = $now_time;
                }
                elseif($operation==2)
                {
                    $quantity_abs=abs($quantity);
                    $stock_arr['outstock_num'] = DB::raw("outstock_num+$quantity_abs");
                    $stock_arr['last_outstock_date'] = $now_time;
                }else{
                    $quantity_abs=abs($quantity);
                    $stock_arr['sales_num'] = DB::raw("sales_num+$quantity_abs");
                    $stock_arr['last_sales_date'] = $now_time;
                }

                $stock_model->where('hq_code', $hq_code)
                    ->where('orgz_id', $orgz_id)
                    ->where('product_id', $product_id)->update($stock_arr);

                $changed_inventory=$quantity+$stock_info->quantity;
            }

            //增加库存变动记录数据
            $stock_change_record_data=['hq_code'=>$hq_code,'stock_id'=>$stock_id,'orgz_id'=>$orgz_id,
                'product_id'=>$product_id,'genre'=>$genre,'quantity'=>$quantity,'changed_inventory'=>$changed_inventory,
                'related_id'=>$related_id,'created_at'=>$now_time];

            $scr_model->insert($stock_change_record_data);
        }
        return true;
    }
}