<?php
// 显示所有错误（调试用，生产环境应移除）
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
/*
* 远程唤醒系统
* 文件名称：mywake/index.php
* 代码作者：刘亮
* 代码时间：2025-8-31
* 代码版本：2.2 (优化性能版)
*/
session_name('RemoteWakeSession');
session_start();

// 配置文件路径
define('CONFIG_FILE', __DIR__ . '/config.json');
define('CACHE_FILE', __DIR__ . '/status_cache.json');

// 当配置文件不存在时，跳转到 init.php
if (!file_exists(CONFIG_FILE)) {
    header("Location: init.php");
    exit;
}


// 状态缓存时间（秒）
$cacheTime = 30;

// 加载状态缓存
function loadStatusCache() {
    if (file_exists(CACHE_FILE)) {
        $cacheContent = file_get_contents(CACHE_FILE);
        return json_decode($cacheContent, true) ?: [];
    }
    return [];
}

// 保存状态缓存
function saveStatusCache($cache) {
    $json = json_encode($cache, JSON_PRETTY_PRINT);
    file_put_contents(CACHE_FILE, $json);
}

// 加载配置
function loadConfig() {
    global $defaultConfig;
    
    if (file_exists(CONFIG_FILE)) {
        $configContent = file_get_contents(CONFIG_FILE);
        return json_decode($configContent, true);
    }
    
    // 如果配置文件不存在，创建默认配置
    saveConfig($defaultConfig);
    return $defaultConfig;
}

// 保存配置 - 使用UTF-8编码保存中文
function saveConfig($config) {
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(CONFIG_FILE, $json);
}

// 优化的设备在线检测函数
function isOnlineByPort($ip) {
    // 优先检测最常用的端口，超时时间缩短
    $ports = [80, 443, 22, 3389, 135]; // 重新排序，减少检测数量
    
    foreach ($ports as $port) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 0.3); // 超时减少到0.3秒
        if ($fp) {
            fclose($fp);
            return true;
        }
        usleep(50000); // 50ms延迟，避免过于密集的连接
    }
    return false;
}

// 获取缓存状态
function getCachedStatus($ip, $forceCheck = false) {
    static $statusCache = null;
    
    if ($statusCache === null) {
        $statusCache = loadStatusCache();
    }
    
    $currentTime = time();
    
    // 如果有缓存且未过期，且不强制检查，则使用缓存
    if (!$forceCheck && isset($statusCache[$ip]) && 
        ($currentTime - $statusCache[$ip]['timestamp']) < $GLOBALS['cacheTime']) {
        return $statusCache[$ip]['status'];
    }
    
    // 执行实际的状态检测
    $status = isOnlineByPort($ip);
    
    // 更新缓存
    $statusCache[$ip] = [
        'status' => $status,
        'timestamp' => $currentTime
    ];
    
    // 异步保存缓存（避免阻塞主线程）
    register_shutdown_function(function() use ($statusCache) {
        saveStatusCache($statusCache);
    });
    
    return $status;
}

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = loadConfig();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                if (isset($_POST['password']) && $_POST['password'] === $config['password']) {
                    $_SESSION['logged_in'] = true;
                } else {
                    $error = "密码错误";
                }
                break;
                
            case 'add_device':
                if (isset($_POST['name'], $_POST['mac'], $_POST['ip'])) {
                    $newDevice = [
                        'name' => $_POST['name'],
                        'mac' => $_POST['mac'],
                        'ip' => $_POST['ip']
                    ];
                    $config['devices'][] = $newDevice;
                    saveConfig($config);
                }
                break;
                
            case 'edit_device':
                if (isset($_POST['index'], $_POST['name'], $_POST['mac'], $_POST['ip'])) {
                    $index = (int)$_POST['index'];
                    if (isset($config['devices'][$index])) {
                        $config['devices'][$index] = [
                            'name' => $_POST['name'],
                            'mac' => $_POST['mac'],
                            'ip' => $_POST['ip']
                        ];
                        saveConfig($config);
                    }
                }
                break;
                
            case 'delete_device':
                if (isset($_POST['index'])) {
                    $index = (int)$_POST['index'];
                    if (isset($config['devices'][$index])) {
                        array_splice($config['devices'], $index, 1);
                        saveConfig($config);
                    }
                }
                break;
                
            case 'wake':
                if (isset($_POST['mac'])) {
                    $mac = preg_replace('/[^a-fA-F0-9:]/', '', $_POST['mac']);
                    if (!empty($mac)) {
                        $broadcast = '255.255.255.255';
                        $macBytes = str_replace(':', '', $mac);
                        
                        if (strlen($macBytes) == 12) {
                            $packet = pack('H*', str_repeat('FF', 6) . str_repeat($macBytes, 16));
                            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                            socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, true);
                            $result = socket_sendto($sock, $packet, strlen($packet), 0, $broadcast, 9);
                            socket_close($sock);
                            
                            echo $result ? 'success' : '发送失败';
                            exit;
                        }
                    }
                    echo 'MAC地址格式错误';
                    exit;
                }
                break;
                
            case 'check_status':
                // 单独的状态检查接口
                if (isset($_POST['ip'])) {
                    $forceCheck = isset($_POST['force']) && $_POST['force'] === 'true';
                    $status = getCachedStatus($_POST['ip'], $forceCheck);
                    echo json_encode(['online' => $status, 'ip' => $_POST['ip']]);
                    exit;
                }
                break;

            case 'quick_add':
                // 快速添加设备
                if (isset($_POST['ip'], $_POST['mac'], $_POST['name'])) {
                    $ip = $_POST['ip'];
                    $mac = $_POST['mac'];
                    $name = $_POST['name'];
                    
                    $newDevice = [
                        'name' => $name,
                        'mac' => strtoupper($mac),
                        'ip' => $ip
                    ];
                    $config['devices'][] = $newDevice;
                    saveConfig($config);
                    echo 'success';
                    exit;
                }
                break;
        }
    }
}

// 获取网关IP
function getDefaultGateway() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        exec('ipconfig', $output);
        foreach ($output as $line) {
            if (preg_match('/默认网关\s*[\.:\s]+(.*)/i', $line, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match('/Default Gateway\s*[\.:\s]+(.*)/i', $line, $matches)) {
                return trim($matches[1]);
            }
        }
    } else {
        $output = [];
        exec('ip route | grep default', $output);
        if (!empty($output[0])) {
            preg_match('/default via (\d+\.\d+\.\d+\.\d+)/', $output[0], $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }
        
        exec('netstat -rn | grep ^0.0.0.0 | awk \'{print $2}\'', $output);
        if (!empty($output[0]) && filter_var($output[0], FILTER_VALIDATE_IP)) {
            return $output[0];
        }
    }
    return '192.168.1.1';
}

// 获取本机IP和子网
function getLocalIP() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('ipconfig | findstr /i "IPv4"', $output);
        foreach ($output as $line) {
            if (preg_match('/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/', $line, $matches)) {
                return $matches[0];
            }
        }
    } else {
        exec("hostname -I | awk '{print $1}'", $output);
        if (!empty($output[0]) && filter_var($output[0], FILTER_VALIDATE_IP)) {
            return $output[0];
        }
    }
    return '192.168.1.100';
}

// 使用ARP表扫描
function scanWithARP() {
    $devices = [];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('arp -a', $output);
        foreach ($output as $line) {
            if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+([0-9a-f-]+)\s+/i', $line, $matches)) {
                $ip = $matches[1];
                // 排除172开头的IP
                if (strpos($ip, '172.') === 0) {
                    continue;
                }
                $mac = strtoupper(str_replace('-', ':', $matches[2]));
                if ($mac !== 'FF-FF-FF-FF-FF-FF' && $mac !== '00-00-00-00-00-00') {
                    $devices[] = [
                        'ip' => $ip,
                        'mac' => $mac,
                        'online' => true
                    ];
                }
            }
        }
    } else {
        exec('arp -n', $output);
        foreach ($output as $line) {
            if (preg_match('/(\d+\.\d+\.\d+\.\d+).*?([0-9a-f:]{17})/i', $line, $matches)) {
                $ip = $matches[1];
                // 排除172开头的IP
                if (strpos($ip, '172.') === 0) {
                    continue;
                }
                $devices[] = [
                    'ip' => $ip,
                    'mac' => strtoupper($matches[2]),
                    'online' => true
                ];
            }
        }
    }
    
    return $devices;
}

// 使用nmap扫描
function scanWithNmap($subnet) {
    $devices = [];
    
    exec('which nmap', $output, $returnCode);
    if ($returnCode !== 0) return $devices;
    
    exec("nmap -sn $subnet0/24", $output);
    
    $currentIP = '';
    foreach ($output as $line) {
        if (preg_match('/Nmap scan report for (.*?) \((\d+\.\d+\.\d+\.\d+)\)/', $line, $matches)) {
            $currentIP = $matches[2];
            // 排除172开头的IP
            if (strpos($currentIP, '172.') === 0) {
                $currentIP = ''; // 清空当前IP，跳过此设备
            }
        } elseif (preg_match('/MAC Address: ([0-9A-F:]{17})/i', $line, $matches) && $currentIP !== '') {
            $mac = strtoupper($matches[1]);
            $devices[] = [
                'ip' => $currentIP,
                'mac' => $mac,
                'online' => true
            ];
            $currentIP = ''; // 重置当前IP
        }
    }
    
    return $devices;
}

// Ping检测
function ping($host) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("ping -n 1 -w 500 $host", $output, $result);
    } else {
        exec("ping -c 1 -W 1 $host", $output, $result);
    }
    return $result === 0;
}

// 获取MAC地址
function getMacAddress($ip) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("arp -a $ip", $output);
        foreach ($output as $line) {
            if (preg_match('/([0-9a-f-]{17})/i', $line, $matches)) {
                return strtoupper(str_replace('-', ':', $matches[1]));
            }
        }
    } else {
        exec("arp -n $ip", $output);
        foreach ($output as $line) {
            if (preg_match('/.*?([0-9a-f:]{17}).*?/', $line, $matches)) {
                return strtoupper($matches[1]);
            }
        }
    }
    return false;
}

// 改进的扫描函数
function scanNetwork($subnet, $start = 1, $end = 15) {
    $devices = [];
    
    // 方法1: 使用arp命令（最可靠）
    $arpDevices = scanWithARP();
    if (!empty($arpDevices)) {
        return $arpDevices;
    }
    
    // 方法2: 使用nmap（如果可用）
    $nmapDevices = scanWithNmap($subnet);
    if (!empty($nmapDevices)) {
        return $nmapDevices;
    }
    
    // 方法3: 使用ping扫描（减少范围）
    for ($i = $start; $i <= $end; $i++) {
        $ip = $subnet . $i;
        
        // 排除172开头的IP和网络/广播地址
        if (strpos($ip, '172.') === 0 || $i == 0 || $i == 255) {
            continue;
        }
        
        if (ping($ip)) {
            $mac = getMacAddress($ip);
            $devices[] = [
                'ip' => $ip,
                'mac' => $mac ?: '未知',
                'online' => true
            ];
        }
        
        usleep(50000);
    }
    
    return $devices;
}

// 处理扫描请求
if (isset($_GET['scan']) && $_GET['scan'] == '1') {
    $gateway = getDefaultGateway();
    $localIP = getLocalIP();
    
    $subnet = substr($localIP, 0, strrpos($localIP, '.') + 1);
    $devices = scanNetwork($subnet, 1, 15);
    
    echo '<div class="scan-results">';
    echo '<h3>局域网设备扫描结果</h3>';
    echo '<p>扫描网段: ' . $subnet . '0/24</p>';
    echo '<p>已排除172.x.x.x地址段的设备</p>';
    
    if (!empty($devices)) {
        echo '<table>';
        echo '<tr><th>IP地址</th><th>MAC地址</th><th>状态</th><th>操作</th></tr>';
        
        foreach ($devices as $device) {
            $status = $device['online'] ? '<span class="online">在线</span>' : '<span class="offline">离线</span>';
            echo '<tr>';
            echo '<td>' . $device['ip'] . '</td>';
            echo '<td>' . $device['mac'] . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td><button class="btn btn-primary btn-small" onclick="showQuickAddModal(\'' . $device['ip'] . '\', \'' . $device['mac'] . '\')">快速添加</button></td>';
            echo '</tr>';
        }
        
        echo '</table>';
    } else {
        echo '<p>未发现设备。请尝试以下方法：</p>';
        echo '<ul>';
        echo '<li>确保您有足够的权限执行网络扫描</li>';
        echo '<li>尝试使用nmap工具：<code>sudo apt install nmap</code></li>';
        echo '<li>或者手动添加设备</li>';
        echo '</ul>';
    }
    
    echo '</div>';
    exit;
}

// 处理状态检查请求
if (isset($_GET['check_status']) && isset($_GET['ip'])) {
    $forceCheck = isset($_GET['force']) && $_GET['force'] === 'true';
    $status = getCachedStatus($_GET['ip'], $forceCheck);
    header('Content-Type: application/json');
    echo json_encode(['online' => $status, 'ip' => $_GET['ip']]);
    exit;
}

// 检查登录状态
if (!isset($_SESSION['logged_in'])) {
    $config = loadConfig();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>远程唤醒登录</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                margin: 0;
                padding: 0;
                display: flex;
                height: 100vh;
                align-items: center;
                justify-content: center;
            }
            .login-box {
                background: white;
                padding: 2em;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                width: 90%;
                max-width: 350px;
                text-align: center;
            }
            .login-box h3 {
                margin-top: 0;
                margin-bottom: 1.5em;
                color: #333;
                font-weight: 600;
            }
            input[type="password"], button {
                width: 100%;
                padding: 0.8em;
                margin-top: 1em;
                font-size: 1em;
                border: 1px solid #ddd;
                border-radius: 6px;
                box-sizing: border-box;
                transition: all 0.3s;
            }
            input[type="password"]:focus {
                border-color: #4CAF50;
                box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
                outline: none;
            }
            button {
                background: #4CAF50;
                color: white;
                border: none;
                cursor: pointer;
                font-weight: 600;
            }
            button:hover {
                background: #45a049;
                transform: translateY(-2px);
                box-shadow: 0 5px 10px rgba(0,0,0,0.1);
            }
            .error {
                color: #e74c3c;
                margin-top: 1em;
                text-align: center;
                font-size: 0.9em;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h3>远程唤醒登录</h3>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" placeholder="请输入密码" required>
                <button type="submit">登录</button>
            </form>
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 加载配置
$config = loadConfig();
$devices = $config['devices'];

// 预加载所有设备状态到缓存
foreach ($devices as $device) {
    getCachedStatus($device['ip']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>远程唤醒设备</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --danger-color: #f44336;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --border-color: #ddd;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
            color: var(--text-color);
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 1em;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.5em;
            padding-bottom: 1em;
            border-bottom: 1px solid var(--border-color);
        }
        .header h1 {
            margin: 0;
            color: var(--text-color);
        }
        .btn {
            padding: 0.6em 1.2em;
            font-size: 0.9em;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-left: 0.5em;
        }
        .btn-small {
            padding: 0.4em 0.8em;
            font-size: 0.8em;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        .btn-secondary:hover {
            background-color: #0b7dda;
            transform: translateY(-2px);
        }
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        .btn-danger:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5em;
            margin-bottom: 1.5em;
        }
        .device-list {
            margin-bottom: 2em;
        }
        .device-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1em;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 1em;
            background: white;
            transition: all 0.3s;
        }
        .device-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .device-info {
            flex: 1;
        }
        .device-name {
            font-weight: 600;
            margin-bottom: 0.3em;
        }
        .device-details {
            font-size: 0.9em;
            color: #666;
        }
        .status {
            font-size: 0.85em;
            padding: 0.3em 0.6em;
            border-radius: 12px;
            margin-left: 0.8em;
        }
        .online { 
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .offline { 
            background-color: #ffebee;
            color: #c62828;
        }
        .device-actions {
            display: flex;
            gap: 0.5em;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2em;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .modal h3 {
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 1.2em;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.8em;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1em;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5em;
            margin-top: 1.5em;
        }
        .scan-results {
            margin-top: 1.5em;
            max-height: 400px;
            overflow-y: auto;
            background: white;
            padding: 1.5em;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .scan-results table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1em;
        }
        .scan-results th, .scan-results td {
            padding: 0.8em;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .scan-results th {
            background-color: var(--light-gray);
            font-weight: 600;
        }
        .last-update {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 1.5em;
        }
        .scan-info {
            background: #e3f2fd;
            padding: 1em;
            border-radius: 6px;
            margin-bottom: 1em;
        }
        .refresh-btn {
            background: none;
            border: none;
            color: #2196F3;
            cursor: pointer;
            font-size: 0.9em;
            margin-left: 0.5em;
        }
        .refresh-btn:hover {
            text-decoration: underline;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1em;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1001;
            transition: all 0.3s;
        }
        .toast.error {
            background: #f44336;
        }
        @media (max-width: 768px) {
            .device-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .device-actions {
                margin-top: 1em;
                width: 100%;
                justify-content: flex-end;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-actions {
                margin-top: 1em;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>远程设备管理</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="showAddModal()">添加设备</button>
            <button class="btn btn-secondary" onclick="scanNetwork()">扫描局域网</button>
            <a href="?logout=1" class="btn btn-danger">退出登录</a>
        </div>
    </div>

    <div class="last-update">
        最后检测时间：<span id="lastUpdateTime"><?= date('Y-m-d H:i:s') ?></span>
        <button class="refresh-btn" onclick="refreshAllStatus(true)">强制刷新</button>
    </div>

    <div class="card">
        <h2>设备列表</h2>
        <div class="device-list" id="deviceList">
				<?php foreach ($devices as $index => $device): ?>
					<?php
						$name = $device['name'];
						$mac = strtoupper($device['mac']);
						$ip  = $device['ip'];
						$online = getCachedStatus($ip);
					?>
					<div class="device-item" id="device-<?= $index ?>" data-ip="<?= $ip ?>" data-mac="<?= $mac ?>" data-name="<?= htmlspecialchars($name) ?>">
						<div class="device-info">
							<div class="device-name"><?= htmlspecialchars($name) ?></div>
							<div class="device-details">
								IP: <?= htmlspecialchars($ip) ?> | 
								MAC: <?= htmlspecialchars($mac) ?>
								<span class="status <?= $online ? 'online' : 'offline' ?>" id="status-<?= $index ?>">
									<?= $online ? '在线' : '离线' ?>
								</span>
								<button class="refresh-btn" onclick="refreshStatus('<?= $ip ?>', <?= $index ?>)">刷新</button>
							</div>
						</div>
						<div class="device-actions">
							<?php if (!$online): ?>
								<button class="btn btn-primary" onclick="wakeDevice('<?= $mac ?>', '<?= htmlspecialchars($name) ?>')">唤醒</button>
							<?php endif; ?>
							<button class="btn btn-secondary" onclick="showEditModal(<?= $index ?>, '<?= htmlspecialchars($name) ?>', '<?= $mac ?>', '<?= $ip ?>')">编辑</button>
							<button class="btn btn-danger" onclick="deleteDevice(<?= $index ?>)">删除</button>
						</div>
					</div>
				<?php endforeach; ?>
        </div>
    </div>

    <div id="scanResults"></div>
</div>

<!-- 添加设备模态框 -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <h3>添加设备</h3>
        <form id="addForm" method="post">
            <input type="hidden" name="action" value="add_device">
            <div class="form-group">
                <label for="name">设备名称</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="mac">MAC地址</label>
                <input type="text" id="mac" name="mac" placeholder="例如: CC:00:DD:DD:32:AA" required>
            </div>
            <div class="form-group">
                <label for="ip">IP地址</label>
                <input type="text" id="ip" name="ip" placeholder="例如: 192.168.1.100" required>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="hideAddModal()">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑设备模态框 -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>编辑设备</h3>
        <form id="editForm" method="post">
            <input type="hidden" name="action" value="edit_device">
            <input type="hidden" id="editIndex" name="index" value="">
            <div class="form-group">
                <label for="editName">设备名称</label>
                <input type="text" id="editName" name="name" required>
            </div>
            <div class="form-group">
                <label for="editMac">MAC地址</label>
                <input type="text" id="editMac" name="mac" required>
            </div>
            <div class="form-group">
                <label for="editIp">IP地址</label>
                <input type="text" id="editIp" name="ip" required>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="hideEditModal()">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 快速添加设备模态框 -->
<div id="quickAddModal" class="modal">
    <div class="modal-content">
        <h3>快速添加设备</h3>
        <form id="quickAddForm">
            <input type="hidden" id="quickAddIp" name="ip">
            <input type="hidden" id="quickAddMac" name="mac">
            <div class="form-group">
                <label for="quickAddName">设备名称</label>
                <input type="text" id="quickAddName" name="name" required>
            </div>
            <div class="form-group">
                <label>IP地址</label>
                <input type="text" id="quickAddIpDisplay" disabled style="background: #f5f5f5;">
            </div>
            <div class="form-group">
                <label>MAC地址</label>
                <input type="text" id="quickAddMacDisplay" disabled style="background: #f5f5f5;">
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="hideQuickAddModal()">取消</button>
                <button type="button" class="btn btn-primary" onclick="submitQuickAdd()">添加</button>
            </div>
        </form>
    </div>
</div>

<script>
// 显示添加设备模态框
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}

// 隐藏添加设备模态框
function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

// 显示编辑设备模态框
function showEditModal(index, name, mac, ip) {
    document.getElementById('editIndex').value = index;
    document.getElementById('editName').value = name;
    document.getElementById('editMac').value = mac;
    document.getElementById('editIp').value = ip;
    document.getElementById('editModal').style.display = 'flex';
}

// 隐藏编辑设备模态框
function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 显示快速添加设备模态框
function showQuickAddModal(ip, mac) {
    if (mac === '未知') {
        showToast('无法添加：MAC地址未知', true);
        return;
    }
    
    document.getElementById('quickAddIp').value = ip;
    document.getElementById('quickAddMac').value = mac;
    document.getElementById('quickAddIpDisplay').value = ip;
    document.getElementById('quickAddMacDisplay').value = mac;
    document.getElementById('quickAddName').value = '设备-' + ip.split('.').pop();
    document.getElementById('quickAddModal').style.display = 'flex';
}

// 隐藏快速添加设备模态框
function hideQuickAddModal() {
    document.getElementById('quickAddModal').style.display = 'none';
}

// 提交快速添加
function submitQuickAdd() {
    const ip = document.getElementById('quickAddIp').value;
    const mac = document.getElementById('quickAddMac').value;
    const name = document.getElementById('quickAddName').value;
    
    if (!name.trim()) {
        showToast('请输入设备名称', true);
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'quick_add');
    formData.append('ip', ip);
    formData.append('mac', mac);
    formData.append('name', name);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if (result === 'success') {
            showToast('设备已成功添加');
            hideQuickAddModal();
            // 刷新页面以显示新设备
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('添加失败：' + result, true);
        }
    })
    .catch(error => {
        showToast('添加失败：' + error, true);
    });
}

// 删除设备
function deleteDevice(index) {
    if (confirm('确定要删除这个设备吗？')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_device">
            <input type="hidden" name="index" value="${index}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// 唤醒设备（使用AJAX，不跳转页面）
function wakeDevice(mac, deviceName) {
    const formData = new FormData();
    formData.append('action', 'wake');
    formData.append('mac', mac);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if (result === 'success') {
            showToast('已向 ' + deviceName + ' 发送唤醒命令');
        } else {
            showToast('唤醒失败：' + result, true);
        }
    })
    .catch(error => {
        showToast('唤醒失败：网络错误', true);
    });
}

// 显示提示信息
function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.className = isError ? 'toast error' : 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// 扫描局域网
function scanNetwork() {
    document.getElementById('scanResults').innerHTML = `
        <div class="scan-info">
            <p>正在扫描局域网，请稍候...</p>
            <p>这可能需要几秒钟时间</p>
        </div>
    `;
    
    fetch('?scan=1')
        .then(response => response.text())
        .then(data => {
            document.getElementById('scanResults').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('scanResults').innerHTML = `
                <div class="scan-results">
                    <h3>扫描失败</h3>
                    <p>错误信息: ${error}</p>
                </div>
            `;
        });
}

// 刷新单个设备状态
// 刷新单个设备状态
function refreshStatus(ip, index) {
    const statusElement = document.getElementById('status-' + index);
    const deviceItem = document.getElementById('device-' + index);
    const wakeButton = deviceItem.querySelector('.btn-primary'); // 查找唤醒按钮
    
    statusElement.innerHTML = '检测中...';
    statusElement.className = 'status';
    
    fetch(`?check_status=1&ip=${encodeURIComponent(ip)}&force=true`)
        .then(response => response.json())
        .then(data => {
            statusElement.innerHTML = data.online ? '在线' : '离线';
            statusElement.className = 'status ' + (data.online ? 'online' : 'offline');
            
            // 根据在线状态显示/隐藏唤醒按钮
            if (wakeButton) {
                wakeButton.style.display = data.online ? 'none' : 'inline-block';
            } else if (!data.online) {
                // 如果设备离线但没有唤醒按钮，则添加一个
                const actionsDiv = deviceItem.querySelector('.device-actions');
                const editButton = deviceItem.querySelector('.btn-secondary');
                const wakeBtn = document.createElement('button');
                wakeBtn.className = 'btn btn-primary';
                wakeBtn.onclick = function() { wakeDevice(deviceItem.dataset.mac, deviceItem.dataset.name); };
                wakeBtn.textContent = '唤醒';
                actionsDiv.insertBefore(wakeBtn, editButton);
            }
            
            updateLastUpdateTime();
        })
        .catch(error => {
            statusElement.innerHTML = '错误';
            console.error('状态检测失败:', error);
        });
}

// 刷新所有设备状态
function refreshAllStatus(force = false) {
    const deviceItems = document.querySelectorAll('.device-item');
    const forceParam = force ? '&force=true' : '';
    
    deviceItems.forEach((item, index) => {
        const ip = item.dataset.ip;
        const statusElement = document.getElementById('status-' + index);
        const wakeButton = item.querySelector('.btn-primary'); // 查找唤醒按钮
        
        if (statusElement) {
            statusElement.innerHTML = '检测中...';
            statusElement.className = 'status';
            
            fetch(`?check_status=1&ip=${encodeURIComponent(ip)}${forceParam}`)
                .then(response => response.json())
                .then(data => {
                    statusElement.innerHTML = data.online ? '在线' : '离线';
                    statusElement.className = 'status ' + (data.online ? 'online' : 'offline');
                    
                    // 根据在线状态显示/隐藏唤醒按钮
                    if (wakeButton) {
                        wakeButton.style.display = data.online ? 'none' : 'inline-block';
                    } else if (!data.online) {
                        // 如果设备离线但没有唤醒按钮，则添加一个
                        const actionsDiv = item.querySelector('.device-actions');
                        const editButton = item.querySelector('.btn-secondary');
                        const wakeBtn = document.createElement('button');
                        wakeBtn.className = 'btn btn-primary';
                        wakeBtn.onclick = function() { wakeDevice(item.dataset.mac, item.dataset.name); };
                        wakeBtn.textContent = '唤醒';
                        actionsDiv.insertBefore(wakeBtn, editButton);
                    }
                })
                .catch(error => {
                    statusElement.innerHTML = '错误';
                    console.error('状态检测失败:', error);
                });
        }
    });
    
    updateLastUpdateTime();
}

// 更新最后检测时间
function updateLastUpdateTime() {
    const now = new Date();
    document.getElementById('lastUpdateTime').textContent = 
        now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
}

// 点击模态框外部关闭模态框
window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    const quickAddModal = document.getElementById('quickAddModal');
    
    if (event.target == addModal) {
        hideAddModal();
    }
    if (event.target == editModal) {
        hideEditModal();
    }
    if (event.target == quickAddModal) {
        hideQuickAddModal();
    }
}

// 页面加载完成后自动刷新状态（使用缓存）
document.addEventListener('DOMContentLoaded', function() {
    // 延迟一点执行，让页面先显示出来
    setTimeout(() => {
        refreshAllStatus(false);
    }, 100);
});
</script>
</body>
</html>
<?php
// 处理退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}