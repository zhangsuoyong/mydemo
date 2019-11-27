<?php


namespace app\tkfadmin\controller;


use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\AdminLog;
use think\Db;
use think\Exception;

class Capital extends Base
{
  //充值页面
  public function index()
  {
    $id = input('id');
    $user=\app\common\model\User::get(input('id'));
    return view('index', ['id' => $user['user']]);
  }

  //充值
  public function update()
  {
    if (!input('id')) {
      throw new ServerException('请输入用户id');
    }
    if (!input('num')) {
      throw new ServerException('请输入数量');
    }
    if (!input('type')) {
      throw new ServerException('请输入类型');
    }
    if (!input('sign')) {
      throw new ServerException('请选择增减');
    }
    switch (input('type')) {
      //互助积分
      case 1:
        $type = 'pos';
        $str = '互助积分';
        break;
      //奖金
      case 3:
        $type = 'point';
        $str = '奖金';
        break;
      //通证
      case 6:
        $type = 'pass_card';
        $str = '通证';
        break;
      //派单币
      case 4:
        $type = 'buy_coin';
        $str = '排单币';
        break;
      //激活码
      case 5:
        $type = 'action_coin';
        $str = '激活码';
        break;
        case 7:
        $type = 'ticket';
        $str = '天使券';
        break;
        case 8:
        $type = 'gold_seed';
        $str = '金种子';
        break;
      default:
        throw new ServerException('类型错误');
    }
    $user=\app\common\model\User::get(['user'=>input('id')]);
    $account = Account::get(['uid' => $user['id']]);
    if (!$account) {
      throw new ServerException('该用户不存在');
    }
    Db::startTrans();
    try {
      if ('+' == input('sign')) {
        $account->setInc($type, input('num'));
        AccountLog::addCoinLog($user['id'], input('type'), input('num'), '+', "平台充值");
        
         if ( $type = 'pos'&&$account->buy_coin>0){
			$user->auto_if_cash=1;
			$user->save ();
	      }
      } else {
        $account->setDec($type, input('num'));
        AccountLog::addCoinLog($user['id'], input('type'), input('num'), '-', "平台扣除");
      }
      AdminLog::add_log('充值资产');
      Db::commit();
      return returnJson(1, '充值成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, '网络异常');
    }


  }

  //充值日志
  public function detail()
  {
    $where = [];
    $query = input('param.');
    if (input('id')) {
      $where['a.uid'] = input('id');
    } else {
      $query['id'] = '';
    }
    if (input('user')) {
      $where['u.user'] = input('user');
    } else {
      $query['user'] = '';
    }
    if (input('type')) {
      $where['a.type'] = input('type');
    } else {
      $query['type'] = '';
    }
    if (input('start')) {
      $where['a.create_time'] = ['>=', input('start')];
    } else {
      $query['start'] = '';
    }
    if (input('end')) {
      $where['a.create_time'] = ['<=', input('end')];
    } else {
      $query['end'] = '';
    }
    $model = new AccountLog();
    $list = $model->alias('a')->field('a.*')->join('user u','a.uid=u.id')->where($where)->order('a.create_time desc')->paginate(15, false, ['query' => $query]);
   
    $query['list'] = $list;
    return view('detail', $query);
  }

  //充值日志
  public function ptdetail()
  {
    $where = [];
    $query = input('param.');
    if (input('id')) {
      $where['a.uid'] = input('id');
    } else {
      $query['id'] = '';
    }
    if (input('user')) {
      $where['u.user'] = input('user');
    } else {
      $query['user'] = '';
    }
    if (input('type')) {
      $where['a.type'] = input('type');
    } else {
      $query['type'] = '';
    }
    if (input('start')) {
      $where['a.create_time'] = ['>=', input('start')];
    } else {
      $query['start'] = '';
    }
    if (input('end')) {
      $where['a.create_time'] = ['<=', input('end')];
    } else {
      $query['end'] = '';
    }
    $model = new AccountLog();
    $list = $model->alias('a')->field('a.*')->join('user u','a.uid=u.id')->where($where)->where(' a.msg like "%平台%"')->order('a.create_time desc')->paginate(15, false, ['query' => $query]);
    $query['list'] = $list;
    return view('ptdetail', $query);
  }


}