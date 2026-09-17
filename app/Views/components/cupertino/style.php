<?php
/**
 * Shared "Cupertino Academic Portal" shell — pure CSS (no Tailwind),
 * following the design tokens the user supplied. Included via
 * $this->include() so every role-shell page (student/*, faculty/*) renders
 * the same sidebar/topbar/card language without hand-copying ~300 lines of
 * CSS per file, the way components/shell/style.php did for the old design.
 */
?>
:root{
  --surface: #faf9fe;
  --surface-container-lowest: #ffffff;
  --surface-container-low: #f4f3f8;
  --surface-container: #eeedf3;
  --surface-container-high: #e9e7ed;
  --on-surface: #1a1b1f;
  --on-surface-variant: #414753;
  --outline: #717785;
  --outline-variant: #c1c6d6;
  --primary: #0059b5;
  --primary-container: #0071e3;
  --on-primary-container: #fcfbff;
  --secondary-container: #6ffb85;
  --on-secondary-container: #00732a;
  --tertiary-container: #6563e3;
  --error: #ba1a1a;
  --error-container: #ffdad6;
  --on-error-container: #93000a;
  --radius-sm: 0.625rem;
  --radius-md: 0.75rem;
  --radius-lg: 1rem;
  --radius-xl: 1.5rem;
  --shadow-card: 0 0 0 1px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.04);
  --shadow-floating: 0 12px 32px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.04);
}

*{ box-sizing: border-box; }
html, body { height: 100%; margin: 0; }
body{
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
  background: var(--surface);
  color: var(--on-surface);
  font-size: 15px;
  line-height: 20px;
  letter-spacing: -0.23px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  display: flex;
  overflow: hidden;
}
button{ font-family: inherit; cursor: pointer; }

.material-symbols-outlined{ font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; font-size: 20px; vertical-align: middle; }

::-webkit-scrollbar{ width: 8px; height: 8px; }
::-webkit-scrollbar-track{ background: transparent; }
::-webkit-scrollbar-thumb{ background: var(--outline-variant); border-radius: 8px; }

/* ===== Sidebar ===== */
.cp-sidebar{
  width: 240px; flex: 0 0 auto; display: flex; flex-direction: column; justify-content: space-between;
  height: 100%;
  background: rgba(248,248,250,.88);
  backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
  border-right: .5px solid rgba(0,0,0,.09);
  z-index: 30;
}
.cp-sidebar-top{ padding: 20px; }
.cp-brand{ display: flex; align-items: center; gap: 10px; padding: 4px 4px 24px; }
.cp-brand-icon{
  width: 32px; height: 32px; border-radius: 10px;
  background: linear-gradient(135deg, #2563eb, #6366f1);
  color: #fff; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 1px 2px rgba(0,0,0,.08);
}
.cp-brand-name{ font-size: 13px; font-weight: 700; color: var(--on-surface); line-height: 1.1; }
.cp-brand-sub{ font-size: 10px; color: var(--on-surface-variant); font-weight: 500; letter-spacing: .02em; }

.cp-nav-label{ padding: 0 12px 8px; font-size: 11px; font-weight: 700; color: #86868b; text-transform: uppercase; letter-spacing: .06em; }
.cp-nav{ display: flex; flex-direction: column; gap: 4px; }
.cp-nav-item{
  display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: var(--radius-md);
  color: var(--on-surface-variant); text-decoration: none; font-size: 14px; font-weight: 500;
  transition: background .15s, color .15s;
}
.cp-nav-item:hover{ background: rgba(0,0,0,.05); color: var(--on-surface); }
.cp-nav-item.is-active{ background: var(--primary-container); color: #fff; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,.1); }

.cp-sidebar-bottom{ padding: 16px 20px; border-top: .5px solid rgba(0,0,0,.09); }
.cp-logout{
  display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: var(--radius-md);
  color: #86868b; text-decoration: none; font-size: 14px; font-weight: 500; transition: background .15s, color .15s;
}
.cp-logout:hover{ background: #fef2f2; color: #ff3b30; }

/* ===== Main / chrome ===== */
.cp-main{ flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100%; overflow: hidden; background: var(--surface); }

.cp-header{
  height: 64px; flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between;
  padding: 0 32px;
  background: rgba(255,255,255,.82);
  backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
  border-bottom: .5px solid rgba(0,0,0,.09);
  z-index: 20;
}
.cp-crumbs{ font-size: 12px; color: #a3a3a3; font-weight: 500; }
.cp-crumbs b{ color: #737373; }
.cp-title{ font-size: 20px; font-weight: 800; letter-spacing: -0.02em; color: var(--on-surface); margin-top: 2px; }

.cp-user-pill{
  display: flex; align-items: center; gap: 10px; padding: 6px 12px 6px 6px; border-radius: 999px;
  background: rgba(255,255,255,.7); border: 1px solid rgba(0,0,0,.08); box-shadow: var(--shadow-card);
}
.cp-user-avatar-sm{
  width: 28px; height: 28px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary-container), #0051a8);
  color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex: 0 0 auto;
}
.cp-user-name{ font-size: 12px; font-weight: 600; color: var(--on-surface); line-height: 1.25; }
.cp-user-role{ font-size: 11px; color: var(--on-surface-variant); line-height: 1.25; }

.cp-content{ flex: 1; min-height: 0; overflow-y: auto; padding: 32px 40px; }
.cp-content-inner{ max-width: 1080px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
.cp-content-inner.narrow{ max-width: 720px; }

/* ===== Cards ===== */
.cp-card{ background: var(--surface-container-lowest); border-radius: var(--radius-xl); box-shadow: var(--shadow-card); padding: 28px 32px; position: relative; overflow: hidden; }
.cp-card-head{ margin-bottom: 4px; }
.cp-card-head h2{ font-size: 16px; font-weight: 700; letter-spacing: -0.01em; color: var(--on-surface); margin: 0 0 4px; }
.cp-card-head p{ font-size: 12.5px; color: var(--on-surface-variant); margin: 0 0 20px; }

.cp-avatar-row{ display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.cp-avatar-lg{
  width: 64px; height: 64px; border-radius: 50%; flex: 0 0 auto;
  background: linear-gradient(140deg, #0055b3, var(--primary-container), #42a5f5);
  color: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800;
}
.cp-avatar-row .name{ font-size: 16px; font-weight: 700; color: var(--on-surface); }
.cp-avatar-row .role{ font-size: 12.5px; color: var(--on-surface-variant); }

/* ===== Fields / inputs (inset trough) ===== */
.cp-field{ margin-bottom: 16px; }
.cp-field:last-child{ margin-bottom: 0; }
.cp-field label{ display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--on-surface-variant); margin-bottom: 6px; }
.cp-field .hint{ font-size: 11.5px; color: #a3a3a3; margin-top: 6px; }
.cp-field-row{ display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.cp-input, .cp-select{
  width: 100%; background: rgba(244,243,248,.9); color: var(--on-surface); font-size: 14px; font-weight: 500;
  padding: 11px 14px; border-radius: var(--radius-md); border: 1px solid rgba(0,0,0,.06);
  box-shadow: inset 0 1px 2px rgba(0,0,0,.04); font-family: inherit;
}
.cp-input:focus, .cp-select:focus{ outline: 2px solid var(--primary-container); outline-offset: 1px; }
.cp-input:disabled{ color: #a3a3a3; cursor: not-allowed; }
.cp-select{
  appearance: none; cursor: pointer; padding-right: 32px;
  background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%226%22%3E%3Cpath d=%22M1 1l4 4 4-4%22 stroke=%22%2386868b%22 stroke-width=%221.5%22 fill=%22none%22/%3E%3C/svg%3E');
  background-repeat: no-repeat; background-position: right 12px center;
}

/* ===== Buttons ===== */
.cp-btn{
  display: inline-flex; align-items: center; gap: 6px; border: none; border-radius: var(--radius-md);
  padding: 11px 22px; font-size: 13.5px; font-weight: 700; cursor: pointer; transition: background .15s, transform .1s;
  font-family: inherit;
}
.cp-btn-primary{ background: var(--primary-container); color: #fff; }
.cp-btn-primary:hover{ background: var(--primary); }
.cp-btn-primary:active{ transform: scale(.97); }
.cp-btn-secondary{ background: var(--surface-container-high); color: var(--on-surface); }
.cp-btn-secondary:hover{ background: var(--outline-variant); }

/* ===== Alerts ===== */
.cp-msg{ display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 13.5px; font-weight: 500; }
.cp-msg-ok{ color: #146c2e; background: #eaf9ee; border: 1px solid rgba(16,163,74,.25); }
.cp-msg-err{ color: #9f1239; background: #fff1f2; border: 1px solid rgba(159,18,57,.2); }
.cp-msg-err p{ margin: 2px 0 0; }
.cp-notice{ background: #eef2ff; color: #3730a3; padding: 16px 20px; border-radius: var(--radius-lg); font-size: 13.5px; }

/* ===== Chips / badges ===== */
.cp-chip{ display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 700; border: 1px solid transparent; }
.cp-chip .dot{ width: 6px; height: 6px; border-radius: 50%; }
.cp-chip.green{ background: #eaf9ee; color: #146c2e; border-color: rgba(16,163,74,.25); } .cp-chip.green .dot{ background: #16a34a; }
.cp-chip.gray{ background: #f4f4f5; color: #52525b; border-color: rgba(82,82,91,.2); } .cp-chip.gray .dot{ background: #a1a1aa; }
.cp-chip.indigo{ background: #eef2ff; color: #3730a3; border-color: rgba(55,48,163,.2); } .cp-chip.indigo .dot{ background: #6366f1; }
.cp-chip.rose{ background: #fff1f2; color: #9f1239; border-color: rgba(159,18,57,.2); } .cp-chip.rose .dot{ background: #f43f5e; }
.cp-chip.amber{ background: #fffbeb; color: #92400e; border-color: rgba(146,64,14,.2); } .cp-chip.amber .dot{ background: #d97706; }

/* ===== Segmented control ===== */
.cp-segmented{ display: inline-flex; padding: 4px; gap: 2px; background: var(--surface-container-high); border-radius: var(--radius-md); }
.cp-segmented button{ border: none; background: none; font-family: inherit; font-size: 12.5px; font-weight: 700; color: var(--on-surface-variant); padding: 8px 18px; border-radius: 8px; cursor: pointer; transition: background .15s, color .15s, box-shadow .15s; }
.cp-segmented button.is-active{ background: #fff; color: var(--on-surface); box-shadow: 0 2px 4px rgba(0,0,0,.08); }

.cp-empty{ text-align: center; padding: 44px 20px; color: #a3a3a3; }
.cp-empty .material-symbols-outlined{ font-size: 36px; opacity: .4; margin-bottom: 8px; }
.cp-empty p{ font-size: 13.5px; margin: 0; }

[hidden]{ display: none !important; }

@media (max-width: 860px){
  .cp-sidebar{ width: 72px; }
  .cp-brand-text, .cp-nav-item span.label, .cp-nav-label{ display: none; }
  .cp-nav-item{ justify-content: center; }
  .cp-content{ padding: 20px; }
  .cp-field-row{ grid-template-columns: 1fr; }
}
