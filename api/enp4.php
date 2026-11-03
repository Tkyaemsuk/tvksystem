<?php
// enpoints_v2.php
$allowedOrigins = [
    'https://tvk69.site',
    'https://www.tvk69.site',
	'https://livev1.tvk69.site',
	'https://api.tvk69.site/'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-CSRF');
header('Content-Type: application/json; charset=UTF-8');


/* =========================================================
   PRE-FLIGHT
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Authenticated admin session for destructive-adjacent settings; API bearer token is NOT admin authentication.
ini_set('session.use_strict_mode', '1');
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'None']);
session_start();
require_once 'db.php';
// Defensive: override any legacy CORS header emitted by included PHP files.
// Keep credentialed requests restricted to the allowed origin list.
header_remove('Access-Control-Allow-Origin');
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
require_once 'semester_reset_engine.php';
$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_uri, '/'));
$endpoint = end($path_parts);
define('API_TOKEN', 'TVKSYSTEM2569'); 

/* 🔐 SERVER TRUSTED IPs */
$ALLOWED_IPS = [
    '127.0.0.1',
    'localhost',
    'tvk69.site' 
];

function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function checkToken() {
    global $ALLOWED_IPS;
    $clientIP = getClientIP();
    if (in_array($clientIP, $ALLOWED_IPS)) return true;

    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!$auth || !preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
        http_response_code(401);
        echo json_encode(["status" => false, "message" => "Missing token"]);
        exit;
    }

    $token = $matches[1];
    if ($token !== API_TOKEN) {
        http_response_code(401);
        echo json_encode(["status" => false, "message" => "Invalid token"]);
        exit;
    }
    return true;
}


/* =========================================================
   TVK69 REALTIME / WEBSOCKET
   PHP API -> https://livev1.tvk69.site/event
   ========================================================= */
define('REALTIME_URL', 'https://livev1.tvk69.site/event');
define('REALTIME_SECRET', getenv('REALTIME_SECRET') ?: 'AYIfwhwEIjt9CXZkoEJ_xAUdBgF4TrfoZhnc8xBcHjw');

function realtimeEmit($event, $data = [], $studentId = null) {
    if (!$event || !function_exists('curl_init')) return false;

    $payload = [
        'event' => (string)$event,
        'data' => is_array($data) ? $data : ['value' => $data],
        'studentId' => $studentId
    ];

    $ch = curl_init(REALTIME_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=UTF-8',
            'X-Realtime-Secret: ' . REALTIME_SECRET
        ],
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 1500,
        CURLOPT_NOSIGNAL => true
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response !== false && $httpCode >= 200 && $httpCode < 300;
}



function realtimeGetDashboardStats($db) {
    return [
        'totalDocuments' => (int)$db->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
        'totalStudents' => (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'totalImages' => (int)$db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
        'totalUsage' => (int)($db->query("SELECT usage_count FROM stats WHERE id = 1")->fetchColumn() ?? 0)
    ];
}

function realtimePushDashboard($db) {
    realtimeEmit('dashboard:update', [
        'stats' => realtimeGetDashboardStats($db),
        'timestamp' => gmdate('c')
    ]);
}

function realtimeGetSaveMeStats($db) {
    $used = (float)($db->query("SELECT SUM(file_size) FROM student_files")->fetchColumn() ?? 0);
    $max = 3 * 1024 * 1024 * 1024;
    return [
        'used' => $used,
        'max' => (float)$max,
        'percent' => $max > 0 ? ($used / $max) * 100 : 0
    ];
}

function realtimePushSaveMeStats($db) {
    realtimeEmit('saveme:stats', [
        'stats' => realtimeGetSaveMeStats($db),
        'timestamp' => gmdate('c')
    ]);
}

function realtimeGetRanking($db) {
    $sql = "SELECT
                s.id,
                s.student_id,
                s.student_name,
                s.images,
                COALESCE(story_stats.story_count, 0) AS story_count,
                COALESCE(comment_stats.comment_count, 0) AS comment_count,
                COALESCE(reaction_stats.reaction_count, 0) AS reaction_count,
                COALESCE(diary_stats.diary_count, 0) AS diary_count,
                COALESCE(file_stats.file_count, 0) AS file_count,
                (
                    COALESCE(story_stats.story_count, 0) +
                    COALESCE(comment_stats.comment_count, 0) +
                    COALESCE(reaction_stats.reaction_count, 0) +
                    COALESCE(diary_stats.diary_count, 0) +
                    COALESCE(file_stats.file_count, 0)
                ) AS activity_count
            FROM students s
            LEFT JOIN (
                SELECT student_id, COUNT(*) AS story_count
                FROM intern_stories
                WHERE COALESCE(is_anonymous, 0) = 0
                GROUP BY student_id
            ) story_stats
              ON story_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN (
                SELECT student_id, COUNT(*) AS comment_count
                FROM intern_comments
                GROUP BY student_id
            ) comment_stats
              ON comment_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN (
                SELECT student_id, COUNT(*) AS reaction_count
                FROM intern_story_reactions
                GROUP BY student_id
            ) reaction_stats
              ON reaction_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN (
                SELECT student_id, COUNT(*) AS diary_count
                FROM intern_diaries
                GROUP BY student_id
            ) diary_stats
              ON diary_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN (
                SELECT student_id, COUNT(*) AS file_count
                FROM student_files
                GROUP BY student_id
            ) file_stats
              ON file_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
            ORDER BY activity_count DESC, comment_count DESC, story_count DESC, s.student_name ASC";

    $ranking = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $rank = 0;
    $position = 0;
    $previousActivity = null;

    foreach ($ranking as &$row) {
        $position++;
        $activity = (int)$row['activity_count'];

        if ($previousActivity === null || $activity !== $previousActivity) {
            $rank = $position;
            $previousActivity = $activity;
        }

        $row['rank'] = $rank;

        foreach (['story_count','comment_count','reaction_count','diary_count','file_count','activity_count'] as $key) {
            $row[$key] = (int)$row[$key];
        }
    }
    unset($row);

    return $ranking;
}

function realtimePushRanking($db) {
    realtimeEmit('ranking:update', [
        'ranking' => realtimeGetRanking($db),
        'timestamp' => gmdate('c')
    ]);
}

function realtimeGetStory($db, $storyId) {
    $story = $db->query(
        "SELECT s.*, st.student_name, st.images
         FROM intern_stories s
         LEFT JOIN students st
           ON st.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
         WHERE s.id = ?
         LIMIT 1",
        [$storyId]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$story) return null;

    $story['comments'] = $db->query(
        "SELECT c.*, st.student_name, st.images
         FROM intern_comments c
         LEFT JOIN students st
           ON st.student_id COLLATE utf8mb4_unicode_ci = c.student_id COLLATE utf8mb4_unicode_ci
         WHERE c.story_id = ?
         ORDER BY c.created_at ASC",
        [$storyId]
    )->fetchAll(PDO::FETCH_ASSOC);

    // my_reaction is client-specific and will be maintained by the browser.
    $story['my_reaction'] = null;
    return $story;
}

function realtimeBroadcastUpdate($scope, $action = 'updated', $data = [], $studentId = null) {
    realtimeEmit('entity:changed', [
        'scope' => $scope,
        'action' => $action,
        'data' => is_array($data) ? $data : [],
        'timestamp' => gmdate('c')
    ], $studentId);
}

function notificationHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sendGmailNotification($to, $studentName, $title, $message, $actionUrl = 'https://tvk69.site') {
    if (empty($to)) return false;

    $safeName = notificationHtml($studentName ?: 'ผู้ใช้งาน');
    $safeTitle = notificationHtml($title);
    $safeMessage = nl2br(notificationHtml($message));
    $safeUrl = notificationHtml($actionUrl);

    $headers = "From: TVK69 Notification <admin@tvk69.site>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff'>
        <div style='background:#111827;padding:24px;text-align:center'>
            <img src='https://cdn.tvk69.site/tvk69.png' alt='TVK69' style='max-height:70px'>
        </div>
        <div style='padding:28px;color:#374151'>
            <h2 style='margin-top:0;color:#111827'>{$safeTitle}</h2>
            <p>สวัสดีคุณ {$safeName}</p>
            <div style='background:#f9fafb;border-left:4px solid #4f46e5;padding:16px;border-radius:8px;line-height:1.7'>{$safeMessage}</div>
            <div style='text-align:center;margin-top:28px'>
                <a href='{$safeUrl}' style='display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold'>เปิด TVK69</a>
            </div>
        </div>
        <div style='background:#f3f4f6;padding:14px;text-align:center;font-size:12px;color:#6b7280'>
            ระบบแจ้งเตือนอัตโนมัติ TVK69
        </div>
    </div>";

    return @mail($to, $title, $body, $headers);
}

function sendLineBroadcastNotification($message) {
    $channelToken = 'RGUGzoEuvQbivUxBtgr8RARjEmgWKqwSQWX0l8xtqjoyX4JBE9WK07OkFkFdwl1DCRgPw6nRHfWZJ3KSOnqhUa3Xj9ULvXYOJOXoCuyTKDWwaRw+7ZlpALI1dQA0YOlRaFpm/Bkw7g1VWgm2i8Js/wdB04t89/1O/w1cDnyilFU=';
    if (!$channelToken) return false;

    $payload = [
        'messages' => [[
            'type' => 'text',
            'text' => $message
        ]]
    ];

    $ch = curl_init('https://api.line.me/v2/bot/message/broadcast');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=UTF-8',
            'Authorization: Bearer ' . $channelToken
        ],
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

function notifyAllStudents($db, $title, $message, $lineMessage = null) {
    $students = $db->query("SELECT student_name, gmail FROM students WHERE gmail IS NOT NULL AND gmail != ''")->fetchAll();
    foreach ($students as $student) {
        sendGmailNotification($student['gmail'], $student['student_name'], $title, $message);
    }

    sendLineBroadcastNotification($lineMessage ?: "🔔 {$title}\n{$message}");
}

function notifyStudentAndLine($db, $studentId, $title, $message, $lineMessage = null) {
    $student = $db->query("SELECT student_name, gmail FROM students WHERE student_id = ? LIMIT 1", [$studentId])->fetch();
    if ($student && !empty($student['gmail'])) {
        sendGmailNotification($student['gmail'], $student['student_name'], $title, $message);
    }

    // ปัจจุบันฐานข้อมูลไม่มี LINE userId รายบุคคล จึงใช้ Broadcast โดยไม่เปิดเผยข้อความคอมเมนต์
    sendLineBroadcastNotification($lineMessage ?: "🔔 {$title}");
}

if (count($path_parts) >= 2) {
    $parent_endpoint = $path_parts[count($path_parts) - 2];
    $dynamic_id = end($path_parts);
    if ($parent_endpoint === 'teacher-details') { $endpoint = 'teacher-details'; $teacher_id = $dynamic_id; }
    elseif ($parent_endpoint === 'program-details') { $endpoint = 'program-details'; $program_id = $dynamic_id; }
}


/* ===== TVK69 RECORDED LESSONS + COMMENTS API v2 ===== */
function tvkEnsureRecordingTables($db) {
    $db->query("CREATE TABLE IF NOT EXISTS recorded_lessons (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(180) NOT NULL,
        teacher VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        recording_date DATE NOT NULL,
        url TEXT NOT NULL,
        thumbnail TEXT NULL,
        description TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_recording_date (recording_date),
        KEY idx_teacher_subject (teacher(64), subject(64))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS recorded_lesson_comments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        recording_id BIGINT UNSIGNED NOT NULL,
        student_id VARCHAR(100) NOT NULL,
        comment_text TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_rec_comments_recording (recording_id, created_at),
        KEY idx_rec_comments_student (student_id),
        CONSTRAINT fk_rec_comments_recording
            FOREIGN KEY (recording_id) REFERENCES recorded_lessons(id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function tvkRecordingInput() {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 32768) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function tvkRecordingAdminAllowed() {
    if (empty($_SESSION['tvk_admin'])) return false;
    $expected = (string)($_SESSION['tvk_admin_csrf'] ?? '');
    $sent = (string)($_SERVER['HTTP_X_ADMIN_CSRF'] ?? '');
    return $expected !== '' && $sent !== '' && hash_equals($expected, $sent);
}

function tvkRecordingSubjects($raw) {
    $items = preg_split('/[,\/\r\n]+/u', (string)$raw) ?: [];
    $items = array_map('trim', $items);
    $items = array_filter($items, fn($v) => $v !== '' && $v !== 'ไม่ได้ระบุรายวิชา');
    return array_values(array_unique($items));
}

function tvkRecordingValidUrl($value, $video = false) {
    $value = trim((string)$value);
    if ($value === '' || strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) return false;
    if (!$video) return true;
    $host = strtolower((string)$parts['host']);
    if (in_array($host, ['youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtube-nocookie.com','vimeo.com','www.vimeo.com','player.vimeo.com'], true)) return true;
    return preg_match('~\.(?:mp4|webm|ogg)$~i', (string)($parts['path'] ?? '')) === 1;
}

function tvkRecordingRow($db, $id) {
    return $db->query("SELECT id,title,teacher,subject,DATE_FORMAT(recording_date,'%Y-%m-%d') AS date,url,thumbnail,description,created_at,updated_at FROM recorded_lessons WHERE id=? LIMIT 1", [(int)$id])->fetch(PDO::FETCH_ASSOC);
}

function tvkRecordingCommentRow($db, $id) {
    return $db->query("SELECT c.id,c.recording_id,c.student_id,c.comment_text,c.created_at,c.updated_at,s.student_name,s.images
        FROM recorded_lesson_comments c
        LEFT JOIN students s ON s.student_id COLLATE utf8mb4_unicode_ci = c.student_id COLLATE utf8mb4_unicode_ci
        WHERE c.id=? LIMIT 1", [(int)$id])->fetch(PDO::FETCH_ASSOC);
}

switch ($endpoint) {

    case 'recordings':
        if ($method === 'GET') {
            try {
                tvkEnsureRecordingTables($db);
                $videos = $db->query("SELECT id,title,teacher,subject,DATE_FORMAT(recording_date,'%Y-%m-%d') AS date,url,thumbnail,description,created_at,updated_at FROM recorded_lessons ORDER BY recording_date DESC,id DESC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success'=>true,'videos'=>$videos], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                error_log('recordings: '.$e->getMessage());
                http_response_code(500);
                echo json_encode(['success'=>false,'message'=>'ไม่สามารถโหลดวิดีโอได้'], JSON_UNESCAPED_UNICODE);
            }
        } else {
            http_response_code(405);
            echo json_encode(['success'=>false,'message'=>'Method not allowed'], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'recording-save':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); break; }
        if (!tvkRecordingAdminAllowed()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin/CSRF ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE); break; }

        try {
            tvkEnsureRecordingTables($db);
            $in = tvkRecordingInput();
            if (!$in) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'JSON ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE); break; }

            $id=(int)($in['id']??0);
            $title=trim((string)($in['title']??''));
            $teacher=trim((string)($in['teacher']??''));
            $subject=trim((string)($in['subject']??''));
            $date=trim((string)($in['date']??''));
            $url=trim((string)($in['url']??''));
            $thumbnail=trim((string)($in['thumbnail']??''));
            $description=trim((string)($in['description']??''));

            $dateObj=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if ($title==='' || $teacher==='' || $subject==='' || !$dateObj || $dateObj->format('Y-m-d')!==$date || !tvkRecordingValidUrl($url,true) || ($thumbnail!=='' && !tvkRecordingValidUrl($thumbnail,false))) {
                http_response_code(422);
                echo json_encode(['success'=>false,'message'=>'ข้อมูลวิดีโอไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $rows=$db->query("SELECT subject FROM teachers WHERE name=?",[$teacher])->fetchAll(PDO::FETCH_ASSOC);
            $ok=false;
            foreach($rows as $row){ if(in_array($subject,tvkRecordingSubjects($row['subject']??''),true)){ $ok=true; break; } }
            if(!$ok){ http_response_code(422); echo json_encode(['success'=>false,'message'=>'รายวิชาไม่ตรงกับอาจารย์'],JSON_UNESCAPED_UNICODE); break; }

            if($id>0){
                if(!tvkRecordingRow($db,$id)){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'ไม่พบวิดีโอ'],JSON_UNESCAPED_UNICODE); break; }
                $db->query("UPDATE recorded_lessons SET title=?,teacher=?,subject=?,recording_date=?,url=?,thumbnail=?,description=? WHERE id=?",[$title,$teacher,$subject,$date,$url,$thumbnail,$description,$id]);
                $action='updated';
            } else {
                $db->query("INSERT INTO recorded_lessons (title,teacher,subject,recording_date,url,thumbnail,description) VALUES (?,?,?,?,?,?,?)",[$title,$teacher,$subject,$date,$url,$thumbnail,$description]);
                $id=(int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                $action='created';
            }

            $record=tvkRecordingRow($db,$id);
            if(function_exists('realtimeBroadcastUpdate')) realtimeBroadcastUpdate('recordings',$action,['record'=>$record]);
            echo json_encode(['success'=>true,'video'=>$record],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch(Throwable $e){
            error_log('recording-save: '.$e->getMessage());
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'บันทึกวิดีโอไม่สำเร็จ'],JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'recording-delete':
        if ($method !== 'DELETE') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); break; }
        if (!tvkRecordingAdminAllowed()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin/CSRF ไม่ถูกต้อง'],JSON_UNESCAPED_UNICODE); break; }
        try {
            tvkEnsureRecordingTables($db);
            $in=tvkRecordingInput(); $id=(int)($in['id']??0);
            if($id<=0 || !tvkRecordingRow($db,$id)){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'ไม่พบวิดีโอ'],JSON_UNESCAPED_UNICODE); break; }
            $db->query("DELETE FROM recorded_lessons WHERE id=?",[$id]);
            if(function_exists('realtimeBroadcastUpdate')) realtimeBroadcastUpdate('recordings','deleted',['id'=>$id]);
            echo json_encode(['success'=>true,'id'=>$id],JSON_UNESCAPED_UNICODE);
        } catch(Throwable $e){
            error_log('recording-delete: '.$e->getMessage());
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'ลบวิดีโอไม่สำเร็จ'],JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'recording-comments':
        if ($method !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); break; }
        try {
            tvkEnsureRecordingTables($db);
            $rid=(int)($_GET['recordingId']??$_GET['recording_id']??0);
            if($rid<=0){ http_response_code(422); echo json_encode(['success'=>false,'message'=>'recordingId ไม่ถูกต้อง'],JSON_UNESCAPED_UNICODE); break; }
            $comments=$db->query("SELECT c.id,c.recording_id,c.student_id,c.comment_text,c.created_at,c.updated_at,s.student_name,s.images
                FROM recorded_lesson_comments c
                LEFT JOIN students s ON s.student_id COLLATE utf8mb4_unicode_ci = c.student_id COLLATE utf8mb4_unicode_ci
                WHERE c.recording_id=? ORDER BY c.created_at ASC,c.id ASC",[$rid])->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'comments'=>$comments],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch(Throwable $e){
            error_log('recording-comments: '.$e->getMessage());
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'โหลดความคิดเห็นไม่สำเร็จ'],JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'recording-comment-add':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); break; }
        try {
            tvkEnsureRecordingTables($db);
            $in=tvkRecordingInput();
            $rid=(int)($in['recordingId']??$in['recording_id']??0);
            $sid=trim((string)($in['studentId']??$in['student_id']??''));
            $comment=trim((string)($in['comment']??$in['text']??$in['comment_text']??''));

            if($rid<=0 || $sid==='' || $comment===''){ http_response_code(422); echo json_encode(['success'=>false,'message'=>'ข้อมูลไม่ครบ'],JSON_UNESCAPED_UNICODE); break; }
            if(mb_strlen($comment)>2000){ http_response_code(422); echo json_encode(['success'=>false,'message'=>'ความคิดเห็นยาวเกิน 2,000 ตัวอักษร'],JSON_UNESCAPED_UNICODE); break; }
            if(!tvkRecordingRow($db,$rid)){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'ไม่พบวิดีโอ'],JSON_UNESCAPED_UNICODE); break; }

            $student=$db->query("SELECT student_id,student_name,images FROM students WHERE student_id=? LIMIT 1",[$sid])->fetch(PDO::FETCH_ASSOC);
            if(!$student){ http_response_code(403); echo json_encode(['success'=>false,'message'=>'ไม่พบรหัสนักศึกษา'],JSON_UNESCAPED_UNICODE); break; }

            $db->query("INSERT INTO recorded_lesson_comments (recording_id,student_id,comment_text) VALUES (?,?,?)",[$rid,$sid,$comment]);
            $cid=(int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            $row=tvkRecordingCommentRow($db,$cid);
            if(function_exists('realtimeEmit')) realtimeEmit('recording:comment-created',['recordingId'=>$rid,'comment'=>$row]);
            echo json_encode(['success'=>true,'comment'=>$row],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch(Throwable $e){
            error_log('recording-comment-add: '.$e->getMessage());
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'เพิ่มความคิดเห็นไม่สำเร็จ'],JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'recording-comment-delete':
        if ($method !== 'DELETE') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); break; }
        try {
            tvkEnsureRecordingTables($db);
            $in=tvkRecordingInput();
            $id=(int)($in['id']??$in['commentId']??0);
            $sid=trim((string)($in['studentId']??$in['student_id']??''));

            $old=tvkRecordingCommentRow($db,$id);
            if(!$old){ http_response_code(404); echo json_encode(['success'=>false,'message'=>'ไม่พบความคิดเห็น'],JSON_UNESCAPED_UNICODE); break; }

            $isOwner=$sid!=='' && hash_equals((string)$old['student_id'],$sid);
            $isAdmin=!empty($_SESSION['tvk_admin']);
            if(!$isOwner && !$isAdmin){ http_response_code(403); echo json_encode(['success'=>false,'message'=>'ไม่มีสิทธิ์ลบความคิดเห็นนี้'],JSON_UNESCAPED_UNICODE); break; }

            $db->query("DELETE FROM recorded_lesson_comments WHERE id=?",[$id]);
            if(function_exists('realtimeEmit')) realtimeEmit('recording:comment-deleted',['recordingId'=>(int)$old['recording_id'],'commentId'=>$id]);
            echo json_encode(['success'=>true,'id'=>$id],JSON_UNESCAPED_UNICODE);
        } catch(Throwable $e){
            error_log('recording-comment-delete: '.$e->getMessage());
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'ลบความคิดเห็นไม่สำเร็จ'],JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'login':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents("php://input"), true);
            $roomCode = $input['roomCode'] ?? '';
            $stmt = $db->query("SELECT * FROM rooms WHERE room_code = ?", [$roomCode]);
            $room = $stmt->fetch();
            if ($room) {
                $db->query("UPDATE stats SET usage_count = usage_count + 1 WHERE id = 1");
                realtimePushDashboard($db);
                echo json_encode(["success" => true, "message" => "Login successful", "room_name" => $room['room_name']]);
            } else {
                echo json_encode(["success" => false, "message" => "รหัสเข้าห้องเรียนไม่ถูกต้อง"]);
            }
        }
        break;

    case 'admin-login':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents("php://input"), true);
            $u = $input['username'] ?? ''; $p = $input['password'] ?? '';
            $stmt = $db->query("SELECT * FROM admins WHERE username = ? AND password = ?", [$u, $p]);
            if ($stmt->fetch()) { session_regenerate_id(true); $_SESSION['tvk_admin']=true; $_SESSION['tvk_admin_csrf']=bin2hex(random_bytes(24)); echo json_encode(['success'=>true,'adminCsrf'=>$_SESSION['tvk_admin_csrf']]); }
            else echo json_encode(["success" => false, "message" => "ข้อมูลไม่ถูกต้อง"]);
        }
        break;

    case 'semester-reset-config':
    case 'semester-reset-preview':
    case 'semester-reset-history':
    case 'semester-reset-save':
        if (empty($_SESSION['tvk_admin'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'กรุณาเข้าสู่ระบบผู้ดูแลอีกครั้ง']); break; }
        if ($endpoint==='semester-reset-save' && ($method!=='POST' || !hash_equals((string)($_SESSION['tvk_admin_csrf']??''),(string)($_SERVER['HTTP_X_ADMIN_CSRF']??'')))) {
            http_response_code(403);echo json_encode(['success'=>false,'message'=>'การยืนยันสิทธิ์ไม่ถูกต้อง']);break;
        }
        try {
            $config=semesterResetConfig($db);
            if ($endpoint==='semester-reset-save') {
                $body=json_decode(file_get_contents('php://input'),true)?:[];
                $config=semesterResetCleanConfig($body,$config);
                $db->query('INSERT INTO semester_reset_config(id,config_json) VALUES (1,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json)',[json_encode($config,JSON_UNESCAPED_UNICODE)]);
            }
            if ($endpoint==='semester-reset-history') {
                $result=['success'=>true,'history'=>$db->query('SELECT * FROM semester_reset_history ORDER BY id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC)];
            } else {
                $result=['success'=>true,'config'=>$config,'preview'=>semesterResetPreview($db,$config)];
            }
            echo json_encode($result,JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        break;


	case 'documents':
	    if ($method === 'GET') {
	        $documents = $db->query("
	            SELECT * FROM documents
	            ORDER BY created_at DESC
	        ")->fetchAll(PDO::FETCH_ASSOC);
	
	        $cdnUrl = 'https://cdn.tvk69.site/';
	
	        foreach ($documents as &$doc) {
	            if (!empty($doc['file_path'])) {
	                $path = trim(str_replace('\\', '/', $doc['file_path']));
	
	                // หากเป็น URL อยู่แล้ว ไม่ต้องแปลงซ้ำ
	                if (!preg_match('~^https?://~i', $path)) {
	
	                    // ลบ path ก่อนหน้า uploads/
	                    $path = preg_replace(
	                        '~^.*?uploads/~i',
	                        '',
	                        $path
	                    );
	
	                    // ลบ ../ และ / ที่นำหน้า
	                    $path = preg_replace('~^(\.\./)+~', '', $path);
	                    $path = ltrim($path, '/');
	
	                    // สร้าง URL เต็ม
	                    $doc['file_path'] = $cdnUrl . $path;
	                }
	            }
	        }
	        unset($doc);
	
	        header('Content-Type: application/json; charset=utf-8');
	        echo json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	    }
	    break;

    case 'upload-document':
        if ($method === 'POST') {
            $subject = $_POST['subject'] ?? '';
            $file = $_FILES['document'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(["success"=>false]); return; }
            if ($file['size'] > 10*1024*1024) { http_response_code(400); echo json_encode(["success"=>false, "message"=>"File > 10MB"]); return; }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['exe','bat','sh','mp4'])) { http_response_code(400); echo json_encode(["success"=>false]); return; }

            $unique = uniqid() . '_' . basename($file['name']);
            $dest = '../uploads/' . $unique;
            if(!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $filename = basename($file['name']);
                $db->query("INSERT INTO documents (subject, filename, file_path) VALUES (?, ?, ?)", [$subject, $filename, $dest]);
                
                // Send Email to students with gmail
                $students = $db->query("SELECT student_name, gmail FROM students WHERE gmail IS NOT NULL AND gmail != ''")->fetchAll();
                if (count($students) > 0) {
                    $emailSubject = "แจ้งเตือนเอกสารใหม่: " . $subject;
                    $headers = "From: admin@tvk69.site\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    
                    // Fetch teacher info based on subject
                    $teacher = $db->query("SELECT name, image FROM teachers WHERE subject LIKE ? LIMIT 1", ["%{$subject}%"])->fetch();
                    $teacherName = $teacher && !empty($teacher['name']) ? $teacher['name'] : 'ไม่ระบุ';
                    $teacherImage = $teacher && !empty($teacher['image']) ? $teacher['image'] : 'https://cdn.tvk69.site/tvk69.png';
                    
                    foreach ($students as $s) {
                        $emailBody = "
                        <div style='font-family: \"Kanit\", Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                            <div style='background: linear-gradient(135deg, #4f46e5, #9333ea); padding: 30px; text-align: center;'>
                                <img src='https://cdn.tvk69.site/tvk69.png' alt='TVK69 Logo' style='max-height: 80px; filter: drop-shadow(0px 4px 4px rgba(0,0,0,0.25));'>
                            </div>
                            <div style='padding: 40px 30px; color: #374151;'>
                                <h2 style='color: #1f2937; margin-top: 0;'>สวัสดีคุณ {$s['student_name']} 👋</h2>
                                <p style='font-size: 16px; line-height: 1.6; color: #4b5563;'>มีเอกสารใหม่ถูกเพิ่มเข้ามาในระบบ สำหรับรายวิชา <strong>{$subject}</strong></p>
                                
                                <div style='background-color: #f9fafb; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #4f46e5;'>
                                    <div style='display: flex; align-items: center; margin-bottom: 15px;'>
                                        <img src='{$teacherImage}' alt='Teacher Profile' style='width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 15px; border: 2px solid #e5e7eb;'>
                                        <div>
                                            <p style='margin: 0; font-size: 14px; color: #6b7280;'>ผู้สอน</p>
                                            <p style='margin: 0; font-size: 16px; font-weight: bold; color: #1f2937;'>{$teacherName}</p>
                                        </div>
                                    </div>
                                    <p style='margin: 0; font-size: 15px; color: #1f2937; padding-top: 12px; border-top: 1px solid #e5e7eb;'><strong>📄 ชื่อไฟล์:</strong> {$filename}</p>
                                </div>
                                
                                <p style='font-size: 16px; color: #4b5563;'>กรุณาเข้าสู่ระบบเพื่อตรวจสอบและดาวน์โหลดเอกสารได้เลยครับ</p>
                                
                                <div style='text-align: center; margin-top: 40px; margin-bottom: 20px;'>
                                    <a href='https://tvk69.site' style='background-color: #4f46e5; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4);'>เข้าสู่ระบบ TVK69</a>
                                </div>
                            </div>
                            <div style='background-color: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;'>
                                &copy; " . date('Y') . " TVK69. All rights reserved.<br>
                                อีเมลฉบับนี้เป็นการแจ้งเตือนจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ
                            </div>
                        </div>";
                        @mail($s['gmail'], $emailSubject, $emailBody, $headers);
                    }
                }
                
                // Send Line Broadcast
                $channelToken = "RGUGzoEuvQbivUxBtgr8RARjEmgWKqwSQWX0l8xtqjoyX4JBE9WK07OkFkFdwl1DCRgPw6nRHfWZJ3KSOnqhUa3Xj9ULvXYOJOXoCuyTKDWwaRw+7ZlpALI1dQA0YOlRaFpm/Bkw7g1VWgm2i8Js/wdB04t89/1O/w1cDnyilFU="; 
                $url = 'https://api.line.me/v2/bot/message/broadcast';
                $payload = [
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => "📢 มีเอกสารใหม่เพิ่มในระบบ!\nวิชา: {$subject}\nไฟล์: {$filename}\n\nกรุณาเข้าสู่ระบบเพื่อตรวจสอบครับ"
                        ]
                    ]
                ];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json; charset=UTF-8',
                    'Authorization: Bearer ' . $channelToken
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_exec($ch);
                curl_close($ch);

                $docId = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                $docPayload = $db->query("SELECT * FROM documents WHERE id = ? LIMIT 1", [$docId])->fetch(PDO::FETCH_ASSOC);
                realtimeBroadcastUpdate('documents', 'created', ['record' => $docPayload]);
                echo json_encode(["success" => true]);
            } else { http_response_code(500); realtimePushDashboard($db);
            echo json_encode(["success"=>false]); }
        }
        break;

    case 'document-update':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("UPDATE documents SET subject = ? WHERE id = ?", [$in['subject'], $in['id']]);
            $docPayload = $db->query("SELECT * FROM documents WHERE id = ? LIMIT 1", [$in['id']])->fetch(PDO::FETCH_ASSOC);
            realtimeBroadcastUpdate('documents', 'updated', ['record' => $docPayload]);
            realtimePushDashboard($db);
            echo json_encode(["success" => true]);
        }
        break;

    case 'document-delete':
        if ($method === 'DELETE') {
            $input = json_decode(file_get_contents("php://input"), true);
            $id = $input['id'] ?? 0;
            $doc = $db->query("SELECT file_path FROM documents WHERE id = ?", [$id])->fetch();
            if ($doc) {
                if (file_exists($doc['file_path'])) unlink($doc['file_path']);
                $db->query("DELETE FROM documents WHERE id = ?", [$id]);
                realtimeBroadcastUpdate('documents', 'deleted', ['id' => $id]);
                realtimePushDashboard($db);
            echo json_encode(["success" => true]);
            }
        }
        break;

    case 'students':
        if ($method === 'GET') echo json_encode($db->query("SELECT id, student_name, student_id, images, gmail FROM students ORDER BY student_id ASC")->fetchAll());
        break;
    
    case 'student-add':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("INSERT INTO students (student_name, student_id, images) VALUES (?, ?, '-')", [$in['name'], $in['studentId']]);
            $studentPayload = $db->query("SELECT id, student_name, student_id, images, gmail FROM students WHERE student_id = ? LIMIT 1", [$in['studentId']])->fetch(PDO::FETCH_ASSOC);
            realtimeBroadcastUpdate('students', 'created', ['record' => $studentPayload]);
            realtimePushDashboard($db);
            realtimePushRanking($db);
            echo json_encode(["success" => true]);
        }
        break;
    
    case 'student-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("DELETE FROM students WHERE id = ?", [$in['id']]);
            realtimeBroadcastUpdate('students', 'deleted', ['id' => $in['id']]);
            realtimePushDashboard($db);
            realtimePushRanking($db);
            echo json_encode(["success" => true]);
        }
        break;

    case 'student-update':
        if ($method === 'POST') { 
            $in = json_decode(file_get_contents("php://input"), true);
            $gmail = $in['gmail'] ?? null;
            $db->query("UPDATE students SET student_name = ?, student_id = ?, gmail = ? WHERE id = ?", [$in['name'], $in['studentId'], $gmail, $in['id']]);
            $studentPayload = $db->query("SELECT id, student_name, student_id, images, gmail FROM students WHERE student_id = ? LIMIT 1", [$in['studentId']])->fetch(PDO::FETCH_ASSOC);
            realtimeBroadcastUpdate('students', 'updated', ['record' => $studentPayload]);
            realtimePushDashboard($db);
            realtimePushRanking($db);
            echo json_encode(["success" => true]);
        }
        break;

    case 'student-image-upload':
        if ($method === 'POST') {
            $id = $_POST['student_id'] ?? '';
            $file = $_FILES['profile_image'] ?? null;
            if (!$file || $file['error'] !== 0) { echo json_encode(["success"=>false]); return; }
            
            $old = $db->query("SELECT images FROM students WHERE id = ?", [$id])->fetch();
            if($old && $old['images'] && file_exists('../'.$old['images'])) unlink('../'.$old['images']);

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newname = $id . '_' . time() . '.' . $ext;
            $dest = '../uploads/clients/' . $newname;
            if(!is_dir('../uploads/clients/')) mkdir('../uploads/clients/', 0777, true);
            
            if(move_uploaded_file($file['tmp_name'], $dest)) {
                $url = 'uploads/clients/'.$newname;
                $db->query("UPDATE students SET images = ? WHERE id = ?", [$url, $id]);
                $studentPayload = $db->query("SELECT id, student_name, student_id, images, gmail FROM students WHERE id = ? LIMIT 1", [$id])->fetch(PDO::FETCH_ASSOC);
                realtimeBroadcastUpdate('students', 'updated', ['record' => $studentPayload]);
                echo json_encode(["success"=>true]);
            }
        }
        break;

    case 'teachers': if ($method === 'GET') echo json_encode($db->query("SELECT * FROM teachers ORDER BY id ASC")->fetchAll()); break;
    case 'teacher-add':
    case 'teacher-update':
        if ($method === 'POST') {
            // Only authenticated administrators may change teacher/advisor settings.
            if (empty($_SESSION['tvk_admin'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'กรุณาเข้าสู่ระบบผู้ดูแล']); break; }
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $role = (string)($in['advisor_role'] ?? 'none');
            $color = (string)($in['highlight_color'] ?? '#4f46e5');
            if (!in_array($role, ['none','primary','co'], true) || !in_array($color, ['#4f46e5','#0284c7','#059669','#d97706','#db2777','#dc2626'], true)) {
                http_response_code(422); echo json_encode(['success'=>false,'message'=>'บทบาทหรือสีไฮไลท์ไม่ถูกต้อง']); break;
            }
            if (trim((string)($in['name'] ?? '')) === '' || trim((string)($in['subject'] ?? '')) === '') {
                http_response_code(422); echo json_encode(['success'=>false,'message'=>'กรุณาระบุชื่อและวิชา']); break;
            }
            try {
                $db->conn->beginTransaction();
                // Serialize advisor role changes for consistent unique primary/co assignments.
                $db->query('SELECT id FROM teachers ORDER BY id FOR UPDATE')->fetchAll();
                if ($role !== 'none') $db->query('UPDATE teachers SET advisor_role = ? WHERE advisor_role = ?', ['none',$role]);
                if ($endpoint === 'teacher-add') {
                    $db->query('INSERT INTO teachers (name,subject,phone,email,image,advisor_role,highlight_color) VALUES (?,?,?,?,?,?,?)',
                        [$in['name'],$in['subject'],$in['phone']??'', $in['email']??'', $in['image']??'', $role,$color]);
                    $teacherId = (int)$db->conn->lastInsertId();
                } else {
                    $teacherId = (int)($in['id'] ?? 0);
                    if (!$teacherId) throw new RuntimeException('ไม่พบรหัสอาจารย์');
                    $db->query('UPDATE teachers SET name=?,subject=?,phone=?,email=?,image=?,advisor_role=?,highlight_color=? WHERE id=?',
                        [$in['name'],$in['subject'],$in['phone']??'', $in['email']??'', $in['image']??'', $role,$color,$teacherId]);
                }
                $db->conn->commit();
                $teacherPayload = $db->query('SELECT * FROM teachers WHERE id=? LIMIT 1',[$teacherId])->fetch(PDO::FETCH_ASSOC);
                // Entire teacher list should refresh because previous advisor may have changed.
                realtimeBroadcastUpdate('teachers', 'updated', ['record'=>$teacherPayload]);
                realtimePushDashboard($db);
                echo json_encode(['success'=>true,'teacher'=>$teacherPayload]);
            } catch (Throwable $e) {
                if ($db->conn->inTransaction()) $db->conn->rollBack();
                http_response_code(500); echo json_encode(['success'=>false,'message'=>'บันทึกอาจารย์ไม่สำเร็จ กรุณาตรวจสอบการติดตั้ง SQL']);
            }
        }
        break;
    case 'teacher-details': if ($method==='GET') echo json_encode(array_merge(['success'=>true], $db->query('SELECT * FROM teachers WHERE id=?', [$teacher_id])->fetch() ?: [])); break;
    case 'teacher-delete': if($method==='DELETE') { $in=json_decode(file_get_contents("php://input"),true); $db->query("DELETE FROM teachers WHERE id=?",[$in['id']]); realtimeBroadcastUpdate('teachers', 'deleted', ['id'=>$in['id']]); realtimePushDashboard($db); echo json_encode(["success"=>true]); } break;

    case 'programs': if ($method === 'GET') echo json_encode($db->query("SELECT * FROM programs ORDER BY id ASC")->fetchAll()); break;
    case 'program-add': 
        if ($method === 'POST') { $in=json_decode(file_get_contents("php://input"),true); $db->query("INSERT INTO programs (name, version, description, link) VALUES (?,?,?,?)", [$in['name'], $in['version'], $in['description'], $in['link']]); $programId = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            $programPayload = $db->query("SELECT * FROM programs WHERE id = ? LIMIT 1", [$programId])->fetch(PDO::FETCH_ASSOC);
            realtimeBroadcastUpdate('programs', 'created', ['record' => $programPayload]); echo json_encode(["success"=>true]); } break;
    case 'program-details': if ($method==='GET') echo json_encode(array_merge(["success"=>true], $db->query("SELECT * FROM programs WHERE id=?", [$program_id])->fetch())); break;
    case 'program-update': if ($method==='POST') { $in=json_decode(file_get_contents("php://input"),true); $db->query("UPDATE programs SET name=?, version=?, description=?, link=? WHERE id=?", [$in['name'], $in['version'], $in['description'], $in['link'], $in['id']]); $programPayload = $db->query("SELECT * FROM programs WHERE id = ? LIMIT 1", [$in['id']])->fetch(PDO::FETCH_ASSOC);
            realtimeBroadcastUpdate('programs', 'updated', ['record' => $programPayload]); echo json_encode(["success"=>true]); } break;
    case 'program-delete': if ($method==='DELETE') { $in=json_decode(file_get_contents("php://input"),true); $db->query("DELETE FROM programs WHERE id=?",[$in['id']]); realtimeBroadcastUpdate('programs', 'deleted', ['id'=>$in['id']]); echo json_encode(["success"=>true]); } break;

    case 'get-bio-data':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $bio = $db->query("SELECT * FROM student_bios WHERE student_id = ?", [$in['studentId']])->fetch();
            $std = $db->query("SELECT student_name, images FROM students WHERE student_id = ?", [$in['studentId']])->fetch();
            echo json_encode(["success" => true, "bio" => $bio, "student" => $std]);
        } break;
    case 'save-bio':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $sid = $in['studentId']; $u = $in['username'];
            if($db->query("SELECT id FROM student_bios WHERE username = ? AND student_id != ?", [$u, $sid])->fetch()) { echo json_encode(["success"=>false, "message"=>"Username ซ้ำ"]); return; }

            // Preserve existing data when an older frontend omits new Bio fields.
            $existingBio = $db->query("SELECT * FROM student_bios WHERE student_id = ? LIMIT 1", [$sid])->fetch(PDO::FETCH_ASSOC) ?: [];
            $tagline = array_key_exists('tagline', $in) ? mb_substr((string)$in['tagline'], 0, 60) : ($existingBio['tagline'] ?? null);
            $musicAutoplay = array_key_exists('music_autoplay', $in) ? (int)(bool)$in['music_autoplay'] : (int)($existingBio['music_autoplay'] ?? 0);
            $customLinks = array_key_exists('custom_links', $in) ? $in['custom_links'] : ($existingBio['custom_links'] ?? '[]');
            $style = array_key_exists('style', $in) ? $in['style'] : ($existingBio['style'] ?? '{}');
            if (is_array($customLinks)) $customLinks = json_encode($customLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_array($style)) $style = json_encode($style, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($customLinks) || !is_array(json_decode($customLinks, true))) $customLinks = $existingBio['custom_links'] ?? '[]';
            if (!is_string($style) || !is_array(json_decode($style, true))) $style = $existingBio['style'] ?? '{}';

            if($db->query("SELECT id FROM student_bios WHERE student_id=?", [$sid])->fetch()) {
                $db->query("UPDATE student_bios SET username=?, bio_text=?, tagline=?, facebook_link=?, instagram_link=?, tiktok_link=?, line_id=?, theme_color=?, bg_music=?, music_autoplay=?, custom_links=?, style=? WHERE student_id=?",
                    [$u, $in['bio_text'], $tagline, $in['facebook'], $in['instagram'], $in['tiktok'], $in['line'], $in['theme_color'], $in['bg_music'], $musicAutoplay, $customLinks, $style, $sid]);
            } else {
                $db->query("INSERT INTO student_bios (student_id, username, bio_text, tagline, facebook_link, instagram_link, tiktok_link, line_id, theme_color, bg_music, music_autoplay, custom_links, style) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$sid, $u, $in['bio_text'], $tagline, $in['facebook'], $in['instagram'], $in['tiktok'], $in['line'], $in['theme_color'], $in['bg_music'], $musicAutoplay, $customLinks, $style]);
            }
            $bioPayload = $db->query("SELECT * FROM student_bios WHERE student_id = ? LIMIT 1", [$sid])->fetch(PDO::FETCH_ASSOC);
            realtimeEmit('bio:updated', ['bio' => $bioPayload], $sid);
            realtimeBroadcastUpdate('students', 'bio-updated', ['studentId' => $sid, 'bio' => $bioPayload]);
            echo json_encode(["success"=>true]);
        } break;

    case 'activity-ranking':
        if ($method === 'GET') {
            $sql = "SELECT
                        s.id,
                        s.student_id,
                        s.student_name,
                        s.images,
                        COALESCE(story_stats.story_count, 0) AS story_count,
                        COALESCE(comment_stats.comment_count, 0) AS comment_count,
                        COALESCE(reaction_stats.reaction_count, 0) AS reaction_count,
                        COALESCE(diary_stats.diary_count, 0) AS diary_count,
                        COALESCE(file_stats.file_count, 0) AS file_count,
                        (
                            COALESCE(story_stats.story_count, 0) +
                            COALESCE(comment_stats.comment_count, 0) +
                            COALESCE(reaction_stats.reaction_count, 0) +
                            COALESCE(diary_stats.diary_count, 0) +
                            COALESCE(file_stats.file_count, 0)
                        ) AS activity_count
                    FROM students s
                    LEFT JOIN (
                        SELECT student_id, COUNT(*) AS story_count
                        FROM intern_stories
                        WHERE COALESCE(is_anonymous, 0) = 0
                        GROUP BY student_id
                    ) story_stats ON story_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN (
                        SELECT student_id, COUNT(*) AS comment_count
                        FROM intern_comments
                        GROUP BY student_id
                    ) comment_stats ON comment_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN (
                        SELECT student_id, COUNT(*) AS reaction_count
                        FROM intern_story_reactions
                        GROUP BY student_id
                    ) reaction_stats ON reaction_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN (
                        SELECT student_id, COUNT(*) AS diary_count
                        FROM intern_diaries
                        GROUP BY student_id
                    ) diary_stats ON diary_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN (
                        SELECT student_id, COUNT(*) AS file_count
                        FROM student_files
                        GROUP BY student_id
                    ) file_stats ON file_stats.student_id COLLATE utf8mb4_unicode_ci = s.student_id COLLATE utf8mb4_unicode_ci
                    ORDER BY activity_count DESC,
                             comment_count DESC,
                             story_count DESC,
                             s.student_name ASC";

            $ranking = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // Ranking แบบ Competition Ranking เช่น 1, 2, 2, 4
            $rank = 0;
            $position = 0;
            $previousActivity = null;

            foreach ($ranking as &$row) {
                $position++;
                $activity = (int)$row['activity_count'];

                if ($previousActivity === null || $activity !== $previousActivity) {
                    $rank = $position;
                    $previousActivity = $activity;
                }

                $row['rank'] = $rank;

                foreach ([
                    'story_count',
                    'comment_count',
                    'reaction_count',
                    'diary_count',
                    'file_count',
                    'activity_count'
                ] as $key) {
                    $row[$key] = (int)$row[$key];
                }
            }
            unset($row);

            echo json_encode([
                'success' => true,
                'ranking' => $ranking,
                'counting_rule' => [
                    'story' => 1,
                    'comment' => 1,
                    'reaction' => 1,
                    'diary' => 1,
                    'saveme_upload' => 1,
                    'anonymous_story' => 0
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'dashboard-stats':
        if ($method === 'GET') {
            echo json_encode([
                "totalDocuments" => $db->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
                "totalStudents" => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
                "totalImages" => $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
                "totalUsage" => $db->query("SELECT usage_count FROM stats WHERE id = 1")->fetchColumn() ?? 0
            ]);
        }
        break;

    case 'saveme-stats':
        if ($method === 'GET') {
            $used = $db->query("SELECT SUM(file_size) FROM student_files")->fetchColumn() ?? 0;
            $max = 3 * 1024 * 1024 * 1024; 
            echo json_encode(["success"=>true, "used"=>(float)$used, "max"=>(float)$max, "percent"=>($used/$max)*100]);
        } break;

    case 'saveme-files':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $files = $db->query("SELECT * FROM student_files WHERE student_id = ? ORDER BY uploaded_at DESC", [$in['studentId']])->fetchAll();
            echo json_encode(["success"=>true, "files"=>$files]);
        } break;

    case 'saveme-upload':
        if ($method === 'POST') {
            $sid = $_POST['studentId'] ?? '';
            $file = $_FILES['file'] ?? null;
            $max = 3 * 1024 * 1024 * 1024;

            if(!$sid || !$file) { echo json_encode(["success"=>false, "message"=>"Missing Data"]); return; }
            
            $current = $db->query("SELECT SUM(file_size) FROM student_files")->fetchColumn() ?? 0;
            if(($current + $file['size']) > $max) { echo json_encode(["success"=>false, "message"=>"Storage Full (3GB Limit)"]); return; }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['bat','sh','php'])) { echo json_encode(["success"=>false, "message"=>"File type not allowed"]); return; }

            $dir = '../uploads/saveme/';
            if(!is_dir($dir)) mkdir($dir, 0777, true);
            
            $newname = $sid . '_' . time() . '_' . basename($file['name']);
            if(move_uploaded_file($file['tmp_name'], $dir.$newname)) {
                $db->query("INSERT INTO student_files (student_id, filename, file_path, file_size) VALUES (?, ?, ?, ?)", [$sid, $file['name'], 'uploads/saveme/'.$newname, $file['size']]);
                $fileId = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                $filePayload = $db->query("SELECT * FROM student_files WHERE id = ? LIMIT 1", [$fileId])->fetch(PDO::FETCH_ASSOC);
                realtimeEmit('saveme:created', ['file' => $filePayload], $sid);
                $adminFilePayload = $db->query(
                    "SELECT f.*, s.student_name, s.images FROM student_files f LEFT JOIN students s ON s.student_id COLLATE utf8mb4_unicode_ci = f.student_id COLLATE utf8mb4_unicode_ci WHERE f.id = ? LIMIT 1",
                    [$fileId]
                )->fetch(PDO::FETCH_ASSOC);
                realtimeEmit('saveme:admin-changed', ['action' => 'created', 'record' => $adminFilePayload]);
                realtimePushSaveMeStats($db);
                realtimePushRanking($db);
                echo json_encode(["success"=>true]);
            } else { echo json_encode(["success"=>false, "message"=>"Upload failed"]); }
        } break;

    case 'admin-saveme-all': 
        if ($method === 'GET') {
            $sql = "SELECT f.*, s.student_name, s.images 
                    FROM student_files f 
                    LEFT JOIN students s ON f.student_id = s.student_id 
                    ORDER BY f.uploaded_at DESC";
            echo json_encode($db->query($sql)->fetchAll());
        } break;
        
    case 'saveme-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            
            $isAdmin = isset($in['isAdmin']) && $in['isAdmin'] === true;
            $sql = $isAdmin ? "SELECT file_path, student_id FROM student_files WHERE id = ?" : "SELECT file_path, student_id FROM student_files WHERE id = ? AND student_id = ?";
            $params = $isAdmin ? [$in['id']] : [$in['id'], $in['studentId']];
            
            $file = $db->query($sql, $params)->fetch();
            
            if ($file) {
                if(file_exists('../'.$file['file_path'])) unlink('../'.$file['file_path']);
                
                $delSql = $isAdmin ? "DELETE FROM student_files WHERE id = ?" : "DELETE FROM student_files WHERE id = ? AND student_id = ?";
                $db->query($delSql, $params);
                
                $deletedStudentId = $file['student_id'] ?? ($in['studentId'] ?? null);
                if ($deletedStudentId) {
                    realtimeEmit('saveme:deleted', ['id' => (int)$in['id']], $deletedStudentId);
                }
                realtimeEmit('saveme:admin-changed', ['action' => 'deleted', 'id' => (int)$in['id']]);
                realtimePushSaveMeStats($db);
                realtimePushRanking($db);
                echo json_encode(["success"=>true]);
            } else { echo json_encode(["success"=>false, "message"=>"File not found or permission denied"]); }
        } break;

    // --- Intern Stories & Comments ---
    case 'intern-stories':
        if ($method === 'GET' || $method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $studentId = $in['studentId'] ?? '';

            $sql = "SELECT s.*, st.student_name, st.images 
                    FROM intern_stories s 
                    LEFT JOIN students st ON s.student_id = st.student_id 
                    ORDER BY s.created_at DESC";
            $stories = $db->query($sql)->fetchAll();

            foreach ($stories as &$story) {
                $cSql = "SELECT c.*, st.student_name, st.images 
                         FROM intern_comments c 
                         LEFT JOIN students st ON c.student_id = st.student_id 
                         WHERE c.story_id = ? 
                         ORDER BY c.created_at ASC";
                $story['comments'] = $db->query($cSql, [$story['id']])->fetchAll();

                $story['my_reaction'] = null;
                if ($studentId) {
                    $rSql = "SELECT reaction_type FROM intern_story_reactions WHERE story_id = ? AND student_id = ?";
                    $myReact = $db->query($rSql, [$story['id'], $studentId])->fetch();
                    if ($myReact) {
                        $story['my_reaction'] = $myReact['reaction_type'];
                    }
                }
            }
            echo json_encode($stories);
        } break;

    case 'intern-story-add':
        if ($method === 'POST') {
            $studentId = $_POST['studentId'] ?? '';
            $content = $_POST['content'] ?? '';
            $bgClass = $_POST['bgClass'] ?? '';
            $isAnonymous = (isset($_POST['isAnonymous']) && $_POST['isAnonymous'] === 'true') ? 1 : 0;
            
            if (empty($studentId)) {
                echo json_encode(["success" => false, "message" => "Missing studentId"]);
                return;
            }

            $uploadedImages = [];
            
            // ตรวจสอบว่ามีการส่งไฟล์มาหรือไม่ แบบ Array
            if (isset($_FILES['images'])) {
                $files = $_FILES['images'];
                $count = count($files['name']);
                
                if ($count > 4) $count = 4; // จำกัดสูงสุด 4 รูป

                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === 0) {
                        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                        $newname = 'story_' . $studentId . '_' . time() . '_' . $i . '.' . $ext;
                        $dest = '../uploads/stories/' . $newname;
                        
                        if (!is_dir('../uploads/stories/')) {
                            mkdir('../uploads/stories/', 0777, true);
                        }
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                            $uploadedImages[] = 'uploads/stories/' . $newname; 
                        }
                    }
                }
            }

            // แปลง Array รูปให้เป็น JSON ถ้าไม่มีรูปให้เป็น null
            $imageUrlJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;

            if (!empty($content) || $imageUrlJson) {
                $db->query("INSERT INTO intern_stories (student_id, content, is_anonymous, image_url, bg_class) VALUES (?, ?, ?, ?, ?)", 
                    [$studentId, $content, $isAnonymous, $imageUrlJson, $bgClass]);

                $student = $db->query("SELECT student_name FROM students WHERE student_id = ? LIMIT 1", [$studentId])->fetch();
                $actorName = $isAnonymous ? 'ผู้ใช้งานไม่ระบุตัวตน' : (($student && !empty($student['student_name'])) ? $student['student_name'] : $studentId);
                $imageCount = count($uploadedImages);

                if ($imageCount > 0 && !empty($content)) {
                    $eventText = "{$actorName} โพสต์ข้อความพร้อมรูปภาพใหม่ {$imageCount} รูป";
                } elseif ($imageCount > 0) {
                    $eventText = "{$actorName} โพสต์รูปภาพใหม่ {$imageCount} รูป";
                } else {
                    $eventText = "{$actorName} โพสต์ข้อความใหม่";
                }

                notifyAllStudents(
                    $db,
                    'มีโพสต์ใหม่ใน Intern Stories',
                    $eventText,
                    "📢 Intern Stories\n{$eventText}\n\nเข้าไปดูได้ที่ https://tvk69.site"
                );

                $storyId = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                $storyPayload = realtimeGetStory($db, $storyId);
                realtimeEmit('story:created', ['story' => $storyPayload]);
                realtimePushRanking($db);
                echo json_encode(["success" => true, "storyId" => $storyId]);
            } else { 
                echo json_encode(["success" => false, "message" => "Content and image cannot be both empty"]); 
            }
        } 
        break;

    case 'intern-story-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            
            $oldStory = $db->query("SELECT image_url FROM intern_stories WHERE id = ?", [$in['id']])->fetch();
            if ($oldStory && !empty($oldStory['image_url'])) {
                $images = json_decode($oldStory['image_url'], true);
                if (is_array($images)) {
                    foreach ($images as $img) {
                        if (file_exists('../' . $img)) unlink('../' . $img);
                    }
                } elseif (file_exists('../' . $oldStory['image_url'])) {
                    // รองรับรูปเดี่ยวในระบบเก่า
                    unlink('../' . $oldStory['image_url']);
                }
            }

            $db->query("DELETE FROM intern_stories WHERE id = ?", [$in['id']]);
            $db->query("DELETE FROM intern_comments WHERE story_id = ?", [$in['id']]);
            $db->query("DELETE FROM intern_story_reactions WHERE story_id = ?", [$in['id']]);

            realtimeEmit('story:deleted', ['storyId' => (int)$in['id']]);
            realtimePushRanking($db);
            echo json_encode(["success" => true]);
        } break;
        
    case 'comment-add':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['storyId']) && !empty($in['studentId']) && !empty($in['text'])) {
                // 1. เพิ่มคอมเมนต์
                $db->query("INSERT INTO intern_comments (story_id, student_id, comment_text) VALUES (?, ?, ?)", 
                    [$in['storyId'], $in['studentId'], $in['text']]);
                $commentId = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                $commentPayload = $db->query(
                    "SELECT c.*, st.student_name, st.images
                     FROM intern_comments c
                     LEFT JOIN students st
                       ON st.student_id COLLATE utf8mb4_unicode_ci = c.student_id COLLATE utf8mb4_unicode_ci
                     WHERE c.id = ? LIMIT 1",
                    [$commentId]
                )->fetch(PDO::FETCH_ASSOC);
                
                // 2. สร้างแจ้งเตือนไปยังเจ้าของโพสต์ (ดึง student_id ของเจ้าของโพสต์มา)
                $owner = $db->query("SELECT student_id FROM intern_stories WHERE id = ?", [$in['storyId']])->fetch();
                if ($owner && $owner['student_id'] !== $in['studentId']) {
                    $db->query("INSERT INTO notifications (recipient_student_id, sender_student_id, story_id, type) VALUES (?, ?, ?, 'comment')", 
                        [$owner['student_id'], $in['studentId'], $in['storyId']]);

                    $sender = $db->query("SELECT student_name FROM students WHERE student_id = ? LIMIT 1", [$in['studentId']])->fetch();
                    $senderName = ($sender && !empty($sender['student_name'])) ? $sender['student_name'] : $in['studentId'];

                    notifyStudentAndLine(
                        $db,
                        $owner['student_id'],
                        'มีคอมเมนต์ใหม่ในโพสต์ของคุณ',
                        "{$senderName} แสดงความคิดเห็นในโพสต์ของคุณ: {$in['text']}",
                        "💬 มีคอมเมนต์ใหม่ใน Intern Stories\n{$senderName} แสดงความคิดเห็นในโพสต์หนึ่ง\nเข้าไปตรวจสอบได้ที่ https://tvk69.site"
                    );

                    $notificationId = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                    realtimeEmit('notification:new', [
                        'notification' => [
                            'id' => $notificationId,
                            'recipient_student_id' => $owner['student_id'],
                            'sender_student_id' => $in['studentId'],
                            'student_name' => $senderName,
                            'story_id' => (int)$in['storyId'],
                            'type' => 'comment',
                            'is_read' => 0
                        ]
                    ], $owner['student_id']);
                }
                
                realtimeEmit('comment:created', [
                    'storyId' => (int)$in['storyId'],
                    'comment' => $commentPayload
                ]);
                realtimePushRanking($db);
                echo json_encode([
                    "success" => true,
                    "storyId" => (int)$in['storyId'],
                    "comment" => $commentPayload
                ]);
            } else { echo json_encode(["success" => false]); }
        } break;

    case 'comment-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("DELETE FROM intern_comments WHERE id = ? AND student_id = ?", [$in['id'], $in['studentId']]);
            realtimeEmit('comment:deleted', [
                'commentId' => (int)$in['id']
            ]);
            realtimePushRanking($db);
            echo json_encode([
                "success" => true,
                "commentId" => (int)$in['id']
            ]);
        } 
        break;

    case 'comment-update':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['id']) && !empty($in['studentId']) && !empty($in['text'])) {
                $db->query("UPDATE intern_comments SET comment_text = ? WHERE id = ? AND student_id = ?", 
                    [$in['text'], $in['id'], $in['studentId']]);
                realtimeEmit('comment:updated', [
                    'commentId' => (int)$in['id'],
                    'text' => $in['text']
                ]);
                echo json_encode([
                    "success" => true,
                    "commentId" => (int)$in['id'],
                    "text" => $in['text']
                ]);
            } else { echo json_encode(["success" => false]); }
        } 
        break;

    case 'story-react':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $storyId = $in['storyId'] ?? null;
            $studentId = $in['studentId'] ?? null;
            $type = $in['reactionType'] ?? null;

            if ($storyId && $studentId && in_array($type, ['like', 'heart', 'haha'])) {
                $sqlCheck = "SELECT reaction_type FROM intern_story_reactions WHERE story_id = ? AND student_id = ?";
                $existing = $db->query($sqlCheck, [$storyId, $studentId])->fetch();

                if ($existing) {
                    $oldType = $existing['reaction_type'];
                    
                    if ($oldType === $type) {
                        $db->query("DELETE FROM intern_story_reactions WHERE story_id = ? AND student_id = ?", [$storyId, $studentId]);
                        $db->query("UPDATE intern_stories SET {$oldType}s = {$oldType}s - 1 WHERE id = ?", [$storyId]);
                    } else {
                        $db->query("UPDATE intern_story_reactions SET reaction_type = ? WHERE story_id = ? AND student_id = ?", [$type, $storyId, $studentId]);
                        $db->query("UPDATE intern_stories SET {$oldType}s = {$oldType}s - 1, {$type}s = {$type}s + 1 WHERE id = ?", [$storyId]);
                    }
                } else {
                    // 3. ยังไม่เคยกด = บันทึกใหม่ (Toggle ON)
                    $db->query("INSERT INTO intern_story_reactions (story_id, student_id, reaction_type) VALUES (?, ?, ?)", [$storyId, $studentId, $type]);
                    $db->query("UPDATE intern_stories SET {$type}s = {$type}s + 1 WHERE id = ?", [$storyId]);
                    
                    // --- เพิ่มส่วนนี้เพื่อส่งการแจ้งเตือน ---
                    $owner = $db->query("SELECT student_id FROM intern_stories WHERE id = ?", [$storyId])->fetch();
                    if ($owner && $owner['student_id'] !== $studentId) {
                        $db->query("INSERT INTO notifications (recipient_student_id, sender_student_id, story_id, type) VALUES (?, ?, ?, 'like')", 
                            [$owner['student_id'], $studentId, $storyId]);

                        $sender = $db->query("SELECT student_name FROM students WHERE student_id = ? LIMIT 1", [$studentId])->fetch();
                        $senderName = ($sender && !empty($sender['student_name'])) ? $sender['student_name'] : $studentId;
                        $reactionLabels = ['like' => '👍 ถูกใจ', 'heart' => '❤️ รักเลย', 'haha' => '😂 ฮ่าๆ'];
                        $reactionLabel = $reactionLabels[$type] ?? $type;

                        notifyStudentAndLine(
                            $db,
                            $owner['student_id'],
                            'มี Reaction ใหม่ในโพสต์ของคุณ',
                            "{$senderName} กด {$reactionLabel} ที่โพสต์ของคุณ",
                            "✨ มี Reaction ใหม่ใน Intern Stories\n{$senderName} กด {$reactionLabel} ที่โพสต์หนึ่ง\nเข้าไปดูได้ที่ https://tvk69.site"
                        );

                        realtimeEmit('notification:new', [
                            'type' => 'like',
                            'storyId' => $storyId,
                            'senderStudentId' => $studentId
                        ], $owner['student_id']);
                    }
                }
                
                $reactionRow = $db->query(
                    "SELECT reaction_type FROM intern_story_reactions WHERE story_id = ? AND student_id = ? LIMIT 1",
                    [$storyId, $studentId]
                )->fetch(PDO::FETCH_ASSOC);

                $storyCounts = $db->query(
                    "SELECT likes, hearts, hahas FROM intern_stories WHERE id = ? LIMIT 1",
                    [$storyId]
                )->fetch(PDO::FETCH_ASSOC);

                realtimeEmit('reaction:updated', [
                    'storyId' => (int)$storyId,
                    'likes' => (int)($storyCounts['likes'] ?? 0),
                    'hearts' => (int)($storyCounts['hearts'] ?? 0),
                    'hahas' => (int)($storyCounts['hahas'] ?? 0),
                    'actorStudentId' => $studentId,
                    'actorReaction' => $reactionRow['reaction_type'] ?? null
                ]);
                realtimePushRanking($db);
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid data"]);
            }
        }
        break;

    // --- Internship Diary ---
    case 'diary-get':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['studentId'])) {
                $sql = "SELECT * FROM intern_diaries WHERE student_id = ? ORDER BY date DESC, created_at DESC";
                $entries = $db->query($sql, [$in['studentId']])->fetchAll();
                echo json_encode(["success" => true, "entries" => $entries]);
            } else {
                echo json_encode(["success" => false, "message" => "Missing studentId"]);
            }
        }
        break;

    case 'diary-add':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $sid = trim((string)($in['studentId'] ?? ''));
            $date = (string)($in['date'] ?? '');
            $status = (string)($in['attendanceStatus'] ?? 'present');
            $hours = (float)($in['hours'] ?? 0);
            $task = trim((string)($in['task'] ?? ''));
            if (!$sid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($status,['present','late','leave','absent'],true)
                || ($status === 'present' || $status === 'late' ? ($hours <= 0 || $hours > 24 || $task === '') : $hours != 0)) {
                http_response_code(422); echo json_encode(['success'=>false,'message'=>'ตรวจสอบวันที่ สถานะ งาน และชั่วโมง']); break;
            }
            $existing=$db->query('SELECT id FROM intern_diaries WHERE student_id=? AND date=? LIMIT 1',[$sid,$date])->fetch(PDO::FETCH_ASSOC);
            if ($existing) {http_response_code(409);echo json_encode(['success'=>false,'message'=>'วันที่นี้มีบันทึกแล้ว กรุณาแก้ไขรายการเดิม']);break;}
            $late=$status==='late'?min(1440,max(1,(int)($in['lateMinutes']??0))):0;
            $leave=$status==='leave' && in_array(($in['leaveType']??''),['sick','personal','other'],true)?$in['leaveType']:'';
            $db->query('INSERT INTO intern_diaries (student_id,date,hours,task,details,problem,solution,attendance_status,late_minutes,leave_type) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$sid,$date,$hours,$task,$in['details']??'',$in['problem']??'',$in['solution']??'',$status,$late,$leave]);
            $id=(int)$db->query('SELECT LAST_INSERT_ID()')->fetchColumn();
            $record=$db->query('SELECT * FROM intern_diaries WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            realtimeEmit('diary:created',['entry'=>$record],$sid);
            realtimePushRanking($db);
            echo json_encode(['success'=>true,'entry'=>$record]);
        } break;

    case 'diary-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['id']) && !empty($in['studentId'])) {
                $db->query("DELETE FROM intern_diaries WHERE id = ? AND student_id = ?", [$in['id'], $in['studentId']]);
                realtimeEmit('diary:deleted', ['id' => (int)$in['id']], $in['studentId']);
                realtimePushRanking($db);
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false]);
            }
        }
        break;

	// ดึงรายการแจ้งเตือนของนักเรียนคนนั้นๆ
	case 'get-notifications':
    if ($method === 'POST') {
        $in = json_decode(file_get_contents("php://input"), true);
        $sid = $in['studentId'] ?? '';
        
        // ดึงเฉพาะของตัวเองเท่านั้น
        $sql = "SELECT n.*, s.student_name 
                FROM notifications n 
                LEFT JOIN students s ON n.sender_student_id = s.student_id 
                WHERE n.recipient_student_id = ? 
                ORDER BY n.created_at DESC";
        echo json_encode($db->query($sql, [$sid])->fetchAll());
    } break;
	
	// อัปเดตสถานะว่าอ่านแล้ว
	case 'mark-notification-read':
	    if ($method === 'POST') {
	        $in = json_decode(file_get_contents("php://input"), true);
	        $db->query("UPDATE notifications SET is_read = 1 WHERE id = ?", [$in['id']]);
            if (!empty($in['studentId'])) {
                realtimeEmit('notification:refresh', ['id' => $in['id']], $in['studentId']);
            }
	        echo json_encode(["success" => true]);
	    } break;
		
    case 'diary-update':
        if ($method === 'POST') {
            $in=json_decode(file_get_contents('php://input'),true)?:[];
            $sid=trim((string)($in['studentId']??''));$id=(int)($in['id']??0);
            $date=(string)($in['date']??'');$status=(string)($in['attendanceStatus']??'present');
            $hours=(float)($in['hours']??0);$task=trim((string)($in['task']??''));
            if (!$sid || $id<1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !in_array($status,['present','late','leave','absent'],true)
                || (in_array($status,['present','late'],true) ? ($hours<=0 || $hours>24 || $task==='') : $hours!=0)) {
                http_response_code(422);echo json_encode(['success'=>false,'message'=>'ข้อมูลไม่ถูกต้อง']);break;
            }
            $owned=$db->query('SELECT id FROM intern_diaries WHERE id=? AND student_id=?',[$id,$sid])->fetch(PDO::FETCH_ASSOC);
            if (!$owned){http_response_code(404);echo json_encode(['success'=>false,'message'=>'ไม่พบบันทึก']);break;}
            $duplicate=$db->query('SELECT id FROM intern_diaries WHERE student_id=? AND date=? AND id<>? LIMIT 1',[$sid,$date,$id])->fetch(PDO::FETCH_ASSOC);
            if ($duplicate){http_response_code(409);echo json_encode(['success'=>false,'message'=>'วันที่นี้มีบันทึกอื่นแล้ว']);break;}
            $late=$status==='late'?min(1440,max(1,(int)($in['lateMinutes']??0))):0;
            $leave=$status==='leave'&&in_array(($in['leaveType']??''),['sick','personal','other'],true)?$in['leaveType']:'';
            $db->query('UPDATE intern_diaries SET date=?,hours=?,task=?,details=?,problem=?,solution=?,attendance_status=?,late_minutes=?,leave_type=? WHERE id=? AND student_id=?',
                [$date,$hours,$task,$in['details']??'',$in['problem']??'',$in['solution']??'',$status,$late,$leave,$id,$sid]);
            $entry=$db->query('SELECT * FROM intern_diaries WHERE id=? AND student_id=?',[$id,$sid])->fetch(PDO::FETCH_ASSOC);
            realtimeEmit('diary:updated',['entry'=>$entry],$sid);
            echo json_encode(['success'=>true,'entry'=>$entry]);
        } break;

    case 'attendance-summary':
        if ($method === 'POST') {
            $in=json_decode(file_get_contents('php://input'),true)?:[];
            $sid=trim((string)($in['studentId']??''));
            if (!$sid){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Missing studentId']);break;}
            // Daily grouping protects totals from duplicate legacy diary rows.
            $sql="SELECT COUNT(*) AS recorded_days, COALESCE(SUM(day_hours),0) AS hours,
                    SUM(is_present=1 AND is_late=0 AND is_leave=0 AND is_absent=0) AS present_days,
                    SUM(is_late=1 AND is_leave=0 AND is_absent=0) AS late_count,
                    SUM(is_leave=1 AND is_absent=0) AS leave_days, SUM(is_absent) AS absent_days
                  FROM (SELECT date, SUM(hours) AS day_hours,
                      MAX(attendance_status='present') AS is_present,
                      MAX(attendance_status='late') AS is_late,
                      MAX(attendance_status='leave') AS is_leave,
                      MAX(attendance_status='absent') AS is_absent
                    FROM intern_diaries WHERE student_id=? GROUP BY date) daily";
            $stats=$db->query($sql,[$sid])->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'summary'=>$stats]);
        } break;

    case 'quota-get':
    case 'resume-get':
        if ($method === 'POST') {
            $in=json_decode(file_get_contents('php://input'),true)?:[]; $sid=trim((string)($in['studentId']??''));
            if(!$sid){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Missing studentId']);break;}
            $type=$endpoint==='quota-get'?'quota':'resume';
            $row=$db->query('SELECT data_json FROM student_app_data WHERE student_id=? AND data_type=? LIMIT 1',[$sid,$type])->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,$type=>$row?json_decode($row['data_json'],true):null]);
        } break;

    case 'quota-save':
    case 'resume-save':
        if ($method === 'POST') {
            $in=json_decode(file_get_contents('php://input'),true)?:[]; $sid=trim((string)($in['studentId']??''));
            $type=$endpoint==='quota-save'?'quota':'resume';
            $payload=$type==='quota'?($in['settings']??null):['fields'=>$in['fields']??[],'photo'=>$in['photo']??''];
            if(!$sid || !is_array($payload) || strlen(json_encode($payload))>100000){http_response_code(422);echo json_encode(['success'=>false,'message'=>'ข้อมูลไม่ถูกต้องหรือมีขนาดใหญ่เกินไป']);break;}
            $db->query('INSERT INTO student_app_data (student_id,data_type,data_json) VALUES (?,?,?) ON DUPLICATE KEY UPDATE data_json=VALUES(data_json),updated_at=CURRENT_TIMESTAMP',
                [$sid,$type,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            echo json_encode(['success'=>true]);
        } break;

    default: http_response_code(404); echo json_encode(["message" => "Endpoint not found."]); break;
}
?>