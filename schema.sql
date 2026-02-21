-- ==============================================
-- To-Do List Application - SQL Schema
-- Project By Prabind
-- ==============================================

CREATE TABLE IF NOT EXISTS tasks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT    NOT NULL,
    description TEXT    DEFAULT '',
    priority    TEXT    DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
    category    TEXT    DEFAULT 'general',
    status      TEXT    DEFAULT 'pending' CHECK(status IN ('pending','in_progress','completed')),
    due_date    TEXT    DEFAULT NULL,
    created_at  TEXT    DEFAULT (datetime('now','localtime')),
    updated_at  TEXT    DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS categories (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT    NOT NULL UNIQUE,
    color TEXT    DEFAULT '#6c63ff',
    icon  TEXT    DEFAULT '📁'
);

-- Default categories
INSERT OR IGNORE INTO categories (name, color, icon) VALUES
    ('General',  '#6c63ff', '📁'),
    ('Work',     '#f59e0b', '💼'),
    ('Personal', '#10b981', '🏠'),
    ('Shopping', '#ef4444', '🛒'),
    ('Health',   '#3b82f6', '💪'),
    ('Study',    '#8b5cf6', '📚');

-- Sample tasks
INSERT OR IGNORE INTO tasks (title, description, priority, category, status) VALUES
    ('Welcome to your To-Do List!', 'Add your first task using the form above.', 'high', 'General', 'pending'),
    ('Complete project setup',       'Set up the development environment.',         'medium', 'Work',    'completed'),
    ('Read a book',                  'Finish reading the current book.',             'low',    'Personal','pending');
