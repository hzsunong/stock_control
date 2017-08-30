<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/22
 * Time: 下午12:59
 */
namespace SuNong\StockControl\Model;

use SuNong\StockControl\Core;

class Outstock extends Core{
    protected $table;

    public function __construct(){
        parent::__construct();
        $this->table=$this->_outstock;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @return string new code
     */
    public function create_code($hq_code){
        return $this->order_create_code($hq_code,$this->table,'CK');
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-24
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $outstock_id
     * @return null|array 获取未审核出库单详情列表
     */
    public function get_unconfirmed_list_by_outstock_id($hq_code,$orgz_id,$outstock_id){
        $data=$this->select('outstock_content.id as content_id','outstock_content.remark',
            'outstock_content.product_id','outstock_content.spec_unit','outstock_content.spec_num',
            'outstock_content.price','outstock_content.amount','outstock_content.quantity','outstock_content.package')
            ->join('outstock_content','outstock_content.outstock_id','=','outstock.id')
            ->where('outstock.id',$outstock_id)->where('outstock.hq_code',$hq_code)->where('outstock.orgz_id',$orgz_id)
            ->where('outstock.confirmed',0)->where('outstock.status',1)->where('outstock_content.status',1)->get();
        if(empty($data)) return null;
        return $data->toArray();
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param integer $outstock_id 出库单id
     * @return mixed 获取出库单类型
     */
    public function get_genre_by_outstock_id($outstock_id){
        $data=$this->select('genre')->where('id',$outstock_id)->first();
        return $data;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $limit
     * @param integer $offset
     * @param null|integer $genre 类型
     * @param null|integer $confirmed 审核状态
     * @param null|array $or_where orWhere条件
     * @return null|array 获取出库单列表
     */
    public function get_outstock_list($hq_code,$orgz_id,$limit=20,$offset=0,$genre=null,$confirmed=null,$or_where=null){
        $data=$this->where('hq_code',$hq_code)->where('orgz_id',$orgz_id);
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
                    $query->orWhere($filed,$operate,$value);
                }
            });
        }
        $result['total']=$data->count();
        if($result['total']==0) return null;
        $result['data']=$data->limit($limit)->offset($offset)->get()->toArray();
        return $result;
    }

    /**
     * @author Javen <w@juyii.com>
     * @date 2017-08-23
     * @param string $hq_code
     * @param integer $orgz_id
     * @param integer $outstock_id
     * @return mixed 获取出库单信息
     */
    public function get_outstock_by_id($hq_code,$orgz_id,$outstock_id){
        $data=$this->where('id',$outstock_id)->where('hq_code',$hq_code)->where('orgz_id',$orgz_id)->first();
        return $data;
    }

}