<?php
/**
 * Created by PhpStorm.
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 上午9:38
 */
namespace SuNong\StockControl;

use App\Models\StockChangeRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SuNong\StockControl\Model\Stock;
use SuNong\StockControl\Model\StockBatch;
use SuNong\StockControl\Model\StockBatchContent;
use SuNong\StockControl\Model\StockBatchFlow;

class core extends Model{

    protected $_instock='instock';

    protected $_instock_content='instock_content';

    protected $_outstock='outstock';

    protected $_outstock_content='outstock_content';

    protected $_stock='stock';

    protected $_stock_batch='stock_batch';

    protected $_stock_batch_content='stock_batch_content';

    protected $_stock_batch_flow='stock_batch_flow';

    protected $_stock_change_record='stock_change_record';

    /**
     * Config constructor.
     * @param array $table
     */
    public function __construct(){
        parent::__construct();
        $table=config('stock_control.table',[]);
        foreach ($table as $table=>$name){
            $table='_'.$table;
            $this->$table=$name;
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @param string $hq_code 企业编码
     * @param string $table 表名
     * @param string $prefix 前缀 例如：GJ改价 CK出库
     * @param int $length 流水号位数
     * @return string 公共单据生成方法 传入公司编号 表明 前缀即可获取新单据编号
     */
    public function order_create_code($hq_code,$table,$prefix,$length=4)
    {
        $data = DB::table($table)->select('code')->where('hq_code', $hq_code)->orderBy('id', 'desc')->first();
        if(empty($data)) return $prefix.substr(date('Ymd'),2,6).'0001';
        $code=$data->code;
        $code_date=substr($code,2,6);
        $code_num=(int)substr($code,8,4)+1;
        $now_date=substr(date('Ymd'),2,6);


        if($now_date!=$code_date){
            $code_num='0001';
        }else{
            $code_num = str_pad($code_num, $length, "0", STR_PAD_LEFT);
        }

        return $prefix.$now_date.$code_num;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @param string $hq_code 企业编号
     * @param integer $orgz_id 组织id
     * @param integer $related_id 相关单据id
     * @param integer $genre 类型
     * @param array $product_info 数据示例
     * 商品信息 [ ['product_id'=>1,'quantity'=>5,'package'=>2,'supplier_id'=>null] ]
     * @param bool $update_stock_price 是否更新库存价格
     * @return array ['stock_batch_id'=>55,'detail'=>$detail]
     */
    protected function add_stock_batch($hq_code,$orgz_id,$related_id,$genre,$product_info,$update_stock_price=false){
        $stock_batch_model=new StockBatch();

        $result = [];
        $details = [];
        $now_time=date('Y-m-d H:i:s');
        $base = [
            'hq_code' => $hq_code,
            'orgz_id' => $orgz_id,
            'related_id' => $related_id,
            'genre' => $genre,
            'code' => $this->order_create_code($hq_code,$this->_stock_batch,'PC',4),
            'created_at' => $now_time
        ];
        $id = $stock_batch_model->insertGetId($base);

        $result['stock_batch_id'] = $id;
        $stock = new Stock();
        $sbc_model = new StockBatchContent();
        $sbf_model = new StockBatchFlow();
        foreach ($product_info as $item)
        {
            // 构建批次明细数据
            $detail['stock_batch_id']=$id;
            $detail['product_id']=is_numeric($item['product_id'])?$item['product_id']:null;
            $detail['quantity']=is_numeric($item['quantity'])?$item['quantity']:null;
            $detail['package']=is_numeric($item['package'])?$item['package']:null;
            $detail['price']=is_numeric($item['price'])?$item['price']:null;
            $detail['supplier_id']=is_numeric($item['supplier_id'])?$item['supplier_id']:null;

            if(!$detail['product_id'] || !$detail['quantity'] || !$detail['package'] || !$detail['price']) continue;

            $detail['total_amount'] = $detail['price'] * $detail['quantity'];
            $detail['inventory'] = $item['quantity'];
            $detail['instock_num'] = $item['quantity'];
            $detail['created_at'] = $now_time;

            // 查询批次负库存，若存在则填补
            $left_quantity=$stock_batch_model->backfill_negative_inventory($hq_code,$orgz_id,$detail['product_id'],$detail['quantity']);
            $detail['quantity']=$left_quantity;

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
                'related_id' => $related_id
            );
            $sbf_model->install($flow);
        }

        $result['details'] = $details;
        return $result;
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
        foreach ($product_info as $product){
            $product_id=$product['product_id'];
            $quantity=$product['quantity'];
            $price=$product['price'];
            $amount_price=$price*$quantity;
            $stock_info=$stock_model->product_isexist_by_orgz_product($hq_code,$orgz_id,$product_id);
            $is_instock=$quantity>0?true:false;
            $changed_inventory=$quantity+$stock_info->inventory;
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
                }elseif($operation==2){
                    $stock_arr['outstock_num']=$quantity;
                }else{
                    $stock_arr['sales_num']=$quantity;
                }
                $stock_id=$this->insertGetId($stock_arr);
            }else{
                $stock_id=$stock_info->id;
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
                    ->where('product_id', $product_id)
                    ->update($stock_arr);
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