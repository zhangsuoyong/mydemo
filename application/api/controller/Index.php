<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 15:50
 */

namespace app\api\controller;


use app\common\model\Account;
use app\common\model\Notice;
use app\common\model\OrderDemand;
use app\common\model\Slide;
use app\common\model\System_config;
use app\common\model\User;

class Index extends UserBase
{
  //主页
  public function index()
  {
    print_r(11111);
    $data = [];
    $slide = Slide::all(function ($query) {
      $query->field('picpath,picname,content,type,id')->where(['state' => 1])->order('create_time desc');
    });
    $data['slide'] = $slide;
    $notice = Notice::get(function ($query) {
      $query->field('id,title')->where(['is_home' => 1]);
    });
    $data['notice'] = $notice;

    $orderDemand = new OrderDemand();
    $config = System_config::get(['name' => 'provide_xn'])['value'];

    $data['provide'] = $orderDemand->where('type', 1)->where('on_amount',0)->where('lock_amount',0)->where('state in (2) ')->count() + $config;
    $config = System_config::get(['name' => 'receive_xn'])['value'];
    $data['receive'] = $orderDemand->where('type', 2)->where('on_amount',0)->where('lock_amount',0)->where('state in (2) ')->count() + $config;

    $count1 = $orderDemand->where('uid', input('id'))->where('type', 1)->where('on_amount', 0)->where('lock_amount', 0)->orderRaw('FIELD(`state`,2,1,3),create_time desc')->count();
    $count2 = $orderDemand->where('uid', input('id'))->where('type', 1)->where(' on_amount > 0 or lock_amount > 0  ')->where('state', 2)->orderRaw('FIELD(`state`,2,1,3),update_time desc')->count();
    $data['provide_num']=['only'=>$count1,'all'=>$count2];


    $count1 = $orderDemand->where('uid', input('id'))->where('type', 2)->where('state = 1 or state = 2')->where('on_amount', 0)->where('lock_amount', 0)->orderRaw('FIELD(`state`,2,1),create_time desc')->count();
    $count2 = $orderDemand->where('uid', input('id'))->where('type', 2)->where(' on_amount > 0 or lock_amount > 0  ')->where('state', 2)->orderRaw('FIELD(`state`,2,1,3),update_time desc')->count();
    $data['receive_num']=['only'=>$count1,'all'=>$count2];


    $user = User::get(input('post.id'));
    if ($user['next_buy_time']) {
      $data['next_time'] = date('Y/m/d H:i:s', strtotime($user['next_buy_time']));
    } else {
      $data['next_time'] = 0;
    }
    return returnJson(1, '查询成功', $data);
  }


}