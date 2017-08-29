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
     * @param integer $limit
     * @param integer $offset
     * @param null|integer $genre
     * @param null|integer $confirmed
     * @return null|array 获取入库单列表
     */
    public function get_instock_list($hq_code,$orgz_id,$limit=20,$offset=0,$genre=null,$confirmed=null){
        $data=$this->where('hq_code',$hq_code)->where('orgz_id',$orgz_id);
        if($genre!=null) $data->where('genre',$genre);
        if($confirmed!=null) $data->where('confirmed',$confirmed);
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

}