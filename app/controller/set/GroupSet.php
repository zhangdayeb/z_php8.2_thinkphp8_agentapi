<?php
namespace app\controller\set;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;

class GroupSet extends AuthApiController
{
    /**
     * 通过请求域名获取集团配置
     * @return \think\Response
     */
    public function get_group_set()
    {
        try {
            // 获取前端请求的域名
            $requestDomain = $this->request->domain();
            
            // 也可以通过 HTTP_HOST 头获取
            $httpHost = $this->request->header('host', '');
            
            // 优先使用 HTTP_HOST，如果没有则使用 domain()
            $currentDomain = !empty($httpHost) ? $httpHost : parse_url($requestDomain, PHP_URL_HOST);
            
            Log::info('获取集团配置请求: ' . json_encode([
                'request_domain' => $requestDomain,
                'http_host' => $httpHost,
                'current_domain' => $currentDomain,
                'full_url' => $this->request->url(true)
            ]));
            
            if (empty($currentDomain)) {
                return $this->error('无法获取请求域名');
            }
            
            // 查询 ntp_group_set 表，通过 agent_url 进行模糊匹配
            $groupConfig = Db::name('group_set')
                ->where('agent_url', 'like', '%' . $currentDomain . '%')
                ->where('status', 1) // 只查询状态为启用的配置
                ->find();
            
            // 如果没有找到匹配的配置，尝试不带协议的匹配
            if (!$groupConfig) {
                // 移除协议部分再次尝试匹配
                $domainOnly = str_replace(['http://', 'https://', 'www.'], '', $currentDomain);
                
                $groupConfig = Db::name('group_set')
                    ->where('agent_url', 'like', '%' . $domainOnly . '%')
                    ->where('status', 1)
                    ->find();
                
                Log::info('第二次匹配尝试: ' . json_encode([
                    'domain_only' => $domainOnly,
                    'found' => !empty($groupConfig)
                ]));
            }
            
            if (!$groupConfig) {
                return $this->error('未找到匹配的集团配置', 404);
            }
            
            // 记录匹配成功的日志
            Log::info('成功匹配到集团配置: ' . json_encode([
                'group_prefix' => $groupConfig['group_prefix'],
                'group_name' => $groupConfig['group_name'],
                'agent_url' => $groupConfig['agent_url'],
                'matched_domain' => $currentDomain
            ]));
            
            // 构建返回数据
            $result = [
                'id' => $groupConfig['id'],
                'group_prefix' => $groupConfig['group_prefix'], // 重点字段
                'group_name' => $groupConfig['group_name'],
                'site_name' => $groupConfig['site_name'],
                'site_wap_logo' => $groupConfig['site_wap_logo'],
                'site_description' => $groupConfig['site_description'],
                'customer_service_url' => $groupConfig['customer_service_url'],
                'web_url' => $groupConfig['web_url'],
                'admin_url' => $groupConfig['admin_url'],
                'agent_url' => $groupConfig['agent_url'],
                'lobby_url' => $groupConfig['lobby_url'],
                'promotion_url' => $groupConfig['promotion_url'],
                'money' => $groupConfig['money'],
                'status' => $groupConfig['status'],
                'remarkt' => $groupConfig['remarkt'],
                'ip_white' => $groupConfig['ip_white'],
                'ip_black' => $groupConfig['ip_black'],
                'supplier_show_ids' => $groupConfig['supplier_show_ids'],
                'supplier_run_ids' => $groupConfig['supplier_run_ids'],
                'game_show_ids' => $groupConfig['game_show_ids'],
                'game_run_ids' => $groupConfig['game_run_ids'],
                'create_at' => $groupConfig['create_at']
            ];
            
            return $this->success($result, '获取集团配置成功');
            
        } catch (\Exception $e) {
            Log::error('获取集团配置失败: ' . json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]));
            
            return $this->error('获取集团配置失败：' . $e->getMessage());
        }
    }
    

}