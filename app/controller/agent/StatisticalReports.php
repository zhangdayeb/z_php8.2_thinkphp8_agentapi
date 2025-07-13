<?php
namespace app\controller\agent;

use app\controller\AuthApiController;
use think\facade\Db;
use think\facade\Log;

class StatisticalReports extends AuthApiController
{
    // 内容注释
    public function get_statistical_reports()
    {
        return 'it work!';
    }
}