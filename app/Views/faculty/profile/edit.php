<?php
/**
 * Profile — Faculty view (Akademus SIS)
 * Same shell as Dashboard/Availability/Schedule so the faculty side reads
 * as one consistent app.
 */
$initials = strtoupper(mb_substr($user['name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Profile · Akademus SIS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
<style>
:root{
    /* Aliased onto the shared design-system tokens (public/assets/css/design-system/tokens.css). */
    --brand:var(--color-primary);
    --brand-soft:var(--color-primary-soft);
    --bg:var(--color-background);
    --border:var(--color-border);
    --ink:var(--color-text-primary);
    --muted:var(--color-text-secondary);
    --green:var(--color-success); --green-soft:var(--color-success-soft); --green-border:#bbf7d0;
    --red:var(--color-danger); --red-soft:var(--color-danger-soft); --red-border:#fecaca;
    --shadow-apple:var(--shadow-sm);
    --shadow-hover:var(--shadow-md);
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    background:var(--bg);color:var(--ink);
    display:flex;padding:12px;gap:12px;overflow:hidden;
    -webkit-font-smoothing:antialiased;
}
button{font-family:inherit;cursor:pointer;border:none;background:none}
button:focus-visible,input:focus-visible{outline:2px solid rgba(0,64,166,.4);outline-offset:2px}

.material-symbols-outlined{
    font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24;
    vertical-align:middle;
}
.fill-icon{font-variation-settings:'FILL' 1}

.glass{
    background:rgba(255,255,255,.75);
    backdrop-filter:blur(24px);
    -webkit-backdrop-filter:blur(24px);
    border:1px solid rgba(255,255,255,.4);
}

/* ===== Sidebar ===== */
.side-drawer{
    width:64px;flex:0 0 auto;
    display:flex;flex-direction:column;align-items:center;
    padding:20px 0;border-radius:20px;
    box-shadow:var(--shadow-apple);
    transition:width .16s ease;overflow:hidden;z-index:50;
}
.side-drawer:hover{width:200px;align-items:stretch}
.side-drawer .brand{
    width:40px;height:40px;border-radius:10px;background:#fff;
    box-shadow:0 1px 2px rgba(0,0,0,.08);color:var(--brand);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 24px;flex:0 0 auto;
}
.side-drawer:hover .brand{margin-left:12px;margin-right:auto}
.side-drawer nav{display:flex;flex-direction:column;gap:8px;width:100%;align-items:center;flex:1}
.side-drawer:hover nav{align-items:stretch;padding:0 8px}
.drawer-link{
    display:flex;align-items:center;gap:12px;
    width:44px;height:44px;border-radius:12px;
    color:var(--muted);text-decoration:none;flex:0 0 auto;
    justify-content:center;transition:background .15s,color .15s;
    white-space:nowrap;overflow:hidden;
}
.side-drawer:hover .drawer-link{width:100%;justify-content:flex-start;padding:0 12px}
.drawer-link .drawer-label{font-size:.82rem;font-weight:600;display:none}
.side-drawer:hover .drawer-label{display:inline}
.drawer-link:hover{color:var(--ink);background:rgba(0,0,0,.05)}
.drawer-link.active{color:var(--brand);background:var(--brand-soft)}
.drawer-link.active .material-symbols-outlined{font-variation-settings:'FILL' 1}
.drawer-link--logout{margin-top:auto}
.drawer-link--logout:hover{color:var(--red);background:var(--red-soft)}

/* ===== Content shell ===== */
.content{flex:1;min-width:0;display:flex;flex-direction:column;gap:12px;height:100%;overflow:hidden}
.topbar{
    border-radius:20px;
    display:flex;justify-content:space-between;align-items:center;
    gap:24px;padding:16px 32px;box-shadow:var(--shadow-apple);
}
.topbar-title{font-size:1.15rem;font-weight:600;letter-spacing:-.01em;white-space:nowrap}
.scroll-area{flex:1;overflow-y:auto;border-radius:20px}
.container{max-width:640px;margin:0 auto;padding:32px}

.msg-ok, .msg-err{
    display:flex;align-items:center;gap:10px;
    padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:.86rem;font-weight:500;
}
.msg-ok{color:var(--green);background:var(--green-soft);border:1px solid var(--green-border)}
.msg-err{color:var(--red);background:var(--red-soft);border:1px solid var(--red-border)}

.card{
    background:#fff;border:1px solid var(--border);border-radius:20px;
    padding:24px;margin-bottom:24px;box-shadow:var(--shadow-apple);
}
.card h2{font-size:1.02rem;font-weight:700;letter-spacing:-.01em;margin-bottom:4px}
.card .card-sub{color:var(--muted);font-size:.82rem;margin-bottom:20px}

.avatar-row{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.avatar-lg{
    width:64px;height:64px;border-radius:50%;background:var(--brand);color:#fff;
    display:grid;place-items:center;font-size:1.4rem;font-weight:700;flex:none;
}
.avatar-row .avatar-name{font-size:1.05rem;font-weight:700}
.avatar-row .avatar-role{font-size:.82rem;color:var(--muted)}

.field{margin-bottom:16px}
.field label{
    display:block;font-size:.7rem;font-weight:700;color:var(--muted);
    text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;
}
.field input{
    width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:10px;
    background:#fafafa;font-family:inherit;font-size:.86rem;color:var(--ink);
}
.field input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(0,64,166,.12)}
.field input:disabled{color:var(--muted);cursor:not-allowed}
.field .hint{font-size:.72rem;color:var(--muted);margin-top:5px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

.btn-primary{
    background:var(--brand);color:#fff;padding:11px 22px;border-radius:12px;
    font-size:.86rem;font-weight:600;transition:background .15s,transform .1s;
}
.btn-primary:hover{background:#003080}
.btn-primary:active{transform:scale(.97)}

@media (max-width:900px){
    .container{padding:24px 20px}
}

@media (max-width:640px){
    body{padding:6px;gap:6px}
    .side-drawer{width:56px}
    .container{padding:16px}
    .topbar{padding:12px 16px}
    .field-row{grid-template-columns:1fr}
}
</style>
</head>

<body>

<!-- Sidebar -->
<aside class="side-drawer glass">
    <div class="brand"><span class="material-symbols-outlined fill-icon">school</span></div>
    <nav>
        <a class="drawer-link" href="<?= site_url('faculty/dashboard') ?>" title="Dashboard">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="drawer-label">Dashboard</span>
        </a>
        <a class="drawer-link active" href="<?= site_url('faculty/profile') ?>" title="Profile">
            <span class="material-symbols-outlined">person</span>
            <span class="drawer-label">Profile</span>
        </a>
        <a class="drawer-link" href="<?= site_url('faculty/schedule') ?>" title="Schedule">
            <span class="material-symbols-outlined">calendar_month</span>
            <span class="drawer-label">Schedule</span>
        </a>
        <a class="drawer-link" href="<?= site_url('faculty/availability') ?>" title="Availability">
            <span class="material-symbols-outlined">event_available</span>
            <span class="drawer-label">Availability</span>
        </a>
    </nav>
    <a class="drawer-link drawer-link--logout" href="<?= site_url('logout') ?>" title="Logout">
        <span class="material-symbols-outlined">logout</span>
        <span class="drawer-label">Logout</span>
    </a>
</aside>

<!-- Content -->
<div class="content">

    <header class="topbar glass">
        <div class="topbar-title">Profile</div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="avatar-lg" style="width:32px;height:32px;font-size:.7rem;"><?= esc($initials) ?></div>
            <div>
                <div style="font-size:.82rem;font-weight:600;line-height:1.2;"><?= esc($user['name']) ?></div>
                <div style="font-size:.7rem;color:var(--muted);line-height:1.2;"><?= esc($user['role']) ?></div>
            </div>
        </div>
    </header>

    <main class="scroll-area">
        <div class="container">

            <?php if (session('profile_message')): ?>
                <div class="msg-ok"><span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> <?= esc(session('profile_message')) ?></div>
            <?php endif ?>
            <?php if (session('profile_error')): ?>
                <div class="msg-err"><span class="material-symbols-outlined" style="font-size:18px;">error</span> <?= esc(session('profile_error')) ?></div>
            <?php endif ?>

            <div class="card">
                <div class="avatar-row">
                    <div class="avatar-lg"><?= esc(mb_strtoupper(mb_substr($account['username'], 0, 1))) ?></div>
                    <div>
                        <div class="avatar-name"><?= esc($account['username']) ?></div>
                        <div class="avatar-role"><?= esc($deptName ?? 'No department') ?> &middot; Faculty</div>
                    </div>
                </div>

                <form method="post" action="<?= site_url('faculty/profile/update') ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Display Name</label>
                        <input type="text" name="username" value="<?= esc($account['username']) ?>" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" value="<?= esc($account['email']) ?>" disabled>
                        <div class="hint">Email is your login and can't be changed here — contact an administrator.</div>
                    </div>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>

            <div class="card">
                <h2>Change Password</h2>
                <div class="card-sub">Choose a new password of at least 8 characters.</div>

                <form method="post" action="<?= site_url('faculty/profile/password') ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label>New Password</label>
                            <input type="password" name="new_password" minlength="8" required>
                        </div>
                        <div class="field">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="8" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Change Password</button>
                </form>
            </div>

        </div>
    </main>
</div>

</body>
</html>
