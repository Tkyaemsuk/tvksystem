<?php
// bio.php หรือ view.php
require_once 'api/db.php'; // เรียกไฟล์ DB ก่อน

// 🚨 สำคัญ: ต้องประกาศ Header ว่าเป็น HTML *หลังจาก* เรียก db.php
// เพื่อแก้ทับ Header JSON ที่อาจจะติดมาจากไฟล์ db.php
header('Content-Type: text/html; charset=utf-8');

$db = new Database();
$username = $_GET['u'] ?? '';
$bio_data = null;

if ($username) {
    // ดึงข้อมูล Bio + ข้อมูลนักเรียน Join กัน
    $sql = "SELECT b.*, s.student_name, s.images 
            FROM student_bios b 
            JOIN students s ON b.student_id = s.student_id 
            WHERE b.username = ?";
    $stmt = $db->query($sql, [$username]);
    $bio_data = $stmt->fetch();
}

// ถ้าไม่เจอผู้ใช้ ให้ redirect หรือแสดง error
if (!$bio_data) {
    die('<div style="text-align:center; padding:50px; font-family:sans-serif;"><h1>404 Not Found</h1><p>ไม่พบผู้ใช้นี้ หรือยังไม่ได้สร้าง Bio</p><a href="/">กลับหน้าหลัก</a></div>');
}

// แปลง Youtube URL เป็น Embed ID (ถ้ามี)
$youtube_embed_url = '';
if (!empty($bio_data['bg_music'])) {
    preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11}).*/', $bio_data['bg_music'], $matches);
    if (!empty($matches[1])) {
        $youtube_embed_url = "https://www.youtube.com/embed/" . $matches[1] . "?autoplay=1&loop=1&playlist=" . $matches[1];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($bio_data['student_name']); ?> - Bio</title>
        <link rel="shortcut icon" href="<?php echo $bio_data['images']; ?>" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Kanit', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .profile-glow {
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($bio_data['theme_color'] ?? 'bg-gray-900'); ?> min-h-screen text-white flex items-center justify-center p-4 transition-colors duration-500">

    <div class="max-w-md w-full relative z-10">
        
        <div class="glass-card rounded-3xl p-8 shadow-2xl text-center animate-float">
            
            <div class="relative w-36 h-36 mx-auto mb-6">
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-20 rounded-full animate-spin-slow"></div>
                <img src="<?php echo !empty($bio_data['images']) ? $bio_data['images'] : 'https://via.placeholder.com/150'; ?>" 
                     class="relative w-36 h-36 rounded-full object-cover border-4 border-white/30 shadow-2xl profile-glow mx-auto transition transform hover:scale-105 duration-300">
                <?php if($youtube_embed_url): ?>
                    <div class="absolute -bottom-2 -right-2 bg-green-500 text-white p-2 rounded-full shadow-lg animate-bounce">
                        <i class="fas fa-music"></i>
                    </div>
                <?php endif; ?>
            </div>

            <h1 class="text-3xl font-extrabold mb-2 tracking-tight drop-shadow-md">
                <?php echo htmlspecialchars($bio_data['student_name']); ?>
            </h1>
            <p class="text-gray-200 mb-8 text-lg font-light italic opacity-90">
                "<?php echo nl2br(htmlspecialchars($bio_data['bio_text'])); ?>"
            </p>

            <div class="space-y-3 mb-8">
                <?php if(!empty($bio_data['facebook_link'])): ?>
                <a href="<?php echo $bio_data['facebook_link']; ?>" target="_blank" 
                   class="block w-full py-3 bg-[#1877F2] hover:bg-[#166fe5] text-white rounded-xl transition transform hover:-translate-y-1 shadow-lg flex items-center justify-center font-bold">
                    <i class="fab fa-facebook-f text-xl mr-3"></i> Facebook
                </a>
                <?php endif; ?>

                <?php if(!empty($bio_data['instagram_link'])): ?>
                <a href="<?php echo $bio_data['instagram_link']; ?>" target="_blank" 
                   class="block w-full py-3 bg-gradient-to-r from-[#833AB4] via-[#FD1D1D] to-[#F77737] hover:opacity-90 text-white rounded-xl transition transform hover:-translate-y-1 shadow-lg flex items-center justify-center font-bold">
                    <i class="fab fa-instagram text-xl mr-3"></i> Instagram
                </a>
                <?php endif; ?>

                <?php if(!empty($bio_data['tiktok_link'])): ?>
                <a href="<?php echo $bio_data['tiktok_link']; ?>" target="_blank" 
                   class="block w-full py-3 bg-black hover:bg-gray-800 text-white rounded-xl border border-gray-700 transition transform hover:-translate-y-1 shadow-lg flex items-center justify-center font-bold">
                    <i class="fab fa-tiktok text-xl mr-3"></i> TikTok
                </a>
                <?php endif; ?>

                <?php if(!empty($bio_data['line_id'])): ?>
                <div class="block w-full py-3 bg-[#06C755] text-white rounded-xl shadow-lg flex items-center justify-center font-bold cursor-default">
                    <i class="fab fa-line text-xl mr-3"></i> ID: <?php echo htmlspecialchars($bio_data['line_id']); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if($youtube_embed_url): ?>
                <div class="mt-6 pt-6 border-t border-white/20">
                    <p class="text-xs text-gray-300 mb-3 flex items-center justify-center gap-2">
                        <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span> 
                        Now Playing
                    </p>
                    <div class="relative overflow-hidden rounded-xl shadow-inner bg-black/20">
                        <iframe class="w-full h-20 opacity-80 hover:opacity-100 transition" 
                                src="<?php echo $youtube_embed_url; ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                        </iframe>
                    </div>
                </div>
             <?php endif; ?>

            <div class="mt-8 text-xs text-gray-400/80 font-light">
                TVK68 Bio System © 2026
            </div>
        </div>
    </div>
    
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-white opacity-5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-black opacity-20 rounded-full blur-3xl"></div>
    </div>

</body>
</html>