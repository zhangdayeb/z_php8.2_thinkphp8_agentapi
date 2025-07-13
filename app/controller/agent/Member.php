<?php
namespace app\controller\agent;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;

class Member extends AuthApiController
{
    /**
     * 获取代理下属会员列表
     * @return \think\response\Json
     */
    public function get_member()
    {
        try {
            // 获取请求参数
            $params = $this->request->only([
                'page',
                'limit', 
                'username',
                'start_date',
                'end_date'
            ]);

            // 参数验证和默认值
            $page = max(1, intval($params['page'] ?? 1));
            $limit = min(100, max(1, intval($params['limit'] ?? 20))); // 限制最大100条
            $username = trim($params['username'] ?? '');
            $startDate = $params['start_date'] ?? '';
            $endDate = $params['end_date'] ?? '';

            // 获取当前登录代理ID (从父类AuthApiController获取)
            $currentAgentId = $this->userId; // 假设父类已设置当前用户ID
            
            if (empty($currentAgentId)) {
                return $this->error('代理身份验证失败');
            }

            // 构建查询条件
            $query = Db::name('common_user')
                ->field([
                    'id',
                    'name', 
                    'created_at',
                    'money',
                    'money_rebate',
                    'vip_grade'
                ])
                ->where('group_prefix', $this->groupPrefix);

            // 代理权限条件 - 查找所有下属会员
            $query->where(function ($q) use ($currentAgentId) {
                $q->whereOr([
                    ['agent_id', '=', $currentAgentId],
                    ['user_agent_id_1', '=', $currentAgentId], 
                    ['user_agent_id_2', '=', $currentAgentId],
                    ['user_agent_id_3', '=', $currentAgentId]
                ]);
            });

            // 用户名搜索
            if (!empty($username)) {
                $query->where('name', 'like', '%' . $username . '%');
            }

            // 注册时间范围搜索
            if (!empty($startDate)) {
                $query->where('created_at', '>=', $startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            }

            // 只查询正常状态用户
            $query->where('status', 1);

            // 排序：按注册时间倒序
            $query->order('created_at', 'desc');

            // 执行分页查询
            $result = $query->paginate([
                'page' => $page,
                'list_rows' => $limit,
                'simple' => false
            ]);

            // 格式化返回数据
            $list = [];
            foreach ($result->items() as $item) {
                $list[] = [
                    'id' => intval($item['id']),
                    'name' => $item['name'],
                    'created_at' => $item['created_at'],
                    'money' => number_format($item['money'], 2, '.', ''),
                    'money_rebate' => number_format($item['money_rebate'], 2, '.', ''),
                    'vip_grade' => intval($item['vip_grade'])
                ];
            }

            // 记录查询日志
            Log::info('代理会员列表查询', [
                'agent_id' => $currentAgentId,
                'group_prefix' => $this->groupPrefix,
                'total' => $result->total(),
                'page' => $page,
                'limit' => $limit,
                'username' => $username,
                'date_range' => [$startDate, $endDate]
            ]);

            return $this->success([
                'list' => $list,
                'total' => $result->total(),
                'page' => $page,
                'limit' => $limit,
                'last_page' => $result->lastPage()
            ]);

        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('代理会员列表查询失败', [
                'agent_id' => $this->userId ?? 0,
                'group_prefix' => $this->groupPrefix,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('查询失败，请稍后重试');
        }
    }
}