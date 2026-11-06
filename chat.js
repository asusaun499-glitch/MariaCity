async function sendToAI(message, history = []) {
  const response = await fetch('/api/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message, history })
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error || 'ส่งข้อความไม่สำเร็จ');
  }

  return data.reply;
}
