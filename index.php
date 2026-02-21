<?php
/**
 * index.php — To-Do List Application Homepage
 * Project By Prabind
 */
require_once __DIR__ . '/db.php';
get_db();   // initialize the database on first load
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO -->
    <title>TaskFlow — Smart To-Do List · Project By Prabind</title>
    <meta name="description"
        content="TaskFlow is a sleek, modern to-do list app. Organise your tasks with priorities, categories, and due dates. Project By Prabind.">
    <meta name="author" content="Prabind">
    <meta property="og:title" content="TaskFlow — Project By Prabind">
    <meta property="og:description" content="Organise your life with a beautiful, feature-rich task manager.">

    <!-- Preconnect for Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="stylesheet" href="style.css">

    <!-- Favicon emoji shortcut -->
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✅</text></svg>">
</head>

<body>

    <!-- ══════════════  LOADING OVERLAY  ══════════════ -->
    <div id="loading-overlay" class="loading-overlay" aria-hidden="true">
        <div class="spinner"></div>
    </div>

    <!-- ══════════════  HEADER  ══════════════ -->
    <header class="site-header">

        <!-- Live badge -->
        <div class="brand-badge" aria-label="Application status">
            <span class="brand-dot"></span>
            TaskFlow &nbsp;·&nbsp; Smart Task Manager
        </div>

        <!-- App title -->
        <h1 class="site-title">✅ TaskFlow</h1>
        <p class="site-subtitle">Organise your day, conquer your goals.</p>

        <!-- ✨ Project By Prabind credit (prominent, animated) ✨ -->
        <div class="project-credit" id="project-credit">
            <span class="star-icon">✦</span>
            Project By Prabind
            <span class="star-icon">✦</span>
        </div>

    </header>

    <!-- ══════════════  STATS BAR  ══════════════ -->
    <div class="stats-bar" role="region" aria-label="Task statistics">

        <div class="stat-chip" title="Total tasks">
            📋 Total &nbsp; <span id="stat-total" class="stat-num">—</span>
        </div>
        <div class="stat-chip yellow" title="Pending tasks">
            ⏳ Pending &nbsp; <span id="stat-pending" class="stat-num">—</span>
        </div>
        <div class="stat-chip purple" title="In progress">
            🔄 Progress &nbsp; <span id="stat-progress" class="stat-num">—</span>
        </div>
        <div class="stat-chip green" title="Completed tasks">
            ✅ Done &nbsp; <span id="stat-completed" class="stat-num">—</span>
        </div>
        <div class="stat-chip red" title="High priority tasks">
            🔴 High &nbsp; <span id="stat-high" class="stat-num">—</span>
        </div>
        <div class="stat-chip red" title="Overdue tasks">
            ⚠️ Overdue &nbsp; <span id="stat-overdue" class="stat-num">—</span>
        </div>
        <div class="stat-chip" title="Completion %">
            📊 Done &nbsp; <span id="stat-pct" class="stat-num">—</span>
        </div>

    </div>

    <!-- Completion progress bar -->
    <div class="progress-ring-wrap" aria-label="Overall completion progress">
        <p class="progress-label">Overall Completion</p>
        <div class="progress-bar-track" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            <div id="progress-fill" class="progress-bar-fill" style="width:0%"></div>
        </div>
    </div>

    <!-- ══════════════  MAIN CONTENT  ══════════════ -->
    <main class="app-container">

        <!-- ── Add Task Card ── -->
        <section class="add-task-section glass-card" aria-label="Add new task">
            <p class="section-label">➕ New Task</p>

            <form id="add-task-form" onsubmit="addTask(event)" novalidate>

                <!-- Title row -->
                <div class="form-group" style="margin-bottom:.5rem">
                    <label class="form-label" for="add-title">Task Title *</label>
                    <input type="text" id="add-title" class="form-control form-control-title"
                        placeholder="What needs to be done?" required autocomplete="off" maxlength="255">
                </div>

                <!-- Description -->
                <div class="form-group" style="margin-bottom:.5rem">
                    <label class="form-label" for="add-desc">Description</label>
                    <textarea id="add-desc" class="form-control" placeholder="Add extra details (optional)…" rows="2"
                        maxlength="1000"></textarea>
                </div>

                <!-- Priority · Category · Due -->
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="add-priority">Priority</label>
                        <select id="add-priority" class="form-control">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🔴 High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-category">Category</label>
                        <select id="add-category" class="form-control">
                            <option value="General">📁 General</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-due">Due Date</label>
                        <input type="date" id="add-due" class="form-control" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Submit -->
                <div style="display:flex;justify-content:flex-end;margin-top:.75rem">
                    <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('add-task-form').reset()" style="margin-right:.6rem">
                        Clear
                    </button>
                    <button type="submit" id="btn-add-task" class="btn btn-primary btn-add">
                        ➕ Add Task
                    </button>
                </div>

            </form>
        </section>

        <!-- ── Toolbar: Search · Filter · Sort ── -->
        <div class="toolbar" role="toolbar" aria-label="Task filters">

            <!-- Search -->
            <div class="toolbar-left">
                <div class="search-wrap">
                    <span class="search-icon" aria-hidden="true">🔍</span>
                    <input type="search" id="search-input" class="form-control search-input"
                        placeholder="Search tasks…  (Ctrl+K)" oninput="onSearchInput(this.value)"
                        aria-label="Search tasks">
                </div>
            </div>

            <!-- Status filter tabs -->
            <div class="filter-tabs" role="tablist" aria-label="Filter by status">
                <button class="filter-tab active" data-filter="all" onclick="setFilter('all')" role="tab"
                    aria-selected="true">All</button>
                <button class="filter-tab" data-filter="pending" onclick="setFilter('pending')" role="tab"
                    aria-selected="false">⏳ Pending</button>
                <button class="filter-tab" data-filter="in_progress" onclick="setFilter('in_progress')" role="tab"
                    aria-selected="false">🔄 In Progress</button>
                <button class="filter-tab" data-filter="completed" onclick="setFilter('completed')" role="tab"
                    aria-selected="false">✅ Done</button>
            </div>

            <!-- Priority filter -->
            <select class="form-control" id="filter-priority" onchange="onPriorityFilterChange(this.value)"
                aria-label="Filter by priority" style="max-width:150px">
                <option value="">All Priorities</option>
                <option value="high">🔴 High</option>
                <option value="medium">🟡 Medium</option>
                <option value="low">🟢 Low</option>
            </select>

            <!-- Category filter -->
            <select class="form-control" id="filter-category" onchange="onCategoryFilterChange(this.value)"
                aria-label="Filter by category" style="max-width:160px">
                <option value="">All Categories</option>
            </select>

            <!-- Sort -->
            <select class="form-control" id="sort-select" onchange="onSortChange(this.value)" aria-label="Sort tasks"
                style="max-width:180px">
                <option value="created_desc">🕑 Newest First</option>
                <option value="created_asc">🕐 Oldest First</option>
                <option value="priority_desc">🔺 Priority</option>
                <option value="due_date">📅 Due Date</option>
            </select>

        </div>

        <!-- ── Task List ── -->
        <section class="task-list-section" aria-label="Task list" aria-live="polite">
            <p class="section-label">📋 Your Tasks</p>
            <div id="task-list" class="task-list">
                <!-- tasks rendered by script.js -->
            </div>
        </section>

    </main>

    <!-- ══════════════  EDIT MODAL  ══════════════ -->
    <div id="edit-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-label="Edit task"
        onclick="if(event.target===this) closeEditModal()">
        <div class="modal-box">

            <div class="modal-title">
                ✏️ Edit Task
                <button class="btn btn-icon" onclick="closeEditModal()" aria-label="Close modal">✕</button>
            </div>

            <form id="edit-task-form" class="modal-form" onsubmit="saveEdit(event)" novalidate>

                <div class="form-group">
                    <label class="form-label" for="edit-title">Title *</label>
                    <input type="text" id="edit-title" class="form-control" required maxlength="255">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-desc">Description</label>
                    <textarea id="edit-desc" class="form-control" rows="3" maxlength="1000"></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="edit-priority">Priority</label>
                        <select id="edit-priority" class="form-control">
                            <option value="low">🟢 Low</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="high">🔴 High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-status">Status</label>
                        <select id="edit-status" class="form-control">
                            <option value="pending">⏳ Pending</option>
                            <option value="in_progress">🔄 In Progress</option>
                            <option value="completed">✅ Done</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-due">Due Date</label>
                        <input type="date" id="edit-due" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-category">Category</label>
                    <select id="edit-category" class="form-control">
                        <option value="General">📁 General</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" id="btn-save-edit" class="btn btn-primary">💾 Save Changes</button>
                </div>

            </form>
        </div>
    </div>

    <!-- ══════════════  TOAST CONTAINER  ══════════════ -->
    <div id="toast-container" class="toast-container" aria-live="polite" aria-label="Notifications"></div>

    <!-- ══════════════  FOOTER  ══════════════ -->
    <footer class="site-footer" role="contentinfo">
        <p>
            Built with ❤️ using HTML · CSS · JavaScript · PHP · SQL · C &nbsp;|&nbsp;
            <span class="footer-credit">✦ Project By Prabind ✦</span>
        </p>
        <p style="margin-top:.4rem;color:var(--text-3);font-size:0.72rem">
            TaskFlow &copy;
            <?= date('Y') ?> &nbsp;·&nbsp; Data stored locally in SQLite
        </p>
    </footer>

    <!-- Scripts -->
    <script src="script.js"></script>
</body>

</html>