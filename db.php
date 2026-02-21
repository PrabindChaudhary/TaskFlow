<?php
/**
 * db.php — Database Connection & Initialization
 * Project By Prabind
 * Uses SQLite via PHP PDO — no separate MySQL server needed
 */

define('DB_PATH', __DIR__ . '/todo.db');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');   // better concurrency
        $pdo->exec('PRAGMA foreign_keys=ON');
        init_db($pdo);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
    }
    return $pdo;
}

function init_db(PDO $pdo): void {
    /* ── tasks table ── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT    NOT NULL,
            description TEXT    DEFAULT '',
            priority    TEXT    DEFAULT 'medium'
                        CHECK(priority IN ('low','medium','high')),
            category    TEXT    DEFAULT 'General',
            status      TEXT    DEFAULT 'pending'
                        CHECK(status IN ('pending','in_progress','completed')),
            due_date    TEXT    DEFAULT NULL,
            created_at  TEXT    DEFAULT (datetime('now','localtime')),
            updated_at  TEXT    DEFAULT (datetime('now','localtime'))
        )
    ");

    /* ── categories table ── */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            name  TEXT    NOT NULL UNIQUE,
            color TEXT    DEFAULT '#6c63ff',
            icon  TEXT    DEFAULT '📁'
        )
    ");

    /* seed default categories */
    $cats = [
        ['General',  '#6c63ff', '📁'],
        ['Work',     '#f59e0b', '💼'],
        ['Personal', '#10b981', '🏠'],
        ['Shopping', '#ef4444', '🛒'],
        ['Health',   '#3b82f6', '💪'],
        ['Study',    '#8b5cf6', '📚'],
    ];
    $stmt = $pdo->prepare(
        "INSERT OR IGNORE INTO categories (name,color,icon) VALUES (?,?,?)"
    );
    foreach ($cats as $c) $stmt->execute($c);

    /* seed sample tasks only if table is empty */
    $count = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare("
            INSERT INTO tasks (title, description, priority, category, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $samples = [
            ['👋 Welcome to your To-Do List!',
             'Start by adding a new task using the form. You can set priorities, categories, and due dates!',
             'high', 'General', 'pending'],
            ['Review project requirements',
             'Go through all the project specs and note down key deliverables.',
             'high', 'Work', 'in_progress'],
            ['Buy groceries',
             'Milk, eggs, bread, fruits and vegetables.',
             'medium', 'Shopping', 'pending'],
            ['Morning jog',
             '30 minute run around the park.',
             'low', 'Health', 'completed'],
        ];
        foreach ($samples as $s) $insert->execute($s);
    }
}

/**
 * Export tasks as CSV for the C helper tool
 */
function export_csv_for_c(): string {
    $pdo  = get_db();
    $rows = $pdo->query(
        "SELECT id,title,priority,status,category FROM tasks ORDER BY id"
    )->fetchAll();

    $path = __DIR__ . '/tasks_export.csv';
    $fp   = fopen($path, 'w');
    fputcsv($fp, ['id','title','priority','status','category']);
    foreach ($rows as $r) {
        fputcsv($fp, [
            $r['id'],
            $r['title'],
            $r['priority'],
            $r['status'],
            $r['category'],
        ]);
    }
    fclose($fp);
    return $path;
}
