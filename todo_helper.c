/*
 * ============================================================
 *  todo_helper.c  —  Task Statistics & Priority Sorter
 *  Project By Prabind
 *
 *  Compile:  gcc todo_helper.c -o todo_helper.exe
 *  Usage:
 *    todo_helper.exe stats   <json_file>
 *    todo_helper.exe sort    <json_file>  <asc|desc>
 *    todo_helper.exe filter  <json_file>  <priority>
 * ============================================================
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>

#define MAX_TASKS   1000
#define MAX_STR     512

/* ── Simple task record read from a flat CSV exported by PHP ── */
typedef struct {
    int  id;
    char title[MAX_STR];
    char priority[16];   /* low / medium / high */
    char status[16];     /* pending / in_progress / completed */
    char category[64];
} Task;

/* Priority to numeric weight */
static int priority_weight(const char *p) {
    if (strcmp(p, "high")   == 0) return 3;
    if (strcmp(p, "medium") == 0) return 2;
    if (strcmp(p, "low")    == 0) return 1;
    return 0;
}

/* ── Read CSV (id,title,priority,status,category) ── */
static int load_tasks(const char *path, Task *tasks) {
    FILE *fp = fopen(path, "r");
    if (!fp) {
        fprintf(stderr, "Error: cannot open file '%s'\n", path);
        return -1;
    }
    char line[MAX_STR * 2];
    int  count = 0;
    /* skip header */
    if (fgets(line, sizeof(line), fp) == NULL) { fclose(fp); return 0; }

    while (fgets(line, sizeof(line), fp) && count < MAX_TASKS) {
        Task *t = &tasks[count];
        /* parse id */
        char *tok = strtok(line, ",");
        if (!tok) continue;
        t->id = atoi(tok);
        /* title */
        tok = strtok(NULL, ",");
        if (!tok) continue;
        strncpy(t->title, tok, MAX_STR - 1);
        /* priority */
        tok = strtok(NULL, ",");
        if (!tok) continue;
        strncpy(t->priority, tok, 15);
        /* status */
        tok = strtok(NULL, ",");
        if (!tok) continue;
        strncpy(t->status, tok, 15);
        /* category — rest of line */
        tok = strtok(NULL, "\n");
        if (tok) strncpy(t->category, tok, 63);
        else     t->category[0] = '\0';

        count++;
    }
    fclose(fp);
    return count;
}

/* ── Comparison for qsort ── */
static int cmp_desc(const void *a, const void *b) {
    return priority_weight(((Task*)b)->priority) -
           priority_weight(((Task*)a)->priority);
}
static int cmp_asc(const void *a, const void *b) {
    return priority_weight(((Task*)a)->priority) -
           priority_weight(((Task*)b)->priority);
}

/* ── Print tasks as simple JSON array ── */
static void print_json(Task *tasks, int n) {
    printf("[\n");
    for (int i = 0; i < n; i++) {
        printf("  {\"id\":%d,\"title\":\"%s\",\"priority\":\"%s\","
               "\"status\":\"%s\",\"category\":\"%s\"}%s\n",
               tasks[i].id, tasks[i].title, tasks[i].priority,
               tasks[i].status, tasks[i].category,
               (i < n-1) ? "," : "");
    }
    printf("]\n");
}

/* ══════════════════════════════════════════════════════════════
   COMMAND: stats
   Outputs JSON with counts per priority / status
   ══════════════════════════════════════════════════════════════ */
static void cmd_stats(const char *path) {
    Task tasks[MAX_TASKS];
    int  n = load_tasks(path, tasks);
    if (n < 0) { printf("{\"error\":\"file not found\"}\n"); return; }

    int total = n;
    int pending = 0, in_progress = 0, completed = 0;
    int high = 0, medium = 0, low = 0;

    for (int i = 0; i < n; i++) {
        if (strcmp(tasks[i].status, "pending")     == 0) pending++;
        else if (strcmp(tasks[i].status, "in_progress") == 0) in_progress++;
        else if (strcmp(tasks[i].status, "completed")   == 0) completed++;

        if (strcmp(tasks[i].priority, "high")   == 0) high++;
        else if (strcmp(tasks[i].priority, "medium") == 0) medium++;
        else if (strcmp(tasks[i].priority, "low")    == 0) low++;
    }

    float pct = (total > 0) ? (completed * 100.0f / total) : 0.0f;

    printf("{\n");
    printf("  \"total\": %d,\n", total);
    printf("  \"pending\": %d,\n", pending);
    printf("  \"in_progress\": %d,\n", in_progress);
    printf("  \"completed\": %d,\n", completed);
    printf("  \"completion_pct\": %.1f,\n", pct);
    printf("  \"priority\": {\"high\": %d, \"medium\": %d, \"low\": %d}\n",
           high, medium, low);
    printf("}\n");
}

/* ══════════════════════════════════════════════════════════════
   COMMAND: sort
   ══════════════════════════════════════════════════════════════ */
static void cmd_sort(const char *path, const char *order) {
    Task tasks[MAX_TASKS];
    int n = load_tasks(path, tasks);
    if (n < 0) return;

    if (strcmp(order, "desc") == 0)
        qsort(tasks, n, sizeof(Task), cmp_desc);
    else
        qsort(tasks, n, sizeof(Task), cmp_asc);

    print_json(tasks, n);
}

/* ══════════════════════════════════════════════════════════════
   COMMAND: filter
   ══════════════════════════════════════════════════════════════ */
static void cmd_filter(const char *path, const char *priority) {
    Task tasks[MAX_TASKS];
    int  n = load_tasks(path, tasks);
    if (n < 0) return;

    Task filtered[MAX_TASKS];
    int  fc = 0;
    for (int i = 0; i < n; i++) {
        if (strcmp(tasks[i].priority, priority) == 0)
            filtered[fc++] = tasks[i];
    }
    print_json(filtered, fc);
}

/* ══════════════════════════════════════════════════════════════
   MAIN
   ══════════════════════════════════════════════════════════════ */
int main(int argc, char *argv[]) {
    if (argc < 3) {
        fprintf(stderr,
            "To-Do Helper — Project By Prabind\n"
            "Usage:\n"
            "  todo_helper stats  <tasks.csv>\n"
            "  todo_helper sort   <tasks.csv> <asc|desc>\n"
            "  todo_helper filter <tasks.csv> <low|medium|high>\n");
        return 1;
    }

    const char *cmd  = argv[1];
    const char *file = argv[2];

    if (strcmp(cmd, "stats") == 0) {
        cmd_stats(file);
    } else if (strcmp(cmd, "sort") == 0) {
        const char *order = (argc >= 4) ? argv[3] : "desc";
        cmd_sort(file, order);
    } else if (strcmp(cmd, "filter") == 0) {
        const char *prio = (argc >= 4) ? argv[3] : "high";
        cmd_filter(file, prio);
    } else {
        fprintf(stderr, "Unknown command: %s\n", cmd);
        return 1;
    }
    return 0;
}
