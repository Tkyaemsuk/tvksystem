<?php
// enpoints_v2.php

require_once 'db.php';
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

if (count($path_parts) >= 2) {
    $parent_endpoint = $path_parts[count($path_parts) - 2];
    $dynamic_id = end($path_parts);
    if ($parent_endpoint === 'teacher-details') { $endpoint = 'teacher-details'; $teacher_id = $dynamic_id; }
    elseif ($parent_endpoint === 'program-details') { $endpoint = 'program-details'; $program_id = $dynamic_id; }
}

switch ($endpoint) {
    case 'login':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents("php://input"), true);
            $roomCode = $input['roomCode'] ?? '';
            $stmt = $db->query("SELECT * FROM rooms WHERE room_code = ?", [$roomCode]);
            $room = $stmt->fetch();
            if ($room) {
                $db->query("UPDATE stats SET usage_count = usage_count + 1 WHERE id = 1");
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
            if ($stmt->fetch()) echo json_encode(["success" => true]);
            else echo json_encode(["success" => false, "message" => "ข้อมูลไม่ถูกต้อง"]);
        }
        break;

    case 'documents':
        if ($method === 'GET') {
            $documents = $db->query("SELECT * FROM documents ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($documents as &$doc) {
                if (!empty($doc['file_path'])) {
                    $doc['file_path'] = str_replace('../uploads/', 'https://cdn.tvk69.site/', $doc['file_path']);
                }
            }
            unset($doc); 
            echo json_encode($documents);
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
                    $headers = "From: [Gmail Server Here]\r\n";
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
                $channelToken = "TOKEN"; 
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

                echo json_encode(["success" => true]);
            } else { http_response_code(500); echo json_encode(["success"=>false]); }
        }
        break;

    case 'document-update':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("UPDATE documents SET subject = ? WHERE id = ?", [$in['subject'], $in['id']]);
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
            echo json_encode(["success" => true]);
        }
        break;
    
    case 'student-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("DELETE FROM students WHERE id = ?", [$in['id']]);
            echo json_encode(["success" => true]);
        }
        break;

    case 'student-update':
        if ($method === 'POST') { 
            $in = json_decode(file_get_contents("php://input"), true);
            $gmail = $in['gmail'] ?? null;
            $db->query("UPDATE students SET student_name = ?, student_id = ?, gmail = ? WHERE id = ?", [$in['name'], $in['studentId'], $gmail, $in['id']]);
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
                echo json_encode(["success"=>true]);
            }
        }
        break;

    case 'teachers': if ($method === 'GET') echo json_encode($db->query("SELECT * FROM teachers ORDER BY id ASC")->fetchAll()); break;
    case 'teacher-add': 
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("INSERT INTO teachers (name, subject, phone, email, image) VALUES (?,?,?,?,?)", [$in['name'], $in['subject'], $in['phone'], $in['email'], $in['image']]);
            echo json_encode(["success"=>true]);
        } break;
    case 'teacher-details': if ($method==='GET') echo json_encode(array_merge(["success"=>true], $db->query("SELECT * FROM teachers WHERE id=?", [$teacher_id])->fetch())); break;
    case 'teacher-update':
        if($method==='POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("UPDATE teachers SET name=?, subject=?, phone=?, email=?, image=? WHERE id=?", [$in['name'], $in['subject'], $in['phone'], $in['email'], $in['image'], $in['id']]);
            echo json_encode(["success"=>true]);
        } break;
    case 'teacher-delete': if($method==='DELETE') { $in=json_decode(file_get_contents("php://input"),true); $db->query("DELETE FROM teachers WHERE id=?",[$in['id']]); echo json_encode(["success"=>true]); } break;

    case 'programs': if ($method === 'GET') echo json_encode($db->query("SELECT * FROM programs ORDER BY id ASC")->fetchAll()); break;
    case 'program-add': 
        if ($method === 'POST') { $in=json_decode(file_get_contents("php://input"),true); $db->query("INSERT INTO programs (name, version, description, link) VALUES (?,?,?,?)", [$in['name'], $in['version'], $in['description'], $in['link']]); echo json_encode(["success"=>true]); } break;
    case 'program-details': if ($method==='GET') echo json_encode(array_merge(["success"=>true], $db->query("SELECT * FROM programs WHERE id=?", [$program_id])->fetch())); break;
    case 'program-update': if ($method==='POST') { $in=json_decode(file_get_contents("php://input"),true); $db->query("UPDATE programs SET name=?, version=?, description=?, link=? WHERE id=?", [$in['name'], $in['version'], $in['description'], $in['link'], $in['id']]); echo json_encode(["success"=>true]); } break;
    case 'program-delete': if ($method==='DELETE') { $in=json_decode(file_get_contents("php://input"),true); $db->query("DELETE FROM programs WHERE id=?",[$in['id']]); echo json_encode(["success"=>true]); } break;

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
            if($db->query("SELECT id FROM student_bios WHERE student_id=?", [$sid])->fetch()) {
                $db->query("UPDATE student_bios SET username=?, bio_text=?, facebook_link=?, instagram_link=?, tiktok_link=?, line_id=?, theme_color=?, bg_music=? WHERE student_id=?", [$u, $in['bio_text'], $in['facebook'], $in['instagram'], $in['tiktok'], $in['line'], $in['theme_color'], $in['bg_music'], $sid]);
            } else {
                $db->query("INSERT INTO student_bios (student_id, username, bio_text, facebook_link, instagram_link, tiktok_link, line_id, theme_color, bg_music) VALUES (?,?,?,?,?,?,?,?,?)", [$sid, $u, $in['bio_text'], $in['facebook'], $in['instagram'], $in['tiktok'], $in['line'], $in['theme_color'], $in['bg_music']]);
            }
            echo json_encode(["success"=>true]);
        } break;

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
            $sql = $isAdmin ? "SELECT file_path FROM student_files WHERE id = ?" : "SELECT file_path FROM student_files WHERE id = ? AND student_id = ?";
            $params = $isAdmin ? [$in['id']] : [$in['id'], $in['studentId']];
            
            $file = $db->query($sql, $params)->fetch();
            
            if ($file) {
                if(file_exists('../'.$file['file_path'])) unlink('../'.$file['file_path']);
                
                $delSql = $isAdmin ? "DELETE FROM student_files WHERE id = ?" : "DELETE FROM student_files WHERE id = ? AND student_id = ?";
                $db->query($delSql, $params);
                
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
                echo json_encode(["success" => true]);
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

            echo json_encode(["success" => true]);
        } break;
        
    case 'comment-add':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['storyId']) && !empty($in['studentId']) && !empty($in['text'])) {
                // 1. เพิ่มคอมเมนต์
                $db->query("INSERT INTO intern_comments (story_id, student_id, comment_text) VALUES (?, ?, ?)", 
                    [$in['storyId'], $in['studentId'], $in['text']]);
                
                // 2. สร้างแจ้งเตือนไปยังเจ้าของโพสต์ (ดึง student_id ของเจ้าของโพสต์มา)
                $owner = $db->query("SELECT student_id FROM intern_stories WHERE id = ?", [$in['storyId']])->fetch();
                if ($owner && $owner['student_id'] !== $in['studentId']) {
                    $db->query("INSERT INTO notifications (recipient_student_id, sender_student_id, story_id, type) VALUES (?, ?, ?, 'comment')", 
                        [$owner['student_id'], $in['studentId'], $in['storyId']]);
                }
                
                echo json_encode(["success" => true]);
            } else { echo json_encode(["success" => false]); }
        } break;

    case 'comment-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            $db->query("DELETE FROM intern_comments WHERE id = ? AND student_id = ?", [$in['id'], $in['studentId']]);
            echo json_encode(["success" => true]);
        } 
        break;

    case 'comment-update':
        if ($method === 'POST') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['id']) && !empty($in['studentId']) && !empty($in['text'])) {
                $db->query("UPDATE intern_comments SET comment_text = ? WHERE id = ? AND student_id = ?", 
                    [$in['text'], $in['id'], $in['studentId']]);
                echo json_encode(["success" => true]);
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
                    }
                }
                
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
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['studentId']) && !empty($in['date']) && !empty($in['hours'])) {
                $task = $in['task'] ?? '';
                $details = $in['details'] ?? '';
                $problem = $in['problem'] ?? '';
                $solution = $in['solution'] ?? '';

                $db->query("INSERT INTO intern_diaries (student_id, date, hours, task, details, problem, solution) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                    [$in['studentId'], $in['date'], $in['hours'], $task, $details, $problem, $solution]);
                    
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "Incomplete data"]);
            }
        }
        break;

    case 'diary-delete':
        if ($method === 'DELETE') {
            $in = json_decode(file_get_contents("php://input"), true);
            if (!empty($in['id']) && !empty($in['studentId'])) {
                $db->query("DELETE FROM intern_diaries WHERE id = ? AND student_id = ?", [$in['id'], $in['studentId']]);
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
	        echo json_encode(["success" => true]);
	    } break;
		
    default: http_response_code(404); echo json_encode(["message" => "Endpoint not found."]); break;
}
?>