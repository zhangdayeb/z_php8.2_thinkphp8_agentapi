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

// ====================================================================
// 代理模块
// ====================================================================
Route::rule('agent/member', 'agent.Member/get_member');                                           // 代理会员列表
Route::rule('agent/promotion', 'agent.Promotion/get_promotion');                                  // 推广信息
Route::rule('agent/statistical_reports', 'agent.StatisticalReports/get_statistical_reports');    // 财务统计
Route::rule('agent/member_balance_record', 'agent.MemberBalanceRecord/get_member_balance_record'); // 会员余额变动
Route::rule('agent/member_game_record', 'agent.MemberGameRecord/get_member_game_record');         // 会员游戏记录
Route::rule('agent/member_deposit_record', 'agent.MemberDepositRecord/get_member_deposit_record'); // 会员存款记录
Route::rule('agent/member_withdrawal_record', 'agent.MemberWithdrawalRecord/get_member_withdrawal_record'); // 会员取款记录
Route::rule('agent/member_rebate_record', 'agent.MemberRebateRecord/get_member_rebate_record');   // 会员返水记录