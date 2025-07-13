<?php
namespace app\controller\login;

use app\controller\PublicApiController;
use think\facade\Db;
use think\facade\Log;
use Firebase\JWT\JWT;

class Login extends PublicApiController
{
    /**
     * 代理登录控制器
     */
    public function login()
    {
        try {
            // 获取POST数据
            $post = $this->request->post();
            
            // 基础数据验证
            $this->validateLoginData($post);
            
            // 查询代理账号
            $agent = $this->findAgent($post['user_name'], $post['pwd']);
            
            // 验证验证码
            $this->validateCaptcha($post['captcha']);
            
            // 使用统一的JWT Token生成方法
            $token = $this->generateToken($agent['id']);
            
            // 存储Session（可选）
            session('agent_user', $agent);
            
            // 记录登录日志
            $this->recordLoginLog($agent);
            
            // 返回成功响应
            return $this->success([
                'token' => $token,
                'user' => $agent
            ], '登录成功',1);
            
        } catch (\Exception $e) {
            // 记录登录失败日志
            $this->recordLoginFailLog($post['user_name'] ?? '', $e->getMessage());
            
            Log::error('代理登录失败', [
                'error' => $e->getMessage(),
                'user_name' => $post['user_name'] ?? '',
                'ip' => $this->request->ip()
            ]);
            
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 验证登录数据
     */
    private function validateLoginData($post)
    {
        if (empty($post['user_name'])) {
            throw new \Exception('请输入代理账号');
        }
        
        if (empty($post['pwd'])) {
            throw new \Exception('请输入密码');
        }
        
        if (empty($post['captcha'])) {
            throw new \Exception('请输入验证码');
        }
    }
    
    /**
     * 查询代理账号
     */
    private function findAgent($userName, $password)
    {
        // 将密码进行Base64编码
        $encodedPassword = base64_encode($password);
        
        // 获取当前集团前缀
        $groupPrefix = $this->getGroupPrefix();
        
        // 查询代理信息
        $agent = Db::table('ntp_common_group_agent')
            ->where('agent_name', $userName)
            ->where('agent_pwd', $encodedPassword)
            ->where('group_prefix', $groupPrefix)
            ->where('status', 1)
            ->find();
            
        if (empty($agent)) {
            throw new \Exception('账号或密码错误，或账号已被冻结');
        }
        
        return $agent;
    }
    
    /**
     * 验证验证码
     */
    private function validateCaptcha($captcha)
    {
        // 固定验证码：aa123456
        if ($captcha !== 'aa123456') {
            throw new \Exception('验证码错误');
        }
    }
    
    /**
     * 生成JWT Token - 使用统一的认证控制器方法
     */
    private function generateToken($userId)
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
     * 记录登录日志
     */
    private function recordLoginLog($agent)
    {
        try {
            // 解析用户代理信息
            $userAgent = $this->request->header('user-agent', '');
            $deviceInfo = $this->parseUserAgent($userAgent);
            
            // 记录到文件日志
            Log::info('代理登录成功', [
                'agent_id' => $agent['id'],
                'agent_name' => $agent['agent_name'],
                'group_prefix' => $agent['group_prefix'],
                'agent_type' => $agent['agent_type'],
                'login_time' => date('Y-m-d H:i:s'),
                'ip' => $this->request->ip(),
                'user_agent' => $userAgent
            ]);
            
            // 插入数据库登录日志
            Db::table('ntp_agent_login_log')->insert([
                'agent_id' => $agent['id'],
                'agent_name' => $agent['agent_name'],
                'group_prefix' => $agent['group_prefix'],
                'login_ip' => $this->request->ip(),
                'login_time' => date('Y-m-d H:i:s'),
                'user_agent' => $userAgent,
                'login_status' => 1,
                'session_id' => session_id(),
                'login_device' => $deviceInfo['device'],
                'browser_info' => $deviceInfo['browser'],
                'create_time' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            // 日志记录失败不影响登录
            Log::warning('登录日志记录失败', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * 解析用户代理信息
     */
    private function parseUserAgent($userAgent)
    {
        $device = 'Unknown';
        $browser = 'Unknown';
        
        if (empty($userAgent)) {
            return ['device' => $device, 'browser' => $browser];
        }
        
        // 简单的设备检测
        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            $device = 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false || strpos($userAgent, 'iPad') !== false) {
            $device = 'Tablet';
        } else {
            $device = 'Desktop';
        }
        
        // 简单的浏览器检测
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        } elseif (strpos($userAgent, 'Opera') !== false) {
            $browser = 'Opera';
        }
        
        return ['device' => $device, 'browser' => $browser];
    }
    
    /**
     * 记录登录失败日志
     */
    private function recordLoginFailLog($userName, $reason)
    {
        try {
            $userAgent = $this->request->header('user-agent', '');
            $deviceInfo = $this->parseUserAgent($userAgent);
            
            // 记录到文件日志
            Log::warning('代理登录失败', [
                'agent_name' => $userName,
                'reason' => $reason,
                'ip' => $this->request->ip(),
                'user_agent' => $userAgent
            ]);
            
            // 插入数据库登录日志
            Db::table('ntp_agent_login_log')->insert([
                'agent_id' => 0,
                'agent_name' => $userName,
                'group_prefix' => $this->getGroupPrefix(),
                'login_ip' => $this->request->ip(),
                'login_time' => date('Y-m-d H:i:s'),
                'user_agent' => $userAgent,
                'login_status' => 0,
                'fail_reason' => $reason,
                'login_device' => $deviceInfo['device'],
                'browser_info' => $deviceInfo['browser'],
                'create_time' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            Log::error('记录登录失败日志出错', ['error' => $e->getMessage()]);
        }
    }
}