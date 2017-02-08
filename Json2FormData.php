<?php
/**
 * Created by PhpStorm.
 * User: T174
 * Date: 2016/12/15
 * Time: 14:16
 */

namespace App\Http\Controllers;

use App\Libraries\MyValidator;
use App\Models\TripContract;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use mPDF;

use App\Libraries\Curl;


class MyValidatorHelp{
    protected $seqData;
    protected $assocData;

    private $isAssoc = true;

    protected $validatorArr;
    protected $errorArr = [];

    protected $rules;
    protected $msg;
    protected $flatDatArr;

    protected $isPassed;


    private function __construct($data, $rules) {
        $this->flatArr($data, '');
        $this->converRules($rules);
        $this->verify();

    }


    private function flatArr($arr, $path) {
        $seqTmp = [];
        foreach ($arr as $k => $v) {
            if (!$this->is_assoc($arr)) {
                //$nextPath = empty($path) ? $k : $path . '#' . $k;

                $this->isAssoc = false;
                $this->flatArr($v, $path);

            } else if (is_array($v)) {
                $this->isAssoc = true;
                $nextPath = empty($path) ? $k : $path . '#' . $k;

                $this->assocData[$nextPath] = "Array";

                $this->flatArr($v, $nextPath);
            } else {
                if ($this->isAssoc) {
                    $this->assocData[$path.'#'.$k] = $v;
                } else {
                    $seqTmp[$path.'#'.$k] = $v;
                }

            }
        }

        if (!empty($seqTmp)) {
            $this->seqData[] = $seqTmp;
        }

    }

    private function is_assoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function converRules($rules) {
        $rule = [];
        $msg = [];

        foreach ($rules as $key => $value) {
            $patterns = [];

            foreach ($value as $k => $v) {
                $patterns[] = $k;
                $name = explode(':', $k);
                $msg[$key . '.' . $name[0]] = $v;
            }

            $rule[$key] = $patterns;
        }

        $this->rules = $rule;
        $this->msg = $msg;
    }
    
    private function getMsg($keys) {
        $arr = [];
        foreach ($keys as $key) {
            foreach ($this->msg as $k => $v) {

                if (strpos($k, $key) !== false) {
                    $arr[$k] = $v;
                }
            }
        }

        return $arr;
    }




    private  function verify()
    {
        // 数字数组
        $seqkeys = [];
        foreach ($this->seqData as $k => $v) {
            $keys = array_keys($v);

            $seqkeys = array_merge($seqkeys, array_flip($keys));

            $seqRules = array_intersect_key($this->rules, array_flip($keys));

            $seqMsg = $this->getMsg($keys);


//            if ($k == 5) {
//                var_dump($v);
//                $val = Validator::make($v, $seqRules, $seqMsg);
//                dd($val->errors());
//            }


            $this->validatorArr[] = Validator::make($v, $seqRules, $seqMsg);
        }


//        dd($seqkeys);
        // 关联数组
//        $assKeys = array_keys($this->assocData);
        $assRules = array_diff_key($this->rules, array_flip(array_keys($seqkeys)));
//        dd($assRules);
        $assMsg = $this->getMsg(array_keys($assRules));
//        dd($assMsg);
        $this->validatorArr[] = Validator::make($this->assocData, $assRules, $assMsg);

        
        foreach ($this->validatorArr as $v) {

            $this->errorArr = array_merge($this->errorArr,  $v->errors()->toArray());
        }

        if (empty($this->errorArr)) {
            $this->isPassed = true;
        } else {
            $this->isPassed = false;
        }
    }


    public function fails() {

        return !$this->isPassed;

    }

    public function errors() {
        return $this->errorArr;
    }

    public static function make(array $data, array $rules, array $messages = array(), array $customAttributes = array()) {

        return new MyValidatorHelp($data, $rules);
    }
}

class Json2FormData {
    protected $formData;

    private function jsonToFormData($json, $path) {

        foreach ($json as $k => $v) {
            if (is_array($v)) {
                $nextPath = empty($path) ? $k : $path . '[' . $k . ']';

                $this->jsonToFormData($v, $nextPath);
            } else {

                $key = empty($path) ? $k : $path.'['.$k.']';
                $this->formData[$key] = $v;
            }
        }
    }

    public function __construct($json)
    {
        $this->jsonToFormData($json, '');
    }

    public function getFormDat() {
        $str = '';
        foreach ($this->formData as $k => $v) {
            $str .= $k . ': ' . $v . "\n";
        }

        return $str;
    }

}


class Pdftest extends Controller
{
    
    
    protected $mpf;

    protected $flatData;


    
    
    public function __construct()
    {
        // A4 210 * 297 milimeter
        $this->mpdf = new mPDF('utf-8');

        $this->mpdf->autoScriptToLang = true;
        $this->mpdf->autoLangToFont = true;
        $this->mpdf->useAdobeCJK = true;

    }




    public function ruleTest() {
        $rules = [
            // 合同验证
            'contract'  => [
                'required' => '合同信息为空',
            ],
            'contract#sn' => [
                'required' => '合同编号为空',
            ],
            'contract#contract_type' => [
                'required' => '合同类型',
                'in:1,2,3,4' => '合同类型为1,2,3,4四种',
            ],
            'contract#start_time' => [
                'required' => '出发时间为空',
                'numeric' => '出发时间[start_time]必填,且需为时间戳'
            ],
            'contract#user_num' => [
                'required' => '合同游客人为空',
                'numeric' => '合同游客人数信息有误为正整数'
            ],
            'contract#price_total' => [
                'required' => "旅游费用合计信息为空",
                'numeric' => '不是整数',
            ],
            'contract#pay_type' => [
                'required' => '支付方式错误'
            ],

            'contract#pay_time' => [
                'required' => '支付时间信息有误'
            ],
            //----------人身意外伤害保险------------
            'contract#insurance' => [
                'required' => '人身意外伤害保险信息有误'
            ],

            'contract#insurance#type' => [
                'required' => '旅游保险方式有误',
                'in:1,2,99' => '1,2,99类型'
            ],

            'contract#insurance#insurance_name' => [
                'required_if:insurance#type,1' => '旅游保险方式有误',
            ],

            //----------end人身意外伤害保险------------

            //-----成团人数与不成团的约定-------

            'contract#least_num_solve' => [
                'required' => '成团人数与不成团的约定信息为空',
            ],

            'contract#least_num_solve#least_num' => [
                'min:1' => '成团最低人数有误',
            ],

            'contract#least_num_solve#other_travel' => [
                'required' => '请选择是否同意委托其他旅行社履行合同',
            ],

            'contract#least_num_solve#other_travel_name' => [
                'required_if:least_num_solve#other_travel,1' => '其他旅行社履行合同旅行社不能为空',
            ],

            'contract#least_num_solve#defer_group' => [
                'required' => '请选择是否同意延期出团',
            ],

            'contract#least_num_solve#change_line' => [
                'required' => '请选择是否同意改变其他线路出团',
            ],
            'contract#least_num_solve#cancel_contract' => [
                'required' => '请选择是否同意解除合同',
            ],
            //-----end----成团人数与不成团的约定-------

            //-------拼团约定--------------
            'contract#medley_pact#medley_group' => [
                'required' => '请选择是否同意与其他旅行社拼团',
            ],
            'contract#medley_pact#medley_travel_name' => [
                'required_if:medley_pact#medley_group,1' => '请选择是否同意与其他旅行社拼团',
            ],
            //------------end---拼团约定--------

            //------争议的解决方式-----------
            'contract#dispude_solve#slove_type' => [
                'required' => '请选择争议的解决方式',
            ],
            'contract#dispude_solve#arbitration_committee' => [
                'required_if:dispude_solve#slove_type,1' => '请输入仲裁委员会名称',
            ],
            //----end--争议的解决方式-------------


            //---合同效力
            'contract#contract_num' => [
                'required' => '合同一式几份必填且为小于10的整数',
                'integer'  => '合同一式几份必填整数',
                'max:10'  => '合同一式几份必填且为小于10的整数',
            ],
            'contract#contract_bothnum' => [
                'required' => '合同双方各持份数',
                'integer'  => '合同双方各持份数必填整数',
                'max:9'  => '合同双方各持份数且为小于9的整数',
            ],
            'contract#user_addr' => [
                'required' => '请输入旅游者代表住址contract[user_addr]',
            ],

            'contract#user_name' => [
                'required' => '请输入旅游者代表名字',
            ],

            'contract#user_tel' => [
                'required' => '旅游者代表联系电话参数错误',
                'regex:/^1[34578]{1}\d{9}$/' => '旅游者代表联系电话参数错误',
            ],

            'contract#user_health' => [
                'required' => '请输入旅游者健康信息contract[user_health]',
            ],
            'contract#org_addr' => [
                'required' => '请输入旅行社营业地址',
            ],

            'contract#org_tel' => [
                'required' => '请输入旅行社联系电话contract[org_tel]',
            ],

            'contract#signaddr' => [
                'required' => '请输入签约地点',
            ],

            'contract#operator' => [
                'required' => '请输入经办人contract[operator]',
            ],
            'contract#group_sn' => [
                'required' => '[group_sn]团号必填',
            ],
            // end 合同验证

            // 验证线路数据
            'line'  => [
                'required' => '线路信息为空',
            ],
            'line#describe'  => [
                'required' => '线路行程描述不能为空',
            ],
            'line#adult_price'  => [
                'required' => '线路成人价格为空',
                'numeric'  => '线路成人价格为数值类型',
            ],
            'line#child_price'  => [
                'required' => '线路儿童价格为空',
                'numeric'  => '线路儿童价格为数值类型',
            ],
            'line#day_count'  => [
                'required' => '线路行程天数有误',
                'integer'  => '线路行程天数有误为整数类型',
            ],
            'line#from_country_name'  => [
                'required' => '线路出发国家不能为空',
            ],
            'line#from_province_name'  => [
                'required' => '线路出发省份不能为空',
            ],
            'line#from_city_name'  => [
                'required' => '线路出发城市不能为空',
            ],
            // end 验证线路数据

            //----线路行程表字段-----
            'line_schedule'  => [
                'required' => '线路行程信息为空',
            ],
            'line_schedule#num'  => [
                'required' => '线路行程进度为空',

            ],
            'line_schedule#stand'  => [
                'required' => '线路行程进度站点为空',
            ],
            'line_schedule#scenery_name'  => [
                'required' => '行程景区名称不能为空',
            ],
            // 境内游专有
            'line_schedule#out_province'  => [
                'required_if:contract#contract_type,1' => '线路行程信息为空',
                'in:1,99'  => '行程是否出省不只能为1或99',
            ],
            // 境外游,台湾游
            'line_schedule#out_expel'  => [
                'required_if:contract#contract_type,2,contract#contract_type,3' => '线路行程信息为空',
                'in:1,99'  => '行程是否出省不只能为1或99',
            ],

            'line_schedule#is_night'  => [
                'required' => '行程是否住宿不能为空',
                'in:1,99'  => '行程是否住宿不只能为1或99',
            ],

            'line_schedule#to_country_name'  => [
                'required' => '线路行程目的国家不能为空',
            ],
            'line_schedule#to_province_name'  => [
                'required' => '线路行程目的省份不能为空',
            ],
            'line_schedule#to_city_name'  => [
                'required' => '线路行程目的城市不能为空',
            ],
            // 境内游专有
            'line_schedule#traffic_type'  => [
                'required_if:contract#contract_type,1' => '线路行程目的城市不能为空',
                'in:1,2,3,4,5,6,99' => '线路行程交通类型不正确只能为1,2,3,4,5,6,99'

            ],

            //----end线路行程表字段-----

            //自愿, 自费
            'line_own#time'  => [
                'required_with:line_own' => '自愿购物具体时间参数错误',
            ],
            'line_own#address'  => [
                'required_with:line_own' => '自愿购物地点参数错误',
            ],
            'line_own#title'  => [
                'required_with:line_own' => '自愿购物购物场所名称参数错误',
            ],
            'line_own#describe'  => [
                'required_with:line_own' => '自愿购物主要商品信息参数错误',
            ],

            'line_own#duration'  => [
                'required_with:line_own' => '自愿购物主最长停留时间（分钟）参数错误',
                'max:999'   => '自愿购物主最长停留时间（分钟）不能大于999分钟'
            ],

            // end 自愿, 自费

            //----游客字段-----
            'users'  => [
                'required' => '游客信息为空',
            ],
            'users#user_sn'  => [
                'required_if:contract#contract_type,2,contract#contract_type,3' => '游客信息为空',
            ],
            'users#name'  => [
                'required' => '游客姓名格式不正确',
            ],
            'users#sex'  => [
                'required' => '游客性别格式不正确',
                'in:1,2' => '游客性别格式不正确',
            ],
            'users#phone'  => [
                'required' => '游客电话为空',
                'regex:/^1[34578]{1}\d{9}$/' => '游客电话参数错误',
            ],
            'users#id_type'  => [
                'required' => '游客证件类型为空',
                'in:1,2,3,4,5,99' => '游客证件类型格式不正确',
            ],
            'users#id_card'  => [
                'required' => '证件号码格式为空',
            ],
            'users#year'  => [
                'required' => '游客出生年份格式为空',
                'integer'  => '游客出生年份格式不正确',
            ],
            'users#month'  => [
                'required' => '游客出生月份格式为空',
                'regex:/^(0?[1-9]|1[12])$/'  =>'游客出生月份格式不正确'

            ],
            'users#day'  => [
                'required' => '游客出生日格式为空',
                'regex:/^(0?[1-9]|1[0-9]|2[0-9]|3[0-1])$/' => '游客出生日格式不正确'
            ],

            //----end 游客字段-----

        ];


//        $arr = $this->converRules($rules);
//        $rule = $arr['rule'];
//        $msg = $arr['msg'];


        $json = '{
       "contract": {
            "sn": "STD-156-TA-117020440080", 
            "group_sn": "GLY-20170207-9657", 
            "contract_type": "1", 
            "start_time": "1486396800", 
            "user_num": "1", 
            "total_adult": "1", 
            "total_child": "0", 
            "guide_price": "0.00", 
            "price_total": "100.00", 
            "pay_type": "银行卡转账", 
            "pay_time": "2017-02-04", 
            "other_pact": "", 
            "contract_num": "2", 
            "contract_bothnum": "1", 
            "user_name": "欣欣第一帅哥", 
            "user_id_card": "350428197903139290", 
            "user_addr": "厦门软件园二期望海路2号", 
            "user_tel": "18065651543", 
            "user_fax": "", 
            "user_postal": "", 
            "user_email": "", 
            "user_health": "加班导致亚健康~！！！", 
            "signaddr": "厦门海沧动物园", 
            "operator": "陈佳佳", 
            "org_tel": "1234567", 
            "org_fax": "0592-1234567", 
            "org_postal": "", 
            "org_email": "fuxz@cncn.com", 
            "org_addr": "洸河路133号", 
            "sup_province_name": "福建", 
            "sup_city_name": "厦门", 
            "sup_dept": "", 
            "sup_postal": "361000", 
            "sup_tel": "0591-55555555", 
            "sup_email": "123456@qq.com", 
            "org_sup_tel": "0591-88888888", 
            "sup_addr": "福建省厦门市思明区", 
            "server_addr": "23", 
            "user_pact": "游客补充约定", 
            "user_rooms": {
                "item_1": "1", 
                "item_2": "2", 
                "item_3": "3", 
                "together_room": [
                    {
                        "name1": "11", 
                        "name2": "22"
                    },
                    {
                        "name1": "22_11", 
                        "name2": "22_22"
                    }
                    
                ]
            }, 
            "insurance": {
                "type": "2", 
                "insurance_name": "213"
            }, 
            "least_num_solve": {
                "least_num": "5", 
                "other_travel": "1", 
                "other_travel_name": "23", 
                "defer_group": "1", 
                "change_line": "1", 
                "cancel_contract": "1"
            }, 
            "medley_pact": {
                "medley_group": "1", 
                "medley_travel_name": "23"
            }, 
            "dispude_solve": {
                "slove_type": "1", 
                "arbitration_committee": "消费者维权"
            }
        }, 
        "line": {
            "title": "测试111", 
            "day_count": "11", 
            "describe": "这个是行程描述", 
            "adult_price": "30.00", 
            "child_price": "0.00", 
            "breakfast_count": "0", 
            "lunch_count": "0", 
            "breakfast_price": "0", 
            "lunch_price": "0", 
            "to_city_name": "福州市,厦门市", 
            "from_country_name": "中国", 
            "from_province_name": "福建", 
            "from_city_name": "福州"
        }, 
        "line_schedule": [
            {
                "num": "1", 
                "stand": "1", 
                "scenery_name": "1111", 
                "shoping": "", 
                "self_pay": "", 
                "hotel_name": "", 
                "traffic_type": "99", 
                "out_province": "99", 
                "out_expel": "99", 
                "is_night": "99", 
                "describe": "dddd", 
                "to_country_name": "美国", 
                "to_province_name": "加州", 
                "to_city_name": "城市"
            },
            {
                "num": "2", 
                "stand": "1", 
                "scenery_name": "2222", 
                "shoping": "", 
                "self_pay": "", 
                "hotel_name": "", 
                "traffic_type": "99", 
                "out_province": "99", 
                "out_expel": "99", 
                "is_night": "99", 
                "describe": "dddd", 
                "to_country_name": "美国2", 
                "to_province_name": "加州2", 
                "to_city_name": "城市2"
            }
        ],
        "line_own":[
            {
                 "time" : "1486454635",
                 "address" : "购物地址",
                 "describe" : "购物描述",
                 "title" : "自愿购物购物场所名称",
                 "duration" : "30"
            }     
        ],
        "users": [
            {
                "is_represents": "1", 
                "name": "欣欣第一帅哥", 
                "sex": "1", 
                "phone": "18065651543", 
                "id_type": "1", 
                "id_card": "350428197903139290", 
                "year": "1999", 
                "month": "12", 
                "day": "23", 
                "address": "厦门软件园二期望海路2号", 
                "nationality": "23", 
                "nation": "23"
            },
            {
                "is_represents": "1", 
                "name": "用户名", 
                "sex": "2", 
                "phone": "18065651543", 
                "id_type": "1", 
                "id_card": "350428197903139290", 
                "year": "1999", 
                "month": "11", 
                "day": "12", 
                "address": "厦门软件园二期望海路2号", 
                "nationality": "23", 
                "nation": "23"
            }
            
        ]
       
     }';


        $json = '			{"access_token":"6f6535857851c8af73dc5e779748ccf3","contract_tpl_no":"STD-156-TA-1","params":{"contract":{"sn":"STD-156-TA-117020826094","group_sn":"XN-20170208-9823","contract_type":"1","start_time":"1486483200","user_num":"2","total_adult":"1","total_child":"1","guide_price":"100.00","price_total":"100.00","pay_type":"\u73b0\u91d1","pay_time":"2017-02-08","other_pact":"","contract_num":"2","contract_bothnum":"1","user_name":"\u738b\u6653\u7d2b","user_id_card":"220503198102151285","user_addr":"\u53a6\u95e8\u7fd4\u5b89","user_tel":"18850223227","user_fax":"","user_postal":"","user_email":"","user_health":"\u826f\u597d","signaddr":"\u8fd9\u662f\u7b7e\u7ea6\u5730\u70b9","operator":"\u9648\u4f73\u4f73","org_tel":"0591-88888888","org_fax":"0592-1234567","org_postal":"","org_email":"393008735@qq.com","org_addr":"\u6d38\u6cb3\u8def133\u53f7","sup_province_name":"\u798f\u5efa","sup_city_name":"\u53a6\u95e8","sup_dept":"","sup_postal":"361000","sup_tel":"0591-55555555","sup_email":"123456@qq.com","org_sup_tel":"0591-88888888","sup_addr":"\u798f\u5efa\u7701\u53a6\u95e8\u5e02\u601d\u660e\u533a","server_addr":"\u6211\u662f\u670d\u52a1\u7f51\u70b9\u540d\u79f0","user_pact":"","user_rooms":{"item_1":"","item_2":"","item_3":"","together_room":[{"name1":"","name2":""}]},"insurance":{"type":"1","insurance_name":"\u6211\u7684\u6d4b\u8bd5\u4fdd\u9669"},"least_num_solve":{"least_num":"10","other_travel":"1","other_travel_name":"\u4e2a\u7535\u996d\u9505","defer_group":"1","change_line":"1","cancel_contract":"1"},"medley_pact":{"medley_group":"1","medley_travel_name":"\u98de\u789f\u8bf4\u53d1\u9001\u5230"},"dispude_solve":{"slove_type":"1","arbitration_committee":"\u6d88\u8d39\u8005\u7ef4\u6743"}},"line":{"title":"\u897f\u85cf\u554a\u897f\u85cf","day_count":"1","describe":"1","adult_price":"1.00","child_price":"100.00","breakfast_count":"0","lunch_count":"0","breakfast_price":"0","lunch_price":"0","to_city_name":"\u897f\u5b81\u5e02","from_country_name":"\u4e2d\u56fd","from_province_name":"\u798f\u5efa","from_city_name":"\u53a6\u95e8"},"line_schedule":[{"num":"1","stand":"1","scenery_name":"","shoping":"","self_pay":"","hotel_name":"","traffic_type":"99","out_province":"99","out_expel":"99","is_night":"99","describe":"1","to_country_name":"","to_province_name":"","to_city_name":""}],"users":[{"is_represents":"1","name":"\u738b\u6653\u7d2b","sex":"2","phone":"18850223227","id_type":"1","id_card":"220503198102151285","year":"0","month":"0","day":"0","address":"\u53a6\u95e8\u7fd4\u5b89","nationality":"\u4e2d\u56fd","nation":"\u6c49"},{"is_represents":"2","name":"\u9ec4\u5927\u7ea2","sex":"1","phone":"18850000000","id_type":"2","id_card":"15022248752","year":"0","month":"0","day":"0","address":"","nationality":"","nation":""}]}}
';

        $data = json_decode($json, true);

        $formData = new Json2FormData($data);

        echo ($formData->getFormDat()); die;

//        dd($data);
//        $this->flatArr($data, '');

//        dd($this->flatData);
        $v = MyValidator::make($data, $rules);

        if ($v->fails()) {
            dd($v->errors());
        } else {
            dd('passed');
        }



    }




    public function getCurrentPage() {
        echo 'page at: ' . count($this->mpdf->pages) . "\n";
        echo 'height: ' .  $this->mpdf->y . "\n";

    }

    public function equalPosition($first, $second) {
        if ($first['page'] == $second['page'] &&
            $first['x'] == $second['x'] &&
            $first['y'] == $second['y'] ) {
            return true;
        } else {
            return false;
        }

    }

    public function filterPosition($positions) {

        if (empty($positions['redundant'])) {
            return $positions['postion'];
        } else {
            foreach ($positions['redundant'] as $more) {

                foreach ($positions['postion'] as $key => $pos ) {
                    if ($this->equalPosition($more, $pos)) {
                        unset($positions['postion'][$key]);
                    }
                }

            }

            return $positions['postion'];
        }

    }


    
    public function tojava() {

        $file = app_path() . '/../public/gh1.pdf';

        //$file = app_path() . '/../public/mpdf.pdf';

        $keywords = [
            'tourist' => [
                '旅游者代表签字（盖章）：',
                '旅游者确认签名（盖章）：',
                '旅游者：(代表人签字)',
                '签名：',

            ],
            'org' => [
                '签约代表签字（盖章）：',
                '经办人：（签字）',
            ]
        ];

        $redundant = '经办人签名：';
        // dd(base64_encode($redundant));

        $url = config('web.position_server');
        var_dump($url);
        $finder = new \App\Libraries\Positions($url);


        $result = $finder->post($file, $keywords, $redundant);

//        dd($result);
        return $result;
    }

    public function index() {

//        dd(md5('123456'));
        // 28境外 , 26境内, 20台湾
        $contract = TripContract::find(28);



        $line = $contract->snapLine;
        $orgInfo = $contract->org;

        $apply = $contract->apply;
        $group = $apply->group;

        if (empty($line)) {
            var_dump($contract->CONTRACT_ID);
        }

        $count = $line->overNight();


        $startAt = $group->GROUP_DOTIME;
        $endAt = $startAt + ($line->LINK_DAYCOUNT - 1) * (24 * 3600);
        $solve = json_decode($contract->CONTRACT_LOWPERSON_SOLVE, true);
        $pack  = json_decode($contract->CONTRACT_GROUP_PACT, true);
        $otherSolve  = json_decode($contract->CONTRACT_SOLVE, true);
        $userRoom = json_decode($contract->USER_ROOMS, true);
        $otherPact = $contract->CONTRACT_PACT;
        $userPact = $contract->USER_PACT;
        $stations = $line->stations();
        $tourist = $apply->tourist();

        $selfpay = $line->selfpay();
        $shopping = $line->shopping();

//        dd($otherPact);

        // dd($otherSolve);
        $data = compact('startAt', 'endAt', 'orgInfo', 'count', 'solve', 'pack', 'otherSolve', 'otherPact',
            'group', 'userRoom', 'tourist', 'stations', 'shopping', 'selfpay', 'userPact');

        
        // 封面
        $cover = view('pdf.contractMcover', compact('contract'));
        // return $cover;

        $html = '';
        if ($contract->CONTRACT_TYPE == TripContract::NATIVE) {
            $html = view('pdf.contractM1_1', compact('contract', 'line', 'data'));
        } elseif ($contract->CONTRACT_TYPE == TripContract::EXPEL) {
            $html = view('pdf.contractM2_2', compact('contract', 'line', 'data'));
        } elseif ($contract->CONTRACT_TYPE == TripContract::TAIWAN) {
            $html = view('pdf.contractM3_3', compact('contract', 'line', 'data'));
        } else {
            Log::write('合同状态异常: 合同编号' . $contract->CONTRACT_ID );
        }


//        dd(public_path() . '/logo_ht.png');



//        return $html;
//
        $this->mpdf->SetWatermarkImage(public_path() . '/logo_ht.png', 1, '', array(172, 275));
        $this->mpdf->showWatermarkImage = true;
        $this->mpdf->WriteHTML($cover);
        $this->mpdf->AddPage();


        $this->mpdf->WriteHTML($html);


        $this->mpdf->Output();

    }


    public function des() {
        $str = request()->input('sign');

        $key = '5bfb3240';

 

        return $str;
    }


    function encrypt($str, $key) {
        $block = mcrypt_get_block_size('des', 'ecb');
        $pad = $block - (strlen($str) % $block);
        $str .= str_repeat(chr($pad), $pad);
        return mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
    }

    function decrypt($str, $key) {
        $str = mcrypt_decrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
        $len = strlen($str);
        $block = mcrypt_get_block_size('des', 'ecb');
        $pad = ord($str[$len - 1]);
        return substr($str, 0, $len - $pad);
    }


    function converRules($rules) {
        $rule = [];
        $msg = [];

        foreach ($rules as $key => $value) {
            $patterns = [];

            foreach ($value as $k => $v) {
                $patterns[] = $k;
                $name = explode(':', $k);
                $msg[$key . '.' . $name[0]] = $v;
            }

            $rule[$key] = $patterns;
        }

        return compact('rule', 'msg');
    }







}