<?php
/**
 * UX Customizer - Computer Dashboard tab template
 *
 * Server-side include (NOT browser-fetched), rendered by
 * ComputerDashboard::displayTabContentForItem(). Expects $data (see gatherData)
 * and $item (the Computer). All output is escaped; everything is wrapped in
 * .uxc-ci-detail so the dashboard CSS (public/css/dashboard.css) is scoped.
 *
 * @var array     $data
 * @var \Computer $item
 *
 * @license GPL-3.0-or-later
 */

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/** One security status card. ok===true → good, false → bad, null → unknown. */
$card = static function (array $c) use ($h): string {
    $state = $c['ok'] === true ? 'ok' : ($c['ok'] === false ? 'bad' : 'unknown');
    return '<div class="uxc-card uxc-status-card uxc-' . $state . '">'
        . '<div class="uxc-status-title"><span class="uxc-dot"></span>' . $h($c['label']) . '</div>'
        . '<div class="uxc-status-detail">' . $h($c['detail']) . '</div>'
        . '</div>';
};

$fmtDate = static fn($d) => $d ? $h(substr((string) $d, 0, 16)) : '—';
$num     = static fn($v) => $v === null ? '—' : (int) $v;
?>
<div class="uxc-ci-detail">

  <!-- Top bar -->
  <div class="uxc-topbar">
    <div class="uxc-topbar-main">
      <span class="uxc-ci-name"><?= $h($data['name']) ?></span>
      <span class="uxc-ci-sub"><?= $h($data['type_label']) ?> · <?= __('Updated', 'uxcustomizer') ?> <?= $fmtDate($data['updated']) ?></span>
    </div>
    <div class="uxc-topbar-badges">
      <span class="uxc-badge uxc-badge-status"><?= $h($data['status']) ?></span>
      <span class="uxc-badge"><i class="ti ti-map-pin"></i> <?= $h($data['location']) ?></span>
      <span class="uxc-badge"><i class="ti ti-user"></i> <?= $h($data['owner']) ?></span>
      <a class="uxc-btn" href="<?= $h($data['edit_url']) ?>"><i class="ti ti-edit"></i> <?= __('Edit') ?></a>
    </div>
  </div>

  <!-- Security status cards -->
  <div class="uxc-grid uxc-grid-4">
    <?= $card($data['connectivity']) ?>
    <?= $card($data['antivirus']) ?>
    <?= $card($data['firewall']) ?>
    <?= $card($data['health']) ?>
  </div>

  <!-- 2x2 detail grid -->
  <div class="uxc-grid uxc-grid-2">

    <div class="uxc-card">
      <div class="uxc-card-title"><i class="ti ti-apps me-1"></i><?= __('Software summary', 'uxcustomizer') ?></div>
      <div class="uxc-metrics">
        <div class="uxc-metric"><span class="uxc-metric-n"><?= (int) $data['software']['installed'] ?></span><span class="uxc-metric-l"><?= __('Installed', 'uxcustomizer') ?></span></div>
        <div class="uxc-metric"><span class="uxc-metric-n uxc-warn"><?= $num($data['software']['unlicensed']) ?></span><span class="uxc-metric-l"><?= __('Unlicensed', 'uxcustomizer') ?></span></div>
        <div class="uxc-metric"><span class="uxc-metric-n"><?= $num($data['software']['uptime']) ?></span><span class="uxc-metric-l"><?= __('Uptime', 'uxcustomizer') ?></span></div>
      </div>
      <dl class="uxc-kv">
        <dt><?= __('OS', 'uxcustomizer') ?></dt><dd><?= $h($data['software']['os']) ?></dd>
        <dt><?= __('Build', 'uxcustomizer') ?></dt><dd><?= $h($data['software']['build']) ?></dd>
        <dt><?= __('OS install date', 'uxcustomizer') ?></dt><dd><?= $fmtDate($data['software']['install_date']) ?></dd>
      </dl>
    </div>

    <div class="uxc-card">
      <div class="uxc-card-title"><i class="ti ti-info-circle me-1"></i><?= __('Details', 'uxcustomizer') ?></div>
      <?php if (!empty($data['custom_fields'])): ?>
        <dl class="uxc-kv">
          <?php foreach ($data['custom_fields'] as $cf): ?>
            <dt><?= $h($cf['label']) ?></dt><dd><?= $h($cf['value']) ?></dd>
          <?php endforeach; ?>
        </dl>
      <?php else: ?>
        <p class="uxc-muted"><?= __('No additional details.', 'uxcustomizer') ?></p>
      <?php endif; ?>
      <?php if (!empty($data['tags'])): ?>
        <div class="uxc-tags">
          <?php foreach ($data['tags'] as $tag): ?><span class="uxc-tag"><?= $h($tag) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="uxc-card">
      <div class="uxc-card-head">
        <div class="uxc-card-title"><i class="ti ti-ticket me-1"></i><?= __('Tickets', 'uxcustomizer') ?></div>
        <a class="uxc-btn uxc-btn-sm" href="<?= $h($data['new_ticket_url']) ?>"><i class="ti ti-plus"></i> <?= __('New ticket', 'uxcustomizer') ?></a>
      </div>
      <div class="uxc-metrics">
        <div class="uxc-metric"><span class="uxc-metric-n"><?= $num($data['tickets']['open']) ?></span><span class="uxc-metric-l"><?= __('Open') ?></span></div>
        <div class="uxc-metric"><span class="uxc-metric-n"><?= (int) $data['tickets']['linked'] ?></span><span class="uxc-metric-l"><?= __('Linked', 'uxcustomizer') ?></span></div>
        <div class="uxc-metric"><span class="uxc-metric-n"><?= $num($data['tickets']['pending']) ?></span><span class="uxc-metric-l"><?= __('Pending') ?></span></div>
      </div>
      <a class="uxc-link" href="<?= $h($data['tickets_url']) ?>"><?= __('View all tickets', 'uxcustomizer') ?> →</a>
    </div>

    <div class="uxc-card">
      <div class="uxc-card-title"><i class="ti ti-file-text me-1"></i><?= __('Contracts', 'uxcustomizer') ?></div>
      <dl class="uxc-kv">
        <dt><?= __('Assigned', 'uxcustomizer') ?></dt><dd><?= (int) $data['contracts']['assigned'] ?></dd>
        <dt><?= __('Type') ?></dt><dd><?= $h($data['contracts']['type']) ?></dd>
        <dt><?= __('Value') ?></dt><dd><?= $data['contracts']['value'] === null ? '—' : $h($data['contracts']['value']) ?></dd>
      </dl>
    </div>

  </div>
</div>
