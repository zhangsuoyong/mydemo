<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 15:39
 */

namespace app\api\controller;

use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\server\yzmServer;
use app\common\serviceimpl\UserServiceImpl;
use app\common\validate\UserValidate;
use think\captcha\Captcha;
use think\Db;
use think\Exception;
use think\Request;

class Login extends Base
{
  private $service;

  public function __construct(Request $request = null, UserServiceImpl $service)
  {
    parent::__construct($request);
    $this->service = $service;
  }

  //注册
  public function register()
  
  {
  	 
    $post = input('post.');
    
    $validate = new UserValidate();
    if (!$validate->scene('register')->check($post)) {
      throw new ServerException($validate->getError());
    }
    if (session('user') != $post['user'] || strtoupper(session('code')) != strtoupper($post['code'])) {
      throw new ServerException('验证码错误');
    }
    unset($post['code']);
    Db::startTrans();
    try {
      $puser = User::get(['invite' => trim($post['invite'])]);
      if ($puser) {
        $post['path'] = $puser['path'] . $puser['id'] . ',';
        $post['pid'] = $puser['id'];
        $post['tid']=$puser->getData('tid');
      } else {
        throw new ServerException('该推荐人不存在');
      }
      unset($post['invite']);
      $post['password'] = encrypt($post['password']);
      $post['paypassword'] = encrypt($post['paypassword']);
     
      $int = User::create($post);
       
      $pass = $int->setPassAddressAttr();
       //return returnJson(0, $token);
      $token = md5(time() . 'tkf');
     
      $int->save(['token' => $token, 'diver' => \request()->header('user-agent'), 'pass_address' => $pass]);
       
      Account::create(['uid' => $int['id']]);
      UserAccount::create(['uid' => $int['id']]);
      //session('code', null);
      session('token', $token);
      Db::commit();
      return returnJson(1, '注册成功', ['id' => $int['id'], 'token' => $token]);
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, $e->getMessage());
    }
  }

  //登陆
  public function login()
  {
    $post = input('post.');
    $validate = new UserValidate();
    $res = $validate->scene('login')->check($post);
    if (!$res) {
      throw new ServerException($validate->getError());
    }
    if (!captcha_check(input('code'))) {
      throw new ServerException('验证码错误');
    };
    $user = User::get(['user' => $post['user']]);
    if (!$user) {
      throw new ServerException('该账号未注册');
    }
    if ($user['password'] != encrypt($post['password'])) {
      throw new ServerException('密码输入错误');
    }
//    if ($user['diver'] != \request()->header('user-agent')) {
//      return returnJson(3, '登陆设备不同！');
//    }
    $token = md5(time().'tkf');
    session('user', $user['user']);
    session('id', $user['id']);
    session('token', $token);
    User::update(['token' => $token,], ['id' => $user['id']]);
    return returnJson(1, '登陆成功', ['id' => $user['id'], 'token' => $token]);
  }

  //验证码
  public function yzm()
  
  {
  	
  	
  	
    $post = \request()->post();
    if (!input('user')) {
      throw new ServerException('请输入账号');
    }
    if (!input('flag')) {
      throw new ServerException('请输入类型');
    }
    $yzm = new yzmServer();
    $code = mt_rand(1000, 9999);
    $user = User::get(['user' => $post['user']]);

    switch ($post['flag']) {
//        注册
      case '1':
        if ($user) {
          return returnJson(0, "该账号已注册");
        }
        break;
//        登录 ,修改密码
      case '2':
        if (!$user) {
          return returnJson(0, "该账号未注册");
        }
        break;
    }
    if (isEmail($post['user'])) {
      return $yzm->send_email($post['user'], $code);
    } else {
      if (strlen($post['user']) != 11) {
        throw new ServerException('请输入正确的手机号');
      }
      return $yzm->sms($post['user'], $code);
    }
  }

  //忘记密码
  public function forget()
  {
    $post = input('post.');
    $validate = new UserValidate();
    $res = $validate->scene('forget')->check($post);
    if (!$res) {
      throw new ServerException($validate->getError());
    }

    $user = User::get(['user' => $post['user']]);
    if (!$user) {
      throw new ServerException('该账号未注册');
    }

    if (session('user') != $post['user'] || strtoupper(session('code')) != strtoupper($post['code'])) {
      throw new ServerException('验证码错误');
    }
    unset($post['code']);
    if ($user->save(['password' => encrypt($post['password'])])) {
      return returnJson(1, '修改成功');
    }
    return returnJson(0, '修改失败');
  }

  //图片验证码
  public function img_yzm()
  {
    $captcha = new Captcha();
    $captcha->length = 4;
    $captcha->codeSet = '0123456789';
    return $captcha->entry();
  }


  //快捷登陆
  public function login_q()
  {
    $post = input('post.');
    $validate = new UserValidate();
    $res = $validate->scene('login_q')->check($post);
    if (!$res) {
      throw new ServerException($validate->getError());
    }
    $user = User::get(['user' => $post['user']]);
    if (!$user) {
      throw new ServerException('该账号未注册');
    }
    if ($user['password'] != encrypt($post['password'])) {
      throw new ServerException('密码输入错误');
    }
    $token = md5(time() + 'tkf');
    session('user', $user['user']);
    session('id', $user['id']);
    session('token', $token);
    User::update(['token' => $token], ['id' => $user['id']]);
    return returnJson(1, '登陆成功', ['id' => $user['id'], 'token' => $token]);
  }


  public function driver()
  {
    if (!input('user')) {
      throw new ServerException('user error');
    }
    if (!input('code')) {
      throw new ServerException('code error');
    }
    $post=input('post.');
    if (session('user') != $post['user'] || strtoupper(session('code')) != strtoupper($post['code'])) {
      throw new ServerException('验证码错误');
    }
    $user=User::get(['user'=>$post['user']]);
    $user->save(['diver'=> \request()->header('user-agent')]);
    return returnJson(1,'校验成功');
  }
}