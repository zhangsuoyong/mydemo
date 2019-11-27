<?php

namespace app\tkfadmin\controller;

use app\common\model\AdminLog;
use think\Controller;
use think\Session;


class Login extends Controller
{
  public function index()
  {
    if (request()->isPost()) {
      $post = input('post.');
      $find = \app\common\model\Admin::get(['username' => $post['user']]);
      //\app\common\model\Admin::create (['username'=>'root_admin','password'=>encrypt('888888')]);
     // $find->password=encrypt('591212');
      //$find->save();
      if ($find) {
        if ($find['password'] == encrypt(input('post.pass'))) {
          session('admin_id', $find['id']);
          session('user', input('post.user'));
          session('ontime', time());
          session('adminrole', $find['roleid']);
          $find->save(['login_ip_ago' => $find['login_ip'], 'login_ip' => $_SERVER['REMOTE_ADDR'],]);
          AdminLog::add_log('登录');
          $this->redirect('/tkfadmin/index/index');
        } else {
          return $this->error('密码错误', 'login/index');
        }
      } else {
        return $this->error('用户名错误', 'login/index');
      }
    }
    return $this->fetch();
  }

  public function out()
  {
    AdminLog::add_log('退出');
    Session::clear();
    $this->redirect('login/index');
  }


}

