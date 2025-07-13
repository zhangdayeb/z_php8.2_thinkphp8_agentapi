<?php
namespace app\controller\agent;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;

class Promotion extends AuthApiController
{
    /**
     * 获取推广信息
     * @return \think\Response
     */
    public function get_promotion()
    {
        try {
            // 获取当前登录代理ID（继承自AuthApiController）
            $agentId = $this->getCurrentUserId();
            
            if (!$agentId) {
                return $this->error('获取用户信息失败', 401);
            }
            
            // 获取当前集团前缀（继承自BaseApiController）
            $groupPrefix = $this->getGroupPrefix();
            
            // 查询代理信息
            $agentInfo = $this->getAgentInfo($agentId, $groupPrefix);
            if (!$agentInfo) {
                return $this->error('代理信息不存在或已被冻结', 404);
            }
            
            // 查询集团配置信息
            $groupInfo = $this->getGroupInfo($groupPrefix);
            if (!$groupInfo) {
                return $this->error('集团配置不存在', 404);
            }
            
            // 生成推广地址
            $promotionData = $this->buildPromotionData($agentInfo, $groupInfo);
            
            // 记录成功日志
            Log::info('代理推广信息获取成功', [
                'agent_id' => $agentId,
                'agent_name' => $agentInfo['agent_name'],
                'group_prefix' => $groupPrefix
            ]);
            
            return $this->success($promotionData, '获取成功');
            
        } catch (\Exception $e) {
            Log::error('获取推广信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'agent_id' => $this->getCurrentUserId(),
                'group_prefix' => $this->getGroupPrefix()
            ]);
            
            return $this->error('系统错误，请稍后重试', 500);
        }
    }
    
    /**
     * 获取代理信息
     * @param int $agentId 代理ID
     * @param string $groupPrefix 集团前缀
     * @return array|null
     */
    private function getAgentInfo($agentId, $groupPrefix)
    {
        return Db::table('ntp_common_group_agent')
            ->where('id', $agentId)
            ->where('group_prefix', $groupPrefix)
            ->where('status', 1) // 正常状态
            ->field('agent_name, invitation_code, money, money_total, group_prefix')
            ->find();
    }
    
    /**
     * 获取集团配置信息
     * @param string $groupPrefix 集团前缀
     * @return array|null
     */
    private function getGroupInfo($groupPrefix)
    {
        return Db::table('ntp_group_set')
            ->where('group_prefix', $groupPrefix)
            ->where('status', 1) // 正常状态
            ->field('promotion_url')
            ->find();
    }
    
    /**
     * 构建推广数据
     * @param array $agentInfo 代理信息
     * @param array $groupInfo 集团信息
     * @return array
     */
    private function buildPromotionData($agentInfo, $groupInfo)
    {
        $promotionUrl = $groupInfo['promotion_url'];
        $invitationCode = $agentInfo['invitation_code'];
        
        // 拼接完整的推广地址
        $fullPromotionUrl = $promotionUrl . '?agent_code=' . $invitationCode;
        
        return [
            'agent_name' => $agentInfo['agent_name'],
            'invitation_code' => $invitationCode,
            'current_balance' => number_format((float)$agentInfo['money'], 2),
            'total_earnings' => number_format((float)$agentInfo['money_total'], 2),
            'promotion_base_url' => $promotionUrl,
            'promotion_url' => $fullPromotionUrl
        ];
    }
}