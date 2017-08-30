<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use SuNong\StockControl\Core;

class Instock extends Core{
    protected $table;

    public function __construct(){
        parent::__construct();
        $this->table=$this->_instock;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @return string new code
     */
    public function create_code($hq_code){
        return $this->order_create_code($hq_code,$this->table,'RK');
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $instock_id
     * @return null|array
     */
    public function get_unconfirmed_list_by_instock_id($hq_code,$orgz_id,$instock_id){
        $data=$this->select('instock_content.product_id','instock_content.spec_unit','instock_content.spec_num',
            'instock_content.price','instock_content.amount','instock_content.quantity','instock_content.package')
            ->join('instock_content','instock_content.instock_id','=','instock.id')
            ->where('instock.id',$instock_id)->where('instock.hq_code',$hq_code)->where('instock.orgz_id',$orgz_id)
            ->where('instock.confirmed',0)->where('instock.status',1)->where('instock_content.status',1)->get();
        if(empty($data)) return null;
        return $data->toArray();
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param integer $instock_id 入库单id
     * @return mixed 获取入库单类型
     */
    public function get_genre_by_instock_id($instock_id){
        $data=$this->select('genre')->where('id',$instock_id)->first();
        return $data;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param array $date_range 时间范围
     * @param integer $limit
     * @param integer $offset
     * @param null|integer $genre
     * @param null|integer $confirmed
     * @param null|array $or_where
     * @return null|array 获取入库单列表
     */
    public function get_instock_list($hq_code,$orgz_id,$date_range,$limit=20,$offset=0,$genre=null,$confirmed=null,$or_where=null){
        $start_time=reset($date_range);
        $end_time=next($date_range);
        $data=$this->where('hq_code',$hq_code)->where('orgz_id',$orgz_id)->whereBetween('instock_date',[$start_time,$end_time]);
        if($genre!=null) $data->where('genre',$genre);
        if($confirmed!=null) $data->where('confirmed',$confirmed);
        if(is_array($or_where) && !empty($or_where)){
            $data->where(function ($query) use($or_where){
                foreach ($or_where as $item){
                    $orWhere=$this->resolver_orWhere($item);
                    if($orWhere==null) continue;
                    $filed=reset($orWhere);
                    $operate=next($orWhere);
                    $value=next($orWhere);
                    switch ($operate){
                        case 'in':
                            $query->orWhereIn($filed,$value);
                            break;
                        case 'between':
                            $query->orWhereBetween($filed,$value);
                            break;
                        case 'not_in':
                            $query->orWhereNotIn($filed,$value);
                            break;
                        default:
                            $query->orWhere($filed,$operate,$value);
                    }
                }
            });
        }
        $result['total']=$data->count();
        if($result['total']==0) return null;
        $result['data']=$data->orderBy('id','desc')->limit($limit)->offset($offset)->get()->toArray();
        return $result;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $instock_id
     * @return mixed 获取入库单信息
     */
    public function get_instock_by_id($hq_code,$orgz_id,$instock_id){
        $data=$this->where('id',$instock_id)->where('hq_code',$hq_code)->where('orgz_id',$orgz_id)->first();
        return $data;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id 组织id
     * @param array $product_ids 商品id列表
     * @return null|array 通过商品id列表获取入库单id列表
     */
    public function get_instock_id_by_product_ids($hq_code,$orgz_id,$product_ids){
        $data=$this->select('instock_content.instock_id')
            ->join('instock_content','instock_cotnent.instock_id','=','instock.id')
            ->where('instock.hq_code',$hq_code)->where('instock.orgz_id',$orgz_id)->where('instock.status',1)
            ->whereIn('instock_content.product_id',$product_ids)->where('instock_content.status',1)->get();
        if($data==null) return null;
        return $data->pluck('instock_id')->toArray();
    }

}