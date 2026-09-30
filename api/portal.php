<?php
require_once 'config.php';
$user = currentUser() ?: ['uid' => '', 'name' => 'ผู้เล่น', 'email' => '', 'photo' => 'https://placehold.co/40x40/1a1a1a/ffffff?text=U'];
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maria City - AI Chat Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // ================= JS AUTH GUARD =================
        const sessionStr = localStorage.getItem('mc_user_session');
        if (!sessionStr) {
            window.location.replace('/');
        }
        
        document.addEventListener("DOMContentLoaded", () => {
            try {
                const session = JSON.parse(sessionStr || '{}');
                if (session.photo) document.getElementById('ui-avatar').src = session.photo;
                if (session.email) document.getElementById('ui-email').textContent = session.email;
                if (session.name) {
                    document.getElementById('ui-name').textContent = session.name;
                    window.USER_NAME = session.name;
                    window.USER_UID = session.uid;
                }
            } catch(e) {}
        });

        function doLogout() {
            localStorage.clear();
            document.cookie = "mc_auth=; path=/; max-age=0;";
            window.location.href = '/logout';
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #111111; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        .msg-enter { animation: msgIn .25s ease-out; }
        @keyframes msgIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .typing-dot { animation: blink 1.2s infinite; }
        .typing-dot:nth-child(2) { animation-delay:.2s; }
        .typing-dot:nth-child(3) { animation-delay:.4s; }
        @keyframes blink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }
        #upload-preview-area img, #upload-preview-area video { max-height: 200px; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen bg-[#111111] text-gray-100 overflow-hidden">

    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 bg-[#161616] border-r border-white/6 flex flex-col p-3 gap-3 transition-all duration-300 z-20 md:relative absolute inset-y-0 left-0 -translate-x-full md:translate-x-0">
        <div class="flex items-center gap-2.5 px-2 py-1">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-600 to-amber-500 flex items-center justify-center text-white font-bold text-sm shadow">
                <i class="fa-solid fa-city"></i>
            </div>
            <span class="font-bold text-sm text-white tracking-wide">MARIA CITY</span>
        </div>

        <button onclick="startNewChat()" class="flex items-center gap-2.5 w-full bg-white/5 hover:bg-white/10 border border-white/8 rounded-xl px-3 py-2.5 text-xs text-gray-300 transition">
            <i class="fa-solid fa-plus text-amber-400"></i> แชทใหม่
        </button>

        <div class="space-y-0.5">
            <p class="text-[10px] uppercase font-semibold text-gray-600 px-2 py-1.5">เมนู</p>
            <a href="https://discord.gg/mariacity" target="_blank" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-gray-400 hover:text-white hover:bg-white/6 transition">
                <i class="fa-brands fa-discord text-indigo-400 w-4 text-center"></i> Discord เซิร์ฟเวอร์
            </a>
            <button onclick="openRules()" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-gray-400 hover:text-white hover:bg-white/6 transition text-left">
                <i class="fa-solid fa-book text-amber-400 w-4 text-center"></i> กฎระเบียบ
            </button>
            <button onclick="openMyTickets()" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-gray-400 hover:text-white hover:bg-white/6 transition text-left">
                <i class="fa-solid fa-ticket text-green-400 w-4 text-center"></i> Tickets ของฉัน
                <span id="ticket-badge" class="ml-auto bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full hidden">0</span>
            </button>
        </div>

        <div class="mt-auto space-y-2 pt-3 border-t border-white/6">
            <div class="flex items-center gap-2.5 bg-white/5 rounded-xl p-2.5">
                <img id="ui-avatar" src="<?= htmlspecialchars($user['photo']) ?>" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="">
                <div class="overflow-hidden flex-1">
                    <p id="ui-name" class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user['name']) ?></p>
                    <p id="ui-email" class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <button onclick="doLogout()" class="text-gray-500 hover:text-red-400 transition p-1">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Chat Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">

        <!-- Header -->
        <header class="h-13 border-b border-white/6 flex items-center justify-between px-4 bg-[#111]/80 backdrop-blur flex-shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')" class="md:hidden p-2 text-gray-400 hover:text-white">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white text-xs shadow">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <span class="text-sm font-semibold text-white">น้องมารี</span>
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                </div>
            </div>
        </header>

        <!-- Chat Messages -->
        <div id="chat-box" class="flex-1 overflow-y-auto px-4 py-6 space-y-5 flex flex-col items-center">
            <!-- Welcome -->
            <div id="welcome-state" class="my-auto text-center max-w-lg space-y-4 select-none">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 mx-auto flex items-center justify-center text-white text-2xl shadow-lg shadow-blue-500/20">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <h2 class="text-xl font-bold text-white">สวัสดีครับ <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>! 👋</h2>
                <p class="text-sm text-gray-500 leading-relaxed">ผมคือน้องมารี ผู้ช่วย AI ของ Maria City Roleplay<br>สามารถถามเรื่องเซิร์ฟเวอร์ หรือแจ้งปัญหาได้เลยครับ</p>
                <div class="grid grid-cols-2 gap-2 pt-2">
                    <button onclick="quickSend('แมพตอนนี้กี่เปอร์เซ็นต์แล้ว?')" class="p-3 bg-white/4 hover:bg-white/8 border border-white/6 rounded-xl text-xs text-gray-300 text-left transition flex items-center gap-2">
                        <i class="fa-solid fa-map text-amber-400"></i> ความคืบหน้าแมพ
                    </button>
                    <button onclick="quickSend('อยากแจ้งบัคในเกม')" class="p-3 bg-white/4 hover:bg-white/8 border border-white/6 rounded-xl text-xs text-gray-300 text-left transition flex items-center gap-2">
                        <i class="fa-solid fa-bug text-red-400"></i> แจ้งบัค / ปัญหา
                    </button>
                    <button onclick="quickSend('อยากรายงานผู้เล่นที่ใช้โปร')" class="p-3 bg-white/4 hover:bg-white/8 border border-white/6 rounded-xl text-xs text-gray-300 text-left transition flex items-center gap-2">
                        <i class="fa-solid fa-user-shield text-red-400"></i> รายงานผู้เล่น
                    </button>
                    <button onclick="quickSend('วิธีสมัคร Whitelist')" class="p-3 bg-white/4 hover:bg-white/8 border border-white/6 rounded-xl text-xs text-gray-300 text-left transition flex items-center gap-2">
                        <i class="fa-brands fa-discord text-indigo-400"></i> สมัคร Whitelist
                    </button>
                </div>
            </div>
        </div>

        <!-- File Preview Area -->
        <div id="upload-preview-area" class="hidden px-4 pb-2 max-w-3xl mx-auto w-full">
            <div class="bg-white/5 border border-white/8 rounded-xl p-3 flex flex-wrap gap-2" id="preview-container"></div>
        </div>

        <!-- Input Bar -->
        <div class="px-4 pb-4 pt-2 flex-shrink-0 bg-gradient-to-t from-[#111] via-[#111]/90 to-transparent">
            <div class="max-w-3xl mx-auto">
                <div class="bg-[#1e1e1e] border border-white/10 rounded-2xl shadow-xl focus-within:border-white/20 transition">
                    <div class="flex items-center gap-2 px-3 pt-2">
                        <textarea id="user-input" rows="1" placeholder="พิมพ์ข้อความถึงน้องมารี..." autocomplete="off"
                            class="flex-1 bg-transparent resize-none text-sm text-gray-200 py-2 focus:outline-none placeholder-gray-600 max-h-36 leading-relaxed"
                            onkeydown="handleKeyDown(event)" oninput="autoResize(this)"></textarea>
                    </div>
                    <div class="flex items-center justify-between px-3 pb-2.5 pt-1">
                        <div class="flex items-center gap-1">
                            <label for="file-upload" class="cursor-pointer p-2 rounded-lg hover:bg-white/8 text-gray-500 hover:text-gray-300 transition" title="อัปโหลดรูป/วิดีโอหลักฐาน">
                                <i class="fa-solid fa-paperclip text-sm"></i>
                            </label>
                            <input type="file" id="file-upload" class="hidden" accept="image/*,video/*" multiple onchange="handleFileSelect(event)">
                            <label for="image-upload" class="cursor-pointer p-2 rounded-lg hover:bg-white/8 text-gray-500 hover:text-gray-300 transition" title="อัปโหลดรูปภาพ">
                                <i class="fa-solid fa-image text-sm"></i>
                            </label>
                            <input type="file" id="image-upload" class="hidden" accept="image/*" multiple onchange="handleFileSelect(event)">
                        </div>
                        <button id="send-btn" onclick="sendMessage()" class="bg-white hover:bg-gray-200 text-black w-8 h-8 rounded-xl flex items-center justify-center transition shadow font-bold">
                            <i class="fa-solid fa-arrow-up text-xs"></i>
                        </button>
                    </div>
                </div>
                <p class="text-center text-[10px] text-gray-700 mt-2">น้องมารีอาจให้ข้อมูลคลาดเคลื่อนได้ กรุณาตรวจสอบกับทีมงานโดยตรงเสมอ</p>
            </div>
        </div>
    </main>

    <!-- My Tickets Modal -->
    <div id="tickets-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#161616] border border-white/8 max-w-2xl w-full rounded-2xl shadow-2xl flex flex-col h-[min(760px,85vh)]">
            <div class="flex items-center justify-between p-5 border-b border-white/6 flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <button id="ticket-back" onclick="showTicketHistory()" class="hidden text-gray-400 hover:text-white" title="กลับไปยังประวัติ"><i class="fa-solid fa-arrow-left"></i></button>
                    <div class="min-w-0"><h3 id="ticket-modal-title" class="font-bold text-white flex items-center gap-2"><i class="fa-solid fa-ticket text-amber-400"></i> Tickets ของฉัน</h3><p id="ticket-modal-subtitle" class="hidden text-[10px] text-gray-500 mt-1"></p></div>
                </div>
                <button onclick="closeTicketsModal()" class="text-gray-500 hover:text-white p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="tickets-list" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
            <div id="ticket-chat-panel" class="hidden flex-1 min-h-0 flex-col">
                <div id="ticket-chat-messages" class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3"></div>
                <div id="ticket-chat-composer" class="border-t border-white/6 p-3">
                    <div id="ticket-image-preview" class="hidden mb-2 text-xs text-emerald-300"></div>
                    <div class="flex items-end gap-2">
                        <label class="cursor-pointer p-2.5 rounded-lg hover:bg-white/8 text-gray-400 hover:text-white" title="แนบรูปภาพ"><i class="fa-solid fa-image"></i><input id="ticket-image-file" type="file" accept="image/*" class="hidden" onchange="selectTicketImage(this)"></label>
                        <textarea id="ticket-chat-input" rows="1" placeholder="พิมพ์ข้อความถึงทีมงาน..." class="flex-1 bg-[#202020] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-gray-200 focus:outline-none focus:border-emerald-500/50 resize-none"></textarea>
                        <button onclick="sendTicketMessage()" class="bg-emerald-500 hover:bg-emerald-400 text-black rounded-xl w-10 h-10 flex items-center justify-center" title="ส่งข้อความ"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules Modal -->
    <div id="rules-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#161616] border border-white/8 max-w-md w-full rounded-2xl shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-white/6 pb-3">
                <h3 class="font-bold text-white"><i class="fa-solid fa-book text-amber-400 mr-2"></i>กฎระเบียบ Maria City</h3>
                <button onclick="closeRules()" class="text-gray-500 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <ul class="text-xs space-y-2.5 text-gray-300 list-disc list-inside">
                <li>ห้ามใช้โปรแกรมโกง (Cheat / Hack / Mod) ทุกรูปแบบ</li>
                <li>ห้าม RDM / VDM โดยไม่มีเหตุผลทาง RP</li>
                <li>เคารพการสวมบทบาท (RP) ของผู้เล่นทุกคน</li>
                <li>ห้ามพูดจาหยาบคาย / เหยียดหยาม ทั้งใน OOC และ IC</li>
                <li>ห้าม Metagaming และ Powergaming</li>
                <li>ปฏิบัติตามคำสั่งของแอดมินเสมอ</li>
            </ul>
            <button onclick="closeRules()" class="w-full bg-white text-black py-2.5 rounded-xl text-xs font-semibold">รับทราบ</button>
        </div>
    </div>

    <script>
    window.USER_UID = '<?= htmlspecialchars($user['uid']) ?>';
    window.USER_NAME = '<?= htmlspecialchars($user['name']) ?>';
    let pendingFiles = [];
    let mapProgress = 0;

    // ==================== INIT ====================
    (async function init() {
        await fetchMapProgress();
        await loadChatHistory();
    })();

    async function fetchMapProgress() {
        try {
            const res = await fetch('/api-endpoint?action=get_map');
            const data = await res.json();
            mapProgress = data.progress || 0;
        } catch(e) { mapProgress = 0; }
    }

    async function loadChatHistory() {
        try {
            const res = await fetch('/api-endpoint?action=get_logs');
            const logs = await res.json();
            if (logs.length > 0) {
                document.getElementById('welcome-state').remove();
                for (const log of logs) {
                    if (log.type === 'file') {
                        appendFileMessage(log.sender, log.files, false);
                    } else {
                        appendMessage(log.sender, log.text, false);
                    }
                }
            }
        } catch(e) {}
    }

    // ==================== AI RESPONSE LOGIC ====================
    function getAIResponse(text) {
        const lower = text.toLowerCase();

        // ตรวจสอบผู้เล่น / โปร
        const checkKeywords = ['ตรวจสอบ','เช็คคน','เช็คผู้เล่น','คนนี้เล่นโปร','ใช้โปร','hack','cheat','โกง','รายงานผู้เล่น','report'];
        if (checkKeywords.some(k => lower.includes(k))) {
            createTicket('check_player', text);
            return '__TICKET_PLAYER__';
        }

        // แจ้งบัค / ปัญหา / แบน
        const reportKeywords = ['แจ้งบัค','บัค','bug','แบน','ban','ปัญหา','รายงานบัค','error','ข้อผิดพลาด','แจ้งปัญหา'];
        if (reportKeywords.some(k => lower.includes(k))) {
            createTicket('report_bug', text);
            return '__REPORT_TEMPLATE__';
        }

        // ถามแมพ
        if (lower.includes('แมพ') || lower.includes('map') || lower.includes('เปอร์เซ็นต์') || lower.includes('คืบหน้า')) {
            return `ตอนนี้แมพ Maria City อยู่ที่ <strong class="text-amber-400">${mapProgress}%</strong> แล้วครับ! ทีมกำลังพัฒนาอย่างต่อเนื่อง คอยติดตามอัปเดตได้ที่ Discord เลยครับ 🗺️`;
        }

        // Whitelist
        if (lower.includes('whitelist') || lower.includes('สมัคร') || lower.includes('เข้าร่วม')) {
            return `สมัคร Whitelist ได้ที่ <a href="https://discord.gg/mariacity" target="_blank" class="text-indigo-400 underline">Discord ของเรา</a> ครับ 👋 กดที่ช่อง <strong>#สมัคร-whitelist</strong> แล้วปฏิบัติตามขั้นตอนได้เลย`;
        }

        // Discord
        if (lower.includes('discord') || lower.includes('ลิงก์')) {
            return `เข้า Discord ได้เลยครับที่ <a href="https://discord.gg/mariacity" target="_blank" class="text-indigo-400 underline font-semibold">discord.gg/mariacity</a> 💬`;
        }

        // Default
        return `ขอบคุณที่ติดต่อมาครับ! สำหรับเรื่องนี้ผมขอให้ทีมงานช่วยตอบโดยตรงนะครับ สามารถแจ้งในช่อง Discord หรือส่ง Ticket ผ่านระบบนี้ได้เลย 😊`;
    }

    // ==================== TICKET ====================
    async function createTicket(type, message) {
        try {
            await fetch('/api-endpoint?action=create_ticket', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, message })
            });
            checkMyTickets();
        } catch(e) {}
    }

    // ==================== SEND MESSAGE ====================
    window.sendMessage = async function() {
        const inputEl = document.getElementById('user-input');
        const text = inputEl.value.trim();
        
        if (!text && pendingFiles.length === 0) return;
        
        // Hide welcome
        const welcome = document.getElementById('welcome-state');
        if (welcome) welcome.remove();

        // Send files if any
        if (pendingFiles.length > 0) {
            const filesToSend = [...pendingFiles];
            pendingFiles = [];
            clearPreviews();
            await sendFilesMessage(filesToSend, text);
            if (!text) { inputEl.value = ''; autoResize(inputEl); return; }
        }

        if (!text) return;

        inputEl.value = '';
        autoResize(inputEl);

        // User message
        appendMessage('user', text);
        await fetch('/api-endpoint?action=add_log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sender: 'user', text })
        }).catch(()=>{});

        // Typing indicator
        const typingId = showTyping();

        // AI Response
        await new Promise(r => setTimeout(r, 600 + Math.random() * 400));
        removeTyping(typingId);

        const aiReply = getAIResponse(text);
        
        if (aiReply === '__TICKET_PLAYER__') {
            appendMessage('bot', `กำลังส่งเรื่องไปให้ทีมงานตรวจสอบให้ครับ... <span class="text-amber-400">รอสักครู่นะครับ</span> 🔍<br><span class="text-xs text-gray-500 mt-1 block">Ticket ถูกสร้างเรียบร้อยแล้ว ทีมงานจะตอบกลับโดยเร็วที่สุด</span>`);
        } else if (aiReply === '__REPORT_TEMPLATE__') {
            appendMessage('bot', getReportTemplate());
        } else {
            appendMessage('bot', aiReply);
        }

        await fetch('/api-endpoint?action=add_log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sender: 'bot', text: aiReply })
        }).catch(()=>{});
    }

    function getReportTemplate() {
        return `หากคุณพบบัค รายงานผู้เล่น หรือปัญหาอื่นๆ ภายในเกม<br>
        กรุณาแนบข้อมูลต่อไปนี้ให้ครบถ้วน<br><br>
        <div class="bg-white/4 border border-white/8 rounded-xl p-3 space-y-1.5 text-xs mt-2">
            <p><span class="text-amber-400 font-semibold">• Username ผู้เล่น</span> (ชัดเจนเพื่อให้ทีมงานตรวจสอบได้)</p>
            <p><span class="text-amber-400 font-semibold">• ภาพหลักฐานประกอบ</span> เช่น แชท, การกระทำ, บัค หรือข้อความแสดงข้อผิดพลาด <span class="text-red-400">(สำคัญ)</span></p>
            <p><span class="text-amber-400 font-semibold">• คำอธิบาย</span> ว่าเกิดอะไรขึ้น</p>
        </div><br>
        ― สามารถส่งเรื่องส่วนตัวได้ที่ <a href="https://discord.gg/mariacity" target="_blank" class="text-indigo-400 underline">Discord</a><br><br>
        <span class="text-gray-500 text-xs">โปรดงดการแชทข้อความเปล่าๆ โดยไม่ระบุปัญหา เนื่องจากเราจะไม่ตอบกลับ DM ที่ไม่มีรายละเอียดใดๆ เพื่อให้ทีมสามารถจัดการเรื่องสำคัญได้อย่างเหมาะสม</span>`;
    }

    // ==================== UI HELPERS ====================
    function appendMessage(sender, text, animate = true) {
        const box = document.getElementById('chat-box');
        const isBot = sender === 'bot';
        const wrapper = document.createElement('div');
        wrapper.className = `w-full max-w-3xl flex gap-3 ${isBot ? '' : 'justify-end'} ${animate ? 'msg-enter' : ''}`;
        if (isBot) {
            wrapper.innerHTML = `
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex-shrink-0 flex items-center justify-center text-white text-xs shadow">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="flex-1">
                    <p class="text-[11px] font-semibold text-amber-400 mb-1.5">น้องมารี</p>
                    <div class="text-sm text-gray-200 leading-relaxed">${text}</div>
                </div>`;
        } else {
            wrapper.innerHTML = `
                <div class="bg-[#2a2a2a] border border-white/8 rounded-2xl px-4 py-3 text-sm text-gray-100 max-w-lg leading-relaxed">${text}</div>
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-red-600 to-amber-500 flex-shrink-0 flex items-center justify-center text-white text-xs shadow">
                    <i class="fa-solid fa-user"></i>
                </div>`;
        }
        box.appendChild(wrapper);
        box.scrollTop = box.scrollHeight;
    }

    function appendFileMessage(sender, files, animate = true) {
        const box = document.getElementById('chat-box');
        const isBot = sender === 'bot';
        const wrapper = document.createElement('div');
        wrapper.className = `w-full max-w-3xl flex gap-3 ${isBot ? '' : 'justify-end'} ${animate ? 'msg-enter' : ''}`;
        
        let fileHTML = files.map(f => {
            if (f.type === 'image') return `<img src="${f.url}" class="max-h-48 rounded-xl border border-white/10 cursor-pointer" onclick="window.open('${f.url}','_blank')" alt="หลักฐาน">`;
            if (f.type === 'video') return `<video src="${f.url}" controls class="max-h-48 rounded-xl border border-white/10 max-w-xs"></video>`;
            return `<a href="${f.url}" class="text-indigo-400 underline text-xs" target="_blank">${f.name}</a>`;
        }).join('');

        const content = `<div class="flex flex-wrap gap-2">${fileHTML}</div>`;
        
        if (isBot) {
            wrapper.innerHTML = `<div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex-shrink-0 flex items-center justify-center text-white text-xs shadow"><i class="fa-solid fa-robot"></i></div><div class="flex-1">${content}</div>`;
        } else {
            wrapper.innerHTML = `${content}<div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-red-600 to-amber-500 flex-shrink-0 flex items-center justify-center text-white text-xs shadow"><i class="fa-solid fa-user"></i></div>`;
        }
        box.appendChild(wrapper);
        box.scrollTop = box.scrollHeight;
    }

    function showTyping() {
        const box = document.getElementById('chat-box');
        const id = 'typing-' + Date.now();
        const el = document.createElement('div');
        el.id = id;
        el.className = 'w-full max-w-3xl flex gap-3 msg-enter';
        el.innerHTML = `
            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex-shrink-0 flex items-center justify-center text-white text-xs shadow"><i class="fa-solid fa-robot"></i></div>
            <div class="bg-[#1e1e1e] border border-white/8 rounded-2xl px-4 py-3 flex items-center gap-1.5">
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
            </div>`;
        box.appendChild(el);
        box.scrollTop = box.scrollHeight;
        return id;
    }

    function removeTyping(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // ==================== FILE UPLOAD ====================
    function handleFileSelect(event) {
        const files = Array.from(event.target.files);
        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = (e) => {
                pendingFiles.push({ file, dataURL: e.target.result, name: file.name, type: file.type.startsWith('image') ? 'image' : 'video' });
                renderPreview();
            };
            reader.readAsDataURL(file);
        });
        event.target.value = '';
    }

    function renderPreview() {
        const area = document.getElementById('upload-preview-area');
        const container = document.getElementById('preview-container');
        container.innerHTML = '';
        
        if (pendingFiles.length === 0) { area.classList.add('hidden'); return; }
        area.classList.remove('hidden');
        
        pendingFiles.forEach((f, idx) => {
            const div = document.createElement('div');
            div.className = 'relative group';
            if (f.type === 'image') {
                div.innerHTML = `<img src="${f.dataURL}" class="h-24 rounded-lg border border-white/10 object-cover">`;
            } else {
                div.innerHTML = `<video src="${f.dataURL}" class="h-24 rounded-lg border border-white/10" controls></video>`;
            }
            const removeBtn = document.createElement('button');
            removeBtn.className = 'absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition';
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.onclick = () => { pendingFiles.splice(idx, 1); renderPreview(); };
            div.appendChild(removeBtn);
            container.appendChild(div);
        });
    }

    function clearPreviews() {
        pendingFiles = [];
        document.getElementById('upload-preview-area').classList.add('hidden');
        document.getElementById('preview-container').innerHTML = '';
    }

    async function sendFilesMessage(files, caption) {
        // แสดงตัวอย่างไฟล์ในแชท (ใช้ dataURL สำหรับ Preview)
        const fileObjs = files.map(f => ({ url: f.dataURL, type: f.type, name: f.name }));
        appendFileMessage('user', fileObjs);
        if (caption) appendMessage('user', caption);

        const typingId = showTyping();
        await new Promise(r => setTimeout(r, 800));
        removeTyping(typingId);
        appendMessage('bot', `ได้รับหลักฐาน ${files.length} ไฟล์เรียบร้อยแล้วครับ 📎 ทีมงานจะตรวจสอบโดยเร็วที่สุด หากต้องการส่ง Ticket อย่างเป็นทางการ กรุณาอธิบายเพิ่มเติมในช่องแชทด้วยนะครับ`);
    }

    // ==================== TICKETS ====================
    async function checkMyTickets() {
        try {
            const res = await fetch('/api-endpoint?action=my_tickets');
            const data = await res.json();
            const replied = (data.tickets || []).filter(t => t.status === 'replied').length;
            const badge = document.getElementById('ticket-badge');
            if (replied > 0) { badge.textContent = replied; badge.classList.remove('hidden'); }
            else badge.classList.add('hidden');
        } catch(e) {}
    }

    let activeUserTicketId = null;
    let selectedTicketImage = '';

    async function loadMyTickets() {
        const res = await fetch('/api-endpoint?action=my_tickets');
        const data = await res.json();
        return data.tickets || [];
    }

    async function openMyTickets() {
        document.getElementById('tickets-modal').classList.remove('hidden');
        activeUserTicketId = null;
        showTicketHistory();
    }

    async function showTicketHistory() {
        activeUserTicketId = null;
        document.getElementById('ticket-back').classList.add('hidden');
        document.getElementById('ticket-modal-title').innerHTML = '<i class="fa-solid fa-ticket text-amber-400"></i> Tickets ของฉัน';
        document.getElementById('ticket-modal-subtitle').classList.add('hidden');
        document.getElementById('ticket-chat-panel').classList.add('hidden');
        const list = document.getElementById('tickets-list');
        list.classList.remove('hidden');
        list.innerHTML = '<div class="text-center text-gray-500 text-sm py-8">กำลังโหลด...</div>';
        try {
            const tickets = await loadMyTickets();
            if (!tickets.length) {
                list.innerHTML = '<div class="text-center text-gray-600 text-sm py-10"><i class="fa-solid fa-inbox text-3xl mb-3 block"></i>ยังไม่มี Ticket</div>';
                return;
            }
            list.innerHTML = '';
            const statusMap = { pending: ['text-amber-400', 'รอตรวจสอบ'], replied: ['text-emerald-400', 'มีการตอบกลับ'], resolved: ['text-emerald-400', 'ตรวจสอบสำเร็จ'], unresolved: ['text-red-400', 'ตรวจสอบไม่สำเร็จ'], closed: ['text-gray-500', 'ปิดแล้ว'] };
            const typeMap = { check_player: 'ตรวจสอบผู้เล่น', report_bug: 'แจ้งบัค', general: 'ทั่วไป' };
            tickets.sort((a, b) => (b.updated_at || b.created_at || 0) - (a.updated_at || a.created_at || 0)).forEach(ticket => {
                const [statusColor, statusText] = statusMap[ticket.status] || ['text-gray-400', ticket.status];
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'w-full text-left bg-white/4 border border-white/8 hover:border-emerald-500/30 rounded-xl p-4 space-y-2 transition';
                const heading = document.createElement('div');
                heading.className = 'flex items-center justify-between gap-3 text-xs';
                const title = document.createElement('span');
                title.className = 'font-semibold text-gray-300 truncate';
                title.textContent = `${ticket.id} · ${typeMap[ticket.type] || ticket.type}`;
                const status = document.createElement('span');
                status.className = `${statusColor} font-semibold whitespace-nowrap`;
                status.textContent = statusText;
                heading.append(title, status);
                const summary = document.createElement('p');
                summary.className = 'text-sm text-gray-300 leading-relaxed truncate';
                summary.textContent = ticket.last_msg || ticket.message || '[รูปภาพ]';
                const stamp = document.createElement('p');
                stamp.className = 'text-[10px] text-gray-600';
                stamp.textContent = new Date((ticket.updated_at || ticket.created_at) * 1000).toLocaleString('th-TH');
                card.append(heading, summary, stamp);
                card.onclick = () => openUserTicket(ticket.id);
                list.appendChild(card);
            });
        } catch(e) {
            list.innerHTML = '<div class="text-center text-red-400 text-sm py-8">เกิดข้อผิดพลาด</div>';
        }
    }

    function legacyTicketMessages(ticket) {
        if (ticket.messages && ticket.messages.length) return ticket.messages;
        const messages = [{ role: 'user', sender_name: USER_NAME, text: ticket.message || '', image: '', ts: (ticket.created_at || 0) * 1000 }];
        if (ticket.admin_reply) messages.push({ role: 'admin', sender_name: 'ทีมงาน', text: ticket.admin_reply, image: '', ts: (ticket.updated_at || ticket.created_at || 0) * 1000 });
        return messages;
    }

    function renderUserTicketMessages(ticket) {
        const box = document.getElementById('ticket-chat-messages');
        box.innerHTML = '';
        legacyTicketMessages(ticket).forEach(message => {
            const system = message.role === 'system';
            const mine = message.role === 'user';
            const row = document.createElement('div');
            row.className = `flex ${system ? 'justify-center' : mine ? 'justify-end' : 'justify-start'}`;
            const bubble = document.createElement('div');
            bubble.className = system ? 'max-w-[90%] text-center text-[11px] text-gray-500 px-3 py-1' : `max-w-[85%] rounded-2xl px-3.5 py-2.5 ${mine ? 'bg-emerald-500/15 border border-emerald-500/20 text-gray-100' : 'bg-white/6 border border-white/8 text-gray-200'}`;
            if (!system) {
                const label = document.createElement('p');
                label.className = `text-[10px] font-semibold mb-1 ${mine ? 'text-emerald-300' : 'text-amber-300'}`;
                label.textContent = mine ? 'คุณ' : (message.sender_name || 'ทีมงาน');
                bubble.appendChild(label);
            }
            if (message.text) {
                const text = document.createElement('p');
                text.className = 'text-sm whitespace-pre-wrap break-words';
                text.textContent = message.text;
                bubble.appendChild(text);
            }
            if (message.image) {
                const image = document.createElement('img');
                image.src = message.image;
                image.alt = 'รูปภาพที่แนบในแชท';
                image.className = 'mt-2 max-h-56 max-w-full rounded-lg cursor-pointer';
                image.onclick = () => window.open(message.image, '_blank', 'noopener');
                bubble.appendChild(image);
            }
            const stamp = document.createElement('p');
            stamp.className = 'text-[9px] text-gray-500 mt-1 text-right';
            stamp.textContent = new Date(message.ts || 0).toLocaleString('th-TH');
            bubble.appendChild(stamp);
            row.appendChild(bubble);
            box.appendChild(row);
        });
        box.scrollTop = box.scrollHeight;
    }

    async function openUserTicket(id) {
        const tickets = await loadMyTickets();
        const ticket = tickets.find(item => item.id === id);
        if (!ticket) return;
        activeUserTicketId = id;
        document.getElementById('tickets-list').classList.add('hidden');
        document.getElementById('ticket-chat-panel').classList.remove('hidden');
        document.getElementById('ticket-chat-panel').classList.add('flex');
        document.getElementById('ticket-back').classList.remove('hidden');
        document.getElementById('ticket-modal-title').textContent = ticket.id;
        const labels = { pending: 'รอทีมงานตอบกลับ', replied: 'ทีมงานตอบกลับแล้ว', resolved: 'ตรวจสอบสำเร็จ', unresolved: 'ตรวจสอบไม่สำเร็จ', closed: 'ปิด Ticket แล้ว' };
        const subtitle = document.getElementById('ticket-modal-subtitle');
        subtitle.textContent = labels[ticket.status] || ticket.status;
        subtitle.classList.remove('hidden');
        renderUserTicketMessages(ticket);
        const composer = document.getElementById('ticket-chat-composer');
        composer.classList.toggle('hidden', ticket.status === 'closed');
    }

    function selectTicketImage(input) {
        const file = input.files && input.files[0];
        input.value = '';
        if (!file) return;
        if (file.size > 1572864) { alert('รูปภาพต้องมีขนาดไม่เกิน 1.5 MB'); return; }
        const reader = new FileReader();
        reader.onload = () => {
            selectedTicketImage = reader.result;
            const preview = document.getElementById('ticket-image-preview');
            preview.textContent = `แนบรูปแล้ว: ${file.name} · กดส่งเพื่อส่งรูป`;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    async function sendTicketMessage() {
        if (!activeUserTicketId) return;
        const input = document.getElementById('ticket-chat-input');
        const text = input.value.trim();
        if (!text && !selectedTicketImage) return;
        try {
            const response = await fetch('/api-endpoint?action=send_ticket_message', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id: activeUserTicketId, text, image: selectedTicketImage })
            });
            const data = await response.json();
            if (!response.ok || !data.success) { alert(data.message || 'ส่งข้อความไม่สำเร็จ'); return; }
            input.value = '';
            selectedTicketImage = '';
            document.getElementById('ticket-image-preview').classList.add('hidden');
            await openUserTicket(activeUserTicketId);
            checkMyTickets();
        } catch(e) { alert('เกิดข้อผิดพลาดในการส่งข้อความ'); }
    }

    document.getElementById('ticket-chat-input').addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendTicketMessage(); }
    });

    function closeTicketsModal() { document.getElementById('tickets-modal').classList.add('hidden'); activeUserTicketId = null; }
    setInterval(() => {
        if (activeUserTicketId && !document.getElementById('tickets-modal').classList.contains('hidden')) openUserTicket(activeUserTicketId);
    }, 8000);
    function openRules() { document.getElementById('rules-modal').classList.remove('hidden'); }
    function closeRules() { document.getElementById('rules-modal').classList.add('hidden'); }

    function startNewChat() {
        const box = document.getElementById('chat-box');
        box.innerHTML = '';
        const welcome = document.createElement('div');
        welcome.id = 'welcome-state';
        welcome.className = 'my-auto text-center max-w-lg space-y-4 select-none';
        welcome.innerHTML = `
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-600 mx-auto flex items-center justify-center text-white text-2xl shadow-lg shadow-blue-500/20"><i class="fa-solid fa-robot"></i></div>
            <h2 class="text-xl font-bold text-white">สวัสดีครับ ${USER_NAME.split(' ')[0]}! 👋</h2>
            <p class="text-sm text-gray-500">จะให้ช่วยอะไรครับ?</p>`;
        box.appendChild(welcome);
    }

    function quickSend(text) { document.getElementById('user-input').value = text; sendMessage(); }
    function handleKeyDown(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }
    function autoResize(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 144) + 'px'; }

    checkMyTickets();
    </script>
</body>
</html>
