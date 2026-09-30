<?php
require_once 'config.php';

// COOP Header: อนุญาตให้ Google One Tap Popup ทำงานได้
// ต้องใช้ same-origin-allow-popups ไม่งั้น postMessage ถูกบล็อก
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');

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
    function resetLoginState() {
        const spinner = document.getElementById('loading-spinner');
        const gsiBtn  = document.querySelector('.g_id_signin');
        if (spinner) spinner.style.display = 'none';
        if (gsiBtn)  gsiBtn.style.display  = 'block';
    }

    function showError(msg) {
        resetLoginState();
        const el = document.getElementById('error-msg');
        el.textContent = '⚠ ' + msg;
        el.classList.remove('hidden');
    }

    function saveToLocalStorage(data) {
        try {
            // บันทึก user data ลง localStorage เพื่อให้หน้าอื่น verify ได้ทันที
            localStorage.setItem('mc_user_session', JSON.stringify({
                uid:   data.uid   || '',
                email: data.email || '',
                name:  data.name  || '',
                photo: data.photo || '',
                role:  data.role  || 'user',
                ts:    Date.now(),
            }));
            localStorage.setItem('mc_user_role',  data.role  || 'user');
            localStorage.setItem('mc_user_email', data.email || '');
        } catch(e) {
            console.warn('[Login] localStorage save failed:', e);
        }
    }

    function doRedirect(url) {
        const spinner = document.getElementById('loading-spinner');
        if (spinner) {
            spinner.innerHTML = '<div class="w-5 h-5 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></div>'
                              + '<span class="text-sm text-emerald-400">กำลังเข้าสู่ระบบ...</span>';
        }
        // setTimeout(0) = break ออกจาก Google One Tap postMessage callback ก่อน
        // ป้องกัน COOP บล็อก navigation
        setTimeout(function() {
            window.top.location.href = url;
        }, 0);
    }

    async function handleGoogleCredentialResponse(response) {
        const spinner  = document.getElementById('loading-spinner');
        const gsiBtn   = document.querySelector('.g_id_signin');
        const errorMsg = document.getElementById('error-msg');

        if (spinner) spinner.style.display = 'flex';
        if (gsiBtn)  gsiBtn.style.display  = 'none';
        errorMsg.classList.add('hidden');

        const controller = new AbortController();
        const timeoutId  = setTimeout(() => controller.abort(), 8000);

        try {
            const res = await fetch('/login', {
                method:  'POST',
                signal:  controller.signal,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'credential=' + encodeURIComponent(response.credential)
            });
            clearTimeout(timeoutId);

            const rawText = await res.text();
            console.log('[Login] HTTP', res.status, '| raw:', rawText.substring(0, 300));

            let data;
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                console.error('[Login] NOT valid JSON:', rawText.substring(0, 500));
                throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (ไม่ใช่ JSON)');
            }

            if (!data.success) {
                throw new Error(data.message || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์');
            }

            // 1. บันทึกข้อมูลลง localStorage ก่อน redirect
            saveToLocalStorage(data);

            const redirectUrl = data.redirect_url || data.redirect
                             || (data.role === 'admin' ? '/admin' : '/portal');

            console.log('[Login] ✓ Role:', data.role, '→ redirecting to:', redirectUrl);

            // 2. Redirect แบบ COOP-safe
            doRedirect(redirectUrl);

        } catch (err) {
            clearTimeout(timeoutId);
            if (err.name === 'AbortError') {
                showError('หมดเวลาเชื่อมต่อ (8s) — กรุณาลองใหม่');
            } else {
                showError(err.message);
            }
            console.error('[Login] Error:', err);
        }
    }
    </script>
</body>
</html>

        if (spinner) spinner.style.display = 'none';
        if (gsiBtn)  gsiBtn.style.display  = 'block';
    }

    function showError(msg) {
        resetLoginState();
        const el = document.getElementById('error-msg');
        el.textContent = '⚠ ' + msg;
        el.classList.remove('hidden');
    }

    /**
     * Redirect หลัง Login สำเร็จ
     * ใช้ setTimeout(0) เพื่อ break ออกจาก Google One Tap postMessage callback
     * ซึ่งอยู่ใน cross-origin context และถูก COOP บล็อกถ้าเรียก location โดยตรง
     */
    function doRedirect(url) {
        // ซ่อน spinner ก่อน
        const spinner = document.getElementById('loading-spinner');
        if (spinner) {
            spinner.innerHTML = '<div class="w-5 h-5 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></div><span class="text-sm text-emerald-400">กำลังเข้าสู่ระบบ...</span>';
        }
        // setTimeout(0) = defer ออกจาก Google callback frame ก่อน
        // จากนั้น top-level navigation จะทำงานปกติโดยไม่โดน COOP บล็อก
        setTimeout(function() {
            window.top.location.href = url;
        }, 0);
    }

    async function handleGoogleCredentialResponse(response) {
        const spinner  = document.getElementById('loading-spinner');
        const gsiBtn   = document.querySelector('.g_id_signin');
        const errorMsg = document.getElementById('error-msg');

        if (spinner) spinner.style.display = 'flex';
        if (gsiBtn)  gsiBtn.style.display  = 'none';
        errorMsg.classList.add('hidden');

        // Timeout 8 วินาที — ป้องกันค้าง
        const controller = new AbortController();
        const timeoutId  = setTimeout(() => controller.abort(), 8000);

        try {
            const res = await fetch('/login', {
                method:  'POST',
                signal:  controller.signal,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'credential=' + encodeURIComponent(response.credential)
            });
            clearTimeout(timeoutId);

            const rawText = await res.text();
            console.log('[Login] HTTP', res.status, '| raw:', rawText.substring(0, 300));

            let data;
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                console.error('[Login] NOT valid JSON:', rawText.substring(0, 500));
                throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (ไม่ใช่ JSON)');
            }

            if (!data.success) {
                throw new Error(data.message || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์');
            }

            const redirectUrl = data.redirect_url || data.redirect || (data.role === 'admin' ? '/admin' : '/portal');
            console.log('[Login] ✓ Role:', data.role, '→ redirecting to:', redirectUrl);

            // Redirect แบบ COOP-safe
            doRedirect(redirectUrl);

        } catch (err) {
            clearTimeout(timeoutId);
            if (err.name === 'AbortError') {
                showError('หมดเวลาเชื่อมต่อ (8s) — กรุณาลองใหม่');
            } else {
                showError(err.message);
            }
            console.error('[Login] Error:', err);
        }
    }
    </script>
</body>
</html>

