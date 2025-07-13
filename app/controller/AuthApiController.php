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
            
            // **核心逻辑：检查Token剩余时间并处理**
            $currentTime = time();
            $expireTime = $payload->exp ?? 0;
            $remainingTime = $expireTime - $currentTime; // 计算剩余时间
            $configExpireTime = (int)($_ENV['JWT_EXPIRE_TIME'] ?? 3600); // 获取配置时间
            
            if ($remainingTime <= 0) {
                // 情况3：Token已过期，提示重新登录
                throw new \Exception('Token已过期');
            } elseif ($remainingTime < $configExpireTime) {
                // 情况1：剩余时间不足配置时间，自动续期
                $this->extendToken($payload, $secretKey, $algorithm, $issuer, $configExpireTime);
                
                Log::debug('Token自动续期', [
                    'user_id' => $this->userId,
                    'remaining_time' => $remainingTime . '秒',
                    'config_time' => $configExpireTime . '秒',
                    'action' => '续期到' . $configExpireTime . '秒'
                ]);
            } else {
                // 情况2：剩余时间充足，不处理
                Log::debug('Token时间充足，无需续期', [
                    'user_id' => $this->userId,
                    'remaining_time' => $remainingTime . '秒',
                    'config_time' => $configExpireTime . '秒'
                ]);
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
     * 延长Token有效期
     * @param object $payload JWT载荷
     * @param string $secretKey 密钥
     * @param string $algorithm 算法
     * @param string $issuer 发行者
     * @param int $configExpireTime 配置的过期时间
     */
    protected function extendToken($payload, $secretKey, $algorithm, $issuer, $configExpireTime)
    {
        try {
            $now = time();
            
            // 构建新的Token载荷（保持原有信息，只更新过期时间）
            $newPayload = [
                'iss' => $payload->iss ?? $issuer,           // 保持原发行者
                'sub' => $payload->sub ?? null,              // 保持原用户ID
                'iat' => $payload->iat ?? $now,              // 保持原发行时间
                'exp' => $now + $configExpireTime,           // **重新设定有效期：当前时间 + 配置时间**
                'loginsec' => $payload->loginsec ?? $now     // 保持原登录时间
            ];
            
            // 生成新的Token
            $newToken = JWT::encode($newPayload, $secretKey, $algorithm);
            
            // 通过响应头返回新Token
            header('Authorization: Bearer ' . $newToken);
            header('X-Token-Extended: 1');
            
            // 更新当前实例的用户信息
            $this->userInfo['exp'] = $now + $configExpireTime;
            
            Log::info('Token续期成功', [
                'user_id' => $this->userId,
                'old_expire_time' => date('Y-m-d H:i:s', $payload->exp ?? 0),
                'new_expire_time' => date('Y-m-d H:i:s', $now + $configExpireTime),
                'extended_duration' => $configExpireTime . '秒'
            ]);
            
        } catch (\Exception $e) {
            // 续期失败不影响正常业务，只记录日志
            Log::warning('Token续期失败', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
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