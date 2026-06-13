<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Akademus SIS - Developer Notes</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            background: #1f4e78;
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        h1, h2 {
            margin-top: 0;
        }

        ul {
            line-height: 1.8;
        }

        code {
            background: #eef2f7;
            padding: 3px 6px;
            border-radius: 4px;
        }

        .success {
            color: #28a745;
            font-weight: bold;
        }

        .route {
            background: #eef7ff;
            padding: 8px 12px;
            border-left: 4px solid #1f4e78;
            margin-bottom: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>AKADEMUS SIS</h1>
        <p>Developer Authentication Guide</p>
    </div>

    <div class="card">
        <h2>HOME PAGE - DELETE (optional)</h2>

        <ul>
            <li>
                Bali Shuay, after pag login ni user, ma redirect sya sa assigned page niya,
                Pwede mo to e delete, purpose of this page para ma test lang ang routing, if gumana
            </li>
        </ul>
    </div>

    <div class="card">
        <h2>Authentication Status</h2>
        <p class="success">Authentication Module Completed</p>

        <ul>
            <li>Login</li>
            <li>Logout</li>
            <li>Role-Based Access Control</li>
            <li>Remember Me</li>
            <li>Forgot Password</li>
            <li>Password Reset via Email</li>
            <li>Route Protection</li>
        </ul>
    </div>

    <div class="card">
        <h2>Frontend Files You Can Edit</h2>

        <ul>
            <li><code>app/Views/auth/login.php</code></li>
            <li><code>app/Views/auth/register.php</code></li>
            <li><code>app/Views/auth/forgot_password.php</code></li>
            <li><code>app/Views/auth/reset_password.php</code></li>
            <li><code>app/Views/auth/layout.php</code></li>
        </ul>

        <p>
            Feel free to modify styling and layout.
            Authentication logic is already implemented in the backend.
        </p>
    </div>

    <div class="card">
        <h2>Authentication Flow</h2>

        <ol>
            <li>User logs in.</li>
            <li>Shield validates credentials.</li>
            <li>User is redirected to <code>/dashboard</code>.</li>
            <li>DashboardController determines user role.</li>
            <li>User is redirected to the appropriate dashboard.</li>
        </ol>
    </div>

    <div class="card">
        <h2>Role Routing</h2>

        <div class="route">
            Admin → <code>/admin/dashboard</code>
        </div>

        <div class="route">
            Faculty → <code>/faculty/dashboard</code>
        </div>

        <div class="route">
            Student → <code>/student/dashboard</code>
        </div>
    </div>

    <div class="card">
        <h2>Password Reset Flow</h2>

        <ol>
            <li>User opens Forgot Password page.</li>
            <li>User enters registered email.</li>
            <li>System generates secure reset token.</li>
            <li>Reset link is delivered via email.</li>
            <li>User opens reset link.</li>
            <li>User enters new password.</li>
            <li>Token is deleted after successful reset.</li>
            <li>Old reset links become invalid.</li>
        </ol>
    </div>

    <div class="card">
        <h2>Important Notes</h2>

        <ul>
            <li>Do not modify Shield vendor files.</li>
            <li>Do not modify authentication routes without backend coordination.</li>
            <li>Password hashing is handled automatically by Shield.</li>
            <li>Remember Me uses the <code>auth_remember_tokens</code> table.</li>
            <li>Password reset tokens are stored in <code>password_reset_tokens</code>.</li>
        </ul>
    </div>

    <div class="footer">
        Akademus SIS • Backend Documentation • Onesys
    </div>

</div>

</body>
</html>