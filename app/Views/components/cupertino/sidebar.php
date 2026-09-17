<?php
/**
 * Shared Cupertino sidebar. Expects:
 *   $navGroups   array<string label, array<array{url,icon,label,key}>>
 *   $activePage  string — matches a nav item's 'key'
 *   $brandSub    string — small subtitle under "Akademus SIS" (e.g. "Student Portal")
 */
?>
<aside class="cp-sidebar">
  <div class="cp-sidebar-top">
    <div class="cp-brand">
      <div class="cp-brand-icon"><span class="material-symbols-outlined" style="font-size:18px;">school</span></div>
      <div class="cp-brand-text">
        <div class="cp-brand-name">Akademus SIS</div>
        <div class="cp-brand-sub"><?= esc($brandSub) ?></div>
      </div>
    </div>

    <?php foreach ($navGroups as $groupLabel => $items): ?>
      <div class="cp-nav-label"><?= esc($groupLabel) ?></div>
      <nav class="cp-nav" style="margin-bottom:16px;">
        <?php foreach ($items as $item): ?>
          <a class="cp-nav-item <?= $activePage === $item['key'] ? 'is-active' : '' ?>" href="<?= site_url($item['url']) ?>">
            <span class="material-symbols-outlined"><?= esc($item['icon']) ?></span><span class="label"><?= esc($item['label']) ?></span>
          </a>
        <?php endforeach ?>
      </nav>
    <?php endforeach ?>
  </div>

  <div class="cp-sidebar-bottom">
    <a class="cp-logout" href="<?= site_url('logout') ?>">
      <span class="material-symbols-outlined">logout</span><span class="label">Log Out</span>
    </a>
  </div>
</aside>
