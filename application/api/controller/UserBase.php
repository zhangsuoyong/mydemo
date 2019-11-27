<?php


namespace app\api\controller;


use app\common\exception\LoginException;
use app\common\exception\ServerException;
use app\common\model\User;

class UserBase extends Base
{
  protected $beforeActionList = [
      'is_post' => ['except' =>['auto_guanbi_cash','img_yzm','auto48_put_buy_demand','kanbudong']],
      'decrypt',
      'is_login'=> ['except' =>['auto_guanbi_cash','img_yzm','auto48_put_buy_demand','kanbudong']],
      'is_lock'=> ['except' =>['auto_guanbi_cash','img_yzm','auto48_put_buy_demand','kanbudong']],
      'is_weihu',
      'authIp'=>['only'=>['kanbudong','auto_guanbi_cash','img_yzm','auto48_put_buy_demand']]
  ];
  
  
  	  protected function is_weihu()
  {
  	
  	if (!config('is_maintain')&&input('id')!=680) {
  	throw  new LoginException('维护中...,请稍后再试.');
  	}
  }

  public function is_login()
  {
    if (!input('id')) {
      throw new LoginException('请重新登陆');
    }
    if (!input('token')) {
      throw new LoginException('请重新登陆');
    }
    $post = input('post.');
    $user = User::get($post['id']);
    if ($user['token'] != $post['token']) {
      throw new LoginException('请重新登陆');
    }
  }

  public function is_lock()
  {
    $user = User::get(input('id'));
    if ($user->getData('is_lock')==1) {
      throw new LoginException('该账户已被冻结');
    }
  }
  
   public function authIp(){
		$ip=request ()->ip ();
		
		if ($ip!='0.0.0.0') {
		 throw new ServerException('拒绝访问');
		} 
	
		
  }

}