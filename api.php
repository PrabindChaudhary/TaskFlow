<?php
/**
 * api.php — REST API for To-Do List
 * Project By Prabind
 *
 * Endpoints:
 *  GET    api.php?action=get_tasks[&status=...][&category=...][&priority=...]
 *  GET    api.php?action=get_categories
 *  GET    api.php?action=get_stats
 *  POST   api.php?action=add_task      body: {title,description,priority,category,due_date}
 *  POST   api.php?action=update_task   body: {id,...fields...}
 *  POST   api.php?action=delete_task   body: {id}
 *  POST   api.php?action=toggle_status body: {id}
 *  GET    api.php?action=export_csv
 *  GET    api.php?action=c_stats       (calls compiled C helper if available)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_tasks';
$pdo = get_db();

/* ──────────────────────────────────────────────────────── */
try {
    switch ($action) {

        /* ── GET: list tasks ── */
        case 'get_tasks': {
            $where = [];
            $params = [];

            if (!empty($_GET['status'])) {
                $where[] = 'status = ?';
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['category'])) {
                $where[] = 'category = ?';
                $params[] = $_GET['category'];
            }
            if (!empty($_GET['priority'])) {
                $where[] = 'priority = ?';
                $params[] = $_GET['priority'];
            }
            if (!empty($_GET['search'])) {
                $where[] = '(title LIKE ? OR description LIKE ?)';
                $term = '%' . $_GET['search'] . '%';
                $params[] = $term;
                $params[] = $term;
            }

            $sql = 'SELECT * FROM tasks';
            if ($where)
                $sql .= ' WHERE ' . implode(' AND ', $where);

            $order_map = [
                'created_desc' => 'created_at DESC',
                'created_asc' => 'created_at ASC',
                'priority_desc' => "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END",
                'due_date' => 'due_date ASC',
            ];
            $sort = $_GET['sort'] ?? 'created_desc';
            $sql .= ' ORDER BY ' . ($order_map[$sort] ?? 'created_at DESC');

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'tasks' => $stmt->fetchAll()]);
            break;
        }

        /* ── GET: categories ── */
        case 'get_categories': {
            $cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
            echo json_encode(['success' => true, 'categories' => $cats]);
            break;
        }

        /* ── GET: stats ── */
        case 'get_stats': {
            $total = (int) $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
            $pending = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='pending'")->fetchColumn();
            $progress = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='in_progress'")->fetchColumn();
            $completed = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn();
            $high = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE priority='high'")->fetchColumn();
            $pct = $total > 0 ? round($completed / $total * 100, 1) : 0;

            /* overdue count */
            $today = date('Y-m-d');
            $overdue = (int) $pdo->query(
                "SELECT COUNT(*) FROM tasks
                 WHERE due_date IS NOT NULL AND due_date < '$today'
                 AND status != 'completed'"
            )->fetchColumn();

            echo json_encode([
                'success' => true,
                'total' => $total,
                'pending' => $pending,
                'in_progress' => $progress,
                'completed' => $completed,
                'high_priority' => $high,
                'overdue' => $overdue,
                'completion_pct' => $pct,
            ]);
            break;
        }

        /* ── POST: add task ── */
        case 'add_task': {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $title = trim($body['title'] ?? '');
            if ($title === '') {
                echo json_encode(['success' => false, 'error' => 'Title is required']);
                break;
            }
            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, description, priority, category, due_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                trim($body['description'] ?? ''),
                in_array($body['priority'] ?? '', ['low', 'medium', 'high'])
                ? $body['priority'] : 'medium',
                trim($body['category'] ?? 'General'),
                !empty($body['due_date']) ? $body['due_date'] : null,
            ]);
            $id = $pdo->lastInsertId();
            $task = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
            $task->execute([$id]);
            echo json_encode(['success' => true, 'task' => $task->fetch()]);
            break;
        }

        /* ── POST: update task ── */
        case 'update_task': {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int) ($body['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID required']);
                break;
            }

            $fields = [];
            $params = [];
            $allowed = ['title', 'description', 'priority', 'category', 'status', 'due_date'];
            foreach ($allowed as $f) {
                if (isset($body[$f])) {
                    $fields[] = "$f=?";
                    $params[] = $body[$f];
                }
            }
            if (!$fields) {
                echo json_encode(['success' => false, 'error' => 'Nothing to update']);
                break;
            }

            $fields[] = "updated_at=datetime('now','localtime')";
            $params[] = $id;
            $pdo->prepare("UPDATE tasks SET " . implode(',', $fields) . " WHERE id=?")->execute($params);

            $s = $pdo->prepare("SELECT * FROM tasks WHERE id=?");
            $s->execute([$id]);
            echo json_encode(['success' => true, 'task' => $s->fetch()]);
            break;
        }

        /* ── POST: delete task ── */
        case 'delete_task': {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int) ($body['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID required']);
                break;
            }
            $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        }

        /* ── POST: toggle status  pending→in_progress→completed→pending ── */
        case 'toggle_status': {
            $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int) ($body['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID required']);
                break;
            }

            $cur = $pdo->prepare("SELECT status FROM tasks WHERE id=?");
            $cur->execute([$id]);
            $row = $cur->fetch();
            if (!$row) {
                echo json_encode(['success' => false, 'error' => 'Task not found']);
                break;
            }

            $next = [
                'pending' => 'in_progress',
                'in_progress' => 'completed',
                'completed' => 'pending',
            ][$row['status']] ?? 'pending';

            $pdo->prepare(
                "UPDATE tasks SET status=?, updated_at=datetime('now','localtime') WHERE id=?"
            )->execute([$next, $id]);

            echo json_encode(['success' => true, 'new_status' => $next]);
            break;
        }

        /* ── GET: export CSV for C helper ── */
        case 'export_csv': {
            $path = export_csv_for_c();
            echo json_encode(['success' => true, 'path' => basename($path)]);
            break;
        }

        /* ── GET: run C helper stats (if compiled) ── */
        case 'c_stats': {
            /* Export CSV first */
            $csv = export_csv_for_c();
            $exe = __DIR__ . '/todo_helper.exe';

            if (!file_exists($exe)) {
                /* graceful fallback: return PHP-computed stats */
                $total = (int) $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
                $done = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn();
                $high = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE priority='high'")->fetchColumn();
                echo json_encode([
                    'success' => true,
                    'source' => 'php_fallback',
                    'note' => 'Compile todo_helper.c to enable C-powered stats',
                    'stats' => [
                        'total' => $total,
                        'completed' => $done,
                        'high_priority' => $high,
                        'completion_pct' => $total > 0 ? round($done / $total * 100, 1) : 0,
                    ],
                ]);
                break;
            }

            /* Run compiled C program */
            $cmd = escapeshellcmd($exe) . ' stats ' . escapeshellarg($csv);
            $output = shell_exec($cmd);
            $data = json_decode($output, true);
            echo json_encode(['success' => true, 'source' => 'c_helper', 'stats' => $data]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
