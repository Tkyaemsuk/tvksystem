# TVK69 v2026.11.03

วันที่: 3 พฤศจิกายน 2026

## Highlights

- Student Single Login: เข้าสู่ระบบครั้งเดียวด้วยรหัสห้องเรียน + รหัสนักศึกษา
- ยกเลิกการถามรหัสนักศึกษาซ้ำใน SaveMe, Intern Stories, Diary, Resume, Hosting, Notifications และ Recorded Lessons
- Bio/Profile: แสดงปุ่มแก้ไขเฉพาะเจ้าของบัญชีที่ล็อกอิน และไม่ต้องยืนยันรหัสนักศึกษาซ้ำ
- Recorded Lessons: วิดีโอหลักฝั่งซ้าย รายการคลิปอื่นฝั่งขวา พร้อมข้อมูลผู้สอนตามรายวิชา
- Comments: รูปโปรไฟล์และชื่อนักศึกษาจากข้อมูล Bio, 10 ความคิดเห็นต่อหน้า, pagination พร้อม ellipsis
- ปรับ UI หน้า Login และระยะห่างส่วนความคิดเห็น
- แก้ error legacy `sidInput is not defined`
- เพิ่ม Recorded Lesson Comments API และ schema ฐานข้อมูล

## Verification

- ตรวจ JavaScript inline ทั้งหมดด้วย `node --check`: ผ่าน
- ไม่พบ `verifyStdId` และ `sidInput` ที่เลิกใช้งานแล้ว
- ตรวจ flow Single Login และ owner guard ของ Bio แล้ว
- ตรวจ frontend `index.html` ที่ commit ล่าสุดบน `main`
- `api/enp4.php` และ `database/tvk69_recordings_comments.sql` ยังตรงกับเวอร์ชันที่เพิ่ม Comments API/DB

## Main release commit

`a6c0f0ad9e8b7fb9c2e4e680557997b62d99a1f9`
