<?php if (!isset($title)) $title = "CINEM4 Admin"; ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- DataTables -->
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <style>
    :root {
      --c4-bg      : #070b14;
      --c4-nav     : #071a33;
      --c4-primary : #1f6fff;
      --c4-card    : rgba(255,255,255,.06);
      --sidebar-w  : 240px;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: var(--c4-bg);
      color: #fff;
      font-family: system-ui, sans-serif;
      min-height: 100vh;
    }

    /* ── SIDEBAR ── */
    .adm-sidebar {
      position: fixed;
      top: 0; left: 0;
      width: var(--sidebar-w);
      height: 100vh;
      background: linear-gradient(180deg, #071a33, #061120);
      border-right: 1px solid rgba(255,255,255,.08);
      display: flex;
      flex-direction: column;
      z-index: 100;
      transition: transform .3s ease;
    }

    /* Logo */
    .adm-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      text-decoration: none;
    }
    .adm-logo-badge {
      font-size: 10px; font-weight: 700;
      letter-spacing: .8px; text-transform: uppercase;
      color: rgba(255,255,255,.45);
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 999px;
      padding: 1px 8px;
    }

    /* Nav */
    .adm-nav { flex: 1; padding: 12px 10px; overflow-y: auto; }

    .adm-nav-label {
      font-size: 10px; font-weight: 700;
      letter-spacing: 1.2px; text-transform: uppercase;
      color: rgba(255,255,255,.30);
      padding: 10px 10px 6px;
    }

    .adm-nav-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 10px;
      color: rgba(255,255,255,.60);
      text-decoration: none;
      font-size: 14px; font-weight: 500;
      transition: all .2s ease;
      margin-bottom: 2px;
    }
    .adm-nav-link i { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
    .adm-nav-link:hover {
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.90);
    }
    .adm-nav-link.active {
      background: rgba(31,111,255,.18);
      color: #fff;
      font-weight: 600;
    }
    .adm-nav-link.active i { color: var(--c4-primary); }

    /* User info bawah sidebar */
    .adm-sidebar-footer {
      padding: 14px 16px;
      border-top: 1px solid rgba(255,255,255,.08);
    }
    .adm-user-info {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .adm-user-avatar {
      width: 34px; height: 34px;
      border-radius: 999px;
      background: rgba(31,111,255,.25);
      border: 1px solid rgba(31,111,255,.40);
      display: grid; place-items: center;
      font-size: 14px; font-weight: 700;
      color: var(--c4-primary);
      flex-shrink: 0;
    }
    .adm-user-name { font-size: 13px; font-weight: 600; color: #fff; }
    .adm-user-role { font-size: 11px; color: rgba(255,255,255,.40); }
    .adm-logout {
      display: flex; align-items: center; gap: 6px;
      margin-top: 10px;
      padding: 7px 12px;
      border-radius: 8px;
      border: 1px solid rgba(255,255,255,.10);
      background: transparent;
      color: rgba(255,255,255,.50);
      font-size: 13px; font-weight: 500;
      text-decoration: none;
      transition: all .2s ease;
      width: 100%;
      justify-content: center;
    }
    .adm-logout:hover {
      background: rgba(220,53,69,.12);
      border-color: rgba(220,53,69,.35);
      color: #ff8a95;
    }

    /* ── MAIN ── */
    .adm-main {
      margin-left: var(--sidebar-w);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Topbar */
    .adm-topbar {
      position: sticky; top: 0;
      background: rgba(7,11,20,.85);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,.08);
      padding: 14px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 50;
    }
    .adm-topbar-title {
      font-size: 16px; font-weight: 700;
      color: #fff;
    }
    .adm-topbar-right {
      display: flex; align-items: center; gap: 12px;
    }
    .adm-topbar-name {
      font-size: 13px; color: rgba(255,255,255,.55);
    }

    /* Hamburger (mobile) */
    .adm-hamburger {
      display: none;
      background: none; border: none;
      color: #fff; font-size: 20px;
      cursor: pointer; padding: 0;
    }

    /* Content area */
    .adm-content { padding: 24px; flex: 1; }

    /* ── CARDS ── */
    .adm-card {
      background: var(--c4-card);
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 16px;
      overflow: hidden;
    }
    .adm-card-header {
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .adm-card-title {
      font-size: 15px; font-weight: 700; color: #fff;
    }
    .adm-card-body { padding: 20px; }

    /* Stat cards */
    .adm-stat {
      background: var(--c4-card);
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 14px;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .adm-stat-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: grid; place-items: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .adm-stat-val { font-size: 26px; font-weight: 900; color: #fff; line-height: 1; }
    .adm-stat-label { font-size: 13px; color: rgba(255,255,255,.45); margin-top: 2px; }

    /* Table */
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th {
      font-size: 11px; font-weight: 700;
      letter-spacing: .8px; text-transform: uppercase;
      color: rgba(255,255,255,.40);
      padding: 10px 16px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      text-align: left;
    }
    .adm-table td {
      padding: 12px 16px;
      font-size: 14px;
      color: rgba(255,255,255,.80);
      border-bottom: 1px solid rgba(255,255,255,.05);
      vertical-align: middle;
    }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tr:hover td { background: rgba(255,255,255,.03); }

    /* Badge status */
    .adm-badge {
      font-size: 11px; font-weight: 700;
      padding: 3px 10px; border-radius: 999px;
      letter-spacing: .4px;
      white-space: nowrap;
      display: inline-block;
    }
    .adm-badge-green  { background: rgba(25,135,84,.20);  border: 1px solid rgba(25,135,84,.40);  color: #6ee7b7; }
    .adm-badge-blue   { background: rgba(31,111,255,.20); border: 1px solid rgba(31,111,255,.40); color: #93c5fd; }
    .adm-badge-yellow { background: rgba(255,193,7,.18);  border: 1px solid rgba(255,193,7,.38);  color: #fde68a; }
    .adm-badge-red    { background: rgba(220,53,69,.18);  border: 1px solid rgba(220,53,69,.38);  color: #fca5a5; }
    .adm-badge-gray   { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); color: rgba(255,255,255,.55); }

    /* Buttons */
    .adm-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 10px;
      font-size: 13px; font-weight: 600;
      text-decoration: none; cursor: pointer;
      border: none; transition: all .2s ease;
    }
    .adm-btn-primary {
      background: var(--c4-primary); color: #fff;
      box-shadow: 0 4px 14px rgba(31,111,255,.30);
    }
    .adm-btn-primary:hover { background: #1a5fd4; color: #fff; transform: translateY(-1px); }
    .adm-btn-outline {
      background: transparent;
      border: 1px solid rgba(255,255,255,.18);
      color: rgba(255,255,255,.70);
    }
    .adm-btn-outline:hover { border-color: rgba(255,255,255,.40); color: #fff; }
    .adm-btn-danger {
      background: rgba(220,53,69,.15);
      border: 1px solid rgba(220,53,69,.35);
      color: #fca5a5;
    }
    .adm-btn-danger:hover { background: rgba(220,53,69,.28); color: #fff; }
    .adm-btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 8px; }

    /* Form controls admin */
    .adm-form-label {
      font-size: 13px; font-weight: 600;
      color: rgba(255,255,255,.70);
      margin-bottom: 6px;
      display: block;
    }
    .adm-form-control {
      width: 100%;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 10px;
      color: #fff;
      padding: 9px 13px;
      font-size: 14px;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
    }
    .adm-form-control:focus {
      border-color: rgba(31,111,255,.55);
      box-shadow: 0 0 0 3px rgba(31,111,255,.12);
      background: rgba(255,255,255,.08);
    }
    .adm-form-control::placeholder { color: rgba(255,255,255,.25); }
    select.adm-form-control option { background: #0d1727; }
    textarea.adm-form-control { resize: vertical; min-height: 90px; }

    /* Alert */
    .adm-alert {
      padding: 11px 16px; border-radius: 10px;
      font-size: 13px; margin-bottom: 16px;
      display: flex; align-items: center; gap: 8px;
    }
    .adm-alert-success { background: rgba(25,135,84,.15); border: 1px solid rgba(25,135,84,.35); color: #6ee7b7; }
    .adm-alert-danger  { background: rgba(220,53,69,.15);  border: 1px solid rgba(220,53,69,.35);  color: #fca5a5; }

    /* Responsive */
    @media (max-width: 991px) {
      .adm-sidebar {
        transform: translateX(-100%);
      }
      .adm-sidebar.open {
        transform: translateX(0);
      }
      .adm-main { margin-left: 0; }
      .adm-hamburger { display: block; }
      .adm-content { padding: 16px; }
    }

    /* Overlay sidebar mobile */
    .adm-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 99;
    }
    .adm-overlay.open { display: block; }

    /* ── DataTables dark theme ── */

    /* Length select & filter input */
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
      background: rgba(255,255,255,.06) !important;
      border: 1px solid rgba(255,255,255,.14) !important;
      border-radius: 8px !important;
      color: #fff !important;
      padding: 6px 12px !important;
      outline: none !important;
      -webkit-appearance: none;
      appearance: none;
      min-width: 70px;
    }
    .dataTables_wrapper .dataTables_length select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.5)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") !important;
      background-repeat: no-repeat !important;
      background-position: right 10px center !important;
      padding-right: 32px !important;
    }
    .dataTables_wrapper .dataTables_length select option {
      background: #0d1727 !important;
      color: #fff !important;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
      border-color: rgba(31,111,255,.55) !important;
      box-shadow: 0 0 0 3px rgba(31,111,255,.12) !important;
    }

    /* Labels & info */
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_info {
      color: rgba(255,255,255,.50) !important;
      font-size: 13px !important;
    }

    /* Pagination */
    .dataTables_wrapper .dataTables_paginate {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      color: rgba(255,255,255,.55) !important;
      font-size: 13px !important;
      border: 1px solid transparent !important;
      border-radius: 8px !important;
      padding: 5px 11px !important;
      background: transparent !important;
      transition: all .2s ease;
      cursor: pointer;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: rgba(255,255,255,.08) !important;
      border-color: rgba(255,255,255,.15) !important;
      color: #fff !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
      background: var(--c4-primary) !important;
      border-color: var(--c4-primary) !important;
      color: #fff !important;
      box-shadow: 0 4px 12px rgba(31,111,255,.40) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
      color: rgba(255,255,255,.20) !important;
      cursor: default !important;
      background: transparent !important;
      border-color: transparent !important;
    }

    /* Table head */
    table.dataTable thead th {
      border-bottom: 1px solid rgba(255,255,255,.08) !important;
    }
    table.dataTable.no-footer {
      border-bottom: 1px solid rgba(255,255,255,.08) !important;
    }
    table.dataTable thead .sorting::before,
    table.dataTable thead .sorting::after,
    table.dataTable thead .sorting_asc::before,
    table.dataTable thead .sorting_asc::after,
    table.dataTable thead .sorting_desc::before,
    table.dataTable thead .sorting_desc::after {
      opacity: .3;
    }
    table.dataTable thead .sorting_asc::after,
    table.dataTable thead .sorting_desc::after {
      opacity: .9;
      color: var(--c4-primary);
    }
    table.dataTable tbody tr:hover > * {
      background: rgba(255,255,255,.03) !important;
      box-shadow: none !important;
    }

    /* Buttons export */
    .dt-buttons { display: flex; gap: 6px; }
    .dt-buttons .adm-btn { font-size: 12px !important; }

    /* Wrapper spacing */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
      margin-bottom: 0;
    }

    /* Sembunyikan length saja — pakai search DataTables */
    .dataTables_wrapper .dataTables_length { display: none !important; }

    /* Rapikan search DataTables */
    .dataTables_wrapper .dataTables_filter {
      display: flex !important;
      align-items: center;
    }
    .dataTables_wrapper .dataTables_filter label {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0;
    }
    .dataTables_wrapper .dataTables_filter input {
      width: 220px !important;
    }

    /* Pagination prev/next putih — force dark */
    /* Pagination prev/next — tanpa kotak pembungkus */
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous,
    .dataTables_wrapper .dataTables_paginate .paginate_button.next {
      background: transparent !important;
      border: none !important;
      color: rgba(255,255,255,.60) !important;
      box-shadow: none !important;
      font-size: 16px !important;
      padding: 4px 10px !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous:hover,
    .dataTables_wrapper .dataTables_paginate .paginate_button.next:hover {
      background: transparent !important;
      border: none !important;
      color: #fff !important;
      box-shadow: none !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.next.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous.disabled:hover,
    .dataTables_wrapper .dataTables_paginate .paginate_button.next.disabled:hover {
      background: transparent !important;
      border: none !important;
      color: rgba(255,255,255,.20) !important;
      box-shadow: none !important;
    }
    /* Force override Bootstrap white background pada paginate */
    .pagination .page-item .page-link,
    .dataTables_paginate .paginate_button {
      background-color: transparent !important;
    }
    /* Override border putih prev/next dari DataTables Bootstrap theme */
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item .page-link {
      background: rgba(255,255,255,.06) !important;
      border-color: rgba(255,255,255,.14) !important;
      color: rgba(255,255,255,.70) !important;
    }
    /* prev/next page-item tanpa kotak */
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item:first-child .page-link,
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item:last-child .page-link {
      background: transparent !important;
      border-color: transparent !important;
      color: rgba(255,255,255,.60) !important;
    }
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item:first-child .page-link:hover,
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item:last-child .page-link:hover {
      background: transparent !important;
      border-color: transparent !important;
      color: #fff !important;
    }
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item.active .page-link {
      background: var(--c4-primary) !important;
      border-color: var(--c4-primary) !important;
      color: #fff !important;
    }
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item.disabled .page-link {
      background: rgba(255,255,255,.03) !important;
      border-color: rgba(255,255,255,.08) !important;
      color: rgba(255,255,255,.20) !important;
    }
    div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item .page-link:hover {
      background: rgba(255,255,255,.12) !important;
      border-color: rgba(255,255,255,.25) !important;
      color: #fff !important;
    }
  </style>
</head>
<body>