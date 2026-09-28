<?php

function table_filter_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function table_filter_date(string $value): ?string
{
    if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $match)) {
        return null;
    }
    return checkdate((int)$match[2], (int)$match[3], (int)$match[1]) ? $value : null;
}

function table_filter_bind(mysqli_stmt $stmt, string $types, array &$values): void
{
    if ($types === '') {
        return;
    }
    $args = [$types];
    foreach ($values as $key => &$value) {
        $args[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $args);
}

function table_filter_url(array $replace = [], array $remove = []): string
{
    $query = $_GET;
    foreach ($remove as $key) {
        unset($query[$key]);
    }
    foreach ($replace as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return '?' . http_build_query($query);
}

function table_filter_table_url(array $table, array $replace = []): string
{
    $params = $table['param_names'] ?? ['page'=>'page','per_page'=>'per_page','sort'=>'sort','dir'=>'dir'];
    $mapped = [];
    foreach ($replace as $key => $value) {
        $mapped[$params[$key] ?? $key] = $value;
    }
    return table_filter_url($mapped);
}

function table_filter_query(mysqli $mysqli, array $config): array
{
    $definitions = $config['filters'] ?? [];
    $values = [];
    $conditions = [];
    $bindValues = [];
    $bindTypes = '';

    foreach ($definitions as $key => $definition) {
        $kind = $definition['kind'] ?? 'text';
        $raw = $_GET[$key] ?? '';
        if (!is_scalar($raw)) {
            $raw = '';
        }
        $raw = trim((string)$raw);
        $value = '';

        if ($raw !== '') {
            if ($kind === 'date') {
                $value = table_filter_date($raw) ?? '';
            } elseif ($kind === 'number') {
                $value = is_numeric($raw) && is_finite((float)$raw) && (float)$raw >= 0
                    ? (string)(float)$raw
                    : '';
            } elseif ($kind === 'select') {
                $allowed = array_keys($definition['options'] ?? []);
                $value = in_array($raw, $allowed, true) ? $raw : '';
            } else {
                $value = substr($raw, 0, 120);
            }
        }
        $values[$key] = $value;
        if ($value === '') {
            continue;
        }

        if ($kind === 'search') {
            $searchColumns = $definition['columns'] ?? [];
            if (!$searchColumns) {
                continue;
            }
            $conditions[] = '(' . implode(' OR ', array_map(
                static fn($column) => $column . ' LIKE ?',
                $searchColumns
            )) . ')';
            foreach ($searchColumns as $_) {
                $bindValues[] = '%' . $value . '%';
                $bindTypes .= 's';
            }
            continue;
        }

        if ($kind === 'select' && isset($definition['expressions'][$value])) {
            $conditions[] = '(' . $definition['expressions'][$value] . ')';
            continue;
        }

        $sql = $definition['sql'] ?? '';
        $operator = $definition['operator'] ?? '=';
        if ($sql === '' || !in_array($operator, ['=', '>=', '<=', '>', '<'], true)) {
            continue;
        }
        if ($kind === 'date' && !empty($definition['inclusive_end'])) {
            $conditions[] = $sql . ' < DATE_ADD(?, INTERVAL 1 DAY)';
        } else {
            $conditions[] = $sql . ' ' . $operator . ' ?';
        }
        $bindValues[] = $kind === 'number' ? (float)$value : $value;
        $bindTypes .= $kind === 'number' ? 'd' : 's';
    }

    $baseWhere = trim((string)($config['base_where'] ?? ''));
    $allConditions = [];
    if ($baseWhere !== '') {
        $allConditions[] = '(' . $baseWhere . ')';
    }
    if ($conditions) {
        $allConditions = array_merge($allConditions, $conditions);
    }
    $whereSql = $allConditions ? ' WHERE ' . implode(' AND ', $allConditions) : '';
    $baseValues = $config['base_params'] ?? [];
    $baseTypes = $config['base_types'] ?? '';
    $allValues = array_merge($baseValues, $bindValues);
    $allTypes = $baseTypes . $bindTypes;

    $sortMap = $config['sort'] ?? [];
    $paramNames = array_merge(['page'=>'page','per_page'=>'per_page','sort'=>'sort','dir'=>'dir'], $config['param_names'] ?? []);
    $requestedSort = $_GET[$paramNames['sort']] ?? ($config['default_sort'] ?? array_key_first($sortMap));
    if (!is_string($requestedSort) || !array_key_exists($requestedSort, $sortMap)) {
        $requestedSort = $config['default_sort'] ?? array_key_first($sortMap);
    }
    $direction = strtoupper((string)($_GET[$paramNames['dir']] ?? ($config['default_dir'] ?? 'DESC')));
    if (!in_array($direction, ['ASC', 'DESC'], true)) {
        $direction = 'DESC';
    }
    $orderSql = isset($sortMap[$requestedSort]) ? $sortMap[$requestedSort] . ' ' . $direction : '';

    $countSql = $config['count_sql'] ?? ('SELECT ' . ($config['count_expression'] ?? 'COUNT(*)') . ' ' . ($config['from_sql'] ?? '') . $whereSql);
    $countStmt = $mysqli->prepare($countSql);
    if (!$countStmt) {
        throw new RuntimeException('Could not prepare table count query.');
    }
    $countValues = $allValues;
    table_filter_bind($countStmt, $allTypes, $countValues);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_row();
    $countStmt->close();
    $total = (int)($countRow[0] ?? 0);

    $summary = [];
    if (!empty($config['summary_sql'])) {
        $summaryStmt = $mysqli->prepare('SELECT ' . $config['summary_sql'] . ' ' . ($config['from_sql'] ?? '') . $whereSql);
        if (!$summaryStmt) {
            throw new RuntimeException('Could not prepare table summary query.');
        }
        $summaryValues = $allValues;
        table_filter_bind($summaryStmt, $allTypes, $summaryValues);
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc() ?: [];
        $summaryStmt->close();
    }

    $allowedPerPage = [10, 25, 50, 100];
    $perPageRaw = $_GET[$paramNames['per_page']] ?? ($config['per_page'] ?? 25);
    $perPage = is_scalar($perPageRaw) && preg_match('/\A\d+\z/', (string)$perPageRaw) ? (int)$perPageRaw : 0;
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = (int)($config['per_page'] ?? 25);
    }
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = 25;
    }
    $pageRaw = $_GET[$paramNames['page']] ?? 1;
    $page = is_scalar($pageRaw) && preg_match('/\A\d+\z/', (string)$pageRaw) ? max(1, (int)$pageRaw) : 1;
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $selectSql = 'SELECT ' . ($config['select_sql'] ?? '*') . ' ' . ($config['from_sql'] ?? '') . $whereSql;
    $groupSql = trim((string)($config['group_by'] ?? ''));
    if ($groupSql !== '') {
        $selectSql .= ' ' . $groupSql;
    }
    $querySql = $selectSql;
    if ($orderSql !== '') {
        $querySql .= ' ORDER BY ' . $orderSql;
    }
    $queryParams = $allValues;
    $queryTypes = $allTypes;
    if (!empty($config['paginate']) || !array_key_exists('paginate', $config)) {
        $querySql .= ' LIMIT ? OFFSET ?';
        $queryParams[] = $perPage;
        $queryParams[] = $offset;
        $queryTypes .= 'ii';
    }

    $stmt = $mysqli->prepare($querySql);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare table query.');
    }
    table_filter_bind($stmt, $queryTypes, $queryParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'rows' => $rows,
        'filters' => $values,
        'filter_definitions' => $definitions,
        'total' => $total,
        'summary' => $summary,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => $offset,
        'first' => $total ? $offset + 1 : 0,
        'last' => min($offset + $perPage, $total),
        'sort' => $requestedSort,
        'dir' => $direction,
        'sort_map' => $sortMap,
        'sort_labels' => $config['sort_labels'] ?? [],
        'param_names' => $paramNames,
        'has_filters' => (bool)array_filter($values, static fn($value) => $value !== ''),
        'paginate' => !array_key_exists('paginate', $config) || (bool)$config['paginate'],
    ];
}

function table_sort_link(array $table, string $key, string $label): string
{
    if (!isset($table['sort_map'][$key])) {
        return table_filter_escape($label);
    }
    $direction = $table['sort'] === $key && $table['dir'] === 'ASC' ? 'DESC' : 'ASC';
    $arrow = $table['sort'] === $key ? ($table['dir'] === 'ASC' ? ' ↑' : ' ↓') : '';
    $url = table_filter_table_url($table, ['sort' => $key, 'dir' => $direction, 'page' => 1]);
    return '<a class="tf-sort" href="' . table_filter_escape($url) . '">' . table_filter_escape($label . $arrow) . '</a>';
}