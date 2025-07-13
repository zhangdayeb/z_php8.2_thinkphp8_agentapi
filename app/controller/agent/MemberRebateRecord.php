<?php
namespace app\controller\agent;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;
use think\Request;

class MemberRebateRecord extends AuthApiController
{
    /**
     * 获取会员返水记录
     * @param Request $request
     * @return \think\Response
     */
    public function get_member_rebate_record(Request $request)
    {
        try {
            // 获取当前登录代理信息
            $agentInfo = $this->getAgentInfo();
            if (!$agentInfo) {
                return $this->error('代理信息获取失败', 401);
            }

            // 获取请求参数
            $params = $request->param();
            $page = intval($params['page'] ?? 1);
            $limit = intval($params['limit'] ?? 20);
            $username = trim($params['username'] ?? '');
            $startDate = $params['start_date'] ?? '';
            $endDate = $params['end_date'] ?? '';

            // 参数验证
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 20;

            // 构建查询条件
            $where = [];
            
            // 添加代理权限限制 - 只查询该代理下的会员
            $memberIds = $this->getAgentMemberIds($agentInfo['id']);
            if (empty($memberIds)) {
                // 该代理下没有会员，返回空数据
                return $this->success([
                    'list' => [],
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit
                ]);
            }
            $where[] = ['fs.uid', 'in', $memberIds];

            // 用户名筛选
            if (!empty($username)) {
                $where[] = ['u.name', 'like', '%' . $username . '%'];
            }

            // 时间范围筛选
            if (!empty($startDate)) {
                $where[] = ['fs.create_time', '>=', $startDate . ' 00:00:00'];
            }
            if (!empty($endDate)) {
                $where[] = ['fs.create_time', '<=', $endDate . ' 23:59:59'];
            }

            // 查询总数
            $total = Db::table('ntp_game_user_money_fs_log')
                ->alias('fs')
                ->leftJoin('ntp_common_user u', 'fs.uid = u.id')
                ->where($where)
                ->count();

            // 查询列表数据
            $list = Db::table('ntp_game_user_money_fs_log')
                ->alias('fs')
                ->leftJoin('ntp_common_user u', 'fs.uid = u.id')
                ->field([
                    'fs.id',
                    'fs.uid',
                    'u.name as username',
                    'fs.money',
                    'fs.create_time',
                    'fs.remark'
                ])
                ->where($where)
                ->order('fs.create_time', 'desc')
                ->limit(($page - 1) * $limit, $limit)
                ->select()
                ->toArray();

            // 格式化数据
            foreach ($list as &$item) {
                $item['money'] = number_format($item['money'], 2);
                $item['create_time'] = $item['create_time'] ?: '';
                $item['remark'] = $item['remark'] ?: '';
            }

            return $this->success([
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            Log::error('获取会员返水记录失败: ' . $e->getMessage());
            return $this->error('获取数据失败，请稍后重试', 500);
        }
    }

    /**
     * 获取代理下所有会员ID
     * @param int $agentId
     * @return array
     */
    private function getAgentMemberIds($agentId)
    {
        try {
            // 查询该代理下的所有会员ID
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
     * 获取当前登录代理信息
     * @return array|null
     */
    private function getAgentInfo()
    {
        try {
            // 从token或session中获取代理ID
            $agentId = $this->getAgentId(); // 这个方法需要在AuthApiController中实现
            
            if (!$agentId) {
                return null;
            }

            $agentInfo = Db::table('ntp_common_group_agent')
                ->where('id', $agentId)
                ->where('status', 1) // 正常状态
                ->find();

            return $agentInfo;
        } catch (\Exception $e) {
            Log::error('获取代理信息失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取代理ID（从父类继承的方法）
     * @return int|null
     */
    protected function getAgentId()
    {
        // 这个方法需要在AuthApiController中实现
        // 暂时返回从token中解析的用户ID
        return $this->getCurrentUserId();
    }
}