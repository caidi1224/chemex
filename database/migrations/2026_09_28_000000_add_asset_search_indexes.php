<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 资产搜索缓慢修复（补丁来源：172.16.68.52 线上库的手工索引）。
 *
 * 线上排查发现：资产列表按部门 / 使用人过滤（admin_users.department_id），
 * 以及设备归属记录关联（device_tracks.device_id、device_tracks.user_id）
 * 都没有可用索引，数据量上来后只能全表扫描，搜索明显变慢。
 *
 * 线上当时是直接执行 SQL 补的索引：
 *   ALTER TABLE admin_users ADD KEY idx_department_id (department_id);
 *   ALTER TABLE device_tracks ADD KEY idx_device_id (device_id), ADD KEY idx_user_id (user_id);
 *
 * 这里用迁移固化同样的索引，保证新环境部署后自带索引，并且可以随迁移回滚。
 * 每个索引都先探测是否存在，重复执行不会报错。
 */
return new class extends Migration {
    /**
     * 索引清单：[表名, 列名, 索引名]。
     */
    private const INDEXES = [
        ['admin_users', 'department_id', 'idx_department_id'],
        ['device_tracks', 'device_id', 'idx_device_id'],
        ['device_tracks', 'user_id', 'idx_user_id'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDEXES as [$table, $column, $index]) {
            if (!$this->hasIndex($table, $index)) {
                DB::statement("ALTER TABLE `{$table}` ADD KEY `{$index}` (`{$column}`)");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::INDEXES as [$table, $column, $index]) {
            if ($this->hasIndex($table, $index)) {
                DB::statement("ALTER TABLE `{$table}` DROP KEY `{$index}`");
            }
        }
    }

    /**
     * 判断索引是否已存在。
     */
    private function hasIndex(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS'
            .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }
};
