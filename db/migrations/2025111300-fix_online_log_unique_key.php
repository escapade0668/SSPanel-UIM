<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $pdo = DB::getPdo();

        // 直接删除旧的唯一索引（user_id, ip）
        $pdo->exec("ALTER TABLE online_log DROP INDEX `user_id`");

        // 添加新的唯一索引，包含 node_id
        $pdo->exec("ALTER TABLE online_log ADD UNIQUE KEY `user_ip_node` (`user_id`, `ip`, `node_id`)");

        return 2025111300;
    }

    public function down(): int
    {
        $pdo = DB::getPdo();

        // 删除新的唯一索引
        $pdo->exec("ALTER TABLE online_log DROP INDEX `user_ip_node`");

        // 恢复旧的唯一索引
        $pdo->exec("ALTER TABLE online_log ADD UNIQUE KEY `user_id` (`user_id`, `ip`)");

        return 2025073100;
    }
};
