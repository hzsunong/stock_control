<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 上午9:38
 */
namespace Sunong\StockControl;

use App\Models\StockChangeRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Core extends Model{

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
        $tables=config('stock_control.table',[]);
        foreach ($tables as $table=>$name){
            $table='_'.$table;
            $this->$table=$name;
        }
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-11-22
     * @param string $hq_code
     * @param string $table 表名
     * @param string $prefix 前缀 例如：GJ改价 CK出库
     * @param int $length 流水号位数
     * @return string 公共单据生成方法 传入公司编号 表明 前缀即可获取新单据编号
     */
    static public function order_create_code($hq_code,$table,$prefix,$length=4)
    {
        $prefixLength=strlen($prefix);
        $data = DB::table($table)->select('code')->where('hq_code', $hq_code)->orderBy('code', 'desc')->first();
        if(empty($data)){
            $code_num=1;
            $now_date=substr(date('Ymd'),2,6);
        }else{
            $code=$data->code;
            $code_date=substr($code,$prefixLength,6);
            $now_date=substr(date('Ymd'),2,6);
            if($now_date!=$code_date){
                $code_num=1;
            }else{
                $code_num=(int)substr($code,$prefixLength+6,$length)+1;
            }

        }
        $code_num = str_pad($code_num, $length, "0", STR_PAD_LEFT);
        return $prefix.$now_date.$code_num;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-30
     * @param array $orWhere 查询条件
     * @return array|null 返回查询数据
     */
    protected function resolver_orWhere($orWhere){
        if(!is_array($orWhere)  && empty($orWhere)) return null;
        $filed=null;
        $operate='=';
        $value=null;
        $count=count($orWhere);
        switch ($count){
            case 2:
                $filed=reset($orWhere);
                $value=next($orWhere);
                break;
            case 3:
                $filed=reset($orWhere);
                $operate=next($orWhere);
                $value=next($orWhere);
                break;
            default:
                return null;
        }
        return [(string)$filed,(string)$operate,$value];
    }

}