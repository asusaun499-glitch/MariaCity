<?php
require_once 'config.php';
$isLoggedIn = isset($_SESSION['user']);
$user = $isLoggedIn ? $_SESSION['user'] : null;
$isDev = isDeveloper();
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maria City Roleplay - Secure AI Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        chatgptbg: '#171717',
                        chatgptsidebar: '#212121',
                        chatgptinput: '#2f2f2f',
                        bordercol: '#2f2f2f',
                        accentred: '#e53e3e',
                        accentgold: '#d69e2e',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #171717; color: #ececec; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #171717; }
        ::-webkit-scrollbar-thumb { background: #383838; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #505050; }
    </style>
</head>
<body class="min-h-screen flex bg-chatgptbg text-gray-100 overflow-hidden">

    <?php if (!$isLoggedIn): ?>
    <!-- Login Screen -->
    <div id="login-overlay" class="fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-chatgptsidebar border border-bordercol max-w-md w-full rounded-2xl p-8 shadow-2xl text-center space-y-6">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-red-600 to-amber-600 mx-auto flex items-center justify-center text-white text-2xl shadow-lg">
                <i class="fa-solid fa-city"></i>
            </div>
            <div class="space-y-2">
                <h2 class="text-xl font-bold text-white">Maria City Roleplay Portal</h2>
                <p class="text-xs text-gray-400">กรุณาเข้าสู่ระบบด้วยบัญชี Google เพื่อเข้าใช้งานระบบแชทและซิงค์ข้อมูลส่วนตัวบนเซิร์ฟเวอร์</p>
            </div>
            
            <div class="flex justify-center mt-4">
                <div id="g_id_onload"
                     data-client_id="<?= htmlspecialchars($GOOGLE_CLIENT_ID) ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleCredentialResponse"
                     data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="rectangular"
                     data-theme="outline"
                     data-text="signin_with"
                     data-size="large"
                     data-logo_alignment="left">
                </div>
            </div>
            <div class="text-[10px] text-gray-500 mt-2">
                <p>หมายเหตุ: ต้องตั้งค่า Google Client ID ใน `config.php` และรันบนเซิร์ฟเวอร์ (เช่น http://localhost) จึงจะล็อกอินได้</p>
            </div>
        </div>
    </div>
    
    <script>
    function handleGoogleCredentialResponse(response) {
        // ส่ง JWT ไปยัง login.php
        fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'credential=' + encodeURIComponent(response.credential)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('เกิดข้อผิดพลาดในการเข้าสู่ระบบ');
            }
        });
    }
    </script>
    <?php else: ?>

    <aside id="sidebar" class="w-64 bg-chatgptsidebar border-r border-bordercol flex flex-col justify-between p-3 select-none transition-all duration-300 z-20 md:relative absolute inset-y-0 left-0 -translate-x-full md:translate-x-0">
        <div class="space-y-4">
            <div class="flex items-center justify-between px-2 py-1">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-600 to-amber-600 flex items-center justify-center text-white font-bold text-sm shadow">
                        <i class="fa-solid fa-city"></i>
                    </div>
                    <span class="font-bold text-sm tracking-wide text-white">MARIA CITY</span>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white p-1.5 rounded-lg hover:bg-gray-800">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <button onclick="clearChat()" class="w-full bg-transparent hover:bg-[#2f2f2f] border border-bordercol text-gray-200 text-xs font-medium py-2.5 px-3 rounded-xl transition flex items-center gap-2.5">
                <i class="fa-solid fa-plus text-amber-400"></i> แชทใหม่กับน้องมารี
            </button>

            <div class="space-y-1 pt-2">
                <div class="text-[10px] font-semibold text-gray-500 uppercase px-2 pb-1">เมนูหลัก</div>
                <a href="https://discord.gg/mariacity" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs text-gray-300 hover:bg-[#2f2f2f] hover:text-white transition">
                    <i class="fa-brands fa-discord text-indigo-400 w-4"></i> Discord เซิร์ฟเวอร์
                </a>
                <button onclick="openRulesModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs text-gray-300 hover:bg-[#2f2f2f] hover:text-white transition text-left">
                    <i class="fa-solid fa-book text-amber-400 w-4"></i> กฎระเบียบเมือง
                </button>
            </div>
        </div>

        <div class="space-y-3 pt-4 border-t border-bordercol">
            <div class="bg-[#1a1a1a] border border-bordercol rounded-xl p-3 space-y-2">
                <div class="flex justify-between items-center text-[11px]">
                    <span class="text-gray-400 flex items-center gap-1.5"><i class="fa-solid fa-map text-amber-500"></i> ความคืบหน้าแมพ</span>
                    <span id="sidebar-progress-text" class="text-amber-400 font-bold">0%</span>
                </div>
                <div class="w-full bg-gray-800 rounded-full h-1.5 overflow-hidden">
                    <div id="sidebar-progress-fill" class="bg-gradient-to-r from-red-600 to-amber-500 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>

            <?php if ($isDev): ?>
            <div id="admin-panel-container" class="bg-red-950/20 border border-red-500/30 rounded-xl p-3 space-y-2.5">
                <div class="flex items-center justify-between text-xs text-red-400 font-semibold">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-shield-halved"></i> ผู้พัฒนา (Developer)</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[10px] text-gray-400">อัปเดตเปอร์เซ็นต์แมพ (0-100)</label>
                    <div class="flex gap-1.5">
                        <input type="number" id="admin-map-input" min="0" max="100" value="0"
                            class="w-full bg-chatgptbg border border-bordercol rounded-lg px-2.5 py-1 text-xs text-gray-200 focus:outline-none focus:border-amber-500">
                        <button onclick="adminUpdateProgress()" class="bg-amber-600 hover:bg-amber-500 text-white px-3 py-1 rounded-lg text-xs font-semibold transition">
                            บันทึก
                        </button>
                    </div>
                </div>
                <button onclick="openTrainingModal()" class="w-full bg-indigo-600/80 hover:bg-indigo-600 text-white py-2 px-2.5 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-2 shadow">
                    <i class="fa-solid fa-brain text-amber-300"></i> จัดการ Log เทรน AI
                </button>
            </div>
            <?php endif; ?>

            <div class="flex items-center justify-between bg-chatgptinput border border-bordercol rounded-xl p-2.5">
                <div class="flex items-center gap-2 overflow-hidden">
                    <img id="user-avatar" src="<?= htmlspecialchars($user['photo']) ?>" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="Avatar">
                    <div class="overflow-hidden">
                        <div id="user-name-display" class="text-xs font-semibold text-gray-200 truncate"><?= htmlspecialchars($user['name']) ?></div>
                        <div id="user-email-display" class="text-[10px] text-gray-400 truncate"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <a href="logout.php" class="text-gray-400 hover:text-red-400 p-1.5 rounded-lg hover:bg-gray-800 transition" title="ออกจากระบบ">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                </a>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen bg-chatgptbg relative overflow-hidden">
        
        <header class="h-14 border-b border-bordercol flex items-center justify-between px-4 md:px-6 bg-chatgptbg/80 backdrop-blur z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-300 hover:text-white p-2 rounded-lg hover:bg-chatgptinput">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="flex items-center gap-2">
                    <h1 class="font-bold text-sm tracking-wide text-white flex items-center gap-2">
                        MARIA CITY <span class="text-[10px] px-2 py-0.5 rounded bg-red-500/20 text-red-400 border border-red-500/30">SECURE AI PORTAL</span>
                    </h1>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> PHP Backend Sync
                </div>
            </div>
        </header>

        <div id="chat-container" class="flex-1 overflow-y-auto px-4 py-6 space-y-6 flex flex-col items-center scroll-smooth">
            <div id="welcome-state" class="my-auto text-center space-y-4 max-w-lg mx-auto px-4 select-none">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 mx-auto flex items-center justify-center text-white text-2xl shadow-lg shadow-blue-500/20">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <h2 class="text-xl md:text-2xl font-bold text-white">ยินดีต้อนรับสู่ Maria City ครับ! 🌟</h2>
                <p class="text-xs md:text-sm text-gray-400 leading-relaxed">
                    สอบถามข้อมูลแมพ, กฎระเบียบเมือง, อาชีพ, หรือพูดคุยกับ <span class="text-amber-400 font-semibold">"น้องมารี"</span> ประวัติการสนทนาของคุณถูกบันทึกไว้อย่างปลอดภัยบนเซิร์ฟเวอร์
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-3">
                    <button onclick="sendQuickPrompt('แมพตอนนี้กี่เปอร์เซ็นต์แล้ว')" class="p-3 bg-chatgptinput hover:bg-[#383838] border border-bordercol rounded-xl text-xs text-gray-300 text-left transition flex items-center gap-2.5">
                        <i class="fa-solid fa-map text-amber-400"></i> แมพตอนนี้กี่เปอร์เซ็นต์แล้ว?
                    </button>
                    <button onclick="sendQuickPrompt('ขอลิงก์ Discord และวิธี Whitelist หน่อย')" class="p-3 bg-chatgptinput hover:bg-[#383838] border border-bordercol rounded-xl text-xs text-gray-300 text-left transition flex items-center gap-2.5">
                        <i class="fa-brands fa-discord text-indigo-400"></i> วิธีสมัคร Whitelist
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4 bg-gradient-to-t from-chatgptbg via-chatgptbg/90 to-transparent">
            <div class="max-w-3xl mx-auto">
                <form id="chat-form" onsubmit="handleSendMessage(event)" class="relative bg-chatgptinput border border-bordercol rounded-2xl p-2.5 shadow-2xl focus-within:border-gray-500 transition">
                    <div class="flex items-center gap-2">
                        <button type="button" class="text-gray-400 hover:text-white p-2 rounded-xl hover:bg-[#383838] transition">
                            <i class="fa-solid fa-plus text-sm"></i>
                        </button>
                        <input type="text" id="user-input" placeholder="พิมพ์ข้อความหาน้องมารี..." autocomplete="off"
                            class="flex-1 bg-transparent border-none px-2 py-1 text-sm text-gray-200 focus:outline-none placeholder-gray-500">
                        <button type="submit" id="send-button"
                            class="bg-white hover:bg-gray-200 text-black font-semibold w-9 h-9 rounded-xl transition flex items-center justify-center shadow">
                            <i class="fa-solid fa-arrow-up text-xs"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Modals (Training and Rules) -->
    <div id="training-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-chatgptsidebar border border-bordercol max-w-2xl w-full rounded-2xl p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between border-b border-bordercol pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-base">แดชบอร์ดเทรนด์ข้อมูลแชท</h3>
                        <p class="text-[11px] text-gray-400">ตรวจสอบคำถามและข้อมูลที่ผู้เล่นสอบถาม</p>
                    </div>
                </div>
                <button onclick="closeTrainingModal()" class="text-gray-400 hover:text-white p-2"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto space-y-2 pr-1 border border-bordercol rounded-xl p-3 bg-chatgptbg">
                <div id="training-logs-list" class="space-y-2 pt-1"></div>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-bordercol">
                <button onclick="clearTrainingLogs()" class="bg-red-600 hover:bg-red-500 text-white font-semibold py-2 px-4 rounded-xl text-xs transition">
                    ล้างข้อมูล
                </button>
                <button onclick="closeTrainingModal()" class="bg-chatgptinput hover:bg-[#383838] text-white font-semibold py-2 px-4 rounded-xl text-xs transition border border-bordercol">
                    ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>
    
    <div id="rules-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-chatgptsidebar border border-bordercol max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-bordercol pb-3">
                <h3 class="font-bold text-white text-base">กฎระเบียบ Maria City</h3>
                <button onclick="closeRulesModal()" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <ul class="text-xs space-y-2.5 text-gray-300">
                <li>ห้ามใช้โปรแกรมโกงหรือมาโครทุกชนิด</li>
                <li>เคารพการสวมบทบาท (RP)</li>
                <li>ห้าม DM ทิ้งทวนหรือก่อกวนนอกเกม (OOC)</li>
            </ul>
            <button onclick="closeRulesModal()" class="w-full bg-white text-black py-2.5 rounded-xl text-xs">รับทราบ</button>
        </div>
    </div>

    <script>
        let mapProgress = 0;

        function fetchMapProgress() {
            fetch('api.php?action=get_map')
                .then(res => res.json())
                .then(data => {
                    mapProgress = data.progress || 0;
                    updateMapProgressUI();
                });
        }

        function updateMapProgressUI() {
            document.getElementById('sidebar-progress-text').textContent = mapProgress + '%';
            document.getElementById('sidebar-progress-fill').style.width = mapProgress + '%';
            if (document.getElementById('admin-map-input')) {
                document.getElementById('admin-map-input').value = mapProgress;
            }
        }

        function adminUpdateProgress() {
            const val = parseInt(document.getElementById('admin-map-input').value);
            fetch('api.php?action=update_map', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ progress: val })
            }).then(res => res.json()).then(data => {
                if(data.success) {
                    mapProgress = data.progress;
                    updateMapProgressUI();
                    alert('อัปเดตแมพสำเร็จ');
                } else {
                    alert('ไม่สามารถอัปเดตได้');
                }
            });
        }
        
        async function loadChatLogs() {
            const res = await fetch('api.php?action=get_logs');
            const logs = await res.json();
            if(logs.length > 0) {
                document.getElementById('welcome-state').style.display = 'none';
                logs.forEach(log => appendMessageRaw(log.sender, log.text));
            }
        }

        function appendMessageRaw(sender, text) {
            document.getElementById('welcome-state').style.display = 'none';
            const container = document.getElementById('chat-container');
            const isBot = sender === 'bot';
            const wrapper = document.createElement('div');
            wrapper.className = `w-full max-w-3xl flex gap-4 ${isBot ? 'bg-chatgptinput/40 -mx-4 px-4 py-4 rounded-2xl border border-bordercol/50' : 'justify-end'}`;
            
            if (isBot) {
                wrapper.innerHTML = `
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex-shrink-0 flex items-center justify-center text-white text-xs font-bold shadow">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="flex-1 space-y-1 text-sm text-gray-200 leading-relaxed">
                        <div class="font-semibold text-xs text-amber-400 mb-1">น้องมารี (Maria City Bot)</div>
                        <div>${text}</div>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `
                    <div class="bg-chatgptinput border border-bordercol rounded-2xl px-4 py-3 text-sm text-gray-100 max-w-xl leading-relaxed shadow-sm">
                        ${text}
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-red-600 to-amber-600 flex-shrink-0 flex items-center justify-center text-white text-xs font-bold shadow">
                        <i class="fa-solid fa-user"></i>
                    </div>
                `;
            }
            container.appendChild(wrapper);
            container.scrollTop = container.scrollHeight;
        }

        async function saveLog(sender, text) {
            await fetch('api.php?action=add_log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sender, text })
            });
        }

        window.handleSendMessage = async function(event) {
            event.preventDefault();
            const inputEl = document.getElementById('user-input');
            const text = inputEl.value.trim();
            if (!text) return;

            appendMessageRaw('user', text);
            await saveLog('user', text);
            inputEl.value = '';

            setTimeout(async () => {
                let reply = '';
                const msg = text.toLowerCase();
                if (msg.includes('เปอร์เซ็นต์') || msg.includes('แมพ')) {
                    reply = `ตอนนี้น้องมารีเช็คสถานะแมพอยู่ที่ ${mapProgress}% แล้วค่ะ!`;
                } else if (msg.includes('discord') || msg.includes('whitelist')) {
                    reply = `สามารถเข้า Discord ได้ที่ลิงก์ด้านซ้ายเลยค่ะ`;
                } else {
                    reply = `บันทึกคำถามนี้เข้าระบบแล้วค่ะ หากมีเรื่องเร่งด่วนเปิด Ticket ใน Discord ได้เลยนะคะ 😊`;
                }
                
                appendMessageRaw('bot', reply);
                await saveLog('bot', reply);
            }, 600);
        }

        window.sendQuickPrompt = function(text) {
            document.getElementById('user-input').value = text;
            window.handleSendMessage(new Event('submit'));
        }

        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('-translate-x-full'); }
        function openRulesModal() { document.getElementById('rules-modal').classList.remove('hidden'); }
        function closeRulesModal() { document.getElementById('rules-modal').classList.add('hidden'); }
        function openTrainingModal() { 
            fetch('api.php?action=get_logs').then(res => res.json()).then(logs => {
                const list = document.getElementById('training-logs-list');
                list.innerHTML = '';
                logs.forEach(log => {
                    list.innerHTML += `<div class="p-2 border-b border-bordercol text-xs text-gray-300"><b>${log.sender}:</b> ${log.text}</div>`;
                });
                document.getElementById('training-modal').classList.remove('hidden'); 
            });
        }
        function closeTrainingModal() { document.getElementById('training-modal').classList.add('hidden'); }
        
        async function clearTrainingLogs() {
            await fetch('api.php?action=clear_logs');
            alert('ล้างสำเร็จ');
            closeTrainingModal();
        }
        
        async function clearChat() {
            await fetch('api.php?action=clear_logs');
            window.location.reload();
        }

        // Init App Data
        fetchMapProgress();
        loadChatLogs();
    </script>
    <?php endif; ?>
</body>
</html>
