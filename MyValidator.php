<?php
/**
 * Created by cncn.com
 * User: lyhux
 * Date: 2017/2/7
 * Time: 16:20
 * 用于对正个多维数组进行验证
 * 多级关联数据用#分隔两个关键字
 * 用法:
 *  $rules = [
 *    'key1#key2' => [
 *       'required' => '这里是提示消息',
 *       'regex:/\d+/' => '这里是提示信息'
 *    ],
 *    'key11#key22#name1' => [
 *
 *    ]
 * ];
 *
 * $data = [
 *   'key1' => [   // 联系数组数据
 *      'key2' => '这里是验证数据',
 *
 *   ],
 *  'key11' => [
 *      'key22' => [  // 索引数组数组
 *          ['name1' => 'name value'],
 *          ['name2' => 'name vaule']
 *       ]
 *   ],
 * ];
 *
 * $validator = MyValidator::make($data, $rules);
 *
 * if ($validator->fails()) {
 *   dd($validator->errors());
 * } else {
 *   echo 'passed';
 * }
 */

namespace App\Libraries;

use Illuminate\Support\Facades\Validator;

class MyValidator{
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

    /**
     * 递归遍历待验证数据将其重新格式化(分离出关联数组和索引数组<多条相同结构的数据>)
     * 1) 生成中间验证数组
     * 2) 生成验证规则
     * 3) 生成验证规则对应的提示信息
     *
     * @param $arr 多维数组
     * @param $path
     */
    private function flatArr($arr, $path) {
        $seqTmp = [];
        foreach ($arr as $k => $v) {
            // 索引数组
            if (!$this->is_assoc($arr)) {
                //$nextPath = empty($path) ? $k : $path . '#' . $k;

                $this->isAssoc = false;
                $this->flatArr($v, $path);

            } else if (is_array($v)) {
                // 关联数组
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

    /**
     * 判断是否是关联数组
     * @param array $arr
     * @return bool
     */
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

    /**
     * 通过键值提取提示信息
     * @param $keys
     * @return array
     */
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


    /**
     * 验证所有规则
     */
    private  function verify()
    {
        // 索引数组
        $seqkeys = [];
        foreach ($this->seqData as $k => $v) {
            $keys = array_keys($v);

            $seqkeys = array_merge($seqkeys, array_flip($keys));

            $seqRules = array_intersect_key($this->rules, array_flip($keys));

            $seqMsg = $this->getMsg($keys);

            $this->validatorArr[] = Validator::make($v, $seqRules, $seqMsg);
        }

        // 关联数组
        // 从所有验证规则中剔除索引数组(多条相同结构的数据)中
        $assRules = array_diff_key($this->rules, array_flip(array_keys($seqkeys)));
        $assMsg = $this->getMsg(array_keys($assRules));
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

    /**
     * 判断是否通过验证
     */
    public function fails() {

        return !$this->isPassed;

    }

    /**
     * 获取所有验证错误信息
     * @return array
     */
    public function errors() {
        return array_values($this->errorArr);
    }

    /**
     * 静态方法用于生成验证类, 此类不能直接进行实例化
     * @param array $data
     * @param array $rules
     * @return MyValidator
     */
    public static function make(array $data, array $rules) {

        return new MyValidator($data, $rules);
    }
}