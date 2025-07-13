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
        $list = Db::name('common_agent_menu')->where(['pid' => 0, 'status' => 1])->order('sort asc')->select()->each(function ($item, $key) {
            $item->children = Db::name('common_agent_menu')->where(['pid' => $item['id'], 'status' => 1])->order('sort asc')->select();
        });
        //处理栏目树
        $this->success($list);
    }


}