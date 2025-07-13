<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

// ====================================================================
// 登录认证模块
// ====================================================================
Route::rule('login/login$', 'login.Login/login');         // 管理员登录页面

// ====================================================================
// 菜单管理模块
// ====================================================================
Route::rule('menu/list$', 'auth.Menu/index');    // 后台菜单列表 


// 代理模块
Route::rule('agent/member', 'agent.Memeber/get_member');
Route::rule('agent/promotion', 'agent.Promotion/get_promotion');
Route::rule('agent/statistical_reports', 'agent.StatisticalReports/get_statistical_reports');
Route::rule('agent/member_balance_record', 'agent.MemberBalanceRecord/get_member_balance_record');
Route::rule('agent/member_game_record', 'agent.MemberGameRecord/get_member_game_recordr');
Route::rule('agent/member_deposit_record', 'agent.MemberDepositRecord/get_member_deposit_record');
Route::rule('agent/member_withdrawal_record', 'agent.MemberWithdrawalcord/get_member_withdrawal_record');
Route::rule('agent/member_rebate_record', 'agent.MemberRebateRecord/get_member_rebate_record');

