<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:08
 */

namespace app\common\serviceimpl;


use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\OrderDemand;
use app\common\model\System_config;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\server\UserService;
use think\Db;
use think\Exception;
use think\Loader;

class UserServiceImpl implements UserService
{
  private $model;

  public function __construct()
  {
    $this->model = new User();
  }

  function getList($where, $query)
  {
    return $this->model->getList($where, $query);
  }
	
	function getInactiveList($arr,$where, $query)
	{
		return $this->model->getInactiveList($arr,$where, $query);
	}
  function update_info()
  {
    // TODO: Implement update_info() method.
  }

  function update_password()
  {
    // TODO: Implement update_password() method.
  }

  function update_paypassword()
  {
    // TODO: Implement update_paypassword() method.
  }

  function log()
  {
    // TODO: Implement log() method.
  }

  function account_log()
  {
    // TODO: Implement account_log() method.
  }

  function account_edit()
  {
    // TODO: Implement account_edit() method.
  }

  function team_list()
  {
    // TODO: Implement team_list() method.
  }

  function user_add($post = [])
  {
    Db::startTrans();
    try {
      $post['password'] = encrypt($post['password']);
      $post['paypassword'] = encrypt($post['paypassword']);
      $int = \app\common\model\User::create($post);
      if ($int['ident']['value'] != 1) {
        $this->qc_codeAndInvite($int);
        if($int->getData('ident')==3){
          $int->save(['is_order_first'=>0]);
        }
      }
      $int = Account::create(['uid' => $int['id']]);
      UserAccount::create(['uid' => $int['uid']]);
      Db::commit();
    } catch (Exception $e) {
      Db::rollback();
      throw new ServerException($e->getMessage());
    }
    if ($int) {
      return returnJson(1, '新增成功');
    }
    return returnJson(0, '新增失败');
  }


  function qc_codeAndInvite(User $user, $level = 1,$admin=0)
  {
  	
    //检查提现开关是否开启
    if(!$admin){
      $config = System_config::get(['name' => 'action_flag'])['value'];
      if ($config) {
        $config = System_config::get(['name' => 'action_sy']);
        if(!$config['value']){
          throw new ServerException('今日激活已达上限');
        }
        $config->setDec('value',1);
      }
    }

    //生成invite
    do {
      $invite = mt_rand(1000000, 9999999);
    } while (User::get(['invite' => $invite]));
    $user->save(['invite' => $invite, 'level' => $level, 'state' => 1]);

    //生成二维码的图
    $this->create_invite($user->getData('id'), $invite,$user);
    
    $pass = $user->setPassAddressAttr();
    $user->save(['pass_address'=>$pass]);
  }

  //生成邀请二维码
  function create_invite($id, $invite,$user)
  {
    Loader::import('ewmcode.Qrcode');
    $QRcode = new \QRcode();
    $pic = "/www/wwwroot/ctkBak/public/uploads/invite/" . $id . "emc.png";//二维码图片路径
   
    $invitepic = "/www/wwwroot/ctkBak/public/uploads/invite/invite.png"; //背景图
   // halt(12);
    $value = 'http://ctkbak.whatsfav.com/index/invite/register?invite=' . $invite;//二维码内容
    
    $errorCorrectionLevel = 'L';//容错级别
    $matrixPointSize = 9;//生成图片大小
    //生成二维码图片
    $QRcode->png($value, $pic, $errorCorrectionLevel, $matrixPointSize, 2);
    // file_put_contents('/1.txt','abc');
    $nickname=$user['nickname'];
    $QR = imagecreatefromstring(file_get_contents($invitepic));
    $picname = imagecreatefromstring(file_get_contents($pic));
    imagecopymerge($QR, $picname, 229, 616, 0, 0, 333, 333, 100);
    $textcolor = imagecolorallocate($QR, 230, 230, 230);
    imagettftext($QR, 21, 0, 260, 280, $textcolor, '/www/wwwroot/ctkBak/public/myfont.ttf', "$nickname  的邀请码");
    $textcolor = imagecolorallocate($QR, 233, 185, 97);
    imagettftext($QR, 40, 0, 271, 415, $textcolor, '/www/wwwroot/ctkBak/public/myfont.ttf', $invite);

    $invpath = "/www/wwwroot/ctkBak/public/uploads/invite/" . $id . ".png";
    imagepng($QR, $invpath);
    @unlink($pic);
  }

  //升级团队
  function team_up($id)
  {
    $father = User::get($id);
    //判断用户是否存在
    if (!$father) {
      return;
    }
    //升级开关
    $flag = 0;

    //判断升级条件
    //1、判断直推人数  获取对应等级的直推人数 和自身直推人数
    //                判断是否满足
    $scount = System_config::get(['class' => 'zt_rs', 'name' => ($father->getData('level') + 1)])['value'];
    $fcount = $this->model->where('pid', $id)->where('level', '>', 0)->count();
    if ($fcount >= $scount) {
      $flag += 1;
    }

    //2、判断vip人数
    $scount = System_config::get(['class' => 'zt_vip', 'name' => ($father->getData('level') + 1)])['value'];
    $list = $this->model->where('pid', $id)->where('level','>=', $father->getData('level'))->select();
    $fcount = 0;
    //判断拍过一次单
    foreach ($list as $item) {
      $int = OrderDemand::get(['uid' => $item['id'], 'state' => 1, 'type' => 1]);
      if ($int) {
        $fcount += 1;
      }
    }


    if ($fcount >= $scount) {
      $flag += 1;
    }

    //升级等级 如果这个人
    if ($flag == 2) {
      if($father->getData('level') < 6){
        $father->setInc('level', 1);
        $this->team_up($id);
      }
    }else{
      $this->team_up($father->getData('pid'));
    }
  }
}