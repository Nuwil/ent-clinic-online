<?php
/**
 * Login Page - Simple Form Display
 * Note: Authentication is now handled by index.php
 * This page just displays the login form and any errors
 */

// Session should already be started by index.php
if (session_status() === PHP_SESSION_NONE) session_start();

// The error variable is set by index.php if login fails
// Use the passed $loginError or check $error from login.php logic
$error = $loginError ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENT Clinic - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .login-header h1 {
            margin: 0.5rem 0 0 0;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.9375rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert-error {
            padding: 0.875rem;
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }
        
        .alert-error i {
            margin-right: 0.5rem;
        }
        
        .login-footer {
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fafafa;
            color: #666;
            font-size: 0.875rem;
        }
        
        .demo-hint {
            margin-top: 1rem;
            padding: 0.75rem;
            background: #f0f7ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            font-size: 0.8125rem;
            color: #0066cc;
        }
        
        .demo-hint strong {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-hospital-alt"></i>
            <h1>ENT Clinic</h1>
            <p>Patient Management System</p>
        </div>
        
        <form method="POST" action="/ENT-clinic-online/ent-app/public/" class="login-body">
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="admin" 
                    autofocus
                    required 
                />
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••" 
                    required 
                />
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
            
            <div class="demo-hint">
                <strong>📋 Demo Accounts:</strong>
                <div style="margin-top: 0.5rem; font-size: 0.85rem; line-height: 1.6;">
                    <div><strong>👑 Admin:</strong> admin / admin123</div>
                    <div><strong>🏥 Doctor:</strong> doctor_demo / password</div>
                    <div><strong>📝 Secretary:</strong> staff_demo / password</div>
                </div>
            </div>
        </form>
        
        <div class="login-footer">
            <i class="fas fa-lock"></i> Secure Login • Session-based Authentication
        </div>
    </div>
</body>
</html>
