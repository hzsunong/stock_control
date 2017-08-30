<?php
/**
 * User: hayashikoubun
 * Date: 2017/8/23
 * Time: 下午3:56
 */
namespace SuNong\StockControl\Func;

use Illuminate\Support\Facades\Log;

class CommonFunc{
    /**
     * 20170213
     * @author 过佳峰 <guojiafeng@comteck.cn>
     * @return $micro_time  返回当前时间毫秒戳
     */
    protected function get_micro_time()
    {
        list($s1, $s2) = explode(' ', microtime());
        $micro_time = (float)sprintf('%.0f', (floatval($s2) + floatval($s1)) * 1000);
        return $micro_time;
    }

    /**
     * @author Javen <w@juyii.com>
     * @param string $type 'info'|'warning'|'error'
     * @param integer $user_id 用户id
     * @param string $msg 提示信息
     * @param null|mixed $request 传入$request 将所有参数代入日志
     * 为了便于日志记录统一管理
     */
    protected function log_record($type,$key,$msg,$request=null){
        $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1];
        $class = $debug['class'];
        $function = $debug['function'];
        $line=isset($debug['line'])?$debug['line']:null;
        if($type!='info' && $type!='warning' && $type!='error'){
            Log::error("class:$class -- function:$function -- line:$line -- 日志类型有误:不支持$type");
        }

        //客户ip
        $ip = null;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], 'unknown')) {
            // 使用透明代理、欺骗性代理的情况
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            // 没有代理、使用普通匿名代理和高匿代理的情况
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // 处理多层代理的情况
        if (strpos($ip, ',') !== false) {
            // 输出第一个IP
            $ip=explode(',', $ip);
            $ip = reset($ip);

        }

        $_SERVER['HTTP_REFERER']=$_SERVER['HTTP_REFERER']??null;

        $msg=$ip.' -- '. $_SERVER['REQUEST_METHOD'] .' '.$_SERVER['REQUEST_URI'].' -- '.
            $_SERVER['HTTP_USER_AGENT'].' -- '. $_SERVER['REDIRECT_STATUS']. ' -- '.$_SERVER['HTTP_REFERER'].' -- '.
            "line:$line".' -- '. "key:$key" .' -- stock_control:' .$msg;
        if($request!==null){

            switch (gettype($request)){
                case 'object':
                    if($request instanceof Request) $request=$request->input();
                    $request=json_encode($request);break;
                case 'array':
                    if($request instanceof Arrayable) $request=$request->toArray();
                    $request=json_encode($request);break;
                case 'string':
                    continue;
            }
            $msg.=" -- 参数:$request";
        }
        Log::$type($msg);
    }

    /**
     * @author Javen <w@juyii.com>
     * @param mixed $value 检查变量
     * @param bool $zeroIsEmpty 数字'0'是否为空
     * @return bool 检查变量是否为空 如果变量
     */
    protected function check_empty($value,$zeroIsEmpty=false) {
        if (!isset($value)) return true;
        if ($value === null) return true;
        $type=gettype($value);
        if($type=='string' && !is_numeric($value) && trim($value) === ""){
            return true;
        }elseif(($type=='array' || $type=='object')){
            if(count($value)==1 && reset($value)==='' || empty($type)) return true;
            return false;
        }elseif($zeroIsEmpty && is_numeric($type) && $type===0){
            return true;
        }

        return false;
    }
}
