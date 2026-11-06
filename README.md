# 🎓 TVK69 System

<p align="center">
  <strong>แพลตฟอร์มสำหรับห้องเรียน เอกสาร ฝึกงาน ชุมชน Realtime Chat, TVK69 AI, Stories และวิดีโอย้อนหลัง</strong>
</p>

<p align="center">
  <a href="https://tvk69.site"><img src="https://img.shields.io/website?url=https%3A%2F%2Ftvk69.site&label=tvk69.site&style=for-the-badge" alt="Website"></a>
  <img src="https://img.shields.io/badge/Version-v2026.11.06.1-16a34a?style=for-the-badge" alt="Version">
  <img src="https://img.shields.io/badge/Frontend-HTML%20%7C%20TailwindCSS%20%7C%20JavaScript-4f46e5?style=for-the-badge" alt="Frontend">
  <img src="https://img.shields.io/badge/API-PHP%20%2B%20MySQL-777bb4?style=for-the-badge" alt="API">
  <img src="https://img.shields.io/badge/Realtime-WebSocket-111827?style=for-the-badge" alt="Realtime">
</p>

> อัปเดตล่าสุด: **6 พฤศจิกายน 2026**  
> เวอร์ชันปัจจุบัน: **v2026.11.06.1**

## ✨ TVK69 คืออะไร

TVK69 เป็นแพลตฟอร์มสำหรับนักศึกษาและเพื่อนในห้องเรียนที่รวมบริการสำคัญไว้ในระบบเดียว เช่น เอกสารการเรียน ข้อมูลอาจารย์ บันทึกฝึกงาน Resume, SaveMe, Feed, Stories, Realtime Chat, TVK69 AI, วิดีโอเรียนย้อนหลัง การแจ้งเตือน และการเข้าถึง TVK69 Hosting

ระบบถูกพัฒนาให้ใช้งานได้ทั้ง Desktop, Tablet และ Mobile พร้อมการเชื่อมต่อ Realtime สำหรับสถานะออนไลน์ แชท Feed, Comments, Reactions และ Stories

## 🆕 อัปเดตล่าสุด — v2026.11.06.1

### ⚡ Login & Session

- ลดขั้นตอน Login เหลือ API request หลักเพียงครั้งเดียว
- เพิ่ม endpoint `session-login` สำหรับตรวจสอบห้องเรียน นักศึกษา และออก Realtime Token ใน request เดียว
- เปิด Main App ได้ทันทีหลัง Login สำเร็จ โดยไม่ต้องรอ WSS หรือ Notification
- ปรับ Session Restore แบบ Balanced ให้เปิดระบบได้เร็วขึ้น
- ปรับการโหลด Documents และ Teachers หลัง Restore Session

### 💬 Realtime Chat

- รองรับห้องแชทแบบ Direct / Private และโครงสร้างสำหรับห้องสนทนา
- บันทึกสมาชิกห้องผ่าน `chat_room_members`
- บันทึกข้อความผ่าน `chat_messages`
- รองรับสถานะข้อความที่ถูกแก้ไขและลบแบบ Soft Delete
- รองรับไฟล์แนบผ่าน `chat_files`
- เก็บสถานะการอ่านข้อความล่าสุดของสมาชิกแต่ละคน
- เชื่อมระบบ Realtime ผ่าน WebSocket

### 🤖 TVK69 AI

- เพิ่มระบบสนทนากับ TVK69 AI
- บันทึกประวัติห้องสนทนาใน `ai_conversations`
- บันทึกข้อความ User / Assistant ใน `ai_messages`
- รองรับหลาย Conversation ต่อผู้ใช้งาน
- รองรับชื่อห้องสนทนาและโหมดของ AI
- ประวัติ AI ถูกเก็บถาวรในฐานข้อมูลและสามารถโหลดกลับมาใช้งานได้

### 📸 Stories & Social

- เพิ่ม Instagram-style Story แยกจาก Feed Post
- เพิ่ม Story Music Library
- เลือกช่วงเวลาเริ่มต้นและระยะเวลาเพลงของ Story ได้
- เล่นเพลงอัตโนมัติเมื่อเปิด Story
- เพิ่ม Verified Badge สำหรับผู้ใช้ที่มีอีเมล
- เพิ่ม Presence / Last Seen แสดงสถานะออนไลน์และระยะเวลาออฟไลน์
- ปรับ Realtime Feed, Comments, Reactions และ Story Events
- ใช้ Realtime domain `livev1.tvk69.site`

### 🎬 Recorded Lessons

- รองรับวิดีโอเรียนย้อนหลัง
- รองรับ Comments ใต้ Recorded Lessons
- Admin สามารถเพิ่ม แก้ไข และลบวิดีโอ
- เลือกอาจารย์และรายวิชาจากข้อมูลในระบบ
- รองรับ YouTube / Vimeo / Direct Video URL

### 🩺 API & Database

- เพิ่ม API Health Endpoint สำหรับตรวจสอบ API และ Database
- อัปเดตฐานข้อมูลหลัก `tvk69.sql`
- เพิ่ม Migration สำหรับ Realtime Story / Music / Presence / Recorded Lessons
- เพิ่ม Migration สำหรับ Realtime Chat และ TVK69 AI History

## 🚀 ฟีเจอร์หลัก

- 📚 **เอกสารการเรียน** — แยกตามรายวิชาและอาจารย์ พร้อมระบบอัปโหลดสำหรับผู้ดูแล
- 🎬 **บันทึกออนไลน์** — รับชมคลิปการเรียนย้อนหลัง ค้นหา กรองรายวิชา และแสดงความคิดเห็น
- 🛠️ **Admin Video Manager** — เพิ่ม แก้ไข และลบวิดีโอ พร้อมเลือกอาจารย์และรายวิชา
- 👨‍🏫 **ข้อมูลอาจารย์และรายวิชา** — ข้อมูลกลางสำหรับเอกสารและวิดีโอ
- 🧑‍🎓 **ข้อมูลนักศึกษา** — โปรไฟล์ รหัสนักศึกษา รูปโปรไฟล์ และข้อมูลที่เกี่ยวข้อง
- 📝 **Internship Diary** — บันทึกงานฝึกงาน ชั่วโมง และข้อมูลการปฏิบัติงาน
- 💾 **SaveMe** — พื้นที่ฝากไฟล์ส่วนตัวของนักศึกษา
- 💬 **Realtime Chat** — ห้องแชทและข้อความแบบ Real-time
- 🤖 **TVK69 AI** — AI Assistant พร้อมระบบ Conversation History
- 📸 **Stories** — Story แบบ Instagram พร้อม Music Library
- 📰 **Intern Stories / Feed** — โพสต์ คอมเมนต์ Reaction และระบบแจ้งเตือน
- 🟢 **Presence / Last Seen** — แสดงสถานะออนไลน์และเวลาที่ใช้งานล่าสุด
- ✅ **Verified Badge** — แสดงเครื่องหมายยืนยันสำหรับผู้ใช้ที่เข้าเงื่อนไข
- 📄 **Resume / Quota** — เครื่องมือเก็บข้อมูลและสร้างข้อมูลสำหรับนักศึกษา
- 🔔 **Realtime & Notification** — WebSocket พร้อม Email / LINE Notification
- 🌐 **TVK69 Hosting** — พื้นที่เว็บไซต์ส่วนตัวสำหรับนักศึกษา
- 📱 **Responsive / PWA** — รองรับ Desktop, Tablet และ Mobile

## 🧩 Tech Stack

| ส่วน | เทคโนโลยี |
|---|---|
| Frontend | HTML5, CSS3, JavaScript, TailwindCSS, SweetAlert2 |
| Backend API | PHP |
| Database | MySQL / MariaDB |
| Realtime | Socket.IO / WebSocket |
| AI | TVK69 AI API Integration |
| Hosting | Apache / DirectAdmin / Cloudflare |
| Notification | Email, LINE Messaging API |
| PWA | Web App Manifest |

## 📁 โครงสร้างโปรเจกต์

```text
tvksystem/
├─ index.html
├─ bio.php
├─ manifest.json
├─ tvk69.sql
├─ RELEASE_NOTES_v2026.11.06.md
├─ api/
│  ├─ db.php
│  ├─ enpoints_v2.php
│  ├─ enp4.php
│  └─ migrate_add_gmail.php
├─ database/
│  ├─ tvk69_2026_11_realtime_story.sql
│  ├─ tvk69_2026_11_chat_ai.sql
│  └─ tvk69_recordings_comments.sql
└─ uploads/
```

## 🗄️ Database Migration

สำหรับระบบใหม่สามารถใช้ `tvk69.sql` เป็นฐานข้อมูลหลักได้

สำหรับระบบ Production เดิม ให้สำรองฐานข้อมูลก่อน แล้วรัน Migration ล่าสุดตามลำดับ:

```text
database/tvk69_2026_11_realtime_story.sql
database/tvk69_2026_11_chat_ai.sql
```

### Migration: Realtime Story

ไฟล์ `database/tvk69_2026_11_realtime_story.sql` เพิ่ม:

- `story_music_library`
- `intern_story_moments`
- `realtime_presence_last_seen`
- `recorded_lessons`
- `recorded_lesson_comments`

### Migration: Chat & AI

ไฟล์ `database/tvk69_2026_11_chat_ai.sql` เพิ่ม:

- `chat_rooms`
- `chat_room_members`
- `chat_messages`
- `chat_files`
- `ai_conversations`
- `ai_messages`

Migration ทั้งสองชุดใช้ `CREATE TABLE IF NOT EXISTS` เพื่อให้สามารถนำไปใช้กับฐานข้อมูลเดิมได้โดยไม่สร้างตารางซ้ำ

## 🎬 Recorded Lessons API

API หลักอยู่ใน `api/enp4.php`

| Endpoint | Method | การใช้งาน |
|---|---:|---|
| `/recordings` | GET | ดึงรายการวิดีโอทั้งหมด |
| `/recording-save` | POST | เพิ่มหรือแก้ไขวิดีโอ |
| `/recording-delete` | DELETE | ลบวิดีโอ |
| `/session-login` | POST | Login + ตรวจนักศึกษา + ออก Realtime Token |
| `/health` | GET | ตรวจสอบสถานะ API / Database |

การเพิ่ม แก้ไข หรือลบข้อมูลสำคัญควรผ่าน Admin Session และ CSRF ฝั่ง Server

## 🌐 TVK69 Hosting

เมนู **สร้างเว็บไซต์ของตัวเอง** เชื่อมผู้ใช้จาก TVK69 ไปยังพื้นที่ Hosting

```text
https://host.tvk69.site/?studentId=<studentId>
```

เว็บไซต์ที่เผยแพร่แล้วสามารถเปิดผ่าน:

```text
https://host.tvk69.site/<studentId>/
```

> ฝั่ง `host.tvk69.site` ควรตรวจสอบสิทธิ์จาก Server Side และไม่ควรใช้ค่า `studentId` จาก URL เพียงอย่างเดียวเป็นหลักฐานยืนยันตัวตน

## 🔐 Security

- ใช้ HTTPS ทุก Subdomain
- จำกัด CORS เฉพาะ Origin ที่อนุญาต
- Admin Action ต้องตรวจ Session และ CSRF ฝั่ง Server
- ใช้ `password_hash()` และ `password_verify()` สำหรับ Password
- Secret / API Key / Token ต้องเก็บฝั่ง Server
- ห้าม Commit Secret จริงลง Public Repository
- ควร Rotate Token ทันทีหากเคยถูกเผยแพร่ใน Git History
- การอัปโหลดไฟล์ต้องตรวจ MIME Type, Extension, File Size และสิทธิ์เจ้าของไฟล์
- Chat และ AI API ต้องตรวจ Student Session ก่อนอ่านหรือแก้ไขข้อมูล

## ⚙️ การติดตั้งโดยย่อ

1. Clone repository ไปยัง Web Server
2. ตั้งค่า Database ใน `api/db.php`
3. Import `tvk69.sql` สำหรับระบบใหม่ หรือรัน Migration สำหรับระบบเดิม
4. ตั้งค่า Secret สำหรับ LINE / Realtime / AI ฝั่ง Server
5. ตั้ง Reverse Proxy และ HTTPS ให้ API / Realtime Domain
6. เปิด `index.html`
7. ทดสอบ Login และ Session Restore
8. ทดสอบ Realtime Chat / TVK69 AI / Stories / Presence
9. ทดสอบ Recorded Lessons และ Notifications
10. ตรวจสอบ API Health ก่อนเปิด Production

## ✅ Production Verification

ก่อน Deploy ควรตรวจสอบอย่างน้อย:

- Fast Login และ `session-login`
- Session Restore
- Realtime WebSocket
- Chat Rooms / Messages / File Attachments
- TVK69 AI Conversations / Messages
- Stories / Story Music
- Presence / Last Seen
- Feed / Comments / Reactions
- Recorded Lessons / Comments
- Admin Session / CSRF
- API Health
- Database Migration
- Mobile Responsive UI

## 📦 Release

### v2026.11.06.1 — 6 พฤศจิกายน 2026

Release ปัจจุบัน build จาก branch `main` และรวมการปรับปรุงหลักด้าน Fast Login, Realtime Social, Stories, Presence, Recorded Lessons รวมถึงฐานข้อมูลล่าสุด

หลัง Release เดิม มีการอัปเดตเพิ่มเติมบน `main` สำหรับ:

- Realtime Chat
- TVK69 AI
- AI Conversation History
- Chat / AI Database Migration
- `index.html` เวอร์ชันล่าสุด

รายละเอียด Release เดิมดูได้ที่ `RELEASE_NOTES_v2026.11.06.md`

## 📌 สถานะโครงการ

**Active Development**

TVK69 อยู่ระหว่างการพัฒนาอย่างต่อเนื่อง โดยระบบหลักในปัจจุบันครอบคลุม Learning, Internship, Social, Realtime Chat, AI Assistant, Stories, Recorded Lessons และ Personal Hosting

ทุกการอัปเดตควรทดสอบ Frontend, API, Database Migration และ Realtime Integration ก่อน Deploy ขึ้น Production

---

<p align="center">
  Made for <strong>TVK69</strong> 🎓<br>
  <sub>Learning • Internship • Community • Realtime • AI • Personal Hosting</sub>
</p>
