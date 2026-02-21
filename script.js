/**
 * script.js — To-Do List Frontend Logic
 * Project By Prabind
 *
 * Handles:
 *  - AJAX calls to api.php
 *  - Dynamic rendering of task cards
 *  - Add / Edit / Delete / Toggle status
 *  - Search, filter, sort
 *  - Stats refresh
 *  - Toast notifications
 */

'use strict';

/* ─────────────────────────────────────────────────
   STATE
───────────────────────────────────────────────── */
const State = {
    tasks:      [],
    categories: [],
    stats:      {},
    filter:     'all',          // all | pending | in_progress | completed
    priority:   '',             // '' | low | medium | high
    category:   '',
    sort:       'created_desc',
    search:     '',
    editId:     null,
};

/* ─────────────────────────────────────────────────
   HELPERS
───────────────────────────────────────────────── */
const $ = id => document.getElementById(id);

async function api(action, method = 'GET', body = null, extra = '') {
    const url = `api.php?action=${action}${extra}`;
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const res  = await fetch(url, opts);
    return res.json();
}

function toast(msg, type = 'info', ms = 3000) {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    $('toast-container').appendChild(el);
    setTimeout(() => {
        el.classList.add('fadeout');
        el.addEventListener('animationend', () => el.remove());
    }, ms);
}

function formatDate(str) {
    if (!str) return '';
    const d = new Date(str);
    return new Intl.DateTimeFormat(undefined, { month:'short', day:'numeric', year:'numeric' }).format(d);
}

function isOverdue(dueDate, status) {
    if (!dueDate || status === 'completed') return false;
    return new Date(dueDate) < new Date(new Date().toDateString());
}

function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ─────────────────────────────────────────────────
   FETCH DATA
───────────────────────────────────────────────── */
async function loadCategories() {
    const data = await api('get_categories');
    if (data.success) {
        State.categories = data.categories;
        populateCategorySelects();
    }
}

async function loadStats() {
    const data = await api('get_stats');
    if (!data.success) return;
    State.stats = data;
    renderStats(data);
}

async function loadTasks() {
    let extra = '';
    if (State.filter !== 'all') extra += `&status=${State.filter}`;
    if (State.priority)         extra += `&priority=${State.priority}`;
    if (State.category)         extra += `&category=${encodeURIComponent(State.category)}`;
    if (State.search)           extra += `&search=${encodeURIComponent(State.search)}`;
    extra += `&sort=${State.sort}`;

    const data = await api('get_tasks', 'GET', null, extra);
    if (data.success) {
        State.tasks = data.tasks;
        renderTasks();
    }
}

async function refreshAll() {
    await Promise.all([loadStats(), loadTasks()]);
}

/* ─────────────────────────────────────────────────
   RENDER: STATS
───────────────────────────────────────────────── */
function renderStats(s) {
    $('stat-total').textContent       = s.total;
    $('stat-pending').textContent     = s.pending;
    $('stat-progress').textContent    = s.in_progress;
    $('stat-completed').textContent   = s.completed;
    $('stat-high').textContent        = s.high_priority;
    $('stat-overdue').textContent     = s.overdue;
    $('stat-pct').textContent         = s.completion_pct + '%';
    $('progress-fill').style.width    = s.completion_pct + '%';
}

/* ─────────────────────────────────────────────────
   RENDER: TASK LIST
───────────────────────────────────────────────── */
function renderTasks() {
    const list = $('task-list');
    list.innerHTML = '';

    if (State.tasks.length === 0) {
        list.innerHTML = `
          <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No tasks found</h3>
            <p>Try adding a new task or adjusting your filters.</p>
          </div>`;
        return;
    }

    State.tasks.forEach((t, idx) => {
        const card = document.createElement('div');
        card.className = `task-card priority-${t.priority} ${t.status === 'completed' ? 'completed' : ''}`;
        card.style.animationDelay = `${idx * 0.04}s`;
        card.dataset.id = t.id;

        /* check icon */
        let checkClass = '';
        let checkIcon  = '';
        if (t.status === 'completed') {
            checkClass = 'done';
            checkIcon  = `<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`;
        } else if (t.status === 'in_progress') {
            checkClass = 'in-prog';
            checkIcon  = `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="#fff"/></svg>`;
        }

        /* priority tag */
        const pLabel = { low:'🟢 Low', medium:'🟡 Medium', high:'🔴 High' }[t.priority] || t.priority;

        /* status tag */
        const sLabel = {
            pending:     '⏳ Pending',
            in_progress: '🔄 In Progress',
            completed:   '✅ Done',
        }[t.status] || t.status;

        /* due date */
        let dueTag = '';
        if (t.due_date) {
            const over = isOverdue(t.due_date, t.status);
            dueTag = `<span class="tag tag-due ${over ? 'overdue' : ''}">
                📅 ${over ? '⚠ Overdue · ' : ''}${formatDate(t.due_date)}
            </span>`;
        }

        card.innerHTML = `
          <button class="task-check ${checkClass}" title="Cycle status"
                  onclick="toggleStatus(${t.id})" aria-label="Toggle status">
            ${checkIcon}
          </button>
          <div class="task-body">
            <div class="task-title">${escHtml(t.title)}</div>
            ${t.description ? `<div class="task-desc">${escHtml(t.description)}</div>` : ''}
            <div class="task-meta">
              <span class="tag tag-priority-${t.priority}">${pLabel}</span>
              <span class="tag tag-status-${t.status}">${sLabel}</span>
              <span class="tag tag-category">📁 ${escHtml(t.category)}</span>
              ${dueTag}
              <span style="font-size:0.68rem;color:var(--text-3);margin-left:auto">
                ${t.created_at ? formatDate(t.created_at) : ''}
              </span>
            </div>
          </div>
          <div class="task-actions">
            <button class="btn btn-icon success" title="Edit" onclick="openEditModal(${t.id})"
                    aria-label="Edit task">✏️</button>
            <button class="btn btn-icon danger" title="Delete" onclick="deleteTask(${t.id})"
                    aria-label="Delete task">🗑️</button>
          </div>`;

        list.appendChild(card);
    });
}

/* ─────────────────────────────────────────────────
   CATEGORY SELECTS
───────────────────────────────────────────────── */
function populateCategorySelects() {
    const selects = ['add-category', 'edit-category', 'filter-category'];
    selects.forEach(id => {
        const el = $(id);
        if (!el) return;
        const current = el.value;
        // keep first "All" option for filter
        const isFilter = id === 'filter-category';
        el.innerHTML = isFilter ? '<option value="">All Categories</option>' : '';
        State.categories.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = `${c.icon} ${c.name}`;
            el.appendChild(opt);
        });
        if (current) el.value = current;
    });
}

/* ─────────────────────────────────────────────────
   ADD TASK
───────────────────────────────────────────────── */
async function addTask(e) {
    e.preventDefault();
    const title = $('add-title').value.trim();
    if (!title) { toast('Please enter a task title', 'error'); return; }

    const btn = $('btn-add-task');
    btn.disabled = true;
    btn.textContent = 'Adding…';

    const data = await api('add_task', 'POST', {
        title,
        description: $('add-desc').value.trim(),
        priority:    $('add-priority').value,
        category:    $('add-category').value,
        due_date:    $('add-due').value || null,
    });

    btn.disabled = false;
    btn.textContent = '+ Add Task';

    if (data.success) {
        toast('✅ Task added!', 'success');
        $('add-task-form').reset();
        await refreshAll();
    } else {
        toast(data.error || 'Failed to add task', 'error');
    }
}

/* ─────────────────────────────────────────────────
   TOGGLE STATUS
───────────────────────────────────────────────── */
async function toggleStatus(id) {
    const data = await api('toggle_status', 'POST', { id });
    if (data.success) {
        const labels = { pending:'⏳ Pending', in_progress:'🔄 In Progress', completed:'✅ Done' };
        toast(`Status → ${labels[data.new_status] || data.new_status}`, 'info');
        await refreshAll();
    } else {
        toast(data.error || 'Failed to update', 'error');
    }
}

/* ─────────────────────────────────────────────────
   DELETE TASK
───────────────────────────────────────────────── */
async function deleteTask(id) {
    if (!confirm('Delete this task? This cannot be undone.')) return;
    const data = await api('delete_task', 'POST', { id });
    if (data.success) {
        toast('🗑️ Task deleted', 'info');
        /* animate removal */
        const card = document.querySelector(`.task-card[data-id="${id}"]`);
        if (card) {
            card.style.transition = '0.3s ease';
            card.style.transform  = 'translateX(-100%)';
            card.style.opacity    = '0';
            setTimeout(() => refreshAll(), 300);
        } else {
            await refreshAll();
        }
    } else {
        toast(data.error || 'Failed to delete', 'error');
    }
}

/* ─────────────────────────────────────────────────
   EDIT MODAL
───────────────────────────────────────────────── */
function openEditModal(id) {
    const task = State.tasks.find(t => t.id == id);
    if (!task) { toast('Task not found', 'error'); return; }

    State.editId = id;
    $('edit-title').value       = task.title;
    $('edit-desc').value        = task.description || '';
    $('edit-priority').value    = task.priority;
    $('edit-status').value      = task.status;
    $('edit-category').value    = task.category;
    $('edit-due').value         = task.due_date || '';
    $('edit-modal').classList.remove('hidden');
    $('edit-title').focus();
}

function closeEditModal() {
    $('edit-modal').classList.add('hidden');
    State.editId = null;
}

async function saveEdit(e) {
    e.preventDefault();
    if (!State.editId) return;

    const btn = $('btn-save-edit');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const data = await api('update_task', 'POST', {
        id:          State.editId,
        title:       $('edit-title').value.trim(),
        description: $('edit-desc').value.trim(),
        priority:    $('edit-priority').value,
        status:      $('edit-status').value,
        category:    $('edit-category').value,
        due_date:    $('edit-due').value || null,
    });

    btn.disabled = false;
    btn.textContent = 'Save Changes';

    if (data.success) {
        toast('✏️ Task updated!', 'success');
        closeEditModal();
        await refreshAll();
    } else {
        toast(data.error || 'Update failed', 'error');
    }
}

/* ─────────────────────────────────────────────────
   FILTER / SEARCH / SORT
───────────────────────────────────────────────── */
function setFilter(filter) {
    State.filter = filter;
    document.querySelectorAll('.filter-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    loadTasks();
}

let searchDebounce;
function onSearchInput(val) {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        State.search = val.trim();
        loadTasks();
    }, 280);
}

function onSortChange(val) {
    State.sort = val;
    loadTasks();
}

function onPriorityFilterChange(val) {
    State.priority = val;
    loadTasks();
}

function onCategoryFilterChange(val) {
    State.category = val;
    loadTasks();
}

/* ─────────────────────────────────────────────────
   KEYBOARD SHORTCUT
───────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeEditModal();
    /* Ctrl/Cmd + K → focus search */
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        $('search-input').focus();
    }
});

/* ─────────────────────────────────────────────────
   INIT
───────────────────────────────────────────────── */
async function init() {
    await loadCategories();
    await refreshAll();

    /* hide loading overlay */
    const overlay = $('loading-overlay');
    if (overlay) {
        overlay.classList.add('gone');
        setTimeout(() => overlay.remove(), 500);
    }
}

window.addEventListener('DOMContentLoaded', init);

/* expose globals for inline handlers */
window.toggleStatus            = toggleStatus;
window.deleteTask              = deleteTask;
window.openEditModal           = openEditModal;
window.closeEditModal          = closeEditModal;
window.setFilter               = setFilter;
window.onSearchInput           = onSearchInput;
window.onSortChange            = onSortChange;
window.onPriorityFilterChange  = onPriorityFilterChange;
window.onCategoryFilterChange  = onCategoryFilterChange;
