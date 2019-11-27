<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 15:50
 */

namespace app\api\controller;


use app\common\exception\ServerException;
use app\common\model\System_config;
use think\Controller;
use think\Exception;

class Base extends Controller
{
  protected $beforeActionList = [
	'is_post'=>['except'=>['auto_guanbi_cash','img_yzm','auto48_put_buy_demand',]],
	'decrypt',
	'authIp'=>['only'=>['auto_guanbi_cash','auto48_put_buy_demand']],
//    'is_start',
	
	];
	
	

	

  protected function is_post()
  {
  
  	
    if (!request()->isPost()) {
      throw  new ServerException('请求方式错误');
    }
  }

  protected function is_start(){
    //跟系统配置的发布提现时间比较
    $config = System_config::get(['name' => 'tx_start'])['value'];
    if (intval(date('H')) < intval($config)) {
      throw  new ServerException('未到提现开放时间');
    }
    //跟系统配置的发布提现时间比较
    $config = System_config::get(['name' => 'tx_end'])['value'];
    if (intval(date('H')) >= intval($config)) {
      throw  new ServerException('已超过提现开放时间');
    }
  }

  public function decrypt()
  {
    $post = input('post.');
    
    try{
      foreach ($post as $k=>$value){
        $b = RSA($value, 'decode');
    
        if (!$b['code']) {
          throw new ServerException('传入参数错误');
        }
        request()->post([$k=>$b['msg']]);
      }
    }catch ( Exception $e){
      throw new ServerException('参数错误!');
    }

  }
  public function authIp(){
		$ip=request ()->ip ();
		
		if ($ip!='0.0.0.0') {
		 throw new ServerException('拒绝访问');
		} 
	
		
  }
  
  
}