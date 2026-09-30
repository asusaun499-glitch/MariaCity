<?php
session_start();

// การตั้งค่า
$DEV_EMAIL = 'asusaun499@gmail.com';
// คุณต้องไปเอา Google Client ID จาก https://console.cloud.google.com/ มาใส่ที่นี่
$GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com'; 

function isDeveloper() {
    return isset($_SESSION['user']) && $_SESSION['user']['email'] === $GLOBALS['DEV_EMAIL'];
}
?>
