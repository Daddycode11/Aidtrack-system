<?php if (!empty($table['paginate'])): ?>
<nav class="tf-pager" aria-label="Table pagination">
    <div class="tf-page-size">
        <label for="tfPerPage">Rows</label>
        <select id="tfPerPage" data-per-page-param="<?= table_filter_escape($table['param_names']['per_page'] ?? 'per_page') ?>" data-page-param="<?= table_filter_escape($table['param_names']['page'] ?? 'page') ?>" onchange="changeTablePageSize(this)">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
            <option value="<?= $size ?>" <?= $table['per_page'] === $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
        <span>Showing <?= (int)$table['first'] ?>-<?= (int)$table['last'] ?> of <?= (int)$table['total'] ?> results</span>
    </div>
    <?php if ($table['pages'] > 1): ?>
    <div class="tf-page-links">
        <?php if ($table['page'] > 1): ?><a href="<?= table_filter_escape(table_filter_table_url($table, ['page' => $table['page'] - 1])) ?>" aria-label="Previous page">‹</a><?php endif; ?>
        <?php for ($pageNumber = max(1, $table['page'] - 2); $pageNumber <= min($table['pages'], $table['page'] + 2); $pageNumber++): ?>
        <a href="<?= table_filter_escape(table_filter_table_url($table, ['page' => $pageNumber])) ?>" class="<?= $pageNumber === $table['page'] ? 'active' : '' ?>" aria-current="<?= $pageNumber === $table['page'] ? 'page' : 'false' ?>"><?= $pageNumber ?></a>
        <?php endfor; ?>
        <?php if ($table['page'] < $table['pages']): ?><a href="<?= table_filter_escape(table_filter_table_url($table, ['page' => $table['page'] + 1])) ?>" aria-label="Next page">›</a><?php endif; ?>
    </div>
    <?php endif; ?>
</nav>
<?php endif; ?>