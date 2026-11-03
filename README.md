# 🎓 TVK69 System

<p align="center">
  <strong>แพลตฟอร์มสำหรับห้องเรียน เอกสาร การฝึกงาน วิดีโอย้อนหลัง และพื้นที่เว็บไซต์ส่วนตัวของนักศึกษา</strong>
</p>

<p align="center">
  <a href="https://tvk69.site"><img src="https://img.shields.io/website?url=https%3A%2F%2Ftvk69.site&label=tvk69.site&style=for-the-badge" alt="Website"></a>
  <img src="https://img.shields.io/badge/Frontend-HTML%20%7C%20TailwindCSS%20%7C%20JavaScript-4f46e5?style=for-the-badge" alt="Frontend">
  <img src="https://img.shields.io/badge/API-PHP%20%2B%20MySQL-777bb4?style=for-the-badge" alt="API">
  <img src="https://img.shields.io/badge/Realtime-WebSocket-111827?style=for-the-badge" alt="Realtime">
</p>

> อัปเดตล่าสุด: **3 พฤศจิกายน 2026**

## ✨ TVK69 คืออะไร

TVK69 เป็นระบบสำหรับนักศึกษาและเพื่อนในห้องเรียน ใช้รวมข้อมูลที่ต้องใช้งานเป็นประจำไว้ในเว็บเดียว เช่น เอกสารการเรียน ข้อมูลอาจารย์ บันทึกฝึกงาน Resume พื้นที่ฝากไฟล์ Intern Stories การแจ้งเตือน วิดีโอเรียนย้อนหลัง และการเข้าถึง TVK69 Hosting

## 🚀 ฟีเจอร์หลัก

- 📚 **เอกสารการเรียน** — แยกตามรายวิชาและอาจารย์ พร้อมระบบอัปโหลดสำหรับผู้ดูแล
- 🎬 **บันทึกออนไลน์** — รับชมคลิปการเรียนย้อนหลัง ค้นหา กรองรายวิชา และเล่น YouTube / Vimeo / ไฟล์วิดีโอโดยตรง
- 🛠️ **Admin Video Manager** — เพิ่ม แก้ไข และลบวิดีโอ โดยเลือกอาจารย์และรายวิชาจากข้อมูลในระบบ
- 👨‍🏫 **ข้อมูลอาจารย์และรายวิชา** — ใช้เป็นข้อมูลกลางให้เอกสารและวิดีโอ
- 🧑‍🎓 **ข้อมูลนักศึกษา** — โปรไฟล์ รหัสนักศึกษา และข้อมูลที่เกี่ยวข้อง
- 📝 **Internship Diary** — บันทึกงานฝึกงาน ชั่วโมง สถานะเข้าเรียน/ทำงาน และสรุปข้อมูล
- 💾 **SaveMe** — พื้นที่ฝากไฟล์ส่วนตัวของนักศึกษา
- 💬 **Intern Stories** — โพสต์ คอมเมนต์ Reaction และระบบแจ้งเตือน
- 📄 **Resume / Quota** — เครื่องมือเก็บข้อมูลและสร้างข้อมูลสำหรับนักศึกษา
- 🔔 **Realtime & Notification** — อัปเดตสถานะผ่าน WebSocket พร้อมระบบแจ้งเตือน
- 🌐 **สร้างเว็บไซต์ของตัวเอง** — ยืนยันรหัสนักศึกษาก่อนเข้า TVK69 Hosting และเปิดเว็บไซต์ในรูปแบบ `https://host.tvk69.site/<studentId>/`
- 📱 **Responsive / PWA** — รองรับ Desktop, Tablet และ Mobile

## 🧩 Tech Stack

| ส่วน | เทคโนโลยี |
|---|---|
| Frontend | HTML5, CSS3, JavaScript, TailwindCSS, SweetAlert2 |
| Backend API | PHP |
| Database | MySQL / MariaDB |
| Realtime | Socket.IO / WebSocket |
| Hosting | Apache / DirectAdmin / Cloudflare |
| Notification | Email, LINE Messaging API |
| PWA | Web App Manifest |

## 📁 โครงสร้างโปรเจกต์

```text
tvksystem/
├─ index.html              # หน้าเว็บหลัก TVK69
├─ bio.php                 # หน้า/ระบบ Bio
├─ manifest.json           # PWA manifest
├─ tvk69.sql               # โครงสร้างฐานข้อมูลเดิม
├─ api/
│  ├─ db.php               # Database connector
│  ├─ enpoints_v2.php      # API รุ่นเดิม
│  ├─ enp4.php             # API รุ่นล่าสุด
│  └─ migrate_add_gmail.php
└─ uploads/
```

## 🎬 Recorded Lessons API

API รุ่นล่าสุดใน `api/enp4.php` เพิ่ม endpoint สำหรับระบบบันทึกออนไลน์:

| Endpoint | Method | การใช้งาน |
|---|---:|---|
| `/recordings` | GET | ดึงรายการวิดีโอทั้งหมด |
| `/recording-save` | POST | เพิ่มหรือแก้ไขวิดีโอ |
| `/recording-delete` | DELETE | ลบวิดีโอ |

การเพิ่ม/แก้ไข/ลบต้องผ่าน **Admin Session + CSRF** และระบบตรวจสอบความสัมพันธ์ระหว่างอาจารย์กับรายวิชาก่อนบันทึก

ตาราง `recorded_lessons` จะถูกสร้างอัตโนมัติเมื่อใช้งาน Recorded Lessons API ครั้งแรก

## 🌐 TVK69 Hosting

เมนู **สร้างเว็บไซต์ของตัวเอง** ในหน้า TVK69 จะให้ผู้ใช้ยืนยันรหัสนักศึกษาก่อน จากนั้นจึงเปิดพื้นที่ Hosting:

```text
https://host.tvk69.site/?studentId=<studentId>
```

เว็บไซต์ที่เผยแพร่แล้วสามารถเข้าดูในรูปแบบ:

```text
https://host.tvk69.site/<studentId>/
```

> ฝั่ง `host.tvk69.site` ควรตรวจสอบตัวตนซ้ำที่ Server Side ไม่ควรใช้ค่า `studentId` จาก URL เป็นหลักฐานยืนยันสิทธิ์เพียงอย่างเดียว

## 🔐 การตั้งค่า Secret

**ห้าม commit token หรือ secret จริงลง GitHub** โดยเฉพาะเมื่อ repository เป็น Public

ตั้งค่าต่อไปนี้ผ่าน Environment Variables บน Server:

```env
LINE_CHANNEL_ACCESS_TOKEN=your_line_channel_access_token
REALTIME_SECRET=your_realtime_secret
```

ตัวอย่างอยู่ที่ไฟล์ `.env.example`

> ค่า Bearer token ที่ฝังอยู่ใน Frontend ไม่ควรถูกใช้เป็นกลไกยืนยันสิทธิ์ Admin เพราะผู้ใช้สามารถดู Source Code ได้ การทำรายการสำคัญควรอาศัย Session/CSRF หรือระบบ Authentication ฝั่ง Server

## ⚙️ การติดตั้งโดยย่อ

1. Clone repository ไปยัง Web Server
2. ตั้งค่า Database ใน `api/db.php`
3. Import `tvk69.sql`
4. ตั้งค่า Environment Variables สำหรับ LINE และ Realtime
5. ตั้ง Virtual Host / Reverse Proxy ให้ `https://api.tvk69.site` ชี้ไปยัง API รุ่นที่ใช้งาน
6. เปิด `index.html` ผ่าน HTTPS
7. ทดสอบ Login, Admin, Recorded Lessons, Realtime และ TVK69 Hosting ก่อนใช้งาน Production

## 🛡️ Security Notes

- ใช้ HTTPS ทุก Subdomain
- จำกัด CORS เฉพาะ Origin ที่อนุญาต
- Admin action ต้องตรวจ Session และ CSRF ฝั่ง Server
- อย่าเก็บ Password แบบ Plain Text ในระบบ Production — ควรใช้ `password_hash()` และ `password_verify()`
- Secret ทุกชนิดควรอยู่ใน Environment Variables หรือ Secret Manager
- ควร Rotate token ทันทีหากเคยถูกเผยแพร่ใน Git history

## 📌 สถานะ

โปรเจกต์นี้อยู่ระหว่างการพัฒนาอย่างต่อเนื่อง ฟีเจอร์ใหม่ควรถูกทดสอบทั้ง Frontend, API, Database และ Realtime integration ก่อน Deploy ขึ้น Production

---

<p align="center">
  Made for <strong>TVK69</strong> 🎓<br>
  <sub>Learning • Internship • Community • Personal Hosting</sub>
</p>
