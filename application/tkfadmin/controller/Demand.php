<?php

namespace app\tkfadmin\controller;

use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\AdminLog;
use think\Log;
use app\common\model\Chat;
use app\common\model\Order;
use app\common\model\OrderDemand;
use app\common\model\OrderSh;
use app\common\model\OrderTransaction;
use app\common\model\OrderTransactionMemory;
use app\common\model\System_config;
use app\common\model\TransactionPart;
use app\common\model\User;
use app\common\model\UserActionLog;
use app\common\server\MatchServer;
use app\common\server\yzmServer;
use app\common\serviceimpl\UserServiceImpl;
use think\Db;
use think\Exception;
use think\Request;
use app\common\model\Robot;
use app\common\model\RobotLogs;


class Demand extends Base
{

    protected $orderDemand;
    protected $orderTransaction;
    protected $orderTransactionMemory;
    protected $order;
    protected $request;
    protected $adminLog;
    protected $chat;
    protected $jPush;
    protected $sms;
    protected $systemConfig;
    protected $userCoin;
    protected $userCoinLog;
    protected $user;
    protected $transactionPart;
    protected $orderSh;

    public function __construct(OrderDemand $orderDemand, OrderSh $orderSh, OrderTransactionMemory $orderTransactionMemory, OrderTransaction $orderTransaction, \app\common\model\Order $order, AdminLog $adminLog, Chat $chat, System_config $systemConfig, AccountLog $userCoinLog, Account $userCoin, \app\common\model\User $user)
    {
        parent::__construct();
        $this->orderDemand = $orderDemand;
        $this->orderTransactionMemory = $orderTransactionMemory;
        $this->orderTransaction = $orderTransaction;
        $this->order = $order;
        $this->request = Request::instance();
        $this->adminLog = $adminLog;
        $this->chat = $chat;
        $this->systemConfig = $systemConfig;
        $this->userCoinLog = $userCoinLog;
        $this->userCoin = $userCoin;
        $this->user = $user;
        $this->transactionPart = new TransactionPart();
        $this->orderSh = $orderSh;
    }

    // 订单列表
    public function order_list()
    {
        $where = [];
        $query = input('param.');
        if (input('get.user')) {
            $where['b.user|b.nickname|a.id|a.uid'] = ['like', input('get.user')];
            $query['user'] = input('get.user');
        } else {
            $query['user'] = '';
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

        if (input('?get.type') && !empty(input('get.type'))) {
            $where['a.type'] = ['like', input('get.type')];
            $query['type'] = input('get.type');
        }

        $list = $this->order->getList($where, $query)->each(function ($item) {
            $int = (strtotime($item['next_gain_time']) - strtotime(date("Y-m-d"))) / 3600 / 24 + 1;
            if ($int > 0) {
                $item['day'] = $int;
            } else {
                $item['day'] = 0;
            }
            return $item;
        });
        $this->assign('list', $list);
        $this->assign($query);
        return $this->fetch();
    }

    // 交易列表
    public function transaction_list()
    {
        $where = [];
        $query = input('param.');
        if (input('get.user')) {
            $where['b.user|b.nickname|a.id|a.buy_uid|a.buy_did|a.sell_uid|a.sell_did|a.transaction_id'] = ['like', input('get.user')];
            $query['user'] = input('get.user');
        } else {
            $query['user'] = '';
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

        if (input('?get.type') && !empty(input('get.type'))) {
            $where['a.type'] = ['like', input('get.type')];
            $query['type'] = input('get.type');
        }

        $list = $this->orderTransaction->getList($where, $query);

        foreach ($list as $key => $value) {
            if (!$value['evidence_path']) {
                $list[$key]['evidence_path'] = null;
            } else {
                $list[$key]['evidence_path'] = $this->request->domain() . '/uploads/' . $value['evidence_path'];
            }
        }
        $this->assign('list', $list);
        $this->assign($query);
        return $this->fetch();
    }

    // 手动匹配列表
    public function manual_match_list()
    {
        ini_set('memory_limit', '1000M');
        $where = [];
        $query = input('param.');
        if (input('demand_id')) {
            $where['o.id'] = input('demand_id');
            $query['id'] = input('demand_id');
        } else {
            $query['id'] = '';
        }
        if (input('nickname')) {
            $where['u.nickname'] = input('nickname');
            $query['nickname'] = input('nickname');
        } else {
            $query['nickname'] = '';
        }
        if (input('real_name')) {
            $where['u.real_name'] = input('real_name');
            $query['real_name'] = input('real_name');
        } else {
            $query['real_name'] = '';
        }
        if (input('user')) {
            $where['u.user'] = input('user');
            $query['user'] = input('user');
        } else {
            $query['user'] = '';
        }

        if (input('start')) {
            $where['o.create_time'] = ['>=', input('start')];
        } else {
            $query['start'] = '';
        }
        if (input('end')) {
            $where['o.create_time'] = ['<=', input('end')];
        } else {
            $query['end'] = '';
        }
        $page = input('get.page') ? ((input('get.page') - 1) * 100) : 0;
        $sellList = $this->orderDemand->alias('o')->join('user u', 'u.id=o.uid')
            ->field('o.*')
            ->where($where)
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('o.is_lock', 2)
            ->where('u.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->limit($page, 100)->select();

        $sellcount = $this->orderDemand->alias('o')->join('user u', 'u.id=o.uid')
            ->field('o.*')
            ->where($where)
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('o.is_lock', 2)
            ->where('u.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->count();

        $buyList = $this->orderDemand->alias('o')->join('user u', 'u.id=o.uid')
            ->field('o.*')
            ->where('u.is_lock', 2)
            ->where($where)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('o.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->limit($page, 100)->select();

        $buycount = $this->orderDemand->alias('o')->join('user u', 'u.id=o.uid')
            ->field('o.*')
            ->where('u.is_lock', 2)
            ->where($where)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('o.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->count();

        $sellAmount = $this->orderDemand->alias('o')->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 2)
            ->where('o.state', 2)->where('o.amount > o.lock_amount + o.on_amount')
            ->where('o.is_lock', 2)->value('sum(o.amount - o.on_amount - o.lock_amount)');
        $buyAmount = $this->orderDemand->alias('o')->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 1)
            ->where('o.state', 2)->where('o.amount > o.lock_amount + o.on_amount')
            ->where('o.is_lock', 2)->value('sum(o.amount - o.on_amount - o.lock_amount)');

        $sellAmount = $sellAmount ? $sellAmount : 0;
        $buyAmount = $buyAmount ? $buyAmount : 0;
        $this->assign('sell_count', $sellAmount);
        $this->assign('buy_count', $buyAmount);
        $this->assign('buy_list', $buyList);
        $this->assign('sell_list', $sellList);
        $this->assign('page', input('get.page') ? input('get.page') : 1);
        $this->assign('buycount', $buycount);
        $this->assign('sellcount', $sellcount);
        $this->assign('item', input('get.item') ? input('get.item') : 0);
        $this->assign($query);

        return $this->fetch();
    }

    // 抢单匹配列表
    public function manual_match_list_q()
    {
        ini_set('memory_limit', '1000M');
        
        
        $page = input('get.page') ? ((input('get.page') - 1) * 100) : 0;
        $sellList = $this->orderTransaction->where('state', 4)->order('is_qg asc')->select();
        $sellcount = $this->orderTransaction->where('state', 4)->order('is_qg asc')->count();
        $sellAmount = $this->orderTransaction->where('state', 4)->where('is_qg', 0)->sum('amount');

        $this->assign('sell_count', $sellAmount);
        $this->assign('sell_list', $sellList);
        $this->assign('page', input('get.page') ? input('get.page') : 1);
        $this->assign('allcount', $sellcount);

        return $this->fetch();
    }

    // 选择匹配
    public function choose_match()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $buyDid = input('buy_did') ? input('buy_did') : 0;
            $sellDid = input('sell_did') ? input('sell_did') : 0;
            $uid = $post['uid'];
            $amount = input('amount');
            $where = [];
            if (isset($post['user'])) {
                $where['b.user|b.id|b.nickname'] = ['like', $post['user']];
            }
            if ($sellDid) {
                $list = $this->orderDemand->field('a.*')->alias('a')->join('user b', 'a.uid = b.id')
                    ->where('a.uid', '<>', $uid)->where('type', 1)->where('a.state', 2)
                    ->where('a.is_lock', 2)->where('a.amount > a.lock_amount + a.on_amount')
                    ->where('b.is_lock', 2)
                    ->where($where)
                    ->select();
            }

            if ($buyDid) {
                $list = $this->orderDemand->field('a.*')->alias('a')
                    ->join('user b', 'a.uid = b.id')
                    ->where('a.uid', '<>', $uid)->where('type', 2)
                    ->where('a.state', 2)->where('a.is_lock', 2)
                    ->where('a.amount > a.lock_amount + a.on_amount')
                    ->where('b.is_lock', 2)
                    ->where($where)
                    ->select();
            }

            $this->assign('list', $list);
            $this->assign('buyDid', $buyDid);
            $this->assign('sellDid', $sellDid);
            $this->assign('amount', $amount);
            if (input('type')) {
                $this->assign('type', input('type'));
            } else {
                $this->assign('type', 0);
            }

            if (input('transaction_id')) {
                $this->assign('transaction_id', input('transaction_id'));
            } else {
                $this->assign('transaction_id', 0);
            }

            return $this->fetch();
        }
    }

    public function choose_finish_match()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();

            $server = new MatchServer;
            $buyDid = $post['buy_did'];
            $sellDid = $post['sell_did'];
            try {
                if (input('type') == 2) {
                    $amount = input('amount');
                    $order = $this->orderDemand->where('id', input('sell_did'))->find();
                    $order->setDec('lock_amount', $amount);
                    $server->setMatchAmount($amount);
                    $orderTransaction = OrderTransaction::get(input('transaction_id'));
                    $orderTransaction->save(['is_show' => 0, 'is_qg' => 1]);
                }

                $res = $server->loadAssignDemand($buyDid, $sellDid);
                if ($res) {
                    throw new ServerException('该用户开启了防撞单功能，并且今天已经匹配了一单');
                }

                $server->match();
                $server->writeMemory();
                $server->finishData();

                //消息推送
                $sms = new yzmServer();
                $pushUids = $server->getPushUids();
                foreach ($pushUids as $key => $value) {
                    $user = model('user')->where('id', $value)->find();
                    try {
                        $sms->sendSmsMatchFinish($user['phone'], $user->getData('nickname'));
                        sleep(1);
                    } catch (Exception $e) {

                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof ServerException) {
                    return returnJson(0, $e->getMessage());
                }
                return returnJson(0, '匹配失败');
            }
            return returnJson(1, '匹配成功');
        }
    }

    // 需求列表
    public function demand_list()
    {
        $where = [];
        $numwhere = [];
        $query = input('param.');
        //搜索分区
        if(input('get.part')){
        	$where['b.user_part']=input('get.part');
        }
        
        if (input('get.user')) {
            $where['b.user|b.nickname|a.id|a.uid|a.demand_id'] = input('get.user');
            $query['user'] = input('get.user');
        } else {
            $query['user'] = input('get.user');
        }

        if (input('?param.uid')) {
            $where['a.uid'] = input('param.uid');
            $numwhere['uid'] = input('param.uid');
            $query['uid'] = input('param.uid');
        }

        if (input('get.type') && !empty(input('get.type'))) {
            $where['a.type'] = ['=', input('get.type')];
            $query['type'] = input('get.type');
        } else {
            $query['type'] = input('get.type');
        }

        if (input('get.genre') && !empty(input('get.genre'))) {
            $where['a.genre'] = ['=', input('get.genre')];
            $query['genre'] = input('get.genre');
        } else {
            $query['genre'] = input('get.genre');
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

        $where['type'] = 2;
        $query['item'] = 0;
        $buyList = $this->orderDemand->alias('a')->field('a.*, b.id userid,b.user,b.nickname name,b.real_name')->join('user b', 'a.uid = b.id', 'left')->where($where)->orderRaw('FIELD(`a`.`state`,2,1,3),create_time asc')->paginate(20, false, [
            'var_page' => 'buy_page',
            'query' => $query,
        ]);
        $sql=OrderDemand::getLastSql();
        Log::write($sql);
        foreach ($buyList as $k => $v) {
		    $flag = $this->transactionPart->where('id', $v['id'])->find();
		    if ($flag) {
			    $buyList[$k]['flag'] = 1;
		    } else {
			    $buyList[$k]['flag'] = 0;
		    }
	    }
        $where['type'] = 1;
        $query['item'] = 1;
        // $sellList = $this->orderDemand->getList($where, $query);
        $sellList = $this->orderDemand->alias('a')->field('a.*, b.id userid,b.user,b.nickname name,b.real_name')->join('user b', 'a.uid = b.id', 'left')->where($where)->orderRaw('FIELD(`a`.`state`,2,1,3),create_time asc')->paginate(20, false, [
            'var_page' => 'sell_page',
            'query' => $query,
        ]);
        foreach ($sellList as $k => $v) {
		    $flag = $this->transactionPart->where('id', $v['id'])->find();
		    if ($flag) {
			    $sellList[$k]['flag'] = 1;
		    } else {
			    $sellList[$k]['flag'] = 0;
		    }
	    }

        $completed = $this->orderDemand->where($numwhere)->where('state', 1)->count();
        $uncompleted = $this->orderDemand->where($numwhere)->where('state', 2)->count();
        $robAmount = $this->systemConfig->getParamValue('rob_amount');
        $onRobAmount = $this->systemConfig->getParamValue('on_rob_amount');

        $this->assign('completed', $completed);
        $this->assign('uncompleted', $uncompleted);
        $this->assign('robAmount', $robAmount);
        $this->assign('onRobAmount', $onRobAmount);
        $this->assign('buy_list', $buyList);
        $this->assign('sell_list', $sellList);
        $item = 0;
        if ($this->request->has('item', 'get')) {
            $item = $this->request->get('item');
        }
        $this->assign($query);
        $this->assign('item', $item);
        return $this->fetch();
    }
    
    
    
    public function robot_list(Robot $robot)
	{
		
	
		$where = [];
		$query = input('param.');
		// var_dump($query['start']);
		// die;
		if($query){
			$account=$query['start'];
		}else{
			$account='';
		}
		
	
		if (preg_match('/^1\d{10}$/',$account)||preg_match('/^[A-Za-z0-9]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/',$account)) {
			$user=User::get(['user'=>$account]);
		} else {
			$user=User::get($account);
		}
		
		if ($user) {
			
			$where=['uid'=>$user->id,'status'=>1];
		}else {
			$where=['status'=>1];
		}
		
		
		
		$list = $robot->getList ($where,$query);
		
		
	
		return view('robot_list', ['list' => $list]);
	}

	public function robotLogs_list(RobotLogs $robotLogs)
	{

	
			$where = [];
		$query = input('param.');
		// var_dump($query['start']);
		// die;
		if($query){
			$account=$query['start'];
		}else{
			$account='';
		}
		
	
		if (preg_match('/^1\d{10}$/',$account)||preg_match('/^[A-Za-z0-9]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/',$account)) {
			$user=User::get(['user'=>$account]);
		} else {
			$user=User::get($account);
		}
		
		if ($user) {
			
			$where=['rid'=>$user->id];
		}else {
			$where=[];
		}
		
			
		
		$list = $robotLogs->getList($where, $query);
		return view('robotLogs_list', ['list' => $list]);
	}

    // 匹配列表
    public function match_list()
    {
        ini_set('memory_limit', '1000M');
        $date = date('Y-m-d');
        $page = input('get.page') ? ((input('get.page') - 1) * 100) : 0;
        $account=input('get.account');
        $name=input('get.name');
        $order_num=input('get.order_num');
        	if (preg_match('/^1\d{10}$/',$account)||preg_match('/^[A-Za-z0-9]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/',$account)) {
			$where=['u.user'=>$account];
		} elseif($account) {
			$where=['u.id'=>$account];
		}else{
			$where=[];
		}
		
		if(input('get.part')){
			$where['u.user_part']=input('get.part');
		}
		if($name){
			$where['u.nickname|u.real_name']=$name;	
		}
    	if($order_num){
    	$where['o.demand_id']=$order_num;
    	}
    
    	
    	
    	
    	// var_dump($where);
    	// die;

        
        
       
        $sellList = $this->orderDemand->alias('o')
            ->join('user u ', 'u.id =o.uid')
            ->field('o.*')
            ->where('o.type', 2)
            ->where($where)
            ->where('o.state', 2)
            ->where('u.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time')->limit($page, 100)->select();

        foreach ($sellList as $k => $v) {
            $flag = $this->transactionPart->where('id', $v['id'])->find();
            if ($flag) {
                $sellList[$k]['flag'] = 1;
            } else {
                $sellList[$k]['flag'] = 0;
            }
        }
        $sellCount = $this->orderDemand->alias('o')
            ->join('user u ', 'u.id =o.uid')
            ->field('o.*')
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('u.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time')->count();
        $buyList = $this->orderDemand->alias('o')
            ->join('user u ', 'u.id =o.uid')
            ->field('o.*')
             ->where($where)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('u.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->limit($page, 100)->select();
        foreach ($buyList as $k => $v) {
            $flag = $this->transactionPart->where('id', $v['id'])->find();
            if ($flag) {
                $buyList[$k]['flag'] = 1;
            } else {
                $buyList[$k]['flag'] = 0;
            }
        }
        $buyCount = $this->orderDemand->alias('o')
            ->join('user u ', 'u.id =o.uid')
            ->field('o.*')
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('u.is_lock', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->count();

        $sellAmount = $this->orderDemand->alias('o')
            ->join('user u ', 'u.id =o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->where('o.is_lock', 2)
            ->value('sum(o.amount - o.on_amount - o.lock_amount)');
        $buyAmount = $this->orderDemand->alias('o')
            ->join('user u ', 'u.id =o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->where('o.is_lock', 2)
            ->value('sum(o.amount - o.on_amount - o.lock_amount)');

        $sellAmount = $sellAmount ? $sellAmount : 0;
        $buyAmount = $buyAmount ? $buyAmount : 0;
        $this->assign('sell_count', $sellAmount);
        $this->assign('buy_count', $buyAmount);
        $this->assign('sell_list', $sellList);
        $this->assign('buy_list', $buyList);

        $this->assign('page', input('get.page') ? input('get.page') : 1);
        $this->assign('buycount', $buyCount);
        $this->assign('sellcount', $sellCount);
        $this->assign('item', input('get.item') ? input('get.item') : 0);

        return $this->fetch();
    }

    // 获取交易信息列表
    public function transaction_message_list()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $transactionId = $post['transaction_id'];
            $type = $post['type'];

            $transaction = $this->orderTransaction->where('id', $transactionId)->find();

            if ($type == 1) {
                $list = $this->chat->where('transaction_id', $transactionId)->where('type = 1 or type = 2')->order('create_time')->select();
            } else {
                $list = $this->chat->where('transaction_id', $transactionId)->where('type', $type)->order('create_time')->select();
            }

            foreach ($list as $key => $value) {
                if ($value['type']['value'] == 2) {
                    if (!$value['img']) {
                        $list[$key]['img'] = null;
                    } else {
                        $list[$key]['img'] = $this->request->domain() . '/uploads/' . $value['img'];
                    }
                }

                if ($uid = $transaction['buy_uid']) {
                    $list[$key]['user_name'] = $transaction['buy_user_name'];
                    $list[$key]['user_cate'] = '买家';
                }

                if ($uid = $transaction['sell_uid']) {
                    $list[$key]['user_name'] = $transaction['sell_user_name'];
                    $list[$key]['user_cate'] = '卖家';
                }
            }

            $this->assign('list', $list);
            return $this->fetch();
        }
    }

    // 临时匹配
    public function temporary_match_list()
    {
        ini_set('memory_limit', '1000M');
        $server = new MatchServer();

        if ($this->request->has('amount', 'get')) {
            $amount = $this->request->get('amount');
            $server->setMatchAmount($amount);
        }
        $server->loadDemand();
        $server->match();
        $server->writeMemory();
        $transactionList = $this->orderTransactionMemory->paginate(99999);
        $this->adminLog->add_log('操作临时匹配');
        $matchAmount = $this->orderTransactionMemory->sum('amount');
        $matchTransactionNum = $this->orderTransactionMemory->count();
        $this->assign('match_amount', $matchAmount);
        $this->assign('match_transaction_num', $matchTransactionNum);
        $this->assign('list', $transactionList);
        return $this->fetch();
    }

    // 完成匹配
    public function finish_match()
    {
        $server = new MatchServer;
        try {
            $server->finishData();
            $this->adminLog->add_log('完成匹配');

            $sms = new yzmServer();
            $pushUids = $server->getPushUids();
            foreach ($pushUids as $key => $value) {
                $user = model('user')->where('id', $value)->find();
                try {
                    $sms->sendSmsMatchFinish($user['phone'], $user->getData('nickname'));
                } catch (Exception $e) {

                }

            }
        } catch (\Exception $e) {
            return returnJson(0, '匹配失败');
        }
        return returnJson(1, '匹配完成');
    }

    // 修改需求订单状态
    public function set_demand_match_state()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $demandId = $post['demand_id'];
            $matchState = $post['state'];

            Db::startTrans();
            try {
                $this->orderDemand->where('id', $demandId)->update(['match_state' => $matchState]);

                // 添加管理员日志
                if ($matchState == 1) {
                    $this->adminLog->add_log('设置需求[' . $demandId . ']为[可匹配]状态');
                }

                if ($matchState == 2) {
                    $this->adminLog->add_log('设置需求[' . $demandId . ']为[不可匹配]状态');
                }
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
            return returnJson(1, '操作成功');
        }
    }

    public function recover_demand_state()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $this->orderDemand->where('state', 2)->where('amount > lock_amount + on_amount')->where('match_time <= CURDATE() or match_state = 1')->where('is_lock', 1)->update(['is_lock' => 2]);
                $this->orderDemand->where('state', 2)->where('genre', '<', 4)->where('amount > lock_amount + on_amount')->where('match_time <= CURDATE() or match_state = 1')->where('sort', 9999)->update(['sort' => 0]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
                return returnJson(0, '操作失败');
            }
            return returnJson(1, '操作成功');
        }
    }

    // TODO::取消需求
    public function cancel_demand()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $demandId = $post['demand_id'];
            Db::startTrans();
            try {
                $demand = $this->orderDemand->where('id', $demandId)->lock(true)->find();

                if ($demand['state']['value'] == 3) {
                    return returnJson(0, '该需求已取消');
                }

                if ($demand['state']['value'] == 1) {
                    return returnJson(0, '该需求已完成');
                }

                if ($demand->getData('lock_amount') != 0) {
                    return returnJson(0, '该需求还有未完成的订单不能取消');
                }

                $this->orderDemand->where('id', $demandId)->update(['state' => 3]);

                if ($demand['type']['value'] == 2) {
                    $uid = $demand['uid'];
                    $amount = $demand['amount'] - $demand['on_amount'];
                    $this->userCoin->where('uid', $uid)->setInc('pos', $amount);
                    $this->userCoinLog->addCoinLog($uid, 1, $amount, '+', '系统取消需求');
                }
                $this->adminLog->add_log('取消需求(id)[' . $demandId . '](参考号)[' . $demand['demand_id'] . ']');
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return returnJson(0, '操作失败');
            }
            return returnJson(1, '操作成功');
        }
    }

    // 取消交易
    public function cancel_transaction()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $transactionId = $post['transaction_id'];
            Db::startTrans();
            try {
                $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

                if ($transaction['state']['value'] == 1) {
                    return returnJson(0, '该交易已放行');
                }

                if ($transaction['state']['value'] == 4) {
                    return returnJson(0, '该交易已取消');
                }

                // 更新购买需求订单
                $this->orderDemand->editDemandOnAmount($transaction['buy_did'], $transaction['amount'], 2);

                // 更新出售需求订单
                $this->orderDemand->editDemandOnAmount($transaction['sell_did'], $transaction['amount'], 2);
                // 修改交易状态
                $this->orderTransaction->where('id', $transactionId)->update(['state' => 4, 'is_lock' => 2]);

                // 添加操作日志
                $this->adminLog->add_log('取消交易(id)[' . $transaction['id'] . '(参考号)[' . $transaction['transaction_id'] . ']');

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
                return returnJson(0, '操作失败');
            }

            return returnJson(1, '操作成功');
        }
    }

    // 放行交易
    public function release_transaction()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $transactionId = $post['transaction_id'];

            Db::startTrans();
            try {
                $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

                if ($transaction['state']['value'] == 1) {
                    return returnJson(0, '该交易已放行');
                }

                if ($transaction['state']['value'] == 4) {
                    return returnJson(0, '该交易已取消');
                }

                // 更新购买需求订单
                $this->orderDemand->editDemandOnAmount($transaction['buy_did'], $transaction['amount']);

                // 更新出售需求订单
                $this->orderDemand->editDemandOnAmount($transaction['sell_did'], $transaction['amount']);

                // 修改交易状态
                $this->orderTransaction->where('id', $transactionId)->update(['state' => 1, 'is_lock' => 2, 'confirm_time' => date('Y-m-d H:i:s', time())]);

                // 添加操作日志
                $this->adminLog->add_log('放行交易(id)[' . $transaction['id'] . '(参考号)[' . $transaction['transaction_id'] . ']');

                // 更新上级等级
                $pid = $this->user->where('id', $transaction['buy_uid'])->value('pid');
                // ===
                $demandNum = $this->orderDemand->where('id', $transaction['buy_did'])->value('demand_num');
                $orderdemant = OrderDemand::get($transaction['buy_did']);

                if ($orderdemant->getData('state') == 1) {
                    $int = $this->order_timeout($orderdemant);
                    if ($orderdemant->getData('genre') == 3) {
                        $day = 0;
                    } else {
                        $day = 1;
                    }

                    $account = Account::get($transaction['buy_uid']);
                    $account->setInc('pri_account', 1);

                    if ($orderdemant->getData('genre') == 4) {
                        User::update(['is_valid' => 1], ['id' => $transaction['buy_uid']]);
                    }
                    // 生成订单
                    $this->order->addOrder($transaction['buy_uid'], $orderdemant['amount'], $demandNum, $int, $day);
                }
                post('', $this->request->domain() . ":3000/refresh");
                //发短信
                //        $server = new yzmServer();
                //        $user = User::get($transaction['buy_uid']);
                //        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')}已完成。");
                //        $user = User::get($transaction['sell_uid']);
                //        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')}已完成。");

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
                return returnJson(0, '操作失败');
            }
            return returnJson(1, '操作成功');
        }
    }

    // 修改需求优先级
    public function update_demand_priority()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $demandId = $post['demand_id'];
            $proproty = $post['priority'];

            $demand = $this->orderDemand->where('id', $demandId)->find();
            if ($demand['user_lock'] == 1) {
                return returnJson(0, '用户已被锁定--无法操作');
            }

            $sort = 0;
            if ($proproty == 9999) {
                $this->adminLog->add_log('[恢复]优先级-需求(id)[' . $demandId . ']');
                $sort = 0;
            }

            if ($proproty == 0) {
                $this->adminLog->add_log('[提升]优先级-需求(id)[' . $demandId . ']');
                $sort = 9999;
            }
            $isTrue = $this->orderDemand->where('id', $demandId)->setField('sort', $sort);

            if ($isTrue) {
                return returnJson(1, '操作成功');
            } else {
                return returnJson(0, '操作失败');
            }
        }
    }

    // 修改需求锁定状态
    public function update_demand_lock_state()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $demandId = $post['demand_id'];
            $state = $post['is_lock'];

            $demand = $this->orderDemand->where('id', $demandId)->find();
            if ($demand['user_lock'] == 1) {
                return returnJson(0, '用户已被锁定--无法操作');
            }

            $isLock = 0;
            if ($state == 2) {
                $isLock = 1;
            }

            if ($state == 1) {
                $isLock = 2;
            }

            $isTrue = $this->orderDemand->where('id', $demandId)->setField('is_lock', $isLock);

            if ($isTrue) {
                if ($state == 2) {
                    $this->adminLog->add_log('[禁止]匹配-需求(id)[' . $demandId . ']');
                }

                if ($state == 1) {
                    $this->adminLog->add_log('[恢复]匹配-需求(id)[' . $demandId . ']');
                }
                return returnJson(1, '操作成功');
            } else {
                return returnJson(0, '操作失败');
            }
        }
    }

    // 设置抢购额度
    public function set_rob_amount()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $amount = $post['amount'];
            $isTrue = $this->systemConfig->where('name', 'rob_amount')->setField('value', $amount);

            if ($isTrue) {
                $this->adminLog->add_log('设置抢购额度[' . $amount . ']');
                return returnJson(1, '操作成功');
            } else {
                return returnJson(0, '操作失败');
            }

        }
    }

    //检测订单超时 返回bl
    public function order_timeout(OrderDemand $orderdemant)
    {
        $list = $this->orderTransaction->where('buy_did', $orderdemant['id'])->select();
        $int = System_config::get(['name' => 'hzjf_bl'])['value'];
        $config = System_config::get(['name' => 'pay_zc_time'])['value'];
        foreach ($list as $item) {
            $time = date_add(date_create($item['create_time']), date_interval_create_from_date_string("$config hours"));
            if (strtotime($item['pay_time']) > strtotime(date_format($time, "Y-m-d H:i:s"))) {
                $int = $int - System_config::get(['name' => 'cs_hzjf_bl'])['value'];
                break;
            }
        }
        return $int;
    }

    //抢购列表
    public function on_home()
    {
        if (!input('id')) {
            throw new ServerException('请选择订单');
        }
        Db::startTrans();
        try {
            $new = OrderTransaction::get(input('id'));
            if ($new->getData('is_show') == 1) {
                $new->save(['is_show' => 0]);
            } else {
                $new->save(['is_show' => 1]);
            }
            Db::commit();
            return returnJson(1, '更改成功');
        } catch (Exception $e) {
            Db::rollback();
            throw new ServerException('网络错误');
        }
    }

    //订单释放
    public function order_thaw()
    {
        $order = new Order();
        $server = new UserServiceImpl();
        $list = $order->where('id', input('post.id'))->find();
        Db::startTrans();
        try {
            $account = Account::get($list->getData('uid'));
            //加日志
            $account->setInc('pos', ($list['amount'] + $list['profit']));
            AccountLog::addCoinLog($list->getData('uid'), 1, ($list['amount'] + $list['profit']), '+', '解冻');
            //送通证
            $config = System_config::get(['name' => 'pass_card'])['value'];
            $num = doubleval(doubleval($list['amount']) / doubleval($config));
            $account->setInc('pass_card', $num);
            AccountLog::addCoinLog($list->getData('uid'), 6, $num, '+', '提供帮助送通证');
            //扣除冻结资产
            $account->setDec('pow', ($list['amount'] + $list['profit']));

            //返利
            $user = User::get($list->getData('uid'));
            $user->save(['is_valid' => 1]);

            $server->team_up($user->getData('pid'));
            if ($list->getData('day')) {
                $this->rebate($user, 1, $list['profit']*0.8, $user->getData('nickname'));
            }
            //更改订单状态
            $list->save(['state' => 3]);
            UserActionLog::addActionLog($user['id'], "解冻资产");
            Db::commit();
            return returnJson(1, '解冻成功');
        } catch (Exception $e) {
            Db::rollback();
            return returnJson(0, $e->getMessage());
        }
    }


    function order_cancel()
    {
        $order = new Order();

        $ids = array_filter(explode(',', input('id')));
        if (count($ids) < 1) {
            throw new ServerException('请选中要取消的订单');
        }
        Db::startTrans();
        try {
            foreach ($ids as $id) {
                $list = $order->where('id', $id)->find();
                $account = Account::get($list->getData('uid'));
                $account->setDec('pow', ($list['amount'] + $list['profit']));
                $list->save(['state' => 5]);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            return returnJson(0, "$id 释放失败，程序终止");
        }
        return returnJson(1, '取消释放成功');

    }

    //订单释放
    public function order_thaw_all()
    {
        $order = new Order();
        $server = new UserServiceImpl();

        $ids = array_filter(explode(',', input('id')));
        foreach ($ids as $id) {
            Db::startTrans();
            try {
                $list = $order->where('id', $id)->lock(true)->find();

                $account = Account::get($list->getData('uid'));
                //加日志
                $account->setInc('pos', ($list['amount'] + $list['profit']));
                AccountLog::addCoinLog($list->getData('uid'), 1, ($list['amount'] + $list['profit']), '+', '解冻');
                //送通证
                $config = System_config::get(['name' => 'pass_card'])['value'];
                $num = doubleval(doubleval($list['amount']) / doubleval($config));
                $account->setInc('pass_card', $num);
                AccountLog::addCoinLog($list->getData('uid'), 6, $num, '+', '提供帮助送通证');
                //扣除冻结资产
                $account->setDec('pow', ($list['amount'] + $list['profit']));

                //返利
                $user = User::get($list->getData('uid'));
                $user->save(['is_valid' => 1]);

                $server->team_up($user->getData('pid'));
                if ($list->getData('day')) {
                    $this->rebate($user, 1, $list['profit'], $user->getData('nickname'));
                }
                //更改订单状态
                $list->save(['state' => 3]);
                UserActionLog::addActionLog($user['id'], "解冻资产");
                Db::commit();

            } catch (Exception $e) {
                Db::rollback();
                return returnJson(0, "$id 释放失败，程序终止");
            }
        }
        return returnJson(1, '释放成功');
    }

    //返利
    public function rebate($user, $int, $amount, $name)
    {
        //查找上级
        $userF = User::get($user->getData('pid'));
        if (!$userF) {
            return;
        }
        //查询用户等级
        $level = $userF->getData('qz_level') ? $userF->getData('qz_level') : $userF->getData('level');
        $level_u = $user->getData('qz_level') ? $user->getData('qz_level') : $user->getData('level');
        //判断是否是有效会员
        if ($userF->getData('is_valid') && $userF->getData('is_lock') == 2) {
            //当父级等级大于自己等级时进行返利
            //      if ($int == 1 || $level > $level_u) {
            //查询用户能拿几代
            $config = System_config::get(['name' => $level, 'class' => 'zt_ds'])['value'];
            //如果代数大于或等于当前代数则获取返利
            if ($config >= $int) {
                //获取当前代数的比例
                $num = System_config::get(['name' => $level, 'class' => 'zt_bl'])['value'];
                //计算返利金额
                $rebate_num = $amount * $num;
                //增加金额
                $account = Account::get($userF['id']);
                $account->setInc('point', $rebate_num);
                AccountLog::addCoinLog($userF['id'], 3, $rebate_num, '+', "第{$int}代 用户{$name} 解冻返利");
                //计算剩余金额
                $amount = $amount - $rebate_num;
//        }
            }
        }
        $int = $int + 1;
        $this->rebate($userF, $int, $amount, $name);
    }

    //匹配池列表
    public function match_part()
    {

        $page = input('get.page') ? ((input('get.page') - 1) * 100) : 0;
        $date = date('Y-m-d');
        $sellList = $this->orderDemand->alias('o')
            ->field('o.*')
            ->join('transaction_part t', 't.id=o.id')
            ->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc ')->limit($page, 100)->select();
        $sellcount = $this->orderDemand->alias('o')
            ->field('o.*')
            ->join('transaction_part t', 't.id=o.id')
            ->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc ')->count();

        $buyList = $this->orderDemand->alias('o')
            ->field('o.*')
            ->join('transaction_part t', 't.id=o.id')
            ->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc ')->limit($page, 100)->select();

        $buycount = $this->orderDemand->alias('o')
            ->field('o.*')
            ->join('transaction_part t', 't.id=o.id')
            ->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc ')->count();

        $sellAmount = $this->orderDemand->alias('o')
            ->field('o.*')
            ->join('transaction_part t', 't.id=o.id')
            ->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 2)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->value('sum(o.amount - o.on_amount - o.lock_amount)');
        $buyAmount = $this->orderDemand->alias('o')
            ->field('o.*')
            ->join('transaction_part t', 't.id=o.id')
            ->join('user u', 'u.id=o.uid')
            ->where('u.is_lock', 2)
            ->where('o.type', 1)
            ->where('o.state', 2)
            ->where('o.amount > o.lock_amount + o.on_amount')
            ->order('o.create_time asc')->value('sum(o.amount - o.on_amount - o.lock_amount)');

        $sellAmount = $sellAmount ? $sellAmount : 0;
        $buyAmount = $buyAmount ? $buyAmount : 0;
        $this->assign('sell_count', $sellAmount);
        $this->assign('buy_count', $buyAmount);
        $this->assign('sell_list', $sellList);
        $this->assign('buy_list', $buyList);
        if (input('?item')) {
            $item = input('item');
        } else {
            $item = 0;
        }
        $this->assign('item', $item);
        $this->assign('page', input('get.page') ? input('get.page') : 1);
        $this->assign('buycount', $buycount);
        $this->assign('sellcount', $sellcount);
        return $this->fetch();
    }

    //移出匹配池
    public function del_part()
    {
        $one = $this->transactionPart->where('id', input('id'))->find();
        $param = input('param.');
        if (input('?item')) {
            $item = '1';
        } else {
            $item = 0;
        }
        $param['item'] = $item;
        if ($one->delete()) {
            return returnJson(1, '移出成功', $param);
        } else {
            return returnJson(0, '移出失败', $param);
        }
    }

    //添加到匹配池
    public function add_part()
    {
        $one = $this->orderDemand->where('id', input('id'))->where('state', 2)->find();
        if (!$one) {
            throw new ServerException('该订单不存在');
        }
        $order = $this->transactionPart->where('id', input('id'))->find();
        if ($order) {
            throw new ServerException('该订单已添加到匹配池');
        }
        $int = $this->transactionPart->insert(['id' => $one->getData('id')]);
        $param = input('param.');
        if ($int) {
            return returnJson(1, '添加成功', $param);
        } else {
            return returnJson(0, '添加失败', $param);
        }
    }

    //匹配池匹配当前
    public function temporary_match_part()
    {
        ini_set('memory_limit', '1000M');
        $server = new MatchServer();
        $server->loadDemandPart();
        $server->match();
        $server->writeMemory();
        $transactionList = $this->orderTransactionMemory->paginate(99999);
        $this->adminLog->add_log('操作临时匹配');
        $matchAmount = $this->orderTransactionMemory->sum('amount');
        $matchTransactionNum = $this->orderTransactionMemory->count();
        $this->assign('match_amount', $matchAmount);
        $this->assign('match_transaction_num', $matchTransactionNum);
        $this->assign('list', $transactionList);
        return view('temporary_match_list');
    }

    //取消抢单
    public function cancel_qg()
    {
        $id = input('id');
        Db::startTrans();
        try {
            $order = $this->orderTransaction->where('id', $id)->find();
            $demand = $this->orderDemand->where('id', $order->getData('sell_did'))->find();
            $order->save(['is_show' => 0, 'is_qg' => 2]);
            $demand->setDec('lock_amount', $order->getData('amount'));
            Db::commit();
            return returnJson(1, '取消成功');
        } catch (Exception $e) {
            Db::rollback();
            return returnJson(0, '取消失败');
        }
    }

    public function capital_list()
    {
        $where = [];
        $query = input('post.');
        $list = $this->orderSh->getList($where, $query);
        return view('capital_list', ['list' => $list]);
    }

    public function capital_confirm()
    {
        $id = input('post.id');
        if (!$id) {
            return null;
        }
        Db::startTrans();
        try {
            $ordeShr=$this->orderSh->where('id',$id)->lock(true)->find();
            $account=Account::get(['id'=>$ordeShr->order->uid]);
            $ordeShr->order->save(['state'=>4]);
            $account->setInc('pos',($ordeShr->order->amount+$ordeShr->order->profit));
            $account->setDec('pow',($ordeShr->order->amount+$ordeShr->order->profit));
            UserActionLog::addActionLog($id, "提取本金");
            $this->orderSh->where('id', $id)->delete();
        } catch (Exception $e) {
            return returnJson($e->getMessage());
        }
        return returnJson(1, '已通过');
    }

    public function capital_cancel()
    {
        $id = input('post.id');
        if (!$id) {
            return null;
        }
        $this->orderSh->where('id', $id)->delete();
        return returnJson(0, '已拒绝');
    }

    public function capital_all_confirm()
    {
        $ids = array_filter(explode(',', input('id')));
        foreach ($ids  as $id){
            Db::startTrans();
            try{
                $orderSh=OrderSh::get($id);
                $account=Account::get(['id'=>$orderSh->order->uid]);
                $orderSh->order->save(['state'=>4]);
                $account->setInc('pos',($orderSh->order->amount+$orderSh->order->profit));
                $account->setDec('pow',($orderSh->order->amount+$orderSh->order->profit));
                UserActionLog::addActionLog($id, "提取本金");
            }catch (Exception $e){
                return returnJson(0, "$id 通过失败，程序终止");
            }
        }
        return returnJson('1','已全部通过');
    }

    public function capital_all_cancel()
    {
        $ids = array_filter(explode(',', input('id')));
        foreach ($ids  as $id){
            Db::startTrans();
            try{
                $this->orderSh->where('id', $id)->delete();
            }catch (Exception $e){
                return returnJson(0, "$id 通过失败，程序终止");
            }
        }
        return returnJson(0,'已全部拒绝');
    }

}
