<?php
require_once __DIR__ . '/../../helpers/table_filters.php';
if (!isset($table) || !isset($table['filter_definitions'])) {
    return;
}
$filterAction = $filterAction ?? basename($_SERVER['PHP_SELF']);
?>
<div class="tf-wrap">
    <button type="button" class="tf-toggle" aria-expanded="false" onclick="toggleTableFilters(this)">
        <i data-lucide="sliders-horizontal" style="width:14px;height:14px"></i> Filters
        <span class="count-pill"><?= count(array_filter($table['filters'], static fn($v) => $v !== '')) ?></span>
    </button>
    <form method="get" action="<?= table_filter_escape($filterAction) ?>" class="tf-form" data-table-filter-form>
        <div class="tf-fields">
            <?php foreach ($table['filter_definitions'] as $key => $definition):
                $kind = $definition['kind'] ?? 'text';
                $value = $table['filters'][$key] ?? '';
                $label = $definition['label'] ?? ucfirst(str_replace('_', ' ', $key));
            ?>
            <label class="tf-field">
                <span><?= table_filter_escape($label) ?></span>
                <?php if ($kind === 'select'): ?>
                    <select name="<?= table_filter_escape($key) ?>" data-auto-apply>
                        <option value="">All</option>
                        <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                        <option value="<?= table_filter_escape($optionValue) ?>" <?= $value === (string)$optionValue ? 'selected' : '' ?>><?= table_filter_escape($optionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="<?= $kind === 'search' ? 'search' : ($kind === 'date' ? 'date' : ($kind === 'number' ? 'number' : 'text')) ?>"
                           name="<?= table_filter_escape($key) ?>"
                           value="<?= table_filter_escape($value) ?>"
                           placeholder="<?= table_filter_escape($definition['placeholder'] ?? '') ?>"
                           <?= $kind === 'search' ? 'data-debounce-search' : '' ?>
                           <?= $kind === 'number' ? 'min="0" step="0.01"' : '' ?>
                           <?= $kind === 'date' ? 'data-auto-apply' : '' ?>>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            <?php if (!empty($table['sort_map'])): ?>
            <label class="tf-field">
                <span>Sort by</span>
                <select name="<?= table_filter_escape($table['param_names']['sort'] ?? 'sort') ?>" data-auto-apply>
                    <?php foreach ($table['sort_map'] as $sortKey => $_sortExpression): ?>
                    <option value="<?= table_filter_escape($sortKey) ?>" <?= $table['sort'] === $sortKey ? 'selected' : '' ?>><?= table_filter_escape($table['sort_labels'][$sortKey] ?? ucfirst(str_replace('_', ' ', $sortKey))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="tf-field">
                <span>Direction</span>
                <select name="<?= table_filter_escape($table['param_names']['dir'] ?? 'dir') ?>" data-auto-apply>
                    <option value="ASC" <?= $table['dir'] === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    <option value="DESC" <?= $table['dir'] === 'DESC' ? 'selected' : '' ?>>Descending</option>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <input type="hidden" name="<?= table_filter_escape($table['param_names']['per_page'] ?? 'per_page') ?>" value="<?= (int)$table['per_page'] ?>">
        <div class="tf-actions">
            <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="filter" style="width:13px;height:13px"></i> Apply</button>
            <?php if ($table['has_filters']): ?>
            <a href="<?= table_filter_escape($filterAction) ?>" class="btn btn-outline btn-sm"><i data-lucide="x" style="width:13px;height:13px"></i> Clear all filters</a>
            <?php endif; ?>
        </div>
        <?php if ($table['has_filters']): ?>
        <div class="tf-chips" aria-label="Active filters">
            <?php foreach ($table['filters'] as $key => $value): if ($value === '') continue;
                $label = $table['filter_definitions'][$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
            ?>
            <a class="tf-chip" href="<?= table_filter_escape(table_filter_url([], [$key, 'page'])) ?>">
                <?= table_filter_escape($label) ?>: <?= table_filter_escape($value) ?>
                <i data-lucide="x" style="width:12px;height:12px"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </form>
</div>