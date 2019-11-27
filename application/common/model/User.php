<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 18:28
 */

namespace app\common\model;


use think\Model;
use think\Loader;

class User extends Model
{

  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)->order('create_time desc')
        ->paginate(15, false, ['query' => $query]);
    return $list;
  }
	
	public function getInactiveList(array $array,$where = [], $query = [])
	{
		$list = $this->whereIn ('id',$array)->where('if_cash','=',2)->where($where)->order('create_time desc')
		->paginate(15, false, ['query' => $query]);
		return $list;
	}
  public function getStateAttr($value)
  {
    $model = ['2' => '未激活', '1' => '已激活',];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getLevelAttr($value)
  {
    $model = ['0' => '无', '1' => 'C1', '2' => 'C2', '3' => 'C3',
        '4' => 'C4', '5' => 'C5', '6' => 'C6'];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getQzLevelAttr($value)
  {
    $model = ['0' => '无', '1' => 'C1', '2' => 'C2', '3' => 'C3',
        '4' => 'C4', '5' => 'C5', '6' => 'C6'];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function account()
  {
    return $this->hasOne('Account', 'uid', 'id');
  }

  public function getIfCashAttr($value)
  {
    $model = ['1' => '允许', '2' => '不允许',];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getIsLockAttr($value)
  {
    $model = ['1' => '已冻结', '2' => '未冻结'];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getIdentAttr($value)
  {
    $model = ['1' => '普通', '2' => '顶级', '3' => '平台'];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getPicnameAttr($value)
  {
    return "http://" . $_SERVER['HTTP_HOST'] . $this->data['picpath'] . '/' . $value;
  }

  // 判断支付密码
  public function jugetPayPassword($uid, $payPass)
  {
    $payPassword = $this->where('id', $uid)->value('paypassword');

    if ($payPassword == encrypt($payPass)) {
      return true;
    } else {
      return false;
    }
  }

  // 团队体系
  public function team($where = '')
  {
    $list = $this->alias('a')
        ->field('a.id, a.pid, a.phone,a.nickname , if(b.id,\'0\',\'1\') is_parent')
        ->join('user b', 'a.id = b.pid', 'left')
        ->where($where)
        ->group('a.id')
        ->select();
    return $list;
  }


  public function setPassAddressAttr()
  {
    do {
      $pass='0x'.getRandCode(40);
    } while (User::get(['pass_address' => $pass]));
    Loader::import('ewmcode.Qrcode');
    $QRcode = new \QRcode();
    $pic = "./uploads/pass/" . $this->id. ".png";//二维码图片路径
    $value =  $pass;//二维码内容
    $errorCorrectionLevel = 'L';//容错级别
    $matrixPointSize = 9;//生成图片大小
    //生成二维码图片
    $QRcode->png($value, $pic, $errorCorrectionLevel, $matrixPointSize, 2);
    return $pass;
  }

  public function getTidAttr($value,$data)
  {
    $user=User::get($value);
    return ['value'=>$value,'msg'=>$user['nickname'],'name'=>$user['real_name']];
  }

  public function getPidAttr($value,$data)
  {
    $user=User::get($value);
    return ['value'=>$value,'msg'=>$user['nickname'],'name'=>$user['real_name']];
  }

}