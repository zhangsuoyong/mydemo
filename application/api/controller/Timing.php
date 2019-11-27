<?php
//定时
namespace app\api\controller;

use app\common\exception\ServerException;
use app\common\model\Order;
use app\common\model\OrderDemand;
use app\common\model\OrderTransaction;
use app\common\model\System_config;
use app\common\model\User;
use think\Db;
use think\Controller;
use app\common\model\Account;
class Timing extends Controller  {
	
	
	// protected $beforeActionList = [
	 		
	//  //检测访问ip
	//  'authIp'=>['except'=>[]],
	//  //检测请求方式
	// 'is_post'=>['except'=>[]],

	// ];
	
	
	// public function authIp(){
	// 	$ip=request ()->ip ();
		
	// 	if ($ip!='0.0.0.0') {
	// 	 throw new ServerException('拒绝访问');
	// 	} 
	
		
 // }	
	
 //protected function is_post()
 // {
 //   if (!request()->isPost()) {
 //     throw  new ServerException('请求方式错误');
 //   }
 // }

	// 购买方过期
	public function buy_due() {
		$orderTransaction = new OrderTransaction();
		Db::startTrans();
		try {
			$config = System_config::get(['name' => 'pay_all_time'])['value'];
			$list = $orderTransaction->where(" date_add(create_time,interval $config hour) < now() ")->where('pay_time is null')->where('is_lock', 2)->where('state', 3)->select();
			foreach ($list as $key => $value) {
				$this->due(1, $value['buy_uid'], $value['buy_did'], $value['sell_uid'], $value['sell_did'], $value['amount'], $value['id']);
			}
			Db::commit();
		} catch (\Exception $e) {
			Db::rollback();
			return returnJson(0, '购买方过期操作失败');
		}
		return returnJson(1, '购买方过期操作成功');
	}

	// 放行方过期
	public function sell_due() {
		$orderTransaction = new OrderTransaction();
		Db::startTrans();
		try {
			$config = System_config::get(['name' => 'confirm_time'])['value'];
			$list = $orderTransaction->where(" date_add(pay_time,interval $config hour) < now()")->where('confirm_time is not null')->where('is_lock', 2)->where('state', 2)->select();
			foreach ($list as $key => $value) {
				$this->due(2, $value['buy_uid'], $value['buy_did'], $value['sell_uid'], $value['sell_did'], $value['amount'], $value['id']);
			}
			Db::commit();
		} catch (\Exception $e) {
			Db::rollback();
			return returnJson(0, '出售方过期操作失败');
		}
		return returnJson(1, '出售方过期操作成功');
	}

	// 过期处理
	private function due($type, $buy_uid, $buy_did, $sell_uid, $sell_did, $amount, $transactionId) {
		$orderTransaction = new OrderTransaction();
		$orderDemand = new OrderDemand();
		$user = new User();
		$order = new Order();
		switch ($type) {
		// 买方封号
		case 1:
			// 用户冻结
			User::update(['is_lock' => 1], ['id' => $buy_uid]);
			// 更新购买需求订单
			$orderDemand->editDemandOnAmount($buy_did, $amount, 2);
			// 更新出售需求订单
			$orderDemand->editDemandOnAmount($sell_did, $amount, 2);
			// 更新交易状态
			$orderTransaction->where('id', $transactionId)->update(['state' => 4, 'is_lock' => 2]);
			break;
		// 卖方封号
		case 2:
			// 用户冻结
			User::update(['is_lock' => 1], ['id' => $sell_uid]);
			// 更新购买需求订单
			$orderDemand->editDemandOnAmount($buy_did, $amount, 1);
			// 更新出售需求订单
			$orderDemand->editDemandOnAmount($sell_did, $amount, 1);
			// 更新交易状态
			$orderTransaction->where('id', $transactionId)->update(['state' => 1, 'is_lock' => 2, 'confirm_time' => date('Y-m-d H:i:s', time())]);
			$demandNum = $orderDemand->where('id', $buy_did)->value('demand_num');
			$orderDemand_new = OrderDemand::get($buy_did);
			// 添加订单
			if ($orderDemand_new->getData('state') == 1) {
				$int = $this->order_timeout($orderDemand_new);
				if ($orderDemand_new->getData('genre') == 3) {
					$day = 0;
				} else {
					$day = 1;
				}
  	 		if ($orderDemand_new->getData('genre') == 4) {
            User::update(['is_valid' => 1], ['id' => $buy_uid]);
          }
				     $account=Account::get($buy_did);
            $account->setInc('pri_account',1);
				// 生成订单
				$order->addOrder($buy_uid, $amount, $demandNum, $int, $day);
			}
//        //发短信
			//        $transaction = $orderTransaction->where('id', $transactionId)->find();
			//        $server = new yzmServer();
			//        $user = User::get($buy_uid);
			//        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')}已完成。");
			//        $user = User::get($sell_uid);
			//        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')}已完成。");
			break;
		default:
			throw new ServerException("Error Processing Request");
			break;
		}
	}

	//订单完成标志
	public function pos_timing() {
		$order = new Order();
		$config = System_config::get(['name' => 'fj_flag'])['value'];
		if ($config) {
			$order->query('update tkf_order set next_gain_time=date(next_gain_time)+1 where state=1');
		}
		$order->query('UPDATE tkf_order set state = 2 where state=1 and next_gain_time <= CURDATE()');
		$order->commit();
		return returnJson(1, 1);
	}

	//系统日志
	public function system_log() {
		$user = new User();
		$demand = new OrderDemand();
		$data = array();
		$data['provide_help'] = $demand->where('type', 1)->where('state in (1,2)')->sum('amount');
		$data['provide_help_new'] = $demand->whereTime('create_time', 'today')->where('type', 1)->sum('amount');
		$data['receive_help'] = $demand->where('type', 2)->where('state in (1,2)')->sum('amount');
		$data['receive_help_new'] = $demand->whereTime('create_time', 'today')->where('type', 2)->sum('amount');
		$data['member'] = $user->count();
		$data['member_new'] = $user->where('state', 1)->whereTime('create_time', 'today')->count();
		//时间
		$data['create_time'] = date('Y-m-d');
		model('system_log')->insert($data);
	}

	//检测订单超时 返回bl
	public function order_timeout(OrderDemand $orderdemant) {
		$list = $this->orderTransaction->where('buy_did', $orderdemant['id'])->select();
		$int = System_config::get(['name' => 'hzjf_bl'])['value'];
		$config = System_config::get(['name' => 'pay_zc_time'])['value'];
		foreach ($list as $item) {
			$time = strtotime("+ $config hours", $item['create_time']);
			if ($item['pay_time'] > $time) {
				$int = System_config::get(['name' => 'cs_hzjf_bl'])['value'];
				break;
			}
		}
		return $int;
	}

	//更新用户上个月的订单数
	public function user_order() {
		// $user = new User();
		// $orderDemand = new OrderDemand();
		// $list = $user->field('u.id id')->alias('u')->join('order_demand o', 'u.id=o.uid and o.genre = 4 and o.type =1', 'left')->where('day( IFNULL(o.create_time,u.create_time))=day(now())')
		// 	->where('date(ifnull(o.create_time,u.create_time)) != date(now())')->select();
		// // $list = $user->where('day(create_time)=day(now())')->where('date(create_time) != date(now())')->select();
		// foreach ($list as $item) {
		// 	$count = $orderDemand->where('uid', $item['id'])->where('type', 1)->where(' date(create_time) >=  date(DATE_ADD(now(),INTERVAL -1 MONTH)) ')->count();
		// 	$user->update(['up_month_order' => $count], ['id' => $item['id']]);
		// }
		Account::query('update tkf_account set pri_account =0 ');
		return returnJson(1, 1);
	}

//  public function turnc_action_coin()
	//  {
	//     $model=new Account();
	//     $model->query('update  tkf_account set action_coin =0 ');
	//     $model->commit();
	//     return returnJson(1,1);
	//  }

//  public function up_team()
	//  {
	//    $list=['ww6888331@163.com',
	//        'ctk00002@163.com',
	//        'ctk00001@163.com',
	//        'ctk00003@163.com',
	//        '13700599775',
	//        '13613594339',
	//        '15296762406',
	//        '15735364442',
	//        '15135922451',
	//        '19926114154@163.com',
	//        '6826207@qq.com',
	//        '17635924337',
	//        '17703598716'];
	//    $server = new UserServiceImpl();
	//    $model=new User();
	//    foreach ($list as $item){
	//      $user=$model->where('user',$item)->find();
	//      $server->team_up($user->getData('id'));
	//    }
	//    return returnJson(1,1);
	//  }
	
	
	
	
		public function jianCeFangJia(){
		
			//是否是放假状态,1放假 0正常
		$status=\app\common\model\System_config::get(['name' => 'fj_flag'])['value'];
	
		if($status){
			//获取进行中的订单,
			$orders=Order::where('state','=',1)->select();
				
		foreach($orders as $order){
			//定时任务每日0点天数加一
			$order->next_gain_time=date('Y-m-d',strtotime("$order->next_gain_time + 1 days"));
			$order->save();
		   }
		}
		
	}
	

	function test() {
		$user = User::get(['id' => 6]);
		//等级判断
		$level = $user->getData('qz_level') ? $user->getData('qz_level') : $user->getData('level');

		$config = System_config::get(['name' => 'qd_level'])['value'];

		if ($level < $config) {
			throw new ServerException('只用c4以上才可以抢单');
		}
		return returnJson(1, 1, $level);

	}
}
