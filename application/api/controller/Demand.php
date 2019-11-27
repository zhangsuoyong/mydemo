<?php

namespace app\api\controller;

use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\OrderDemand;
use app\common\model\OrderTransaction;
use app\common\model\System_config;
use app\common\model\User;
use app\common\model\Robot;
use app\common\model\UserAccount;
use app\common\model\UserActionLog;
use app\common\server\MatchServer;
use PragmaRX\Google2FA\Google2FA;
use think\Db;
use think\Exception;
use think\Log;

class Demand extends UserBase {
	//提供帮助
	public function provide() {
		
		
		
		
		
		$data = [];
		$str=System_config::get(['name'=>'due'])['value']?System_config::get(['name'=>'due'])['value']:'48,72,96';
		
		if(!preg_match('/\d.*,\d.*,\d.*/',$str)){
			$str='48,72,96';
		}
			
			$due=explode (',',$str);
		$robot=Robot::where ('uid','=',input ('post.id'))->find();
	
		
		 //return   returnJson(0,input ('post.id'),['robot'=>$robot]);
		if($robot){
			$status=$robot['status'];
			$mydue=$robot['due'];
			if($robot['status']==1){
					$next_time=date('Y-m-d H:i:s',$robot['next_buy_at']);
			}else{
					$next_time='';
			}
		
		}else{
			$status='2';
			$mydue='48';
			$next_time='';
		}
		
		switch ($mydue) {
			case '48':
				$index='0';
				break;
				case '72':
				$index='1';
				break;
				case '96':
				$index='2';
				break;
			
			default:
					$index=0;
				break;
		}
		
		//排单币
		$account = Account::get(input('post.id'))['buy_coin'];
		$data['buy_coin'] = $account;
		//后台配置的金额
		$config = System_config::get(['name' => 'accept_help'])['value'];
		$list = explode(',', $config);

		//判断个人等级是否为c1
		$user = User::get(input('id'));
		$min = $user['on_buy_num'];
		$max = max($list);

		if ($user->getData('qz_level') == 1 || ($user->getData('qz_level') == 0 && $user->getData('level') == 1)) {
			//是否首单
			$demand = OrderDemand::get(['uid' => input('id'), 'genre' => 4, 'type' => 1]);
			if (!$demand) {
				$min_c1 = System_config::get(['name' => 'min_provide_c1'])['value'];
				$max_c2 = System_config::get(['name' => 'max_provide_c1'])['value'];
				$min = max($min, $min_c1);
				$max = min($max, $max_c2);
			}
		}
		foreach ($list as $k => $item) {
			if (intval($item) < $min || intval($item) > $max) {
				unset($list[$k]);
			}
		}
		$data['list'] = array_values($list);
		$bl = System_config::get(['name' => 'admission_cost_ratio'])['value'];
		$data['bl'] = $bl;
		$rule = System_config::get(['name' => 'accept_rule'])['value'];
		$data['rule'] = $rule;
		$config = System_config::get(['name' => 'rob_flag'])['value'];
		if ($config) {
			$config = System_config::get(['name' => 'robAmount'])['value'];
			$data['num'] = $config;
		} else {
			$data['num'] = 0;
		}
		$ticket = Account::get(input('id'))['ticket'];
		$data['ticket'] = $ticket;
		$config = System_config::get(['name' => 'pro_remark'])['value'];
		$data['remark'] = $config;
			$data['due']=$due;
		$data['status']=$status;
		$data['mydue']=['index'=>$index,'mydue'=>$mydue];
		$data['next_time']=$next_time;
		return returnJson(1, '查询成功', $data);
	}

	//发布提供帮助需求
	public function put_buy_demand() {
		
		
			$robot=Robot::where ('uid','=',input ('post.id'))->find();
			
			if($robot){
				
				
				if($robot->status==1){
			
			throw new ServerException('自动排单中,请先关闭自动功能');
		}
				
				
			}
		
		
		
		$useraccount = new UserAccount();
		if (!input('amount')) {
			throw new ServerException('请输入金额');
		}
		$amount = input('amount');
		$user = User::get(input('id'));
		//判断是否激活
		if (!$user['invite']) {
			throw new ServerException('该账户未激活');
		}
		//判断二级密码
		if (!input('paypassword')) {
			throw new ServerException('请输入二级密码');
		}
		// 判断是否有设置账户
		$isSet = $useraccount->judgeSetAccountInfo(input('id'));
		if ($isSet) {
			return returnJson(4, '请前往个人中心，完善个人资料.');
		}

		if ($user->getData('is_google')) {
			$google2fa = new Google2FA();
			if (input('paypassword') != $google2fa->getCurrentOtp($user->getData('google'))) {
				throw new ServerException('验证码输入错误');
			}
		} else {
			if ($user['paypassword'] != encrypt(input('paypassword'))) {
				throw new ServerException('二级密码错误');
			}
		}

		//判断他是不是平台账户
		if ($user->getData('ident') == 3) {
			Db::startTrans();
			try {
				$demand =$this->putDemand(input('id'), $amount, 1, 1, 0);
				UserActionLog::addActionLog(input('id'), "发布提供帮助需求{$demand['id']}");
				Db::commit();
				return returnJson(1, '发布成功');
			} catch (Exception $e) {
				Db::rollback();
				return returnJson(0, '发布失败');
			}
		}







		// 判断收购币
		$bl = System_config::get(['name' => 'admission_cost_ratio'])['value'];
		$account = Account::get(input('id'));
		$onBuyCoin = input('amount') * $bl;
		if ($account['buy_coin'] - $onBuyCoin < 0) {
			throw new ServerException('互助币不足');
		}
		//判断跟上次提供帮助金额比较
		if ($amount < $user['on_buy_num']) {
			return returnJson(0, '提供帮助金额不能小于' . $user['on_buy_num']);
		}

		//跟系统配置的最小排单比较
		$config = System_config::get(['name' => 'min_provide'])['value'];
		if ($amount < $config) {
			return returnJson(0, '提供帮助金额不能小于' . $user['on_buy_num']);
		}
		//跟系统配置的最大排单比较
		$config = System_config::get(['name' => 'max_provide'])['value'];
		if ($amount > $config) {
			return returnJson(0, '提供帮助金额不能大于' . $user['on_buy_num']);
		}
		//判断系统配置的倍数比较
		$config = System_config::get(['name' => 'sell_min_num'])['value'];
		if (($amount % $config) != 0) {
			return returnJson(0, '提供帮助金额需要是' . $config . '的倍数');
		}

		//判断下次提供帮助时间
		if (strtotime($user['next_buy_time']) - strtotime('now') > 0) {
			throw new ServerException('提供帮助时间间隔不足');
		}

		//判断是否是抢购状态
		$config = System_config::get(['name' => 'rob_flag'])['value'];
		//是
		//抢购开关
		$qg_num = 0;
		//用券开关
		$ticket_num = 0;
		if ($config) {
			$qg_num = System_config::get(['name' => 'robAmount'])['value'];
			if ($qg_num < $amount) {
				//判断是否有天使券
				$ticket_num = $account->getData('ticket');
				if (!$ticket_num) {
					throw new ServerException('可抢购金额不足');
				}
			}
		}

		//判断是否首单
		$demand = OrderDemand::get(['uid' => input('id'), 'genre' => 4, 'type' => 1]);
		// $status=input('status');//0未开启 1开启
		// $due=input ('due');     //间隔时间 48h 72h 96h
		// if ($status&&$demand){
		// 	throw new ServerException('首单不可以开启自动排单');
		// }
		// if ($status&&!$due){
		// 	throw new ServerException('请选择自动排单时间');

		// }
		
		$config = System_config::get(['name' => 'admission_interval'])['value'];
		Db::startTrans();
		try {
			
			// $robot=Robot::where('uid','=',$user['id'])->find();
			// if ($status&&!$robot){
			// 	Robot::create ([
			// 	'uid'=>$user['id'],
			// 	'due'=>$due,
			// 	'status'=>1
			// 	]);
			// }elseif ($status&&$robot&&$robot['due']!=$due){
			// 	$robot->due=$due;
			// 	$robot->status=1;
			// 	$robot->isUpdate (true)->save ();
			// }elseif (!$status&&$robot){
			// 	$robot->status=0;
			// 	$robot->isupdate(true)->save();
			// }
			//如果用券, 抢购金额为0 或 抢购金额不足
			//那么将抢购金额归零并减少券
			if ($ticket_num) {
				System_config::get(['name' => 'robAmount'])->save(['value' => 0]);
				System_config::get(['name' => 'onRobAmount'])->setInc('value', $amount);
				$account->setDec('ticket', 1);
				AccountLog::addCoinLog(input('id'), 7, 1, '-', '提供帮助消耗');
			} else if ($qg_num) {
				System_config::get(['name' => 'robAmount'])->setDec('value', $amount);
				System_config::get(['name' => 'onRobAmount'])->setInc('value', $amount);
			}
			if ($demand) {
				//正常
				$this->putDemand(input('id'), $amount, 1, 1, 0);
				// 扣除收购币
				$account->setDec('buy_coin', $onBuyCoin);
				$account->setInc('buy_coin_total', $onBuyCoin);
				$account->setInc('admission', $amount);

				// 添加扣除日志
				AccountLog::addCoinLog(input('id'), 4, $onBuyCoin, '-', '提供帮助消耗');
					if($user->need_two!=1){
				$user->save(['on_buy_time' => date("Y-m-d H:i:s", time()), 'on_buy_num' => $amount, 'next_buy_time' => date("Y-m-d H:i:s", strtotime("+ $config hours")),'need_two'=>$user->need_two-=1,'if_cash'=>1]);
				}
				$user->save(['on_buy_time' => date("Y-m-d H:i:s", time()), 'on_buy_num' => $amount, 'next_buy_time' => date("Y-m-d H:i:s", strtotime("+ $config hours"))]);
			} else {
				//首单
				$level = $user['qz_level']['value'] == 1 || ($user['qz_level']['value'] == 0 && $user['level']['value'] == 1);
				if ($level) {
					$min = System_config::get(['name' => 'min_provide_c1'])['value'];
					$max = System_config::get(['name' => 'max_provide_c1'])['value'];
					if ($min > $amount) {
						return returnJson(0, "提供帮助金额不能小于$min");
					}
					if ($max < $amount) {
						return returnJson(0, "提供帮助金额不能大于$max");
					}
				}
				// 添加首单需求
				$this->putDemand(input('id'), $amount, 1, 4, 0);
				// 扣除收购币
				$account->setDec('buy_coin', $onBuyCoin);
				$account->setInc('buy_coin_total', $onBuyCoin);
				$account->setInc('admission', $amount);
				// 添加扣除日志
				AccountLog::addCoinLog(input('id'), 4, $onBuyCoin, '-', '提供帮助');
				if($user->need_two!=1){
				$user->save(['on_buy_time' => date("Y-m-d H:i:s", time()), 'on_buy_num' => $amount, 'next_buy_time' => date("Y-m-d H:i:s", strtotime("+ $config hours")),'need_two'=>$user->need_two-=1,'if_cash'=>1]);
				}
				
				$user->save(['on_buy_time' => date("Y-m-d H:i:s", time()), 'on_buy_num' => $amount, 'next_buy_time' => date("Y-m-d H:i:s", strtotime("+ $config hours"))]);
			}
			UserActionLog::addActionLog(input('id'), '发布提供帮助需求');
			Db::commit();
			return returnJson(1, '提供帮助成功');
		} catch (Exception $e) {
			Db::rollback();
			return returnJson(0, '网络错误');
		}
	}

	//接受帮助页面
	public function receive() {
		
		$data = [];
		$account = Account::get(input('post.id'));
		$data['pos'] = $account['pos'];
		$data['point'] = $account['point'];
		$data['frequency']=$account['pri_account'];
	
//    $config = System_config::get(['name' => 'accept_help'])['value'];
		//    $list = explode(',', $config);
		//    $user = User::get(input('id'));
		//    $min = $user->getData('accecpt_num');
		//    foreach ($list as $k => $v) {
		//      if ($v < $min) {
		//        unset($list[$k]);
		//      }
		//    }
		//    $data['pos_list'] = array_values($list);
		//    $list = explode(',', $config);
		//    $level = $user->getData('qz_level') ? $user->getData('qz_level') : $user->getData('level');
		//    $config = System_config::get(['class' => 'zt_jj', 'name' => $level])['value'];
		//    foreach ($list as $k => $v) {
		//      if ($v > $config) {
		//        unset($list[$k]);
		//      }
		//    }
		//    $data['point_list'] = array_values($list);
		$rule = System_config::get(['name' => 'receive_rule'])['value'];
		$data['rule'] = $rule;
		return returnJson(1, '查询成功', $data);
	}

	//发布接受帮助需求
	public function put_sell_demand() {
		
		
		
	
		if (!input('flag')) {
			throw new ServerException('flag error');
		}
		if (!input('amount')) {
			throw new ServerException($op);
		}
		$amount = input('amount');
		
		$orderDemand=new OrderDemand();
		$max=$orderDemand->getMaxProvide (input ('post.id'));
			$user = User::get(input('id'));	
		$op=System_config::get(['name' => 'cash_rate'])['value']?System_config::get(['name' => 'cash_rate'])['value']:1.1;
		
		if ($amount>$max*$op&&$user['ident']['value']!=3&&input('id')!=568){
			throw new ServerException('当前最大提现金额为'.$user['ident']['value']);
		}
	
		//判断二级密码
		if (!input('paypassword')) {
			throw new ServerException('请输入二级密码');
		}
		if ($user->getData('is_google')) {
			$google2fa = new Google2FA();
			if (input('paypassword') != $google2fa->getCurrentOtp($user->getData('google'))) {
				throw new ServerException('验证码输入错误');
			}
		} else {
			if ($user['paypassword'] != encrypt(input('paypassword'))) {
				throw new ServerException('二级密码错误');
			}
		}

		$useraccount = new UserAccount();
		$account = Account::get(input('id'));
		// 判断是否有设置账户
		$isSet = $useraccount->judgeSetAccountInfo(input('id'));
		if ($isSet) {
			return returnJson(4, '请前往个人中心，完善个人资料.');
		}

		//判断是否是首单
		$order = OrderDemand::get(['state' => 1, 'type' => 2, 'uid' => input('id')]);
		if ($order) {
			$geren = 1;
		} else {
			$geren = 4;
		}
		$flag = input('flag');
		if ($user->getData('ident') == 3) {
		switch ($flag) {
			case 1:
				if ($account['pos'] < $amount) {
					throw new ServerException('剩余自由资产不足');
				}
				break;
			case 2:
				if ($account['point'] < $amount) {
					throw new ServerException('剩余分享奖励不足');
				}
				break;
		}
	

			Db::startTrans();
			try {
				
	
				
				$account->setDec('pos', $amount);
				$account->setInc('pos_total', $amount);
				$demand = $this->putDemand(input('id'), $amount, 2, $geren);
				AccountLog::addCoinLog($user['id'], 1, $amount, '-', "发布接受帮助需求{$demand['id']}");
				Db::commit();
				return returnJson(1, '发布成功');
			} catch (Exception $e) {
				Db::rollback();
				return returnJson(0, '发布失败');
			}
		}

		//判断是否激活
		if (!$user['invite']) {
			throw new ServerException('该账户未激活');
		}

		if ($user->getData('if_cash') == 2||$user->getData('auto_if_cash') == 0) {
			throw new ServerException('该用户不能提现，请联系管理员');
		}
		
		if($user->getData('need_two') !=1){
			$num=$user->getData('need_two')-1;
			throw new ServerException('长时间未排单,你需要再排'.$num.'单,才可以提现');
			
		}

		// 是否有正在进行的订单
		$orderDemand = new OrderDemand();
		$sellTrue = $orderDemand->where(['type' => 2, 'uid' => input('id')])->whereTime('create_time', 'today')->find();
		if ($sellTrue) {
			return returnJson(0, '每天只能发一个接受帮助的需求');
		}

		//获取系统提现时间
		$config = System_config::get(['name' => 'tx_flag'])['value'];
		if ($config) {
			$this->is_start();
		}

		//判断系统配置的倍数比较
		$config = System_config::get(['name' => 'tx_money_bs'])['value'];
		if (($amount % $config) != 0) {
			return returnJson(0, '接受帮助金额需要是' . $config . '的倍数');
		}
		Db::startTrans();
		try {
			switch ($flag) {
			case 1:
				//正常
				if ($account['pos'] < $amount) {
					throw new ServerException('剩余自由资产不足');
				}

				//跟系统配置的最低匹配金额
				$config = System_config::get(['name' => 'js_min_num'])['value'];
				if ($amount < $config) {
					return returnJson(0, "最低接受金额为{$config}");
				}

				$account->setDec('pos', $amount);
				$account->setInc('pos_total', $amount);
				$demand = $this->putDemand(input('id'), $amount, 2, $geren);
				AccountLog::addCoinLog($user['id'], 1, $amount, '-', "发布接受帮助需求{$demand['id']}");
				$user->save(['accecpt_num' => $amount]);
				break;
			case 2:
				//奖金
				if ($account['point'] < $amount) {
					throw new ServerException('剩余分享奖励不足');
				}
				//跟系统配置的最低匹配金额
				$config = System_config::get(['name' => 'fx_min_num'])['value'];
				if ($amount < $config) {
					return returnJson(0, "最低接受金额为{$config}");
				}

				//查看最后一单的状态
				$list = $order->where('uid', input('post.id'))->where('state in (1,2)')->where('type', 1)->order('create_time desc')->find();
				if ($list->getData('state') == 4) {
					return returnJson(0, "上笔订单没有正常解冻,不能使用分享奖励接受帮助");
				}

				// //判断上个月订单是否达到3单
				// if ($user->getData('up_month_order') < 3) {
				// 	throw new ServerException('上个月的提供帮助不足3次,不能使用分享奖励接受帮助');
				// }


				//判断是否可以体现
				if ($account->getData('pri_account') < 1) {
					return returnJson(0, "可提现次数不足，请提供帮助增加提现次数");
				}

				$level = $user->getData('qz_level') ? $user->getData('qz_level') : $user->getData('level');
				$config = System_config::get(['class' => 'zt_jj', 'name' => $level])['value'];
				$config = $config * $list['amount'];
				if ($amount > $config) {
					throw new ServerException("今日分享奖励最大只能是 $config");
				}
				$account->setDec('point', $amount);
				$account->setInc('point_total', $amount);
				$account->setDec('pri_account',1);
				$demand = $this->putDemand(input('id'), $amount, 2, $geren);
				AccountLog::addCoinLog($user['id'], 3, $amount, '-', "发布接受帮助需求{$demand['id']}");
				$user->save(['accecpt_num' => $amount]);
				break;
			}
			UserActionLog::addActionLog(input('id'), "发布接受帮助需求{$demand['id']}");
			Db::commit();
			return returnJson(1, '发布成功');
		} catch (Exception $e) {
			Db::rollback();
			return returnJson(0, $e->getMessage());
		}
	}

	// 添加需求
	private function putDemand($uid, $amount, $type, $genre, $matchTime = 0) {
		$demand = new OrderDemand();
		return $demand->addDemand($uid, $amount, $type, $genre, $matchTime);
	}

	//需求列表
	public function demand_list() {
		$post = request()->post();
		$page = input("page") ? input("page") : 1;
		$pageSize = 10;
		$p = ($page - 1) * $pageSize . "," . $pageSize;

		$uid = $post['id'];

		$type = $post['type'];
		$orderDemand = new OrderDemand();

		if ($type != 1 && $type != 2) {
			throw new ServerException("type error");
		}

		$flag = $post['flag'];
		switch ($flag) {
		// 全部
		case 1:
			$list = $orderDemand->where('uid', $uid)->where('type', $type)->orderRaw('FIELD(`state`,2,1,3),update_time desc')->limit($p)->select();
			$count = $orderDemand->where('uid', $uid)->where('type', $type)->orderRaw('FIELD(`state`,2,1,3),update_time desc')->count();
			break;
		// 待匹配
		case 2:
			$list = $orderDemand->where('uid', $uid)->where('type', $type)->where('state = 1 or state = 2')->where('on_amount', 0)->where('lock_amount', 0)->limit($p)->orderRaw('FIELD(`state`,2,1),create_time desc')->select();
			$count = $orderDemand->where('uid', $uid)->where('type', $type)->where('state = 1 or state = 2')->where('on_amount', 0)->where('lock_amount', 0)->orderRaw('FIELD(`state`,2,1),create_time desc')->count();
		//	Log::write ('sql',[$orderDemand->getLastSql()]);
			break;
		// 进行中
		case 3:
			$list = $orderDemand->where('uid', $uid)->where('type', $type)->where('state', 2)->where(' on_amount > 0 or lock_amount > 0  ')->limit($p)->orderRaw('FIELD(`state`,2,1,3),update_time desc')->select();
			$count = $orderDemand->where('uid', $uid)->where('type', $type)->where('state', 2)->where(' on_amount > 0 or lock_amount > 0  ')->orderRaw('FIELD(`state`,2,1,3),update_time desc')->count();
			break;
		// 已完成
		case 4:
			$list = $orderDemand->where('uid', $uid)->where('type', $type)->where('state = 1 or state = 3')->orderRaw('FIELD(`state`,2,1,3),update_time desc')->limit($p)->select();
			$count = $orderDemand->where('uid', $uid)->where('type', $type)->where('state = 1 or state = 3')->orderRaw('FIELD(`state`,2,1,3),update_time desc')->count();

			break;

		default:
			throw new ServerException("flag error");
			break;
		}
		$page = ceil($count / $pageSize);
		foreach ($list as $k => $item) {
			$list[$k]['sy_amount'] = $item['amount'] - $item['on_amount'];
			$list[$k]['sy_order_num'] = $item['order_num'];
		}
		return returnJson(1, '获取成功', ['list' => $list, 'page' => $page]);
	}

	//抢单大厅
	public function rob_odd_home() {
		$orderTransaction = new OrderTransaction();
		$user = new User();
		$list = $orderTransaction->where('is_show', 1)->where('is_qg', 0)->select();
		$config = System_config::get(['name' => 'admission_cost_ratio'])['value'];
		foreach ($list as $k => $v) {
			$data = $user->where('id', $v['sell_uid'])->find();
			$list[$k]['info'] = ['name' => $data['nickname'], 'phone' => $data['phone'], 'pic' => $data['picname']];
			$list[$k]['buy_coin'] = $v['amount'] * $config;
		}
		$rule = System_config::get(['name' => 'qz_rule'])['value'];
		$data = [];
		$data['list'] = $list;
		$data['rule'] = $rule;
		$config = System_config::get(['name' => 'pro_remark'])['value'];
		$data['remark'] = $config;
		return returnJson(1, '查询成功', $data);
	}

	//抢单
	public function rob_odd() {
		//新增购买需求
		$user = User::get(input('id'));
		//判断是否激活
		if (!$user['invite']) {
			throw new ServerException('该账户未激活');
		}
		//判断二级密码
		if (!input('paypassword')) {
			throw new ServerException('请输入二级密码');
		}
		if ($user->getData('is_google')) {
			$google2fa = new Google2FA();
			if (input('paypassword') != $google2fa->getCurrentOtp($user->getData('google'))) {
				throw new ServerException('验证码输入错误');
			}
		} else {
			if ($user['paypassword'] != encrypt(input('paypassword'))) {
				throw new ServerException('二级密码错误');
			}
		}

		$useraccount = new UserAccount();
		// 判断是否有设置账户
		$isSet = $useraccount->judgeSetAccountInfo(input('id'));
		if ($isSet) {
			return returnJson(4, '请前往个人中心，完善个人资料.');
		}
		// 判断收购币
		$bl = System_config::get(['name' => 'admission_cost_ratio'])['value'];
		$account = Account::get(input('id'));
		$onBuyCoin = input('amount') * $bl;
		if ($account['buy_coin'] - $onBuyCoin < 0) {
			throw new ServerException('互助币不足');
		}
		//判断今天抢过单
		$model = new OrderDemand();
		$order = $model->where(['uid' => input('id'), 'type' => 1, 'genre' => 3])->whereTime('create_time', 'today')->find();
		if ($order) {
			throw new ServerException('今天已经抢过单,请明天再来');
		}

		//等级判断
		$level = $user->getData('qz_level') ? $user->getData('qz_level') : $user->getData('level');

		$config = System_config::get(['name' => 'qd_level'])['value'];
		if ($level < $config) {
			throw new ServerException('只用c4以上才可以抢单');
		}
		$order = OrderTransaction::get(input('transaction_id'));
		$amount = $order['amount'];
		Db::startTrans();
		try {
			//抢单
			$demand = $this->putDemand(input('id'), $amount, 1, 3, 0);
			// 扣除收购币
			$account->setDec('buy_coin', $onBuyCoin);
			$account->setInc('buy_coin_total', $onBuyCoin);
			// 添加扣除日志
			AccountLog::addCoinLog(input('id'), 4, $onBuyCoin, '-', '抢购大厅抢单消耗');
			$buyDid = $demand['id'];
			$sellDid = $order['sell_did'];
			$server = new MatchServer;
			$server->loadAssignDemand($buyDid, $sellDid);
			$server->setMatchAmount($amount);
			$server->match();
			$server->writeMemory();
			$server->finishData();
			$order->save(['is_show' => 0, 'is_qg' => 1]);
			Db::commit();
			return returnJson(1, '抢单成功');
			//后替换原来订单中的购买方
		} catch (Exception $e) {
			Db::rollback();
			return returnJson(0, '网络错误');
		}

	}

}