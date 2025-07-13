<?php
namespace app\controller\auth;

use app\controller\PublicApiController;
use think\facade\Db;
use think\facade\Log;

class Menu extends PublicApiController
{

    /**
     * 菜单栏目树
     */
    public function index()
    {
        try {
            // 获取父级菜单
            $parentMenus = Db::name('common_agent_menu')
                ->where(['pid' => 0, 'status' => 1])
                ->order('sort asc')
                ->select()
                ->toArray();
            
            // 为每个父级菜单添加子菜单
            foreach ($parentMenus as &$item) {
                $item['children'] = Db::name('common_agent_menu')
                    ->where(['pid' => $item['id'], 'status' => 1])
                    ->order('sort asc')
                    ->select()
                    ->toArray();
            }
            
            // 记录日志用于调试
            Log::info('Menu data:', ['count' => count($parentMenus), 'data' => $parentMenus]);
            
            // 修改成功响应的 code 为 1（前端期待 res.code == 1）
            return json([
                'code' => 1,
                'data' => ['data' => $parentMenus],
                'message' => '菜单获取成功',
                'timestamp' => time()
            ]);
            
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('Menu index error: ' . $e->getMessage());
            
            // 返回错误响应
            return $this->error('菜单获取失败: ' . $e->getMessage(), 500);
        }
    }

}