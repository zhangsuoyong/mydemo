<?php

namespace app\api\controller;

use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\OrderDemand;
use app\common\model\Robot;
use app\common\model\RobotLogs;
use app\common\model\User;
use think\Log;
use think\Db;
use think\Exception;
use think\Request;
use app\common\model\System_config;
use app\common\model\UserActionLog;


class Robotcontroller extends UserBase
{


    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {

	    $demand = OrderDemand::get(['uid' => input('id'), 'genre' => 4, 'type' => 1]);
		$user=User::get (input ('id'));
		
		// $status=1;
		// $status=$request->status;
	    $status=input('status');//0未开启 1开启
	    
	    
	    $due=input ('due');     //间隔时间 48h 72h 96h
	    $type=getType($status);
	    
	    if (!$status||!$due) {
	     throw new ServerException('参数错误');
	    } 
	    
	    
	
	    if ($status=='1'&&!$demand){
		    throw new ServerException('首单不可以开启自动排单');
	    }
	    if ($status=='1'&&!$due){
		    throw new ServerException('请选择自动排单时间');

	    }		
	 
	    
	    $robot=Robot::where('uid','=',$user['id'])->find();
	     //排单时间递增
	    if($robot){
	    	$last_due=$robot->due;
	    	if($due<$last_due){
	    		 throw new ServerException('排单间隔时间不能小于上次的排单间隔时间');
	    	}
	    }
	    if ($status=='1'&&!$robot){
		    Robot::create ([
		    'uid'=>$user['id'],
		    'due'=>$due,
			'next_buy_at'=>time()+$due*60*60,
		    'status'=>1
		    ]);

		    return returnJson (1,'设置成功');
	    }elseif ($status=='1'&&$robot){
	    	
	    	 
	    	
		    $robot->due=$due;
		    $robot->status=1;
		    $robot->next_buy_at=time()+$due*60*60;
		    $robot->isUpdate (true)->save ();
		    return returnJson (1,'修改成功');
	    }elseif ($status=='2'&&$robot){
		    $robot->status=2;
		     
		    $robot->isupdate(true)->save();
		    return returnJson (1,'修改成功');
	    }
	    
	    
	    
	     return returnJson (1,'保存成功');

    }

	public function auto48_put_buy_demand(Robot $robot,OrderDemand $orderDemand,Account $account){
	

		
		
		
	
			
		//获取自动排单列表
		// $robots=$robot->get48Robots ();
		$robots=$robot->get_due_data();
		
		
	
		$config_time = System_config::get(['name' => 'admission_interval'])['value'];

		try{
			foreach ($robots as $robot){
			
			
				try{
					Db::startTrans ();
					//获取最新的排单
					
						$user=User::get ($robot->uid);
						
						
					if (!$user) {
						
						$robotLogs->addLog ($robot->uid,'用户未找到');
						
						Db::commit ();
						
						continue;
					
					}
					$demand=$orderDemand->getLastdemand ($robot->uid);
				
					if (!$demand) {
							$robotLogs->addLog ($robot->uid,'请先手动排单一次');
						
						Db::commit ();
						
						continue;
					}
					
					//判断排单币
					$user_account=$account->get($robot->uid);
					
						
						//当前用户的排单币是否充足
						$buy_cion=$user_account->buy_coin;
					
					
					
						if ($buy_cion<=-1){
								
							$robotLogs->addLog ($robot->uid,'排单币不足,自动排单失败,自动排单功能关闭');
							$robot->status=2;
							$robot->save();
							
							Db::commit ();
							
							continue;
						
						}
						
						
						
						//判断是否是抢购状态
					 $config = System_config::get(['name' => 'rob_flag'])['value'];
						// $config=0;
						
						
						
		//是
		//抢购开关
		$qg_num = 0;
		//用券开关
		$ticket_num = 0;
		if ($config) {
			$qg_num = System_config::get(['name' => 'robAmount'])['value'];
			
			
			if ($qg_num < $demand->amount) {	
				
			
				//判断是否有天使券
				$ticket_num = $user_account->getData('ticket');
				
				if (!$ticket_num) {
						
						$robotLogs->addLog ($robot->uid,'可抢购金额不足,自动排单失败');
						
							Db::commit ();
							
							continue;
					
				}else{
					
					$user_account->ticket-=1;
					$user_account->save();
				
					$orderDemand->addDemand ($robot->uid,$demand->amount,1,1,0,1);
					
				
				
					$user_account->buy_coin-=1;
				
					$user_account->save ();
					
					AccountLog::addCoinLog ($user->id,4,$need_cion,'-','自动排单扣除');
					//
					if ($user_account->buy_coin<=-1){
						
						$user->auto_if_cash=0;
						$user->on_buy_time=date("Y-m-d H:i:s", time());
						$user->on_buy_num=$demand->amount;
						$user->next_buy_time=date("Y-m-d H:i:s", strtotime("+ $config_time hours"));
						$user->save ();
						
						$newTime=Robot::get($robot->id);
						$robot->next_buy_at=time()+$newTime->due*60*60;
						$robot->save();
						$robotLogs->addLog ($robot->uid,"自动排单成功,扣除天使劵:'.$demand->amount.'扣除排1个单币,当前余额:$user_account->buy_coin,提现关闭");
						
							Db::commit ();
						
					}
						//更新用户数据
						$user->on_buy_time=date("Y-m-d H:i:s", time());
						$user->on_buy_num=$demand->amount;
						$user->next_buy_time=date("Y-m-d H:i:s", strtotime("+ $config_time hours"));
						$user->save ();
						//写入日志
						$robotLogs->addLog ($robot->uid,"自动排单成功,扣除天使劵:'.$demand->amount.',扣除排1个单币,当前余额:$user_account->buy_coin");
						//获取下次自动执行时间
						$newTime=Robot::get($robot->id);
						$robot->next_buy_at=time()+$newTime->due*60*60;
						$robot->save();
					
						Db::commit ();
				
				}
			}
		}else {
					
			
					$orderDemand->addDemand ($robot->uid,$demand->amount,1,1,0,1);
					
					$need_cion=$demand->amount/1000;
					$user_account->buy_coin-=$need_cion;
				
					$user_account->save ();
					AccountLog::addCoinLog ($user->id,4,$need_cion,'-','自动排单扣除');
					//
					if ($user_account->buy_coin<=-1){
						
						$user->auto_if_cash=0;
						$user->on_buy_time=date("Y-m-d H:i:s", time());
						$user->on_buy_num=$demand->amount;
						$user->next_buy_time=date("Y-m-d H:i:s", strtotime("+ $config_time hours"));
						$user->save ();
						
						$newTime=Robot::get($robot->id);
						$robot->next_buy_at=time()+$newTime->due*60*60;
						$robot->save();
						$robotLogs=new RobotLogs();
						$robotLogs->addLog ($robot->uid,"自动排单成功,扣除".$need_cion."个排单币,当前余额:$user_account->buy_coin,提现关闭");
						
							Db::commit ();
						
					}
						//更新用户数据
						$user->on_buy_time=date("Y-m-d H:i:s", time());
						$user->on_buy_num=$demand->amount;
						$user->next_buy_time=date("Y-m-d H:i:s", strtotime("+ $config_time hours"));
						$user->save ();
						//写入日志
						$robotLogs=new RobotLogs();
						$robotLogs->addLog ($robot->uid,"自动排单成功,扣除".$need_cion."个排单币,当前余额:$user_account->buy_coin");
						//获取下次自动执行时间
						$newTime=Robot::get($robot->id);
						$robot->next_buy_at=time()+$newTime->due*60*60;
						$robot->save();
					
						Db::commit ();
		}

				
				

				
				}catch(Exception $exception){
					Db::rollback ();
					Log::write ('自动排单错误信息,序号:'.$robot->id.'信息:'.$exception->getMessage ());
						return returnJson (0, $exception->getMessage ());
					continue;
				
				}
			
			}
			
				Log::write ('48h自动排单执行完成,处理数据'.count($robots).'条');
		}catch (Exception $exception){
			Db::rollback ();
			Log::write ('自动排单错误信息,序号:'.$robot->id.'信息:'.$exception->getMessage ());
			return returnJson (0, $exception->getMessage ());
		}


	
	}


		public function auto_guanbi_cash(){

			$user=new User();
			//获取所有激活用户
			$users=$user->all(['state'=>1,'if_cash'=>1]);
			$config_time=$min = System_config::get(['name' => 'max_due_time'])['value']?System_config::get(['name' => 'max_due_time'])['value']:120;
			foreach($users as $user){
				//若果超过下次购买时间120个小时,关闭提现
				if(time ()-strtotime ($user->next_buy_time?$user->next_buy_time:$user->create_time)>=$config_time*60*60){

					$user->auto_if_cash=0;
					$user->need_two=3;
					$user->save();
					UserActionLog::addActionLog ($user->id,$config_time."小时未排单,提现功能关闭");
				}
			}



		}
	
	

}
