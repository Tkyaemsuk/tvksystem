# TVK69 v2026.11.06.1

วันที่: 6 พฤศจิกายน 2026

## Highlights

- ⚡ Fast Login: ลดขั้นตอน Login เหลือ API request หลักเพียงครั้งเดียว
- 🔐 เพิ่ม `session-login` สำหรับตรวจห้องเรียน + นักศึกษา + ออก Realtime Token ใน request เดียว
- 🚀 เปิดหน้า Main App ทันทีหลัง Login สำเร็จ โดยไม่รอ WSS / Notification
- 🧠 ปรับ Session Restore แบบ Balanced ให้เปิดระบบเร็วและไม่แย่ง resource กับหน้า Documents
- 📄 ปรับการโหลด Documents/Teachers ให้เหมาะกับการใช้งานหลัง restore session
- 📸 เพิ่ม Instagram-style Story แยกจาก Feed Post
- 🎵 รองรับ Story Music Library, เลือกช่วงเพลง และเล่นเพลงอัตโนมัติเมื่อเปิดดู Story
- ✅ Verified badge สำหรับผู้ใช้ที่มีอีเมล
- 🟢 Presence / Last Seen แสดงออนไลน์และระยะเวลาออฟไลน์
- 💬 ปรับ Realtime Feed, Comments, Reactions และ Story events
- 🌐 ใช้ Realtime domain `livev1.tvk69.site`
- 🩺 เพิ่ม API health endpoint สำหรับตรวจ API/Database
- 🗄️ อัปเดตฐานข้อมูลหลักและ migration สำหรับ Story Music, IG Story, Presence และ Recorded Lessons

## Database

ไฟล์ฐานข้อมูลที่อัปเดต:

- `tvk69.sql`
- `database/tvk69_2026_11_realtime_story.sql`
- `database/tvk69_recordings_comments.sql`

สำหรับฐานข้อมูล Production เดิม ให้รัน migration:

`database/tvk69_2026_11_realtime_story.sql`

## Main files

- `index.html`
- `api/enp4.php`
- `tvk69.sql`

## Verification

- ตรวจ Fast Login marker ใน `index.html`
- ตรวจ `session-login` และ `health` ใน `api/enp4.php`
- ตรวจ schema `intern_story_moments`
- ตรวจ schema `story_music_library`
- ตรวจ schema `realtime_presence_last_seen`
- ตรวจ migration database ล่าสุด
- ZIP package ถูกตรวจด้วย `unzip -t`
- สร้าง SHA256 checksum สำหรับไฟล์ Release

## Release source

Release นี้ build จาก branch `main` หลังอัปเดตระบบวันที่ 6 พฤศจิกายน 2026
