<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'overview';
$pageTitle     = 'Dashboard';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/portal-shell.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/portal-shell.css') ?>">
<style>
    /* ==========================================================================
       Chairperson Dashboard — Refined Apple-Grade System Theme
       - Built on Akademus SIS Design Tokens (--color-primary, etc.)
       - Perfectly equal-height two-column layout with zero dead space
       - Interactive Program Modals & Section Breakdown
       ========================================================================== */

    /* ===== Welcome Hero ===== */
    .dash-hero {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: var(--radius-xl);
        color: #ffffff;
        padding: 24px 28px;
        margin-bottom: 18px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 20px -4px rgba(18, 63, 136, 0.28), var(--shadow-sm);
    }
    .dash-hero::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: radial-gradient(circle at 85% 15%, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 65%);
        pointer-events: none;
    }
    .dash-hero::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0) 70%);
        pointer-events: none;
    }
    .dash-hero__inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .dash-hero__main {
        max-width: 660px;
    }
    .dash-hero__badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .dash-hero__badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 4px 11px;
        border-radius: var(--radius-pill);
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.35);
        letter-spacing: -0.01em;
    }
    .dash-hero__badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #34d399;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.35);
        animation: heroDotPulse 2.4s infinite ease-in-out;
    }
    @keyframes heroDotPulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.2); opacity: 0.75; }
    }
    .dash-hero__term {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.82);
        background: rgba(255, 255, 255, 0.1);
        padding: 3px 10px;
        border-radius: var(--radius-pill);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .dash-hero__title {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin: 0 0 6px;
        color: #ffffff;
        line-height: 1.25;
    }
    .dash-hero__sub {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.84);
        line-height: 1.5;
        margin: 0;
    }
    .dash-hero__sub strong {
        color: #ffffff;
        font-weight: 600;
    }
    .dash-hero__actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: none;
    }
    .btn-hero-primary {
        background: #ffffff;
        color: var(--color-primary-hover) !important;
        border: 1px solid #ffffff;
        border-radius: var(--radius-pill);
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 650;
        letter-spacing: -0.01em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
        white-space: nowrap;
    }
    .btn-hero-primary:hover {
        background: var(--color-primary-soft);
        border-color: var(--color-primary-soft);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16);
    }
    .btn-hero-primary:active {
        transform: scale(0.98);
    }
    .btn-hero-secondary {
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: var(--radius-pill);
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: -0.01em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
        white-space: nowrap;
    }
    .btn-hero-secondary:hover {
        background: rgba(255, 255, 255, 0.22);
        border-color: rgba(255, 255, 255, 0.38);
        transform: translateY(-1px);
    }
    .btn-hero-secondary:active {
        transform: scale(0.98);
    }

    /* ===== KPI Cards ===== */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }
    .kpi-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 16px 18px;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .kpi-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--color-border-medium);
        transform: translateY(-1px);
    }
    .kpi-card__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 34px;
    }
    .kpi-icon-squircle {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--color-primary-soft);
        color: var(--color-primary);
    }
    .kpi-icon-squircle .material-symbols-outlined {
        font-size: 19px;
    }
    .kpi-icon-squircle--warn {
        background: var(--color-warning-soft);
        color: var(--color-warning);
    }
    .kpi-icon-squircle--ok {
        background: var(--color-success-soft);
        color: var(--color-success);
    }

    .kpi-flag {
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: var(--radius-pill);
        white-space: nowrap;
        letter-spacing: -0.01em;
    }
    .kpi-flag--ok {
        background: var(--color-success-soft);
        color: var(--color-success);
        border: 1px solid rgba(21, 128, 61, 0.2);
    }
    .kpi-flag--warn {
        background: var(--color-warning-soft);
        color: var(--color-warning);
        border: 1px solid rgba(166, 91, 8, 0.25);
    }
    .kpi-flag--info {
        background: var(--color-primary-soft);
        color: var(--color-primary);
        border: 1px solid rgba(23, 78, 166, 0.2);
    }
    .kpi-flag--neutral {
        background: var(--color-surface-sunken);
        color: var(--color-text-secondary);
        border: 1px solid var(--color-border);
    }

    .kpi-value-row {
        display: flex;
        align-items: baseline;
        gap: 4px;
        margin-top: 12px;
    }
    .kpi-value {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--color-text-primary);
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }
    .kpi-value-suffix {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-tertiary);
    }
    .kpi-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--color-text-secondary);
        margin-top: 4px;
        letter-spacing: -0.01em;
    }
    .kpi-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid var(--color-border);
        font-size: 11.5px;
    }
    .kpi-footer span {
        color: var(--color-text-tertiary);
        font-weight: 400;
    }
    .kpi-footer a {
        color: var(--color-primary);
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 2px;
        transition: color 0.15s ease, transform 0.15s ease;
    }
    .kpi-footer a:hover {
        color: var(--color-primary-hover);
        text-decoration: underline;
        transform: translateX(1px);
    }
    .kpi-footer a .material-symbols-outlined {
        font-size: 14px;
    }

    /* ===== Split Layout (Balanced 1:1 Height) ===== */
    .dash-split {
        display: grid;
        grid-template-columns: 1.25fr 1fr;
        gap: 18px;
        align-items: stretch;
    }
    @media (max-width: 960px) {
        .dash-split { grid-template-columns: 1fr; }
    }

    .dash-panel {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        padding: 20px 22px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        box-sizing: border-box;
    }
    .dash-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .dash-panel__head h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--color-text-primary);
    }
    .dash-panel__head p {
        margin: 3px 0 0;
        font-size: 12px;
        color: var(--color-text-secondary);
        font-weight: 400;
    }
    .dept-meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 600;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        margin-top: 4px;
        border: 1px solid rgba(23, 78, 166, 0.15);
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dept-meta-pill .material-symbols-outlined {
        font-size: 15px;
        flex-shrink: 0;
    }

    /* Program Cards with Embedded Sections */
    .program-card-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        flex: 1;
        justify-content: space-between;
    }
    .program-card {
        background: var(--color-surface-secondary);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .program-card:hover {
        background: var(--color-surface);
        border-color: var(--color-border-medium);
        box-shadow: var(--shadow-xs);
    }
    .program-card__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .program-card__title-group {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .program-card__icon {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-md);
        background: var(--color-primary-soft);
        color: var(--color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .program-card__icon .material-symbols-outlined {
        font-size: 19px;
    }
    .program-card__name {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--color-text-primary);
        line-height: 1.3;
        letter-spacing: -0.01em;
    }
    .program-card__badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .btn-inspect-modal {
        background: var(--color-surface);
        color: var(--color-primary);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-pill);
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s ease;
    }
    .btn-inspect-modal:hover {
        background: var(--color-primary-soft);
        border-color: rgba(23, 78, 166, 0.25);
    }
    .btn-inspect-modal .material-symbols-outlined {
        font-size: 14px;
    }

    /* Embedded Section Plotting Rows */
    .program-gaps-container {
        display: flex;
        flex-direction: column;
        gap: 7px;
        padding-top: 2px;
    }
    .prog-gap-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-size: 12px;
    }
    .prog-gap-item__left {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        min-width: 0;
    }
    .prog-cohort-tag {
        font-weight: 650;
        color: var(--color-text-primary);
    }
    .section-pill-sm {
        font-size: 10.5px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 4px;
        background: var(--color-primary-soft);
        color: var(--color-primary);
        border: 1px solid rgba(23, 78, 166, 0.16);
    }
    .section-pill-sm--empty {
        background: var(--color-warning-soft);
        color: var(--color-warning);
        border-color: rgba(166, 91, 8, 0.2);
    }
    .gap-code-chip-sm {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 10.5px;
        font-weight: 600;
        background: var(--color-surface-sunken);
        color: var(--color-text-secondary);
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid var(--color-border);
    }
    .prog-gap-item__right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .btn-plot-mini {
        font-size: 11px;
        font-weight: 600;
        color: var(--color-primary);
        background: var(--color-primary-soft);
        border: 1px solid rgba(23, 78, 166, 0.18);
        border-radius: var(--radius-pill);
        padding: 3px 9px;
        display: inline-flex;
        align-items: center;
        gap: 2px;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .btn-plot-mini:hover {
        background: var(--color-primary);
        color: #ffffff;
        border-color: var(--color-primary);
    }
    .btn-plot-mini .material-symbols-outlined {
        font-size: 13px;
    }
    .prog-all-clear {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--color-success);
        font-weight: 550;
        padding: 6px 10px;
        background: var(--color-surface);
        border: 1px solid rgba(21, 128, 61, 0.15);
        border-radius: var(--radius-sm);
    }
    .prog-all-clear .material-symbols-outlined {
        font-size: 17px;
    }

    /* Program Card Action Footer */
    .program-card__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--color-border);
        font-size: 11.5px;
    }
    .program-quick-links {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .program-action-link {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--color-primary);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        transition: color 0.15s ease;
    }
    .program-action-link:hover {
        color: var(--color-primary-hover);
        text-decoration: underline;
    }
    .program-action-link .material-symbols-outlined {
        font-size: 14px;
    }

    /* Tool Grid (Quick Navigation) */
    .tool-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        flex: 1;
    }
    @media (max-width: 480px) {
        .tool-grid { grid-template-columns: 1fr; }
    }
    .tool-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 10px;
        padding: 13px 15px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface-secondary);
        text-decoration: none;
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }
    .tool-card:hover {
        background: var(--color-surface);
        border-color: var(--color-primary);
        box-shadow: var(--shadow-sm);
        transform: translateY(-1px);
    }
    .tool-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .tool-icon-squircle {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--color-primary-soft);
        color: var(--color-primary);
        transition: background 0.18s, color 0.18s;
    }
    .tool-icon-squircle .material-symbols-outlined {
        font-size: 19px;
    }
    .tool-card:hover .tool-icon-squircle {
        background: var(--color-primary);
        color: #ffffff;
    }
    .tool-card__chevron {
        color: var(--color-border-medium);
        font-size: 17px;
        transition: transform 0.18s, color 0.18s;
    }
    .tool-card:hover .tool-card__chevron {
        color: var(--color-primary);
        transform: translateX(2px);
    }
    .tool-card__info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .tool-card__title {
        font-size: 12.5px;
        font-weight: 650;
        color: var(--color-text-primary);
        letter-spacing: -0.01em;
    }
    .tool-card__sub {
        font-size: 10.5px;
        color: var(--color-text-tertiary);
        font-weight: 400;
    }

    /* ===== Apple-Style Modal Dialog ===== */
    .apple-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .apple-modal-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }
    .apple-modal {
        background: #ffffff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-xl);
        box-shadow: 0 20px 50px -10px rgba(15, 23, 42, 0.25), 0 4px 16px rgba(0, 0, 0, 0.06);
        width: 100%;
        max-width: 680px;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: scale(0.96) translateY(8px);
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .apple-modal-backdrop.is-open .apple-modal {
        transform: scale(1) translateY(0);
    }
    .apple-modal__header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--color-surface);
    }
    .apple-modal__title-box {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .apple-modal__title-box h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: var(--color-text-primary);
        letter-spacing: -0.015em;
    }
    .apple-modal__title-box p {
        margin: 2px 0 0;
        font-size: 12px;
        color: var(--color-text-secondary);
    }
    .apple-modal__close {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1px solid var(--color-border);
        background: var(--color-surface-secondary);
        color: var(--color-text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .apple-modal__close:hover {
        background: var(--color-surface-sunken);
        color: var(--color-text-primary);
    }
    .apple-modal__close .material-symbols-outlined {
        font-size: 18px;
    }
    .apple-modal__body {
        padding: 20px 24px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .modal-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--color-text-tertiary);
        margin-bottom: 8px;
    }
    .modal-sections-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        overflow: hidden;
        font-size: 12px;
    }
    .modal-sections-table th {
        background: var(--color-surface-secondary);
        padding: 9px 12px;
        text-align: left;
        font-weight: 650;
        color: var(--color-text-secondary);
        border-bottom: 1px solid var(--color-border);
    }
    .modal-sections-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text-primary);
    }
    .modal-sections-table tr:last-child td {
        border-bottom: 0;
    }
    .apple-modal__footer {
        padding: 16px 24px;
        border-top: 1px solid var(--color-border);
        background: var(--color-surface-secondary);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ac-page-header">
    <div>
        <h2 class="ac-page-header__title">Dashboard</h2>
        <p class="ac-page-header__sub">Department overview — scheduling progress, faculty, and pending approvals.</p>
    </div>
</div>

<?php if ($deptName === null): ?>
    <div class="dash-panel">
        <div class="dash-empty-state">
            <span class="material-symbols-outlined">domain_disabled</span>
            <p>You're not assigned to a department yet. Contact an administrator.</p>
        </div>
    </div>
<?php elseif ($activeYear === null): ?>
    <div class="dash-panel">
        <div class="dash-empty-state">
            <span class="material-symbols-outlined">event_busy</span>
            <p>No active academic year is set. An administrator needs to activate one under Admin &gt; Academic Years.</p>
        </div>
    </div>
<?php else: ?>

    <?php
        $curriculumPct = $stats['curriculum_slots'] > 0
            ? round($stats['curriculum_set'] / $stats['curriculum_slots'] * 100)
            : 0;

        // Group gaps by program
        $gapsByProgram = [];
        foreach ($plottingGaps as $gap) {
            $key = strtolower(trim($gap['program']));
            if (!isset($gapsByProgram[$key])) {
                $gapsByProgram[$key] = [
                    'items'         => [],
                    'section_count' => 0,
                    'missing_count' => 0,
                ];
            }
            $gapsByProgram[$key]['items'][] = $gap;
            $gapsByProgram[$key]['section_count']++;
            $gapsByProgram[$key]['missing_count'] += (int) $gap['missing_count'];
        }

        // Group all registered sections by program
        $sectionsByProgram = [];
        foreach ($deptSections ?? [] as $sec) {
            $key = strtolower(trim($sec['program']));
            if (!isset($sectionsByProgram[$key])) {
                $sectionsByProgram[$key] = [];
            }
            $sectionsByProgram[$key][] = $sec;
        }
    ?>

    <section class="dash-hero">
        <div class="dash-hero__inner">
            <div class="dash-hero__main">
                <div class="dash-hero__badges">
                    <span class="dash-hero__badge"><span class="dot"></span> Term Active</span>
                    <span class="dash-hero__term"><?= esc($activeYear) ?></span>
                </div>
                <h1 class="dash-hero__title">Welcome back, <?= esc($user['name']) ?></h1>
                <p class="dash-hero__sub">Department overview for <strong><?= esc($deptName) ?></strong> — live tracking of class plotting and faculty teaching loads.</p>
            </div>

            <div class="dash-hero__actions">
                <a class="btn-hero-primary" href="<?= site_url('chairperson/dashboard') ?>">
                    <span class="material-symbols-outlined" style="font-size:18px;">calendar_month</span>
                    Go to Schedule Plotting
                </a>
                <a class="btn-hero-secondary" href="<?= site_url('chairperson/sections') ?>">
                    <span class="material-symbols-outlined" style="font-size:18px;">groups</span>
                    Manage Sections &amp; Roster
                </a>
            </div>
        </div>
    </section>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-card__top">
                <div class="kpi-icon-squircle">
                    <span class="material-symbols-outlined">calendar_today</span>
                </div>
                <span class="kpi-flag kpi-flag--neutral">This Term</span>
            </div>
            <div class="kpi-value-row">
                <span class="kpi-value"><?= esc($stats['plotted_classes']) ?></span>
            </div>
            <div class="kpi-label">Classes plotted this term</div>
            <div class="kpi-footer">
                <span>Weekly plotting grid</span>
                <a href="<?= site_url('chairperson/dashboard') ?>">
                    <span>Plot now</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <div class="kpi-icon-squircle">
                    <span class="material-symbols-outlined">groups</span>
                </div>
                <span class="kpi-flag kpi-flag--neutral">Department</span>
            </div>
            <div class="kpi-value-row">
                <span class="kpi-value"><?= esc($stats['faculty_count']) ?></span>
            </div>
            <div class="kpi-label">Faculty in department</div>
            <div class="kpi-footer">
                <span>Active teaching staff</span>
                <a href="<?= site_url('chairperson/sections') ?>">
                    <span>Roster</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <div class="kpi-icon-squircle <?= $stats['pending_approvals'] > 0 ? 'kpi-icon-squircle--warn' : 'kpi-icon-squircle--ok' ?>">
                    <span class="material-symbols-outlined">swap_horiz</span>
                </div>
                <?php if ($stats['pending_approvals'] > 0): ?>
                    <span class="kpi-flag kpi-flag--warn">Action Required</span>
                <?php else: ?>
                    <span class="kpi-flag kpi-flag--ok">All Clear</span>
                <?php endif ?>
            </div>
            <div class="kpi-value-row">
                <span class="kpi-value"><?= esc($stats['pending_approvals']) ?></span>
            </div>
            <div class="kpi-label">Swap approvals pending</div>
            <div class="kpi-footer">
                <span>Reviewed on Plotter</span>
                <a href="<?= site_url('chairperson/dashboard') ?>">
                    <span>Review</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <div class="kpi-icon-squircle">
                    <span class="material-symbols-outlined">menu_book</span>
                </div>
                <span class="kpi-flag <?= $curriculumPct >= 100 ? 'kpi-flag--ok' : 'kpi-flag--info' ?>"><?= $curriculumPct ?>% Synced</span>
            </div>
            <div class="kpi-value-row">
                <span class="kpi-value"><?= esc($stats['curriculum_set']) ?></span>
                <span class="kpi-value-suffix">/ <?= esc($stats['curriculum_slots']) ?></span>
            </div>
            <div class="kpi-label">Curriculum terms published</div>
            <div class="kpi-footer">
                <span>Program &times; year level</span>
                <a href="<?= site_url('chairperson/curriculum') ?>">
                    <span>Curriculum</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__top">
                <div class="kpi-icon-squircle <?= $stats['needs_plotting_count'] > 0 ? 'kpi-icon-squircle--warn' : 'kpi-icon-squircle--ok' ?>">
                    <span class="material-symbols-outlined">hub</span>
                </div>
                <?php if ($stats['needs_plotting_count'] > 0): ?>
                    <span class="kpi-flag kpi-flag--warn">Action Required</span>
                <?php else: ?>
                    <span class="kpi-flag kpi-flag--ok">All Clear</span>
                <?php endif ?>
            </div>
            <div class="kpi-value-row">
                <span class="kpi-value"><?= esc($stats['needs_plotting_count']) ?></span>
            </div>
            <div class="kpi-label">Classes needing your subjects</div>
            <div class="kpi-footer">
                <span>Any department, live demand</span>
                <a href="<?= site_url('chairperson/offerings') ?>">
                    <span>Plot now</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>
        </div>
    </div>

    <div class="dash-split">
        <div>
            <div class="dash-panel">
                <div>
                    <div class="dash-panel__head">
                        <div>
                            <h3>Academic Programs &amp; Sections to Plot</h3>
                            <div class="dept-meta-pill" title="<?= esc($deptName) ?> · <?= esc($activeYear) ?>">
                                <span class="material-symbols-outlined">domain</span>
                                <span><?= esc($deptName) ?> &middot; <?= esc($activeYear) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($programs === []): ?>
                        <div class="dash-empty-state">
                            <span class="material-symbols-outlined">folder_off</span>
                            <p>No programs registered under this department yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="program-card-list">
                            <?php foreach ($programs as $progIdx => $program): ?>
                                <?php
                                    $progKey          = strtolower(trim($program));
                                    $progGapInfo      = $gapsByProgram[$progKey] ?? null;
                                    $progGaps         = $progGapInfo['items'] ?? [];
                                    $progSectionGaps  = $progGapInfo['section_count'] ?? 0;
                                    $progMissingTotal = $progGapInfo['missing_count'] ?? 0;
                                    $modalId          = 'progModal_' . $progIdx;
                                ?>
                                <div class="program-card">
                                    <div class="program-card__header">
                                        <div class="program-card__title-group">
                                            <div class="program-card__icon">
                                                <span class="material-symbols-outlined">school</span>
                                            </div>
                                            <div>
                                                <div class="program-card__name"><?= esc($program) ?></div>
                                            </div>
                                        </div>
                                        <div class="program-card__badges">
                                            <?php if ($progSectionGaps > 0): ?>
                                                <span class="kpi-flag kpi-flag--warn"><?= $progSectionGaps ?> to plot</span>
                                            <?php else: ?>
                                                <span class="kpi-flag kpi-flag--ok">All Clear</span>
                                            <?php endif ?>
                                            <button type="button" class="btn-inspect-modal" data-modal-target="<?= esc($modalId) ?>">
                                                <span class="material-symbols-outlined">visibility</span>
                                                <span>All classes</span>
                                            </button>
                                        </div>
                                    </div>

                                    <?php if ($progGaps !== []): ?>
                                        <div class="program-gaps-container">
                                            <?php foreach ($progGaps as $gap): ?>
                                                <div class="prog-gap-item">
                                                    <div class="prog-gap-item__left">
                                                        <span class="prog-cohort-tag"><?= esc($gap['year_level']) ?></span>
                                                        <span class="sep" style="color:var(--color-border-medium);">&middot;</span>
                                                        <?php if ($gap['section'] === null): ?>
                                                            <span class="section-pill-sm section-pill-sm--empty">No section</span>
                                                        <?php else: ?>
                                                            <span class="section-pill-sm">Section <?= esc($gap['section']) ?></span>
                                                        <?php endif; ?>
                                                        <span class="sep" style="color:var(--color-border-medium);">&middot;</span>
                                                        <?php foreach ($gap['missing_codes'] as $code): ?>
                                                            <span class="gap-code-chip-sm"><?= esc($code) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="prog-gap-item__right">
                                                        <?php if ($gap['section'] === null): ?>
                                                            <form class="gap-ensure-form" method="post"
                                                                  action="<?= esc($gap['ensureUrl'], 'attr') ?>"
                                                                  data-no-dirty-tracking
                                                                  data-plot-url-base="<?= esc($gap['plotUrlBase'], 'attr') ?>">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="academic_year" value="<?= esc($gap['academicYear'], 'attr') ?>">
                                                                <input type="hidden" name="ay_label" value="<?= esc($gap['ayLabel'], 'attr') ?>">
                                                                <input type="hidden" name="semester" value="<?= esc($gap['semester'], 'attr') ?>">
                                                                <input type="hidden" name="program" value="<?= esc($gap['program'], 'attr') ?>">
                                                                <input type="hidden" name="year_level" value="<?= esc($gap['year_level'], 'attr') ?>">
                                                                <button type="submit" class="gap-ensure btn-plot-mini">
                                                                    <span>Create</span>
                                                                    <span class="material-symbols-outlined">arrow_forward</span>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <a class="btn-plot-mini" href="<?= esc($gap['url']) ?>">
                                                                <span>Plot</span>
                                                                <span class="material-symbols-outlined">arrow_forward</span>
                                                            </a>
                                                        <?php endif ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="prog-all-clear">
                                            <span class="material-symbols-outlined">check_circle</span>
                                            <span>All sections in this program are fully plotted for this term.</span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="program-card__footer">
                                        <div class="program-quick-links">
                                            <a class="program-action-link" href="<?= site_url('chairperson/dashboard') ?>?program=<?= urlencode($program) ?>">
                                                <span>Plotter</span>
                                                <span class="material-symbols-outlined">arrow_forward</span>
                                            </a>
                                            <span style="color:var(--color-border-medium);">&middot;</span>
                                            <a class="program-action-link" href="<?= site_url('chairperson/sections') ?>?program=<?= urlencode($program) ?>">
                                                <span>Sections</span>
                                                <span class="material-symbols-outlined">arrow_forward</span>
                                            </a>
                                        </div>
                                        <button type="button" class="program-action-link" style="background:none;border:0;padding:0;cursor:pointer;" data-modal-target="<?= esc($modalId) ?>">
                                            <span>Inspect program</span>
                                            <span class="material-symbols-outlined">open_in_new</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div>
            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div>
                        <h3>Quick Navigation</h3>
                        <p>Frequent actions and management tools</p>
                    </div>
                </div>
                <div class="tool-grid">
                    <a class="tool-card" href="<?= site_url('chairperson/dashboard') ?>">
                        <div class="tool-card__head">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">calendar_month</span>
                            </div>
                            <span class="material-symbols-outlined tool-card__chevron">chevron_right</span>
                        </div>
                        <div class="tool-card__info">
                            <span class="tool-card__title">Schedule Plotter</span>
                            <span class="tool-card__sub">Weekly visual grid</span>
                        </div>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/sections') ?>">
                        <div class="tool-card__head">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">groups</span>
                            </div>
                            <span class="material-symbols-outlined tool-card__chevron">chevron_right</span>
                        </div>
                        <div class="tool-card__info">
                            <span class="tool-card__title">Sections &amp; Roster</span>
                            <span class="tool-card__sub">Student cohort list</span>
                        </div>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/offerings') ?>">
                        <div class="tool-card__head">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">merge_type</span>
                            </div>
                            <span class="material-symbols-outlined tool-card__chevron">chevron_right</span>
                        </div>
                        <div class="tool-card__info">
                            <span class="tool-card__title">Merged Classes</span>
                            <span class="tool-card__sub">Cross-section pool</span>
                        </div>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/curriculum') ?>">
                        <div class="tool-card__head">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">menu_book</span>
                            </div>
                            <span class="material-symbols-outlined tool-card__chevron">chevron_right</span>
                        </div>
                        <div class="tool-card__info">
                            <span class="tool-card__title">Curriculum</span>
                            <span class="tool-card__sub">Subject catalog</span>
                        </div>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/grade-approvals') ?>">
                        <div class="tool-card__head">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">grade</span>
                            </div>
                            <span class="material-symbols-outlined tool-card__chevron">chevron_right</span>
                        </div>
                        <div class="tool-card__info">
                            <span class="tool-card__title">Grade Approvals</span>
                            <span class="tool-card__sub">Lock &amp; review</span>
                        </div>
                    </a>
                    <a class="tool-card" href="<?= site_url('chairperson/profile') ?>">
                        <div class="tool-card__head">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            <span class="material-symbols-outlined tool-card__chevron">chevron_right</span>
                        </div>
                        <div class="tool-card__info">
                            <span class="tool-card__title">Profile</span>
                            <span class="tool-card__sub">Account settings</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Modals for Each Program ===== -->
    <?php if ($programs !== []): ?>
        <?php foreach ($programs as $progIdx => $program): ?>
            <?php
                $progKey      = strtolower(trim($program));
                $progGaps     = $gapsByProgram[$progKey]['items'] ?? [];
                $progSections = $sectionsByProgram[$progKey] ?? [];
                $modalId      = 'progModal_' . $progIdx;
            ?>
            <div class="apple-modal-backdrop" id="<?= esc($modalId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= esc($modalId) ?>_title">
                <div class="apple-modal">
                    <div class="apple-modal__header">
                        <div class="apple-modal__title-box">
                            <div class="tool-icon-squircle">
                                <span class="material-symbols-outlined">school</span>
                            </div>
                            <div>
                                <h3 id="<?= esc($modalId) ?>_title"><?= esc($program) ?></h3>
                                <p><?= esc($deptName) ?> &middot; <?= esc($activeYear) ?></p>
                            </div>
                        </div>
                        <button type="button" class="apple-modal__close" data-modal-close aria-label="Close modal">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="apple-modal__body">
                        <!-- Sections Needing Plotting in this Program -->
                        <div>
                            <div class="modal-section-title">Sections Needing Schedule Plotting</div>
                            <?php if ($progGaps !== []): ?>
                                <div class="program-gaps-container">
                                    <?php foreach ($progGaps as $gap): ?>
                                        <div class="prog-gap-item" style="padding:10px 14px;">
                                            <div class="prog-gap-item__left">
                                                <span class="prog-cohort-tag" style="font-size:13px;"><?= esc($gap['year_level']) ?></span>
                                                <span class="sep" style="color:var(--color-border-medium);">&middot;</span>
                                                <?php if ($gap['section'] === null): ?>
                                                    <span class="section-pill-sm section-pill-sm--empty">No section yet</span>
                                                <?php else: ?>
                                                    <span class="section-pill-sm">Section <?= esc($gap['section']) ?></span>
                                                <?php endif; ?>
                                                <span class="sep" style="color:var(--color-border-medium);">&middot;</span>
                                                <span style="font-size:11.5px;color:var(--color-text-secondary);"><?= (int)$gap['missing_count'] ?> subjects missing:</span>
                                                <?php foreach ($gap['missing_codes'] as $code): ?>
                                                    <span class="gap-code-chip-sm"><?= esc($code) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="prog-gap-item__right">
                                                <?php if ($gap['section'] === null): ?>
                                                    <a class="btn-plot-mini" href="<?= site_url('chairperson/sections') ?>">
                                                        <span>Create Section</span>
                                                        <span class="material-symbols-outlined">add</span>
                                                    </a>
                                                <?php else: ?>
                                                    <a class="btn-plot-mini" href="<?= esc($gap['url']) ?>">
                                                        <span>Plot Now</span>
                                                        <span class="material-symbols-outlined">arrow_forward</span>
                                                    </a>
                                                <?php endif ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="prog-all-clear" style="padding:10px 14px;">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span>All sections in <?= esc($program) ?> are completely plotted for <?= esc($activeYear) ?>.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Registered Cohorts & Sections -->
                        <div>
                            <div class="modal-section-title">Registered Program Sections (Term Roster)</div>
                            <?php if ($progSections !== []): ?>
                                <table class="modal-sections-table">
                                    <thead>
                                        <tr>
                                            <th>Year Level</th>
                                            <th>Section Code</th>
                                            <th>Capacity</th>
                                            <th>Status</th>
                                            <th style="text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($progSections as $sec): ?>
                                            <tr>
                                                <td><strong><?= esc($sec['year_level']) ?></strong></td>
                                                <td><span class="section-pill-sm">Section <?= esc($sec['section']) ?></span></td>
                                                <td><?= esc($sec['capacity'] ?? '30') ?> students</td>
                                                <td>
                                                    <span class="kpi-flag kpi-flag--ok"><?= esc(ucfirst($sec['status'] ?? 'active')) ?></span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <a class="btn-plot-mini" href="<?= site_url('chairperson/dashboard') ?>?program=<?= urlencode($program) ?>&year_level=<?= urlencode($sec['year_level']) ?>&section=<?= urlencode($sec['section']) ?>">
                                                        <span>Plot Grid</span>
                                                        <span class="material-symbols-outlined">arrow_forward</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="font-size:12.5px;color:var(--color-text-tertiary);margin:0;">No sections currently registered under this program for this term.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="apple-modal__footer">
                        <button type="button" class="btn btn-secondary" data-modal-close style="border-radius:var(--radius-pill);padding:8px 16px;font-size:12px;">Close</button>
                        <a class="btn btn-primary" href="<?= site_url('chairperson/dashboard') ?>?program=<?= urlencode($program) ?>" style="border-radius:var(--radius-pill);padding:8px 16px;font-size:12px;">
                            <span class="material-symbols-outlined" style="font-size:16px;">calendar_month</span>
                            <span>Open in Plotter</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(() => {
    // Modal dialog controller (Apple-style smooth dismiss and backdrop click)
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-open');
        if (!document.querySelector('.apple-modal-backdrop.is-open')) {
            document.body.style.overflow = '';
        }
    };

    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', (evt) => {
            evt.preventDefault();
            const targetId = trigger.getAttribute('data-modal-target');
            openModal(targetId);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.apple-modal-backdrop');
            closeModal(modal);
        });
    });

    document.querySelectorAll('.apple-modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (evt) => {
            if (evt.target === backdrop) {
                closeModal(backdrop);
            }
        });
    });

    document.addEventListener('keydown', (evt) => {
        if (evt.key === 'Escape') {
            const openBackdrop = document.querySelector('.apple-modal-backdrop.is-open');
            if (openBackdrop) closeModal(openBackdrop);
        }
    });

    // Progressive enhancement for "Create section" fast-path
    document.querySelectorAll('.gap-ensure-form').forEach(form => {
        form.addEventListener('submit', async evt => {
            const btn = form.querySelector('.gap-ensure');
            evt.preventDefault();
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.innerHTML = '<span>Creating…</span>';
            try {
                const payload = {};
                new FormData(form).forEach((value, key) => { payload[key] = value; });
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => null);
                if (!res.ok || !data?.ok) throw new Error(data?.error || 'Could not create the section.');
                window.location.href = form.dataset.plotUrlBase + '&section=' + encodeURIComponent(data.section);
            } catch (error) {
                btn.disabled = false;
                btn.innerHTML = original;
                alert(error.message);
            }
        });
    });
})();
</script>
<?= $this->endSection() ?>
