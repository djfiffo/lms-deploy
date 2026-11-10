<?php
require_once dirname(__DIR__) . '/config.php';

$files = [
    __DIR__ . '/migrations/001_initial.sql',
    __DIR__ . '/migrations/002_ml_foundation.sql',
    __DIR__ . '/migrations/003_runtime_compatibility.sql',
    __DIR__ . '/migrations/004_course_curriculum.sql',
];

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('อ่าน migration ไม่ได้: ' . $file);
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if (preg_match('/^ALTER\s+TABLE\s+([a-zA-Z0-9_]+)\s+ADD\s+COLUMN\s+([a-zA-Z0-9_]+)/i', $statement, $match)) {
            $columnCheck = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $columnCheck->execute([$match[1], $match[2]]);
            if ((int)$columnCheck->fetchColumn() > 0) {
                continue;
            }
        }
        $pdo->exec($statement);
    }
}

echo "Database migrations completed.\n";
