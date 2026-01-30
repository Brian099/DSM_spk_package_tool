<?php
session_name('RemoteWakeSession');
session_start();

// 配置文件路径
define('CONFIG_FILE', __DIR__ . '/config.json');

// 默认配置
$defaultConfig = [
    'password' => '1234',
    'devices' => []
];

// 如果配置文件不存在，则创建默认配置文件
if (!file_exists(CONFIG_FILE)) {
    file_put_contents(CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 读取配置
$config = json_decode(file_get_contents(CONFIG_FILE), true);

// 保存密码并跳转
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $config['password'] = $_POST['password'];
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初始化配置 - 远程唤醒系统</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #06d6a0;
            --border-radius: 12px;
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--dark);
        }
        
        .container {
            width: 100%;
            max-width: 450px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .box {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .box:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 15px 15px 15px 45px;
            font-size: 16px;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        input[type="password"]:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .requirements {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 576px) {
            .box {
                padding: 20px;
            }
            
            .title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-power-off"></i>
            </div>
            <h1 class="title">远程唤醒系统</h1>
            <p class="subtitle">首次使用，请设置管理员密码</p>
        </div>
        
        <div class="box">
            <form method="post" id="setupForm">
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <div class="input-with-icon">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" name="password" id="password" placeholder="输入安全密码" required>
                        <span class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="requirements">
                        建议使用至少8位字符，包含字母和数字
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">确认密码</label>
                    <div class="input-with-icon">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" id="confirmPassword" placeholder="再次输入密码" required>
                        <span class="password-toggle" id="confirmPasswordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div id="passwordMatch" class="requirements"></div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> 保存并进入系统
                </button>
            </form>
        </div>
        
        <div class="footer">
            <p>© 2023 远程唤醒系统 | 安全可靠的设备管理</p>
        </div>
    </div>

    <script>
        // 密码可见性切换
        const setupPasswordToggle = (toggleElement, inputElement) => {
            toggleElement.addEventListener('click', () => {
                const type = inputElement.getAttribute('type') === 'password' ? 'text' : 'password';
                inputElement.setAttribute('type', type);
                
                // 切换图标
                toggleElement.querySelector('i').classList.toggle('fa-eye');
                toggleElement.querySelector('i').classList.toggle('fa-eye-slash');
            });
        };
        
        // 初始化密码可见性切换
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');
        setupPasswordToggle(passwordToggle, passwordInput);
        
        const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        setupPasswordToggle(confirmPasswordToggle, confirmPasswordInput);
        
        // 密码匹配验证
        const passwordMatch = document.getElementById('passwordMatch');
        const confirmPassword = document.getElementById('confirmPassword');
        
        confirmPassword.addEventListener('input', () => {
            if (confirmPassword.value !== passwordInput.value) {
                passwordMatch.textContent = '密码不匹配';
                passwordMatch.style.color = '#e63946';
            } else {
                passwordMatch.textContent = '密码匹配';
                passwordMatch.style.color = 'var(--success)';
            }
        });
        
        // 表单提交验证
        document.getElementById('setupForm').addEventListener('submit', (e) => {
            if (passwordInput.value !== confirmPassword.value) {
                e.preventDefault();
                alert('密码不匹配，请重新输入');
                passwordInput.focus();
            }
            
            if (passwordInput.value.length < 4) {
                e.preventDefault();
                alert('密码长度至少需要4个字符');
                passwordInput.focus();
            }
        });
    </script>
</body>
</html>