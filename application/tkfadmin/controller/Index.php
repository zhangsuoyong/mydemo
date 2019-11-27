<?php

namespace app\tkfadmin\controller;


use app\common\model\Account;
use app\common\model\OrderDemand;
use app\common\model\OrderTransaction;

/**
 * @Author: YeMiao
 * @Date:   2017-07-29 15:03:52
 * @Last Modified by:   YeMiao
 * @Last Modified time: 2018-04-27 11:35:28
 */
class Index extends Base
{

  public function index()
  {
    $find = \app\common\model\Admin::get(['username' => session('user')]);
    $this->assign('to_url', input('cookie.to_url'));

    cookie('to_url', null);
    $this->assign('data', $find);
    return $this->fetch();
  }

  public function home()
  {
    $user = new \app\common\model\User();
    $demand = new OrderDemand();
    $account = new Account();
    $transaction=new OrderTransaction();
    $data = [];
    $admin = \app\common\model\Admin::get(session('admin_id'));
    //登陆地址
    $data['login_ip'] = $admin['login_ip'];
    $data['login_ip_ago'] = $admin['login_ip_ago'];

    //今日概览
    $data['new_persion'] = $user->whereTime('create_time', 'today')->count();
    $data['new_action_persion'] = $user->where('state', 1)->whereTime('create_time', 'today')->count();
    $data['new_provide'] = $demand->whereTime('create_time', 'today')->where('type', 1)->sum('amount');
    $data['new_receive'] = $demand->whereTime('create_time', 'today')->where('type', 2)->sum('amount');

    //会员统计
    $data['persion'] = $user->count();
    $data['action_persion'] = $user->where('state', 1)->count();
    $data['disaction_persion'] = $user->where('state', 2)->count();
    $data['lock_persion'] = $user->where('is_lock', 1)->count();
    $data['c1_persion'] = $user->where('level', 1)->count();
    $data['c2_persion'] = $user->where('level', 2)->count();
    $data['c3_persion'] = $user->where('level', 3)->count();
    $data['c4_persion'] = $user->where('level', 4)->count();
    $data['c5_persion'] = $user->where('level', 5)->count();
    $data['c6_persion'] = $user->where('level', 6)->count();

    //资产统计
    $data['pos_total'] = $account->sum('pos_total');
    $data['pos'] = $account->sum('pos');
    $data['pow'] = $account->sum('pow');
    $data['point_total'] = $account->sum('point_total');
    $data['point'] = $account->sum('point');
    $data['action_coin'] = $account->sum('action_coin');
    $data['action_coin_total'] = $account->sum('action_coin_total');
    $data['buy_coin_total'] = $account->sum('buy_coin_total');
    $data['buy_coin'] = $account->sum('buy_coin');

    //提供帮助统计
    $data['provide_sum'] = $demand->where('type', 1)->where('state in (1,2)')->sum('amount');
    $data['provide_all'] = $transaction->where('state',1)->sum('amount');
    $data['provide_only'] = $transaction->where('state in (2,3)')->sum('amount');
    $data['provide_dis']=$data['provide_sum']-$data['provide_all']-$data['provide_only'];

    //接受帮助统计
    $data['receive_sum'] = $demand->where('type', 2)->where('state in (1,2)')->sum('amount');
    $data['receive_all'] = $transaction->where('state = 1')->sum('amount');
    $data['receive_only'] = $transaction->where('state in (2,3) ')->sum('amount');
    $data['receive_dis']=$data['receive_sum']-$data['receive_all']-$data['receive_only'];

    $this->assign($data);
    return $this->fetch();
  }

  public function pass_update(){
    if(!request()->isPost()){
      return returnJson(0,'非法入侵');
    }
    $post = input('post.');
    $session_info = session('admin_id');
    $userinfo = model("admin")->where("id",$session_info)->find();
    if($userinfo['password'] == encrypt($post['oldpass'])){
      $res = model("admin")->where("id",$userinfo['id'])->update(['password'=>encrypt($post['newpass'])]);
      if($res){
        return returnJson(1,'修改成功');
      }else{
        return returnJson(0,'修改失败');
      }
    }else{
      return returnJson(0,'原密码错误');
    }
  }
}


