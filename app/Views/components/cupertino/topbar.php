<?php
/**
 * Shared Cupertino header. Expects:
 *   $crumbTrail  string — e.g. "Student Info"
 *   $pageTitle   string — e.g. "Profile"
 *   $userName    string
 *   $userRole    string
 */
$initial = mb_strtoupper(mb_substr($userName, 0, 1));
?>
<header class="cp-header">
  <div>
    <div class="cp-crumbs"><?= esc($crumbTrail) ?> / <b><?= esc($pageTitle) ?></b></div>
    <div class="cp-title"><?= esc($pageTitle) ?></div>
  </div>

  <div class="cp-user-pill">
    <div class="cp-user-avatar-sm"><?= esc($initial) ?></div>
    <div>
      <div class="cp-user-name"><?= esc($userName) ?></div>
      <div class="cp-user-role"><?= esc($userRole) ?></div>
    </div>
  </div>
</header>
