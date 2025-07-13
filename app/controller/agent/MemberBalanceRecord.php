<?php
namespace app\controller\agent;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;

class MemberBalanceRecord extends AuthApiController
{
    /**
     * 会员余额记录接口
     */
    public function get_member_balance_record()
    {
        try {
            // 获取当前登录代理信息
            $agentInfo = $this->getAgentInfo();
            if (!$agentInfo) {
                return $this->error('代理信息获取失败', 401);
            }

            // 获取请求参数
            $page = intval(input('page', 1));
            $limit = intval(input('limit', 20));
            $username = trim(input('username', ''));
            $startDate = input('start_date', '');
            $endDate = input('end_date', '');

            // 参数验证
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 20;

            // 构建查询条件
            $where = [];
            
            // 添加代理权限限制
            $memberIds = $this->getAgentMemberIds($agentInfo['id']);
            if (empty($memberIds)) {
                return $this->success([
                    'list' => [],
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit
                ]);
            }
            $where[] = ['ml.uid', 'in', $memberIds];

            // 用户名筛选
            if (!empty($username)) {
                $where[] = ['u.name', 'like', '%' . $username . '%'];
            }

            // 时间范围筛选
            if (!empty($startDate)) {
                $where[] = ['ml.create_time', '>=', $startDate . ' 00:00:00'];
            }
            if (!empty($endDate)) {
                $where[] = ['ml.create_time', '<=', $endDate . ' 23:59:59'];
            }

            // 查询总数
            $total = Db::table('ntp_common_pay_money_log')
                ->alias('ml')
                ->leftJoin('ntp_common_user u', 'ml.uid = u.id')
                ->where($where)
                ->count();

            // 查询列表数据
            $list = Db::table('ntp_common_pay_money_log')
                ->alias('ml')
                ->leftJoin('ntp_common_user u', 'ml.uid = u.id')
                ->field([
                    'ml.id',
                    'ml.uid',
                    'u.name as username',
                    'ml.type',
                    'ml.money',
                    'ml.create_time'
                ])
                ->where($where)
                ->order('ml.create_time', 'desc')
                ->limit(($page - 1) * $limit, $limit)
                ->select()
                ->toArray();

            // 格式化数据
            foreach ($list as &$item) {
                $item['money'] = number_format($item['money'], 2);
                $item['type_text'] = $this->getTypeText($item['type']);
                $item['create_time'] = $item['create_time'] ?: '';
            }

            return $this->success([
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            Log::error('获取会员余额记录失败: ' . $e->getMessage());
            return $this->error('获取数据失败，请稍后重试', 500);
        }
    }

    /**
     * 获取代理下所有会员ID
     */
    private function getAgentMemberIds($agentId)
    {
        try {
            $memberIds = Db::table('ntp_common_user')
                ->where('agent_id', $agentId)
                ->column('id');

            return $memberIds ?: [];
        } catch (\Exception $e) {
            Log::error('获取代理会员ID失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取类型文本
     */
    private function getTypeText($type)
    {
        $typeMap = [
            1 => '收入',
            2 => '支出'
        ];

        return $typeMap[$type] ?? '其他';
    }

    /**
     * 获取当前登录代理信息
     */
    private function getAgentInfo()
    {
        try {
            $agentId = $this->getAgentId();
            
            if (!$agentId) {
                return null;
            }

            $agentInfo = Db::table('ntp_common_group_agent')
                ->where('id', $agentId)
                ->where('status', 1)
                ->find();

            return $agentInfo;
        } catch (\Exception $e) {
            Log::error('获取代理信息失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取代理ID
     */
    protected function getAgentId()
    {
        return $this->getCurrentUserId();
    }
}