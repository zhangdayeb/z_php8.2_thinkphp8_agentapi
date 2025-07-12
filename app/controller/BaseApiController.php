<?php
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * API基础控制器 - 处理通用请求参数
 */
abstract class BaseApiController extends BaseController
{
    // 请求参数
    protected $groupPrefix = '';
    protected $authHeader = '';
    protected $token = '';
    protected $language = '';
    
    // 用户信息
    protected $isLoggedIn = false;
    protected $userId = null;
    protected $userInfo = null;
    
    /**
     * 控制器初始化
     */
    public function initialize()
    {
        parent::initialize();
        
        // 提取通用请求参数
        $this->extractRequestParams();
        
        // 记录请求日志
        $this->logRequest();
    }
    
    /**
     * 提取请求参数 - 参考 Register 控制器的逻辑
     */
    protected function extractRequestParams()
    {
        // 尝试多种方式获取 group_prefix
        $groupPrefixAttempts = [
            'property' => $this->groupPrefix,
            'group_prefix' => $this->request->header('group_prefix'),
            'group-prefix' => $this->request->header('group-prefix'),
            'Group_prefix' => $this->request->header('Group_prefix'),
            'Group-Prefix' => $this->request->header('Group-Prefix'),
            'GROUP_PREFIX' => $this->request->header('GROUP_PREFIX'),
            'GROUP-PREFIX' => $this->request->header('GROUP-PREFIX'),
            'groupprefix' => $this->request->header('groupprefix'),
            'GroupPrefix' => $this->request->header('GroupPrefix'),
        ];
        
        // 如果通过属性获取不到，尝试直接获取并设置
        if (empty($this->groupPrefix)) {
            foreach ($groupPrefixAttempts as $key => $value) {
                if (!empty($value) && $key !== 'property') {
                    $this->groupPrefix = $value;
                    Log::info("通过 {$key} 获取到 group_prefix", ['value' => $value]);
                    break;
                }
            }
            
            // 如果还是为空，设置默认值
            if (empty($this->groupPrefix)) {
                $this->groupPrefix = 'DHYL'; // 设置默认值
                Log::info('使用默认 group_prefix', ['default_value' => 'DHYL']);
            }
        }
        
        // 获取 authorization 头
        $this->authHeader = $this->getHeaderValue([
            'authorization',
            'Authorization',
            'AUTHORIZATION'
        ]);
        
        $this->token = str_replace('Bearer ', '', $this->authHeader);
        $this->language = $this->request->param('lang', 'zh_cn');
    }
    
    /**
     * 获取请求头值 - 尝试多种大小写组合
     * @param array $headerNames 要尝试的请求头名称数组
     * @param string $default 默认值
     * @return string
     */
    protected function getHeaderValue(array $headerNames, string $default = ''): string
    {
        foreach ($headerNames as $headerName) {
            $value = $this->request->header($headerName, null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return $default;
    }
    
    /**
     * 记录请求日志 - 增强版本
     */
    protected function logRequest()
    {
        // 记录所有请求头用于调试
        $allHeaders = $this->request->header();
        
        // 查找所有可能的 group_prefix 相关头部
        $groupPrefixHeaders = [];
        foreach ($allHeaders as $key => $value) {
            if (stripos($key, 'group') !== false || stripos($key, 'prefix') !== false) {
                $groupPrefixHeaders[$key] = $value;
            }
        }
        
        Log::info('API Request', [
            'controller' => get_class($this),
            'action' => $this->request->action(),
            'method' => $this->request->method(),
            'group_prefix' => $this->groupPrefix,
            'group_prefix_headers' => $groupPrefixHeaders, // 调试信息
            'has_token' => !empty($this->token),
            'language' => $this->language,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->header('user-agent', ''),
            'url' => $this->request->url(true),
            'all_headers' => $allHeaders // 完整的请求头信息（仅调试时启用）
        ]);
        
        // 如果获取到了 group_prefix，记录成功信息
        if (!empty($this->groupPrefix)) {
            Log::info('Group Prefix 获取成功', [
                'group_prefix' => $this->groupPrefix
            ]);
        }
    }
    
    /**
     * 构建通用数据库查询条件（基于 group_prefix）
     */
    protected function buildGroupQuery($query)
    {
        if (!empty($this->groupPrefix)) {
            // 通用数据(NULL或空) + 专属数据(匹配group_prefix)
            $query->where(function ($q) {
                $q->whereNull('group_prefix')
                  ->whereOr('group_prefix', '')
                  ->whereOr('group_prefix', $this->groupPrefix);
            });
        } else {
            // 如果没有group_prefix，只返回通用数据
            $query->where(function ($q) {
                $q->whereNull('group_prefix')
                  ->whereOr('group_prefix', '');
            });
        }
        
        return $query;
    }
    
    /**
     * 获取当前的 group_prefix
     * @return string
     */
    protected function getGroupPrefix(): string
    {
        return $this->groupPrefix;
    }
    
    /**
     * 统一成功响应格式
     */
    protected function success($data = [], $message = 'success', $code = 200)
    {
        if(is_string($data) && $message =='success'){
            // 如果只是输入 成功字符串
            return json([
                'code' => $code,
                'data' => [],
                'message' => $data,
                'timestamp' => time()
            ]);
        }else{
            // 默认返回成功的数据
            return json([
                'code' => $code,
                'data' => $data,
                'message' => $message,
                'timestamp' => time()
            ]);
        }
        
    }
    
    /**
     * 统一错误响应格式
     */
    protected function error($message = 'error', $code = 500, $data = [])
    {
        return json([
            'code' => $code,
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ]);
    }
}