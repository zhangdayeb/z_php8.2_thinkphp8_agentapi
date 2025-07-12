<?php
// ==================== app/controller/AuthApiController.php ====================
namespace app\controller;

use think\exception\HttpException;
use think\facade\Db;
use think\facade\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 需要认证的API控制器 - 必须登录
 */
abstract class AuthApiController extends BaseApiController
{
    /**
     * 控制器初始化
     */
    public function initialize()
    {
        parent::initialize();
        
        // 验证用户登录状态
        $this->validateAuth();
    }
    
    /**
     * 验证用户认证
     */
    protected function validateAuth()
    {
        if (empty($this->token)) {
            Log::warning('Missing token in auth API', [
                'controller' => get_class($this),
                'ip' => $this->request->ip()
            ]);
            
            return $this->error('请先登录',401,[]);
        }
        
        try {
            // 直接从.env读取JWT配置
            $secretKey = $_ENV['JWT_SECRET_KEY'] ?? 'default-secret-key';
            $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
            $issuer = $_ENV['JWT_ISSUER'] ?? '';
            
            $payload = JWT::decode($this->token, new Key($secretKey, $algorithm));
            
            // 验证发行者（如果设置了）
            if ($issuer && isset($payload->iss) && $payload->iss !== $issuer) {
                throw new \Exception('Token发行者不匹配');
            }
            
            $this->isLoggedIn = true;
            $this->userId = $payload->sub ?? null;
            $this->userInfo = [
                'user_id' => $this->userId,
                'login_time' => $payload->loginsec ?? null,
                'exp' => $payload->exp ?? null
            ];
            
            // 检查token是否过期
            if (isset($payload->exp) && time() > $payload->exp) {
                throw new \Exception('Token已过期');
            }
            
            Log::debug('User authenticated successfully', [
                'user_id' => $this->userId,
                'controller' => get_class($this)
            ]);
            
        } catch (\Exception $e) {
            Log::warning('Authentication failed', [
                'error' => $e->getMessage(),
                'controller' => get_class($this),
                'ip' => $this->request->ip()
            ]);
            
            return $this->error('登录已过期，请重新登录',401,[]);
        }
    }
    
    /**
     * 获取当前用户ID
     */
    protected function getCurrentUserId()
    {
        return $this->userId;
    }
    
    /**
     * 获取当前用户信息
     */
    protected function getCurrentUserInfo()
    {
        return $this->userInfo;
    }
    
    /**
     * 生成新Token（用于登录成功后）
     */
    public function generateToken($userId)
    {
        $secretKey = $_ENV['JWT_SECRET_KEY'] ?? 'default-secret-key';
        $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
        $issuer = $_ENV['JWT_ISSUER'] ?? '';
        $expireTime = (int)($_ENV['JWT_EXPIRE_TIME'] ?? 3600);
        
        $now = time();
        $payload = [
            'iss' => $issuer,           // 发行者
            'sub' => $userId,           // 用户ID
            'iat' => $now,              // 发行时间
            'exp' => $now + $expireTime, // 过期时间
            'loginsec' => $now          // 登录时间
        ];
        
        return JWT::encode($payload, $secretKey, $algorithm);
    }
    
    /**
     * 检查用户权限（可重写）
     */
    protected function checkPermission($permission = '')
    {
        // 子类可以重写此方法实现具体的权限检查
        return true;
    }
}