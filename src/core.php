<?php
/**
 * Created by PhpStorm.
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 上午9:38
 */
namespace SuNong\StockControl;

class core{

    private $_instock='instock';

    private $_instock_content='instock_content';

    private $_outstock='outstock';

    private $_outstock_content='outstock_content';

    private $_stock='stock';

    private $_stock_batch='stock_batch';

    private $_stock_batch_content='stock_batch_content';

    private $_stock_batch_flow='stock_batch_flow';

    private $_stock_change_record='stock_change_record';

    /**
     * Config constructor.
     * @param array $table
     */
    public function __construct($table=[]){
        $table=config('stock_control.table',[]);
        foreach ($table as $table=>$name){
            $table='_'.$table;
            $this->$table=$name;
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回入库单表名
     */
    private function get_instock_table_name(){
        return $this->_instock;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回入库单明细表表名
     */
    private function get_instock_content_table_name(){
        return $this->_instock_content;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回出库单表名
     */
    private function get_outstock_table_name(){
        return $this->_outstock;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回出库单明细表表名
     */
    private function get_outstock_content_table_name(){
        return $this->_outstock_content;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回库存表名
     */
    private function get_stock_table_name(){
        return $this->_stock;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回库存批次表名
     */
    private function get_stock_batch_table_name(){
        return $this->_stock_batch;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回库存批次明细表名
     */
    private function get_stock_batch_content_table_name(){
        return $this->_stock_batch_content;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回库存批次流水表表名
     */
    private function get_stock_batch_flow_table_name(){
        return $this->_stock_batch_flow;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-22
     * @return string 返回库存批次变动记录表名
     */
    private function get_stock_change_record_table_name(){
        return $this->_stock_change_record;
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

    protected function add_stock_batch($hq_code,$orgz_id,$related_id,$genre,$product_info){
        $result = [];
        $details = [];
        $base = [
            'hq_code' => $hq_code,
            'orgz_id' => $orgz_id,
            'related_id' => $related_id,
            'genre' => $genre,
            'code' => $this->order_create_code($hq_code,$this->get_stock_batch_table_name(),'PC',4),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $id = $this->insertGetId($base);

        $result['stock_batch_id'] = $id;
        $stock = new Stock;
        $om = new StockBatchContent;
        $fw = new StockBatchFlow;
        foreach ($product_info as $item)
        {
            $keys = ['price', 'spec_num', 'spec_unit'];
            $full = true;
            foreach ($keys as $key)
            {
                if(!array_key_exists($key,$item)){
                    $full=false;
                    break;
                }
            }

            if (!$full) continue;

            $detail = ['stock_batch_id' => $id, 'product_id' => $item['product_id'], 'quantity' => $item['quantity'],
                'supplier_id' => isset($item['supplier_id']) && is_numeric($item['supplier_id'])?$item['supplier_id']:null];

            if (isset($item['package']) and $item['package'])
            {
                $detail['package'] = $item['package'];
            }
            else
            {
                if (!is_numeric($detail['spec_num']) or $detail['spec_num'] == 0)
                {
                    $detail['package'] = 0;
                }
                else
                {
                    $detail['package'] = round($detail['quantity']*1.0/$detail['spec_num'], 3);
                }
            }
            $detail['total_amount'] = $detail['price'] * $detail['quantity'];
            $detail['inventory'] = $item['quantity'];
            $detail['instock_num'] = $item['quantity'];
            $detail['created_at'] = date('Y-m-d H:i:s');

            $detail_id = $om->insertGetId($detail);
            $detail['stock_batch_content_id']=$detail_id;

            $detail['id'] = $detail_id;
            array_push($details, $detail);


            // 更新库存表商品最新批次单价
            if ($genre == 0 && $detail['spec_num'] != 0)
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
                'price' => $detail['price'],
                'spec_num' => $detail['spec_num'],
                'spec_unit' => $detail['spec_unit'],
                'quantity' => $detail['quantity'],
                'package' => $detail['package'],
                'total_amount' => $detail['total_amount'],
                'source' => $genre,
                'ref_id' => $related_id
            );
            $fw->add_stock_batch_flow($flow);
            // 查询批次负库存，若存在则填补
            if ($item['quantity'] > 0)
            {
                $left_quantity = $this->backfill_negative_inventory($hq_code, $orgz_id, $detail['product_id'], $item['quantity']);
                if ($left_quantity < $item['quantity'])
                {
                    $om->where('id', $detail_id)->update(['inventory'=>$left_quantity]);
                }
            }
        }

        $result['details'] = $details;
        return $result;
    }
}