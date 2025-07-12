<?php
namespace app\controller;

use think\facade\Db;
use think\facade\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 公开API控制器 - 不需要登录验证
 */
abstract class PublicApiController extends BaseApiController
{
    /**
     * 控制器初始化
     */
    public function initialize()
    {
        parent::initialize();
        
        // 公开接口不验证token，但可以尝试解析用户信息
        $this->tryParseUserInfo();
    }
    
    /**
     * 尝试解析用户信息（不强制要求）
     */
    protected function tryParseUserInfo()
    {
        if (!empty($this->token)) {
            try {
                $secretKey = config('jwt.secret_key', 'your-secret-key');
                $payload = JWT::decode($this->token, new Key($secretKey, 'HS256'));
                
                $this->isLoggedIn = true;
                $this->userId = $payload->sub ?? null;
                $this->userInfo = [
                    'user_id' => $this->userId,
                    'login_time' => $payload->loginsec ?? null,
                    'exp' => $payload->exp ?? null
                ];
                
                Log::debug('User info parsed from token', [
                    'user_id' => $this->userId,
                    'controller' => get_class($this)
                ]);
                
            } catch (\Exception $e) {
                // Token无效不影响公开接口访问
                Log::debug('Token parse failed in public API', [
                    'error' => $e->getMessage(),
                    'controller' => get_class($this)
                ]);
            }
        }
    }
}