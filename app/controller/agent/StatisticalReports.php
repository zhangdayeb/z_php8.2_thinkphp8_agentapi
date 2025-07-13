<?php
namespace app\controller\agent;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;

class StatisticalReports extends AuthApiController
{
    /**
     * 获取财务统计报告
     * @return \think\Response
     */
    public function get_statistical_reports()
    {
        try {
            // 获取当前登录代理ID
            $agentId = $this->getCurrentUserId();
            if (!$agentId) {
                return $this->error('获取用户信息失败', 401);
            }
            
            // 获取当前集团前缀
            $groupPrefix = $this->getGroupPrefix();
            
            // 获取搜索参数
            $params = $this->getSearchParams();
            
            // 获取代理下属会员IDs
            $memberIds = $this->getAgentMemberIds($agentId, $groupPrefix);
            if (empty($memberIds)) {
                return $this->success([
                    'total_recharge_amount' => '0.00',
                    'total_recharge_count' => 0,
                    'total_withdraw_amount' => '0.00', 
                    'total_withdraw_count' => 0,
                    'total_game_lose' => '0.00',
                    'total_game_win' => '0.00'
                ]);
            }
            
            // 获取各项统计数据
            $statistics = [
                'total_recharge_amount' => $this->getRechargeAmount($memberIds, $params),
                'total_recharge_count' => $this->getRechargeCount($memberIds, $params),
                'total_withdraw_amount' => $this->getWithdrawAmount($memberIds, $params),
                'total_withdraw_count' => $this->getWithdrawCount($memberIds, $params),
                'total_game_lose' => $this->getGameLoseAmount($memberIds, $params),
                'total_game_win' => $this->getGameWinAmount($memberIds, $params)
            ];
            
            // 记录成功日志
            Log::info('财务统计查询成功', [
                'agent_id' => $agentId,
                'group_prefix' => $groupPrefix,
                'member_count' => count($memberIds),
                'params' => $params
            ]);
            
            return $this->success($statistics, '获取成功');
            
        } catch (\Exception $e) {
            Log::error('获取财务统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'agent_id' => $this->getCurrentUserId(),
                'group_prefix' => $this->getGroupPrefix()
            ]);
            
            return $this->error('系统错误，请稍后重试', 500);
        }
    }
    
    /**
     * 获取搜索参数
     * @return array
     */
    private function getSearchParams()
    {
        $post = $this->request->post();
        
        return [
            'username' => $post['username'] ?? '',
            'start_date' => $post['start_date'] ?? '',
            'end_date' => $post['end_date'] ?? ''
        ];
    }
    
    /**
     * 获取代理下属会员IDs
     * @param int $agentId 代理ID
     * @param string $groupPrefix 集团前缀
     * @param array $params 搜索参数
     * @return array
     */
    private function getAgentMemberIds($agentId, $groupPrefix, $params = [])
    {
        $query = Db::table('ntp_common_user')
            ->where('agent_id', $agentId)
            ->where('group_prefix', $groupPrefix)
            ->where('status', 1); // 正常状态
            
        // 如果有用户名搜索条件
        if (!empty($params['username'])) {
            $query->where('name', 'like', '%' . $params['username'] . '%');
        }
        
        return $query->column('id');
    }
    
    /**
     * 获取充值总金额
     * @param array $memberIds 会员IDs
     * @param array $params 搜索参数
     * @return string
     */
    private function getRechargeAmount($memberIds, $params)
    {
        $query = Db::table('ntp_common_pay_recharge')
            ->whereIn('user_id', $memberIds)
            ->where('status', 1); // 已通过
            
        // 添加时间条件
        $this->addTimeCondition($query, $params, 'create_time');
        
        $amount = $query->sum('money') ?: 0;
        return number_format((float)$amount, 2);
    }
    
    /**
     * 获取充值总笔数
     * @param array $memberIds 会员IDs
     * @param array $params 搜索参数
     * @return int
     */
    private function getRechargeCount($memberIds, $params)
    {
        $query = Db::table('ntp_common_pay_recharge')
            ->whereIn('user_id', $memberIds)
            ->where('status', 1); // 已通过
            
        // 添加时间条件
        $this->addTimeCondition($query, $params, 'create_time');
        
        return $query->count();
    }
    
    /**
     * 获取提现总金额
     * @param array $memberIds 会员IDs
     * @param array $params 搜索参数
     * @return string
     */
    private function getWithdrawAmount($memberIds, $params)
    {
        $query = Db::table('ntp_common_pay_withdraw')
            ->whereIn('u_id', $memberIds)
            ->where('status', 1); // 成功
            
        // 添加时间条件
        $this->addTimeCondition($query, $params, 'create_time');
        
        $amount = $query->sum('money') ?: 0;
        return number_format((float)$amount, 2);
    }
    
    /**
     * 获取提现总笔数
     * @param array $memberIds 会员IDs
     * @param array $params 搜索参数
     * @return int
     */
    private function getWithdrawCount($memberIds, $params)
    {
        $query = Db::table('ntp_common_pay_withdraw')
            ->whereIn('u_id', $memberIds)
            ->where('status', 1); // 成功
            
        // 添加时间条件
        $this->addTimeCondition($query, $params, 'create_time');
        
        return $query->count();
    }
    
    /**
     * 获取游戏总输金额
     * @param array $memberIds 会员IDs
     * @param array $params 搜索参数
     * @return string
     */
    private function getGameLoseAmount($memberIds, $params)
    {
        $query = Db::table('ntp_game_user_money_logs')
            ->whereIn('member_id', $memberIds)
            ->where('number_type', -1); // 减少金额（输）
            
        // 添加时间条件
        $this->addTimeCondition($query, $params, 'created_at');
        
        $amount = $query->sum('money') ?: 0;
        return number_format((float)$amount, 2);
    }
    
    /**
     * 获取游戏总赢金额
     * @param array $memberIds 会员IDs
     * @param array $params 搜索参数
     * @return string
     */
    private function getGameWinAmount($memberIds, $params)
    {
        $query = Db::table('ntp_game_user_money_logs')
            ->whereIn('member_id', $memberIds)
            ->where('number_type', 1); // 增加金额（赢）
            
        // 添加时间条件
        $this->addTimeCondition($query, $params, 'created_at');
        
        $amount = $query->sum('money') ?: 0;
        return number_format((float)$amount, 2);
    }
    
    /**
     * 添加时间条件
     * @param object $query 查询对象
     * @param array $params 搜索参数
     * @param string $timeField 时间字段名
     */
    private function addTimeCondition($query, $params, $timeField)
    {
        if (!empty($params['start_date'])) {
            $query->where($timeField, '>=', $params['start_date'] . ' 00:00:00');
        }
        
        if (!empty($params['end_date'])) {
            $query->where($timeField, '<=', $params['end_date'] . ' 23:59:59');
        }
    }
}