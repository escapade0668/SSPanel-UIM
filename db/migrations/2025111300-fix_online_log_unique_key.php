<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $pdo = DB::getPdo();
        
        try {
            // 开始事务
            $pdo->beginTransaction();
            
            // 步骤1: 检查并删除旧的唯一键约束 UNIQUE KEY (user_id, ip)
            // 先查询约束名称（可能是自动生成的）
            $stmt = $pdo->query("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'online_log' 
                AND CONSTRAINT_TYPE = 'UNIQUE'
                AND CONSTRAINT_NAME != 'PRIMARY'
            ");
            
            $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 删除所有非主键的唯一约束
            foreach ($constraints as $constraintName) {
                $pdo->exec("ALTER TABLE online_log DROP INDEX `{$constraintName}`");
            }
            
            // 步骤2: 由于旧约束导致数据可能不完整，清理一下当前的在线日志
            // 只保留最近24小时的数据，避免脏数据影响
            $pdo->exec("DELETE FROM online_log WHERE last_time < UNIX_TIMESTAMP() - 86400");
            
            // 步骤3: 添加新的唯一键约束，包含 node_id
            // 这样可以支持同一IP连接多个节点
            $pdo->exec("
                ALTER TABLE online_log 
                ADD UNIQUE KEY `user_ip_node` (`user_id`, `ip`, `node_id`)
            ");
            
            // 步骤4: 确保其他必要的索引存在
            $pdo->exec("
                ALTER TABLE online_log 
                ADD KEY IF NOT EXISTS `idx_node_id` (`node_id`),
                ADD KEY IF NOT EXISTS `idx_last_time` (`last_time`)
            ");
            
            // 提交事务
            $pdo->commit();
            
            return 2025111300;
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollBack();
            throw $e;
        }
    }

    public function down(): int
    {
        $pdo = DB::getPdo();
        
        try {
            $pdo->beginTransaction();
            
            // 回滚：删除新的唯一键约束
            $pdo->exec("ALTER TABLE online_log DROP INDEX IF EXISTS `user_ip_node`");
            
            // 恢复旧的唯一键约束（虽然不推荐，但为了回滚完整性）
            $pdo->exec("
                ALTER TABLE online_log 
                ADD UNIQUE KEY `user_id` (`user_id`, `ip`)
            ");
            
            $pdo->commit();
            
            return 2024061600;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
};

