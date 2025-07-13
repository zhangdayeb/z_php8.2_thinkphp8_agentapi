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
        $list = $this->model->where(['pid' => 0, 'status' => 1])->order('sort asc')->paginate()->each(function ($item, $key) {
            $item->children = $this->model->where(['pid' => $item['id'], 'status' => 1])->order('sort asc')->select();
        });
        //处理栏目树
        $this->success($list);
    }


}