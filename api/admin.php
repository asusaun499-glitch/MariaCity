<?php
require_once 'config.php';
// Fallback ว่างเปล่า กรณี PHP อ่าน Cookie ไม่เจอ (Vercel)
// จะให้ JS ดึงข้อมูลจาก localStorage มาแปะทับอีกครั้ง
$user = currentUser() ?: ['email' => '', 'photo' => 'https://placehold.co/40x40/1a1a1a/ffffff?text=A'];
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maria City - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // ================= JS AUTH GUARD =================
        const role = localStorage.getItem('mc_user_role');
        const email = localStorage.getItem('mc_user_email');
        if (role !== 'admin' && email !== 'asusaun499@gmail.com') {
            window.location.replace('/');
        }
        
        // Restore UI state from localStorage
        document.addEventListener("DOMContentLoaded", () => {
            const sessionStr = localStorage.getItem('mc_user_session');
            if (sessionStr) {
                try {
                    const session = JSON.parse(sessionStr);
                    if (session.photo) document.getElementById('ui-avatar').src = session.photo;
                    if (session.email) document.getElementById('ui-email').textContent = session.email;
                } catch(e) {}
            }
        });

        function doLogout() {
            localStorage.clear();
            document.cookie = "mc_auth=; path=/; max-age=0;";
            window.location.href = '/logout';
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0d0d0d; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        .nav-item.active { background: rgba(255,255,255,0.08); color: white; }
        .status-pending { background: rgba(217,119,6,.15); color: #f59e0b; }
        .status-replied { background: rgba(16,185,129,.15); color: #10b981; }
        .status-closed  { background: rgba(107,114,128,.15); color: #6b7280; }
        .ticket-card.selected { border-color: rgba(245,158,11,.4); background: rgba(245,158,11,.05); }
        video { background: #000; }
    </style>
</head>
<body class="flex h-screen text-gray-100 overflow-hidden">

    <!-- ===================== LEFT NAV ===================== -->
    <nav class="w-56 bg-[#111] border-r border-white/6 flex flex-col p-3 gap-2 flex-shrink-0">
        <div class="flex items-center gap-2 px-2 py-2 mb-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-600 to-amber-500 flex items-center justify-center text-white font-bold text-sm shadow">
                <i class="fa-solid fa-city"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-white">MARIA CITY</p>
                <p class="text-[9px] text-red-400 font-semibold uppercase">Admin Panel</p>
            </div>
        </div>

        <button class="nav-item active flex items-center gap-2.5 w-full px-3 py-2.5 rounded-xl text-xs text-gray-300 transition hover:bg-white/6 text-left" onclick="showSection('tickets')">
            <i class="fa-solid fa-ticket text-amber-400 w-4 text-center"></i> จัดการ Tickets
            <span id="pending-count" class="ml-auto bg-amber-500 text-black text-[9px] font-bold px-1.5 py-0.5 rounded-full">0</span>
        </button>
        <button class="nav-item flex items-center gap-2.5 w-full px-3 py-2.5 rounded-xl text-xs text-gray-300 transition hover:bg-white/6 text-left" onclick="showSection('map')">
            <i class="fa-solid fa-map text-emerald-400 w-4 text-center"></i> อัปเดตแมพ
        </button>
        <button class="nav-item flex items-center gap-2.5 w-full px-3 py-2.5 rounded-xl text-xs text-gray-300 transition hover:bg-white/6 text-left" onclick="showSection('logs')">
            <i class="fa-solid fa-scroll text-indigo-400 w-4 text-center"></i> บันทึก Logs
        </button>

        <div class="mt-auto pt-3 border-t border-white/6 space-y-2">
            <div class="flex items-center gap-2 bg-red-950/30 border border-red-500/20 rounded-xl p-2.5">
                <img id="ui-avatar" src="<?= htmlspecialchars($user['photo']) ?>" class="w-7 h-7 rounded-full" alt="">
                <div class="overflow-hidden flex-1">
                    <p class="text-[10px] font-bold text-red-400 truncate">Developer</p>
                    <p id="ui-email" class="text-[9px] text-gray-500 truncate"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
            <button onclick="doLogout()" class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs text-gray-500 hover:text-red-400 hover:bg-white/4 transition">
                <i class="fa-solid fa-right-from-bracket w-4 text-center"></i> ออกจากระบบ
            </button>
        </div>
    </nav>

    <!-- ===================== MAIN ===================== -->
    <div class="flex-1 flex overflow-hidden">

        <!-- SECTION: TICKETS -->
        <section id="section-tickets" class="flex-1 flex overflow-hidden">

            <!-- Ticket List Panel -->
            <div class="w-80 border-r border-white/6 bg-[#111] flex flex-col flex-shrink-0">
                <div class="p-4 border-b border-white/6 flex-shrink-0">
                    <h2 class="font-bold text-white text-sm">Tickets ทั้งหมด</h2>
                    <div class="flex gap-1.5 mt-3">
                        <button onclick="filterTickets('all')" id="filter-all" class="filter-btn active px-3 py-1.5 rounded-lg text-xs bg-white/10 text-white transition">ทั้งหมด</button>
                        <button onclick="filterTickets('pending')" id="filter-pending" class="filter-btn px-3 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/6 transition">รอตรวจสอบ</button>
                        <button onclick="filterTickets('replied')" id="filter-replied" class="filter-btn px-3 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white/6 transition">ตอบแล้ว</button>
                    </div>
                </div>
                <div id="ticket-list" class="flex-1 overflow-y-auto p-3 space-y-2"></div>
            </div>

            <!-- Ticket Detail Panel -->
            <div class="flex-1 flex flex-col bg-[#0d0d0d] overflow-hidden">
                <!-- Empty State -->
                <div id="ticket-empty" class="flex-1 flex items-center justify-center flex-col gap-4 text-center p-8">
                    <div class="w-16 h-16 rounded-2xl bg-white/4 flex items-center justify-center text-3xl text-gray-600">
                        <i class="fa-solid fa-ticket"></i>
                    </div>
                    <p class="text-gray-500 text-sm">เลือก Ticket เพื่อดูรายละเอียดและตอบกลับ</p>
                </div>

                <!-- Ticket Detail -->
                <div id="ticket-detail" class="flex-1 flex flex-col hidden overflow-hidden">

                    <!-- Detail Header -->
                    <div class="p-5 border-b border-white/6 flex-shrink-0 bg-[#111]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 id="detail-id" class="font-bold text-white text-base"></h3>
                                    <span id="detail-status-badge" class="text-xs px-2 py-0.5 rounded-full font-semibold"></span>
                                    <span id="detail-type-badge" class="text-xs px-2 py-0.5 rounded-full bg-white/8 text-gray-300 font-medium"></span>
                                </div>
                                <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-500">
                                    <span id="detail-from"></span>
                                    <span id="detail-date"></span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="changeTicketStatus('closed')" class="px-3 py-1.5 bg-white/6 hover:bg-white/10 border border-white/8 rounded-lg text-xs text-gray-400 transition">ปิด Ticket</button>
                            </div>
                        </div>
                    </div>

                    <!-- Conversation & Evidence -->
                    <div class="flex-1 overflow-y-auto p-5 space-y-5">
                        <!-- Original Message -->
                        <div>
                            <p class="text-[11px] text-gray-500 mb-2 font-semibold uppercase tracking-wide">ข้อความจากผู้เล่น</p>
                            <div id="detail-message" class="bg-white/4 border border-white/8 rounded-xl p-4 text-sm text-gray-200 leading-relaxed"></div>
                        </div>

                        <!-- Evidence Files -->
                        <div id="evidence-section" class="hidden">
                            <p class="text-[11px] text-gray-500 mb-2 font-semibold uppercase tracking-wide">หลักฐานที่แนบมา</p>
                            <div id="evidence-container" class="flex flex-wrap gap-3"></div>
                        </div>

                        <!-- Existing Reply -->
                        <div id="existing-reply-section" class="hidden">
                            <p class="text-[11px] text-emerald-500 mb-2 font-semibold uppercase tracking-wide">การตอบกลับของ Admin</p>
                            <div id="existing-reply" class="bg-emerald-950/30 border border-emerald-500/20 rounded-xl p-4 text-sm text-gray-200 leading-relaxed"></div>
                        </div>
                    </div>

                    <!-- Reply Box -->
                    <div id="reply-box" class="p-4 border-t border-white/6 bg-[#111] flex-shrink-0">
                        <p class="text-xs font-semibold text-gray-400 mb-2">ตอบกลับผู้เล่น</p>
                        <textarea id="admin-reply-text" rows="3" placeholder="พิมพ์ข้อความตอบกลับ..." class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-3 text-sm text-gray-200 focus:outline-none focus:border-white/25 placeholder-gray-600 resize-none transition"></textarea>
                        <div class="flex justify-end gap-2 mt-2">
                            <button onclick="sendAdminReply()" class="bg-amber-500 hover:bg-amber-400 text-black font-semibold px-5 py-2 rounded-xl text-xs transition shadow">
                                <i class="fa-solid fa-paper-plane mr-1.5"></i>ส่งคำตอบ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION: MAP UPDATE -->
        <section id="section-map" class="flex-1 p-8 overflow-y-auto hidden">
            <h2 class="text-xl font-bold text-white mb-6">อัปเดตความคืบหน้าแมพ</h2>
            <div class="max-w-xl bg-[#111] border border-white/6 rounded-2xl p-6 space-y-5">
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-400">ความคืบหน้าแมพปัจจุบัน</span>
                        <span id="map-current-text" class="text-amber-400 font-bold">0%</span>
                    </div>
                    <div class="w-full bg-white/6 rounded-full h-3 overflow-hidden">
                        <div id="map-bar" class="bg-gradient-to-r from-red-600 to-amber-500 h-full rounded-full transition-all duration-700" style="width:0%"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-2">กำหนดเปอร์เซ็นต์ใหม่ (0 - 100)</label>
                    <div class="flex gap-3">
                        <input type="number" id="map-input" min="0" max="100" placeholder="0"
                            class="flex-1 bg-[#1a1a1a] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-200 focus:outline-none focus:border-amber-500 transition">
                        <button onclick="saveMapProgress()" class="bg-amber-500 hover:bg-amber-400 text-black font-bold px-5 py-2.5 rounded-xl text-sm transition">บันทึก</button>
                    </div>
                </div>
                <div id="map-save-msg" class="hidden text-xs text-emerald-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-check-circle"></i> บันทึกสำเร็จ!
                </div>
            </div>
        </section>

        <!-- SECTION: LOGS -->
        <section id="section-logs" class="flex-1 p-8 overflow-y-auto hidden">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white">บันทึกการแชท (Logs)</h2>
                <button onclick="exportLogs()" class="bg-white/6 hover:bg-white/10 border border-white/8 text-white px-4 py-2 rounded-xl text-xs transition flex items-center gap-2">
                    <i class="fa-solid fa-download text-amber-400"></i> Export JSON
                </button>
            </div>
            <div id="logs-container" class="space-y-2 max-w-3xl">
                <div class="text-gray-600 text-sm text-center py-8">กำลังโหลด...</div>
            </div>
        </section>

    </div>

    <!-- Video Lightbox -->
    <div id="video-lightbox" class="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" onclick="closeLightbox(event)">
        <div class="relative max-w-4xl w-full">
            <button onclick="closeLightbox()" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-sm"><i class="fa-solid fa-xmark mr-1"></i>ปิด</button>
            <video id="lightbox-video" controls class="w-full rounded-2xl bg-black shadow-2xl max-h-[80vh]"></video>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div id="img-lightbox" class="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" onclick="closeImgLightbox()">
        <img id="lightbox-img" src="" class="max-h-[90vh] max-w-full rounded-xl shadow-2xl" alt="">
    </div>

    <script>
    let allTickets = [];
    let currentTicket = null;
    let currentFilter = 'all';
    let mapProgress = 0;

    // ==================== INIT ====================
    (async function init() {
        await loadTickets();
        await loadMapProgress();
    })();

    // ==================== NAVIGATION ====================
    function showSection(name) {
        document.querySelectorAll('[id^="section-"]').forEach(s => s.classList.add('hidden'));
        document.getElementById('section-' + name).classList.remove('hidden');
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        event.currentTarget.classList.add('active');
        if (name === 'logs') loadLogs();
        if (name === 'map') document.getElementById('map-input').value = mapProgress;
    }

    // ==================== TICKETS ====================
    async function loadTickets() {
        try {
            const res = await fetch('/api-endpoint?action=all_tickets');
            const data = await res.json();
            allTickets = data.tickets || [];
            renderTicketList();
            const pending = allTickets.filter(t => t.status === 'pending').length;
            document.getElementById('pending-count').textContent = pending;
            document.getElementById('pending-count').className = pending > 0 
                ? 'ml-auto bg-amber-500 text-black text-[9px] font-bold px-1.5 py-0.5 rounded-full'
                : 'ml-auto bg-gray-700 text-gray-300 text-[9px] font-bold px-1.5 py-0.5 rounded-full';
        } catch(e) { console.error(e); }
    }

    function filterTickets(filter) {
        currentFilter = filter;
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('active','bg-white/10','text-white');
            b.classList.add('text-gray-400');
        });
        const activeBtn = document.getElementById('filter-' + filter);
        activeBtn.classList.add('active','bg-white/10','text-white');
        activeBtn.classList.remove('text-gray-400');
        renderTicketList();
    }

    function renderTicketList() {
        const list = document.getElementById('ticket-list');
        const filtered = currentFilter === 'all' ? allTickets : allTickets.filter(t => t.status === currentFilter);
        
        if (filtered.length === 0) {
            list.innerHTML = '<div class="text-center text-gray-600 text-xs py-10">ไม่มี Ticket</div>';
            return;
        }
        
        list.innerHTML = '';
        const typeMap = { check_player: { icon: 'user-shield', color: 'text-red-400', label: 'ตรวจสอบผู้เล่น' }, report_bug: { icon: 'bug', color: 'text-amber-400', label: 'แจ้งบัค' }, general: { icon: 'comment', color: 'text-blue-400', label: 'ทั่วไป' } };
        
        filtered.sort((a, b) => b.created_at - a.created_at).forEach(t => {
            const typeInfo = typeMap[t.type] || typeMap.general;
            const card = document.createElement('div');
            card.className = `ticket-card bg-white/4 border border-white/8 rounded-xl p-3 cursor-pointer hover:border-white/15 transition space-y-2 ${currentTicket?.id === t.id ? 'selected' : ''}`;
            card.onclick = () => openTicket(t.id);
            card.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-gray-400">${t.id}</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold status-${t.status}">${{ pending:'รอตรวจสอบ', replied:'ตอบแล้ว', closed:'ปิดแล้ว' }[t.status]}</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-${typeInfo.icon} ${typeInfo.color} text-xs w-4 text-center"></i>
                    <span class="text-xs text-gray-300 font-medium">${typeInfo.label}</span>
                </div>
                <p class="text-xs text-gray-500 truncate">${t.message}</p>
                <p class="text-[10px] text-gray-600">${t.from_name} · ${new Date(t.created_at * 1000).toLocaleDateString('th-TH')}</p>`;
            list.appendChild(card);
        });
    }

    async function openTicket(id) {
        const ticket = allTickets.find(t => t.id === id);
        if (!ticket) return;
        currentTicket = ticket;

        // Highlight selected
        document.querySelectorAll('.ticket-card').forEach(c => c.classList.remove('selected'));
        document.querySelectorAll('.ticket-card').forEach(c => {
            if (c.querySelector('.text-gray-400')?.textContent === id) c.classList.add('selected');
        });

        // Show detail panel
        document.getElementById('ticket-empty').classList.add('hidden');
        document.getElementById('ticket-detail').classList.remove('hidden');

        const statusMap = { pending: 'status-pending', replied: 'status-replied', closed: 'status-closed' };
        const statusLabel = { pending: 'รอตรวจสอบ', replied: 'ตอบกลับแล้ว', closed: 'ปิดแล้ว' };
        const typeMap = { check_player: 'ตรวจสอบผู้เล่น', report_bug: 'แจ้งบัค', general: 'ทั่วไป' };

        document.getElementById('detail-id').textContent = ticket.id;
        document.getElementById('detail-status-badge').className = `text-xs px-2 py-0.5 rounded-full font-semibold ${statusMap[ticket.status]}`;
        document.getElementById('detail-status-badge').textContent = statusLabel[ticket.status];
        document.getElementById('detail-type-badge').textContent = typeMap[ticket.type] || ticket.type;
        document.getElementById('detail-from').innerHTML = `<i class="fa-solid fa-user mr-1"></i>${ticket.from_name} (${ticket.from_email})`;
        document.getElementById('detail-date').innerHTML = `<i class="fa-regular fa-clock mr-1"></i>${new Date(ticket.created_at * 1000).toLocaleString('th-TH')}`;
        document.getElementById('detail-message').textContent = ticket.message;

        // Evidence
        const evidenceSection = document.getElementById('evidence-section');
        const evidenceContainer = document.getElementById('evidence-container');
        if (ticket.evidence && ticket.evidence.length > 0) {
            evidenceSection.classList.remove('hidden');
            evidenceContainer.innerHTML = '';
            ticket.evidence.forEach(ev => {
                if (ev.type === 'image') {
                    evidenceContainer.innerHTML += `<img src="${ev.url}" class="h-32 rounded-xl border border-white/10 object-cover cursor-pointer hover:opacity-80 transition" onclick="openImgLightbox('${ev.url}')" alt="หลักฐาน">`;
                } else if (ev.type === 'video') {
                    const div = document.createElement('div');
                    div.className = 'relative group cursor-pointer';
                    div.onclick = () => openVideoLightbox(ev.url);
                    div.innerHTML = `
                        <video src="${ev.url}" class="h-32 rounded-xl border border-white/10 object-cover" muted></video>
                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-xl group-hover:bg-black/60 transition">
                            <div class="w-10 h-10 rounded-full bg-white/90 flex items-center justify-center shadow">
                                <i class="fa-solid fa-play text-black text-sm ml-0.5"></i>
                            </div>
                        </div>`;
                    evidenceContainer.appendChild(div);
                }
            });
        } else {
            evidenceSection.classList.add('hidden');
        }

        // Existing reply
        if (ticket.admin_reply) {
            document.getElementById('existing-reply-section').classList.remove('hidden');
            document.getElementById('existing-reply').textContent = ticket.admin_reply;
            document.getElementById('admin-reply-text').value = ticket.admin_reply;
        } else {
            document.getElementById('existing-reply-section').classList.add('hidden');
            document.getElementById('admin-reply-text').value = '';
        }

        // If closed, disable reply
        const replyBox = document.getElementById('reply-box');
        if (ticket.status === 'closed') {
            replyBox.style.opacity = '0.5';
            replyBox.style.pointerEvents = 'none';
        } else {
            replyBox.style.opacity = '1';
            replyBox.style.pointerEvents = 'auto';
        }
    }

    async function sendAdminReply() {
        if (!currentTicket) return;
        const replyText = document.getElementById('admin-reply-text').value.trim();
        if (!replyText) { alert('กรุณาพิมพ์ข้อความตอบกลับ'); return; }

        const btn = event.currentTarget;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>กำลังส่ง...';

        try {
            const res = await fetch('/api-endpoint?action=reply_ticket', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id: currentTicket.id, reply: replyText })
            });
            const data = await res.json();
            if (data.success) {
                currentTicket.admin_reply = replyText;
                currentTicket.status = 'replied';
                document.getElementById('existing-reply-section').classList.remove('hidden');
                document.getElementById('existing-reply').textContent = replyText;
                await loadTickets();
                openTicket(currentTicket.id);
            }
        } catch(e) { alert('เกิดข้อผิดพลาด'); }
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1.5"></i>ส่งคำตอบ';
    }

    async function changeTicketStatus(status) {
        if (!currentTicket) return;
        try {
            const res = await fetch('/api-endpoint?action=update_ticket_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id: currentTicket.id, status })
            });
            const data = await res.json();
            if (data.success) { await loadTickets(); openTicket(currentTicket.id); }
        } catch(e) {}
    }

    // ==================== MAP ====================
    async function loadMapProgress() {
        try {
            const res = await fetch('/api-endpoint?action=get_map');
            const data = await res.json();
            mapProgress = data.progress || 0;
            document.getElementById('map-current-text').textContent = mapProgress + '%';
            document.getElementById('map-bar').style.width = mapProgress + '%';
        } catch(e) {}
    }

    async function saveMapProgress() {
        const val = parseInt(document.getElementById('map-input').value);
        if (isNaN(val) || val < 0 || val > 100) { alert('กรุณากรอกตัวเลข 0-100'); return; }
        try {
            const res = await fetch('/api-endpoint?action=update_map', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ progress: val })
            });
            const data = await res.json();
            if (data.success) {
                mapProgress = val;
                document.getElementById('map-current-text').textContent = val + '%';
                document.getElementById('map-bar').style.width = val + '%';
                const msg = document.getElementById('map-save-msg');
                msg.classList.remove('hidden');
                setTimeout(() => msg.classList.add('hidden'), 3000);
            }
        } catch(e) { alert('เกิดข้อผิดพลาด'); }
    }

    // ==================== LOGS ====================
    async function loadLogs() {
        const container = document.getElementById('logs-container');
        container.innerHTML = '<div class="text-gray-600 text-sm text-center py-8">กำลังโหลด...</div>';
        try {
            const res = await fetch('/api-endpoint?action=all_logs');
            const data = await res.json();
            const logs = data.logs || [];
            if (logs.length === 0) {
                container.innerHTML = '<div class="text-gray-600 text-sm text-center py-8">ยังไม่มีบันทึก</div>';
                return;
            }
            container.innerHTML = '';
            logs.forEach(log => {
                const isUser = log.sender === 'user';
                container.innerHTML += `
                    <div class="flex items-center gap-3 bg-[#111] border border-white/6 rounded-xl p-3">
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${isUser ? 'bg-blue-500/20 text-blue-400' : 'bg-amber-500/20 text-amber-400'}">${isUser ? 'ผู้เล่น' : 'บอท'}</span>
                        <span class="text-xs text-gray-300 flex-1 truncate">${log.text || ''}</span>
                        <span class="text-[10px] text-gray-600 flex-shrink-0">${new Date(log.timestamp).toLocaleString('th-TH')}</span>
                    </div>`;
            });
        } catch(e) { container.innerHTML = '<div class="text-red-400 text-sm text-center py-8">เกิดข้อผิดพลาด</div>'; }
    }

    async function exportLogs() {
        const res = await fetch('/api-endpoint?action=all_logs');
        const data = await res.json();
        const a = document.createElement('a');
        a.href = 'data:text/json,' + encodeURIComponent(JSON.stringify(data.logs, null, 2));
        a.download = 'maria_city_logs.json';
        a.click();
    }

    // ==================== LIGHTBOX ====================
    function openVideoLightbox(url) {
        const lb = document.getElementById('video-lightbox');
        document.getElementById('lightbox-video').src = url;
        lb.classList.remove('hidden');
    }
    function closeLightbox(e) {
        if (!e || e.target === document.getElementById('video-lightbox') || !e.target) {
            const lb = document.getElementById('video-lightbox');
            lb.classList.add('hidden');
            document.getElementById('lightbox-video').src = '';
        }
    }
    function openImgLightbox(url) {
        document.getElementById('lightbox-img').src = url;
        document.getElementById('img-lightbox').classList.remove('hidden');
    }
    function closeImgLightbox() { document.getElementById('img-lightbox').classList.add('hidden'); }

    // Auto-refresh tickets every 30s
    setInterval(loadTickets, 30000);
    </script>
</body>
</html>
