<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 16:43
 */

namespace app\common\model;


use think\Model;

class OrderSh extends Model
{
    function orderInfo()
    {
      return  $this->hasOne('order', 'id', 'oid');
    }

    public function getStateAttr($value)
    {
        $status = ['1' => '已完成', '2' => '已拒绝', '3' => '等待审核'];
        return ['value' => $value, 'msg' => $status[$value]];
    }

    public  function getList($where = '', $query = [])
    {
        $list = $this
            ->where($where)->orderRaw('FIELD(state,3,1,2),id asc')->paginate(20, false, ['query' => $query]);
        return $list;
    }

}