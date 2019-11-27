<?php
namespace app\index\controller;
use think\Controller;
use app\common\model\User;
use app\common\serviceimpl\UserServiceImpl;

class Invite extends Controller
{
  public function register()
  {
    $invite_code = input('param.invite');
    $this->assign('invite_code',$invite_code);
    return $this->fetch('111');
  }
 
 
}
