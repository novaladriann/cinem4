<?php
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
$adm = currentAdmin();
$roleLabel = adminRoleLabel($adm['role'] ?? 'admin');
$roleBadgeClass = isSuperAdmin() ? 'adm-badge-blue' : 'adm-badge-gray';
?>
<div class="adm-topbar">
  <div class="d-flex align-items-center gap-3">
    <button class="adm-hamburger" onclick="openSidebar()">
      <i class="bi bi-list"></i>
    </button>
    <div>
      <div class="adm-topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
      <div class="d-md-none mt-1">
        <span class="adm-badge <?= $roleBadgeClass ?>"><?= htmlspecialchars($roleLabel) ?></span>
      </div>
    </div>
  </div>
  <div class="adm-topbar-right">
    <div class="d-none d-md-flex align-items-center gap-2">
      <span class="adm-topbar-name"><?= htmlspecialchars($adm['name'] ?? '') ?></span>
      <span class="adm-badge <?= $roleBadgeClass ?>"><?= htmlspecialchars($roleLabel) ?></span>
    </div>
    <a href="logout.php" class="adm-btn adm-btn-outline adm-btn-sm">
      <i class="bi bi-box-arrow-left"></i>
      <span class="d-none d-md-inline">Logout</span>
    </a>
  </div>
</div>
