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
                    'money_fanyong',
                    'vip_grade',
                    'fanyong_proportion',
                    'agent_id',
                    'user_agent_id_1',
                    'user_agent_id_2',
                    'user_agent_id_3'
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
                    'money_fanyong' => number_format($item['money_fanyong'], 2, '.', ''),
                    'vip_grade' => intval($item['vip_grade']),
                    'fanyong_proportion' => number_format($item['fanyong_proportion'], 2, '.', ''),
                    'agent_id' => intval($item['agent_id']),
                    'user_agent_id_1' => $item['user_agent_id_1'] ? intval($item['user_agent_id_1']) : 0
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

    /**
     * 调整会员余额
     * @return \think\response\Json
     */
    public function adjust_member_balance()
    {
        // 开启事务
        Db::startTrans();
        
        try {
            // 获取请求参数
            $params = $this->request->only([
                'user_id',
                'type',
                'amount',
                'remark'
            ]);

            // 参数验证
            $userId = intval($params['user_id'] ?? 0);
            $type = $params['type'] ?? '';
            $amount = floatval($params['amount'] ?? 0);
            $remark = trim($params['remark'] ?? '');

            if (empty($userId) || empty($type) || $amount <= 0 || empty($remark)) {
                return $this->error('参数错误');
            }

            if (!in_array($type, ['add', 'subtract'])) {
                return $this->error('调整类型错误');
            }

            // 获取当前代理ID
            $currentAgentId = $this->userId;
            if (empty($currentAgentId)) {
                return $this->error('代理身份验证失败');
            }

            // 获取代理信息
            $agentInfo = Db::name('common_group_agent')
                ->where('id', $currentAgentId)
                ->where('group_prefix', $this->groupPrefix)
                ->where('status', 1)
                ->find();

            if (!$agentInfo) {
                return $this->error('代理信息不存在');
            }

            // 获取用户信息并验证权限
            $userInfo = Db::name('common_user')
                ->where('id', $userId)
                ->where('group_prefix', $this->groupPrefix)
                ->where('status', 1)
                ->find();

            if (!$userInfo) {
                return $this->error('用户不存在');
            }

            // 验证是否是该代理下的用户
            $hasPermission = ($userInfo['agent_id'] == $currentAgentId) ||
                           ($userInfo['user_agent_id_1'] == $currentAgentId) ||
                           ($userInfo['user_agent_id_2'] == $currentAgentId) ||
                           ($userInfo['user_agent_id_3'] == $currentAgentId);

            if (!$hasPermission) {
                return $this->error('无权限调整此用户余额');
            }

            // 验证余额
            if ($type === 'add') {
                // 增加用户金额，需要验证代理余额
                if ($agentInfo['money'] < $amount) {
                    return $this->error('代理余额不足');
                }
            } else {
                // 减少用户金额，需要验证用户余额
                if ($userInfo['money'] < $amount) {
                    return $this->error('用户余额不足');
                }
            }

            $currentTime = date('Y-m-d H:i:s');

            // 计算调整后的金额
            $userMoneyBefore = $userInfo['money'];
            $agentMoneyBefore = $agentInfo['money'];

            if ($type === 'add') {
                $userMoneyAfter = $userMoneyBefore + $amount;
                $agentMoneyAfter = $agentMoneyBefore - $amount;
                $userLogType = 1; // 收入
                $userLogStatus = 301; // 后台调整
                $agentLogType = 2; // 支出
                $agentLogStatus = 201; // 支出
                $userRemark = "代理调整增加余额：{$amount}元，{$remark}";
                $agentRemark = "调整用户[{$userInfo['name']}]余额：-{$amount}元，{$remark}";
            } else {
                $userMoneyAfter = $userMoneyBefore - $amount;
                $agentMoneyAfter = $agentMoneyBefore + $amount;
                $userLogType = 2; // 支出
                $userLogStatus = 201; // 支出
                $agentLogType = 1; // 收入
                $agentLogStatus = 101; // 收入
                $userRemark = "代理调整减少余额：{$amount}元，{$remark}";
                $agentRemark = "调整用户[{$userInfo['name']}]余额：+{$amount}元，{$remark}";
            }

            // 更新用户余额
            $userUpdateResult = Db::name('common_user')
                ->where('id', $userId)
                ->update([
                    'money' => $userMoneyAfter,
                    'updated_at' => $currentTime
                ]);

            if (!$userUpdateResult) {
                throw new \Exception('更新用户余额失败');
            }

            // 更新代理余额
            $agentUpdateResult = Db::name('common_group_agent')
                ->where('id', $currentAgentId)
                ->update([
                    'money' => $agentMoneyAfter
                ]);

            if (!$agentUpdateResult) {
                throw new \Exception('更新代理余额失败');
            }

            // 记录用户资金流水
            Db::name('common_pay_money_log')->insert([
                'group_prefix' => $this->groupPrefix,
                'create_time' => $currentTime,
                'type' => $userLogType,
                'status' => $userLogStatus,
                'money_before' => $userMoneyBefore,
                'money_end' => $userMoneyAfter,
                'money' => $amount,
                'uid' => $userId,
                'source_id' => $currentAgentId,
                'market_uid' => 0,
                'mark' => $userRemark
            ]);

            // 记录代理资金流水
            Db::name('common_pay_money_agent_log')->insert([
                'group_prefix' => $this->groupPrefix,
                'create_time' => $currentTime,
                'type' => $agentLogType,
                'status' => $agentLogStatus,
                'money_before' => $agentMoneyBefore,
                'money_end' => $agentMoneyAfter,
                'money' => $amount,
                'agent_id' => $currentAgentId,
                'source_id' => $userId,
                'admin_uid' => 0,
                'mark' => $agentRemark
            ]);

            // 提交事务
            Db::commit();

            // 记录操作日志
            Log::info('代理调整会员余额成功', [
                'agent_id' => $currentAgentId,
                'user_id' => $userId,
                'username' => $userInfo['name'],
                'type' => $type,
                'amount' => $amount,
                'user_money_before' => $userMoneyBefore,
                'user_money_after' => $userMoneyAfter,
                'agent_money_before' => $agentMoneyBefore,
                'agent_money_after' => $agentMoneyAfter,
                'remark' => $remark
            ]);

            return $this->success('余额调整成功');

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();

            // 记录错误日志
            Log::error('代理调整会员余额失败', [
                'agent_id' => $this->userId ?? 0,
                'user_id' => $params['user_id'] ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('调整失败：' . $e->getMessage());
        }
    }

    /**
     * 调整会员返佣比例
     * @return \think\response\Json
     */
    public function adjust_member_commission()
    {
        try {
            // 获取请求参数
            $params = $this->request->only([
                'user_id',
                'proportion',
                'remark'
            ]);

            // 参数验证
            $userId = intval($params['user_id'] ?? 0);
            $proportion = floatval($params['proportion'] ?? 0);
            $remark = trim($params['remark'] ?? '');

            if (empty($userId) || $proportion < 0 || $proportion > 1 || empty($remark)) {
                return $this->error('参数错误');
            }

            // 获取当前代理ID
            $currentAgentId = $this->userId;
            if (empty($currentAgentId)) {
                return $this->error('代理身份验证失败');
            }

            // 获取用户信息并验证权限
            $userInfo = Db::name('common_user')
                ->where('id', $userId)
                ->where('group_prefix', $this->groupPrefix)
                ->where('status', 1)
                ->find();

            if (!$userInfo) {
                return $this->error('用户不存在');
            }

            // 验证是否是该代理下的用户
            if ($userInfo['agent_id'] != $currentAgentId) {
                return $this->error('无权限调整此用户返佣比例');
            }

            // 验证是否可以调整返佣比例（只能调整直属用户，且user_agent_id_1为空或0）
            if (!empty($userInfo['user_agent_id_1']) && $userInfo['user_agent_id_1'] != 0) {
                return $this->error('该用户的返佣比例只能通过用户前端进行调整，请登录相应的用户账号');
            }

            $oldProportion = $userInfo['fanyong_proportion'];
            $currentTime = date('Y-m-d H:i:s');

            // 更新用户返佣比例
            $updateResult = Db::name('common_user')
                ->where('id', $userId)
                ->update([
                    'fanyong_proportion' => $proportion,
                    'updated_at' => $currentTime
                ]);

            if (!$updateResult) {
                return $this->error('更新返佣比例失败');
            }

            // 记录操作日志
            Log::info('代理调整会员返佣比例成功', [
                'agent_id' => $currentAgentId,
                'user_id' => $userId,
                'username' => $userInfo['name'],
                'old_proportion' => $oldProportion,
                'new_proportion' => $proportion,
                'remark' => $remark
            ]);

            return $this->success('返佣比例调整成功');

        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('代理调整会员返佣比例失败', [
                'agent_id' => $this->userId ?? 0,
                'user_id' => $params['user_id'] ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('调整失败：' . $e->getMessage());
        }
    }

    /**
     * 获取代理信息（包括余额）
     * @return \think\response\Json
     */
    public function get_agent_info()
    {
        try {
            $currentAgentId = $this->userId;
            
            if (empty($currentAgentId)) {
                return $this->error('代理身份验证失败');
            }

            $agentInfo = Db::name('common_group_agent')
                ->field(['id', 'agent_name', 'money', 'money_total'])
                ->where('id', $currentAgentId)
                ->where('group_prefix', $this->groupPrefix)
                ->where('status', 1)
                ->find();

            if (!$agentInfo) {
                return $this->error('代理信息不存在');
            }

            return $this->success([
                'id' => intval($agentInfo['id']),
                'agent_name' => $agentInfo['agent_name'],
                'money' => number_format($agentInfo['money'], 2, '.', ''),
                'money_total' => number_format($agentInfo['money_total'], 2, '.', '')
            ]);

        } catch (\Exception $e) {
            Log::error('获取代理信息失败', [
                'agent_id' => $this->userId ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('获取代理信息失败');
        }
    }
}