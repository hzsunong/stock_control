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

}