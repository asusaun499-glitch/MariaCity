<?php
require_once 'config.php';

// ถ้า Login แล้ว Redirect ไปยังหน้าที่เหมาะสมทันที
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: /admin');
    } else {
        header('Location: /portal');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maria City Roleplay - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-grid {
            background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        .float { animation: float 4s ease-in-out infinite; }
        @keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
        .shimmer {
            background: linear-gradient(90deg, #e53e3e, #d69e2e, #e53e3e);
            background-size: 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s linear infinite;
        }
        .gsi-btn-container {
            display: flex;
            justify-content: center;
            min-height: 44px;
        }
        #loading-spinner { display: none; }
    </style>
</head>
<body class="min-h-screen bg-[#0d0d0d] bg-grid flex items-center justify-center p-4 overflow-hidden">

    <!-- Ambient Glow -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-red-600/10 blur-[120px] rounded-full pointer-events-none"></div>
    <div class="absolute bottom-0 right-0 w-[400px] h-[300px] bg-amber-600/8 blur-[100px] rounded-full pointer-events-none"></div>

    <div class="relative w-full max-w-md">
        <!-- Card -->
        <div class="bg-[#111111] border border-white/8 rounded-3xl p-8 shadow-2xl shadow-black/60 space-y-7">
            
            <!-- Logo + Title -->
            <div class="text-center space-y-4">
                <div class="float w-20 h-20 rounded-2xl bg-gradient-to-br from-red-600 to-amber-500 mx-auto flex items-center justify-center text-white text-3xl shadow-lg shadow-red-500/30">
                    <i class="fa-solid fa-city"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-white">MARIA CITY</h1>
                    <p class="text-sm font-medium tracking-widest text-amber-400 uppercase mt-0.5">Roleplay Portal</p>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">
                    ระบบแชทและรายงานปัญหาของเซิร์ฟเวอร์<br>เข้าสู่ระบบด้วยบัญชี Google เพื่อเริ่มใช้งาน
                </p>
            </div>

            <!-- Divider -->
            <div class="flex items-center gap-3">
                <div class="flex-1 h-px bg-white/8"></div>
                <span class="text-xs text-gray-600">เข้าสู่ระบบ</span>
                <div class="flex-1 h-px bg-white/8"></div>
            </div>

            <!-- Google Sign-In Button -->
            <div class="space-y-3">
                <div id="g_id_onload"
                     data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleCredentialResponse"
                     data-auto_prompt="false">
                </div>
                <div class="gsi-btn-container">
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="filled_black"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="center"
                         data-width="320">
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="loading-spinner" class="flex items-center justify-center gap-3 py-3">
                    <div class="w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-sm text-gray-400">กำลังตรวจสอบสิทธิ์...</span>
                </div>

                <!-- Error Message -->
                <div id="error-msg" class="hidden bg-red-950/50 border border-red-500/30 rounded-xl px-4 py-3 text-xs text-red-400 text-center"></div>
            </div>

            <!-- Features -->
            <div class="grid grid-cols-3 gap-2 pt-1">
                <div class="bg-white/4 rounded-xl p-3 text-center space-y-1.5">
                    <i class="fa-solid fa-robot text-cyan-400 text-lg"></i>
                    <p class="text-[10px] text-gray-400">AI Chat</p>
                </div>
                <div class="bg-white/4 rounded-xl p-3 text-center space-y-1.5">
                    <i class="fa-solid fa-ticket text-amber-400 text-lg"></i>
                    <p class="text-[10px] text-gray-400">Ticket System</p>
                </div>
                <div class="bg-white/4 rounded-xl p-3 text-center space-y-1.5">
                    <i class="fa-solid fa-shield-halved text-red-400 text-lg"></i>
                    <p class="text-[10px] text-gray-400">Admin Panel</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-[11px] text-gray-600 mt-5">Maria City Roleplay © 2026 · Powered by AI</p>
    </div>

    <script>
    async function handleGoogleCredentialResponse(response) {
        const spinner = document.getElementById('loading-spinner');
        const errorMsg = document.getElementById('error-msg');
        const gsiBtn = document.querySelector('.g_id_signin');
        
        spinner.style.display = 'flex';
        if (gsiBtn) gsiBtn.style.display = 'none';
        errorMsg.classList.add('hidden');

        try {
            const res = await fetch('/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'credential=' + encodeURIComponent(response.credential)
            });
            const data = await res.json();
            if (data.success) {
                // Redirect ตาม Role ที่ Backend ส่งมา
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (e) {
            spinner.style.display = 'none';
            if (gsiBtn) gsiBtn.style.display = 'block';
            errorMsg.textContent = '⚠ ' + e.message + ' — กรุณาลองใหม่อีกครั้ง';
            errorMsg.classList.remove('hidden');
        }
    }
    </script>
</body>
</html>
