<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

// In ra ngay lập tức, tránh “đứng” do buffer
@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
ob_implicit_flush(true);

// === DEBUG GLOBAL ===
$DEBUG = false;
$DEBUG_FILE = '';
@mkdir('logs', 0777, true);
function debug_log($msg) {
    global $DEBUG, $DEBUG_FILE;
    if (!$DEBUG) return;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($DEBUG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

// ====== HÀM DÙNG CHUNG CHO MÀU ======
function ok($msg){ echo "\033[1;32m{$msg}\033[0m\n"; }     // Xanh lá
function err($msg){ echo "\033[1;31m{$msg}\033[0m\n"; }    // Đỏ
function prompt($msg){ echo "\033[1;32m{$msg}\033[0m"; fflush(STDOUT); } // Prompt xanh + flush

// ====== HÀM IN TRẠNG THÁI 1 DÒNG (CHỐNG SPAM) ======
function status_line($msg){ echo "\r\033[1;33m{$msg}\033[0m"; fflush(STDOUT); } // Vàng
function clear_status_line(){ echo "\r\033[2K\r"; fflush(STDOUT); } // Xóa sạch dòng hiện tại
// ====== HIỂN THỊ 1 DÒNG NGẮN RỒI TỰ XOÁ ======
function status_flash($msg, $ms = 900){
    status_line($msg);
    usleep($ms * 1000);  // 0.9 giây
    clear_status_line();
}

// ====== ADB ======
function check_adb_connection() {
    try {
        $result = shell_exec("adb devices 2>&1");
        $devices = array();
        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            if (strpos($line, "\tdevice") !== false) {
                $parts = explode("\t", $line);
                $devices[] = $parts[0];
            }
        }
        if (count($devices) > 0) {
            ok("[✓] Thiết bị đã được kết nối qua ADB.");
            return array(true, $devices[0]);
        } else {
            err("[✖] Không có thiết bị nào được kết nối qua ADB.");
            return array(false, null);
        }
    } catch (Exception $e) {
        err("[✖] Không thể chạy lệnh ADB. Vui lòng kiểm tra lại cài đặt ADB.");
        return array(false, null);
    }
}

function save_device_info($device_id) {
    file_put_contents("device_info.txt", $device_id);
    ok("✅ Đã lưu thông tin thiết bị.");
}

function load_device_info() {
    if (file_exists("device_info.txt")) {
        $device_id = trim(file_get_contents("device_info.txt"));
        echo "════════════════════════════════════════════════\n";
        ok("Đã tải thông tin kết nối từ thiết bị.");
        return $device_id;
    } else {
        err("Không tìm thấy file thông tin thiết bị.");
        return null;
    }
}

// ====== CHỈ CÒN TOẠ ĐỘ LIKE (BỎ FOLLOW/BACK) ======
function save_coordinates_like($like_x, $like_y) {
    $content = "like_x=$like_x\nlike_y=$like_y\n";
    file_put_contents("coordinates.txt", $content);
    ok("✅ Đã lưu tọa độ LIKE.");
}

function load_coordinates_like() {
    if (file_exists("coordinates.txt")) {
        $coordinates = array();
        $lines = file("coordinates.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            list($key, $value) = explode("=", $line);
            $coordinates[$key] = intval($value);
        }
        if (!isset($coordinates["like_x"]) || !isset($coordinates["like_y"])) {
            err("File tọa độ không hợp lệ (thiếu like_x/like_y).");
            return null;
        }
        ok("Đã tải tọa độ LIKE.");
        return $coordinates;
    } else {
        echo "════════════════════════════════════════════════\n";
        err("Không tìm thấy file tọa độ.");
        return null;
    }
}

function connect_android_11() {
    while (true) {
        try {
            prompt("Nhập IP của thiết bị (ví dụ: 192.168.100.3): ");
            $ip = trim(fgets(STDIN));
            prompt("Nhập cổng khi bật gỡ lỗi không dây (ví dụ: 43487): ");
            $debug_port = trim(fgets(STDIN));
            prompt("Nhập cổng khi ghép nối thiết bị (ví dụ: 40833): ");
            $pair_port = trim(fgets(STDIN));
            prompt("Nhập mã ghép nối Wi-Fi: ");
            $wifi_code = trim(fgets(STDIN));

            shell_exec("adb pair $ip:$pair_port $wifi_code");
            shell_exec("adb connect $ip:$debug_port");

            list($is_connected, $device_id) = check_adb_connection();
            if ($is_connected) {
                save_device_info($device_id);
                ok("Thiết bị đã kết nối thành công qua ADB!");
                return true;
            } else {
                err("Không thể kết nối thiết bị. Vui lòng kiểm tra lại thông tin.");
            }
        } catch (Exception $e) {
            err("Đã xảy ra lỗi: " . $e->getMessage());
        }
    }
}

function connect_android_10() {
    while (true) {
        try {
            prompt("Nhập IP của thiết bị (ví dụ: 192.168.100.3): ");
            $ip = trim(fgets(STDIN));
            prompt("Nhập cổng khi bật gỡ lỗi không dây (ví dụ: 5555): ");
            $debug_port = trim(fgets(STDIN));

            shell_exec("adb connect $ip:$debug_port");

            list($is_connected, $device_id) = check_adb_connection();
            if ($is_connected) {
                save_device_info($device_id);
                ok("Thiết bị đã kết nối thành công qua ADB!");
                return true;
            } else {
                err("❌ Không thể kết nối thiết bị. Vui lòng kiểm tra lại IP và cổng.");
            }
        } catch (Exception $e) {
            err("Đã xảy ra lỗi: " . $e->getMessage());
        }
    }
}

function tap_screen($x, $y) {
    shell_exec("adb shell input tap " . intval($x) . " " . intval($y));
}

// =====================================================================
// ========== NHÓM HÀM ADB/UI DUMP (BUỘC DỪNG + FOLLOW BẰNG UI) =========
// =====================================================================
function adb_shell($cmd){
    return shell_exec("adb shell ".$cmd." 2>&1");
}
function adb_exec($cmd){
    return shell_exec($cmd." 2>&1");
}
function detect_tiktok_pkg(){
    $cands = [
        'com.zhiliaoapp.musically',     // TikTok Global
        'com.zhiliaoapp.musically.go',  // TikTok Lite
        'com.ss.android.ugc.trill'      // Một vài bản vùng khác
    ];
    foreach ($cands as $p){
        $out = adb_shell("pm path $p");
        if (is_string($out) && strpos($out, "package:") !== false) return $p;
    }
    return 'com.zhiliaoapp.musically';
}
function open_app_info($pkg){
    adb_shell("am start -a android.settings.APPLICATION_DETAILS_SETTINGS -d package:$pkg");
    sleep(2);
}
function uia_dump(){
    $tmp = "/sdcard/__win_dump.xml";
    adb_shell("uiautomator dump $tmp >/dev/null 2>&1");
    $xml = adb_shell("cat $tmp");
    if (!is_string($xml) || trim($xml) === '' || strpos($xml, 'hierarchy') === false) return null;
    return $xml;
}
function find_center_by_texts($xml, $texts){
    if (!is_string($xml) || $xml === '') return null;
    foreach ($texts as $t){
        $pattern = '/(text|content-desc)=\"[^"]*'.preg_quote($t, '/').'[^"]*\"[^>]*bounds=\"\[(\d+),(\d+)\]\[(\d+),(\d+)\]\"/ui';
        if (preg_match($pattern, $xml, $m)){
            $x1 = (int)$m[2]; $y1 = (int)$m[3];
            $x2 = (int)$m[4]; $y2 = (int)$m[5];
            $cx = (int)(($x1 + $x2)/2);
            $cy = (int)(($y1 + $y2)/2);
            return [$cx, $cy];
        }
    }
    return null;
}
function find_center_by_ids($xml, $ids){
    if (!is_string($xml) || $xml === '') return null;
    foreach ($ids as $id){
        $pattern = '/resource-id=\"[^"]*'.preg_quote($id,'/').'\"[^>]*bounds=\"\[(\d+),(\d+)\]\[(\d+),(\d+)\]\"/ui';
        if (preg_match($pattern, $xml, $m)){
            $x1 = (int)$m[1]; $y1 = (int)$m[2];
            $x2 = (int)$m[3]; $y2 = (int)$m[4];
            $cx = (int)(($x1+$x2)/2); $cy = (int)(($y1+$y2)/2);
            return [$cx,$cy];
        }
    }
    return null;
}
function is_force_dialog_open(){
    $xml = uia_dump();
    if (!$xml) return false;
    if (preg_match('/Buộc dừng\?|Force stop\?/ui', $xml)) return true;
    if (preg_match('/(text|content-desc)=\"(Hủy|Huỷ|Cancel|OK|Ok|Buộc dừng|Force stop)\"/ui', $xml)) return true;
    if (preg_match('/resource-id=\"android:id\/button[12]\"/ui', $xml)) return true;
    return false;
}
function tap_by_texts($texts, $retries=3){
    for ($i=0; $i<$retries; $i++){
        $xml = uia_dump();
        if ($xml){
            $pt = find_center_by_texts($xml, $texts);
            if ($pt){
                tap_screen($pt[0], $pt[1]);
                return true;
            }
        }
        usleep(300000);
    }
    return false;
}
function force_stop_tiktok(){
    $pkg = detect_tiktok_pkg();
    ok("→ Đang buộc dừng TikTok ($pkg)...");
    open_app_info($pkg);

    // 1) Click nút "Buộc dừng" chính trên trang info
    $clicked_main = false;
    for ($i=0; $i<4; $i++){
        $xml = uia_dump();
        if ($xml){
            $pt = find_center_by_texts($xml, ['Buộc dừng','Force stop']);
            if (!$pt){
                $pt = find_center_by_ids($xml, ['force_stop_button','button_force_stop',':id/force_stop_button']);
            }
            if ($pt){
                tap_screen($pt[0], $pt[1]);
                usleep(400000);
                $clicked_main = true;
                break;
            }
        }
        usleep(250000);
    }

    if (!$clicked_main){
        adb_shell("am force-stop $pkg");
        usleep(500000);
        ok("✅ Fallback: am force-stop (không tìm thấy nút buộc dừng chính).");
        return true;
    }

    // 2) Xác nhận trong dialog
    $confirmed = false;
    for ($i=0; $i<6; $i++){
        if (!is_force_dialog_open()){
            $confirmed = true;
            break;
        }
        $xml2 = uia_dump();
        if ($xml2){
            $ptOk = find_center_by_ids($xml2, ['android:id/button1', ':id/button1']);
            if (!$ptOk){
                $ptOk = find_center_by_texts($xml2, ['Buộc dừng','Đồng ý','OK','Ok','Force stop']);
            }
            if ($ptOk){
                tap_screen($ptOk[0], $ptOk[1]);
                usleep(500000);
                if (!is_force_dialog_open()){
                    $confirmed = true;
                    break;
                }
            } else {
                adb_shell("input keyevent 22");
                usleep(200000);
                adb_shell("input keyevent 66");
                usleep(500000);
                if (!is_force_dialog_open()){
                    $confirmed = true;
                    break;
                }
            }
        }
        usleep(250000);
    }

    // 3) Fallback nếu dialog vẫn còn
    if (!$confirmed){
        adb_shell("am force-stop $pkg");
        usleep(500000);
        if (is_force_dialog_open()){
            $xml3 = uia_dump();
            $ptCancel = $xml3 ? find_center_by_texts($xml3, ['Hủy','Huỷ','Cancel']) : null;
            if ($ptCancel){ tap_screen($ptCancel[0], $ptCancel[1]); usleep(200000); }
            adb_shell("input keyevent 4");
        }
        ok("✅ Đã buộc dừng ");
    } else {
        ok("✅ Buộc dừng thành công.");
    }
    usleep(400000);
    return true;
}
function tap_follow_ui($max_retry = 12){
    $want_texts = ['Follow','Theo dõi']; 
    $bad_texts  = ['Đã','Đang','Following','Đã theo dõi','Đang theo dõi'];

    for ($try=0; $try<$max_retry; $try++){
        $xml = uia_dump();
        if (!$xml) { usleep(400000); continue; }

        // Nếu đã ở trạng thái theo dõi thì coi là thành công
        if (preg_match('/(Đang theo dõi|Đã theo dõi|Following)/ui', $xml)) {
            status_line("• Đã ở trạng thái theo dõi.");
            usleep(600000);
            clear_status_line();
            return true;
        }

        preg_match_all('/<node[^>]+>/u', $xml, $all_nodes);
        $nodes = $all_nodes[0] ?? [];
        $cands = [];

        foreach ($nodes as $n){
            $get = function($attr) use($n){
                if (preg_match('/\b'.$attr.'="([^"]*)"/u', $n, $m)) return $m[1];
                return '';
            };
            $text = $get('text');
            $desc = $get('content-desc');
            $rid  = $get('resource-id');
            $hay  = trim($text!==''?$text:$desc);
            $clk  = strtolower($get('clickable'))==='true';
            $bStr = $get('bounds');

            if (!preg_match('/\[(\d+),(\d+)\]\[(\d+),(\d+)\]/',$bStr,$bm)) continue;
            $x1=(int)$bm[1]; $y1=(int)$bm[2]; $x2=(int)$bm[3]; $y2=(int)$bm[4];
            $area = max(1,$x2-$x1)*max(1,$y2-$y1);

            // bỏ text xấu
            $bad=false;
            foreach ($bad_texts as $b) if (mb_stripos($hay,$b)!==false) { $bad=true; break; }
            if ($bad) continue;

            // chỉ nhận đúng Follow/ Theo dõi
            $oktxt=false;
            foreach ($want_texts as $t){
                if (preg_match('/^\s*'.preg_quote($t,'/').'\s*$/ui', $hay)){ $oktxt=true; break; }
            }

            // fallback nếu id gợi ý follow
            if (!$oktxt && preg_match('/follow/ui',$rid)) $oktxt=true;
            if (!$oktxt) continue;

            // gom ứng viên
            $cands[] = ['clickable'=>$clk,'bounds'=>[$x1,$y1,$x2,$y2],'area'=>$area];
        }

        if (empty($cands)){ usleep(400000); continue; }

        // Ưu tiên: clickable → diện tích lớn
        usort($cands, function($a,$b){
            if ($a['clickable']!==$b['clickable']) return $a['clickable']? -1:1;
            return $b['area'] <=> $a['area'];
        });
        $best = $cands[0];

        $x = intval(($best['bounds'][0]+$best['bounds'][2])/2);
        $y = intval(($best['bounds'][1]+$best['bounds'][3])/2);

        tap_screen($x,$y);
        status_line("→ Đã bấm vào nút Follow ");
        usleep(800000);
        clear_status_line();

        // xác nhận đổi trạng thái
        for ($w=0; $w<10; $w++){
            usleep(500000);
            $xml2 = uia_dump();
            if ($xml2 && preg_match('/(Đang theo dõi|Đã theo dõi|Following|Nhắn tin|Message)/ui',$xml2)){
                status_line("✓ Follow thành công.");
                usleep(800000);
                clear_status_line();
                return true;
            }
        }
    }
    status_line("❌ Không tìm thấy nút Follow (UI).");
    usleep(1200000);
    clear_status_line();
    return false;
}

// =====================================================================

function bes4($url) {
    try {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true]
        ]));
        if ($response !== false) {
            $doc = new DOMDocument();
            @$doc->loadHTML($response);
            $xpath = new DOMXPath($doc);
            $version_tag = $xpath->query("//span[@id='version_keyADB']")->item(0);
            $maintenance_tag = $xpath->query("//span[@id='maintenance_keyADB']")->item(0);
            $version = $version_tag ? trim($version_tag->nodeValue) : null;
            $maintenance = $maintenance_tag ? trim($maintenance_tag->nodeValue) : null;
            return array($version, $maintenance);
        }
    } catch (Exception $e) {}
    return array(null, null);
}

// ===== BANNER (rút gọn) =====
function banner() {}
banner();

echo "════════════════════════════════════════════════\n";

// ===== HỎI DEBUG NGAY KHI CHẠY =====
prompt("[?] Bật debug lưu file? (y/n): ");
$ans_debug = strtolower(trim(fgets(STDIN)));
if ($ans_debug === 'y') {
    $DEBUG = true;
    $DEBUG_FILE = 'logs/run_' . date('Ymd_His') . '.log';
    @file_put_contents($DEBUG_FILE, "=== START ".date('Y-m-d H:i:s')." ===\n", FILE_APPEND);
    ok("[✓] Debug ON → $DEBUG_FILE");
} else {
    ok("[•] Debug OFF");
}

// ===== MENU =====
echo "════════════════════════════════════════════════\n";
ok("Nhập 1 Để giữ lại Authorization");
ok("Nhập 2 Để Xóa Authorization ");

while (true) {
    try {
        prompt("Nhập Lựa Chọn (1 hoặc 2): ");
        $choose = intval(trim(fgets(STDIN)));
        if ($choose != 1 && $choose != 2) {
            err("❌ Lựa chọn không hợp lệ! Hãy nhập lại.");
            continue;
        }
        break;
    } catch (Exception $e) {
        err("Sai định dạng! Vui lòng nhập số.");
    }
}

if ($choose == 2) {
    $file = "Authorization.txt";
    if (file_exists($file)) {
        if (@unlink($file)) ok("[✓] Đã xóa $file!");
        else err("[✖] Không thể xóa $file!");
    } else {
        ok("[!] File $file không tồn tại!");
    }
    ok("👉 Vui lòng nhập lại thông tin!");
}

$file = "Authorization.txt";
if (!file_exists($file)) {
    if (file_put_contents($file, "") === false) {
        err("[✖] Không thể tạo file $file!");
        exit(1);
    }
}

$author = "";
if (file_exists($file)) {
    $author = file_get_contents($file);
    if ($author === false) {
        err("[✖] Không thể đọc file $file!");
        exit(1);
    }
    $author = trim($author);
}

while (empty($author)) {
    echo "════════════════════════════════════════════════\n";
    prompt("Nhập Authorization: ");
    $author = trim(fgets(STDIN));
    if (file_put_contents($file, $author) === false) {
        err("[✖] Không thể ghi vào file $file!");
        exit(1);
    }
}

$headers = [
    'Accept-Language' => 'vi,en-US;q=0.9,en;q=0.8',
    'Referer' => 'https://app.golike.net/',
    'Sec-Ch-Ua' => '"Not A(Brand";v="99", "Google Chrome";v="121", "Chromium";v="121"',
    'Sec-Ch-Ua-Mobile' => '?0',
    'Sec-Ch-Ua-Platform' => "Windows",
    'Sec-Fetch-Dest' => 'empty',
    'Sec-Fetch-Mode' => 'cors',
    'Sec-Fetch-Site' => 'same-site',
    'T' => 'VFZSak1FMTZZM3BOZWtFd1RtYzlQUT09',
    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
    "Authorization" => $author,
    'Content-Type' => 'application/json;charset=utf-8'
];

echo "════════════════════════════════════════════════\n";
ok("💘 Đăng nhập thành công");

// ===== API WRAPPERS =====
function buildHeaders($headers) {
    $headerString = "";
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }
    return $headerString;
}

function chonacc() {
    global $headers;
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => buildHeaders($headers),
            'timeout' => 25,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents('https://gateway.golike.net/api/tiktok-account', false, $ctx);
    return json_decode($response ?: 'null', true);
}

function nhannv($account_id) {
    global $headers;
    $url = 'https://gateway.golike.net/api/advertising/publishers/tiktok/jobs?' . http_build_query([
        'account_id' => $account_id,
        'data' => 'null'
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => buildHeaders($headers),
            'timeout' => 25,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return json_decode($response ?: 'null', true);
}

function hoanthanh($ads_id, $account_id) {
    global $headers;
    $json_data = [
        'ads_id' => $ads_id,
        'account_id' => $account_id,
        'async' => true,
        'data' => null
    ];
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => buildHeaders($headers),
            'content' => json_encode($json_data),
            'ignore_errors' => true,
            'timeout' => 25
        ]
    ]);
    $response = @file_get_contents('https://gateway.golike.net/api/advertising/publishers/tiktok/complete-jobs', false, $context);
    if ($response === false) return ['error' => 'Không thể kết nối đến server!'];

    $http_code = 0;
    if (isset($http_response_header) && preg_match('/HTTP\/\d\.\d\s(\d+)/', $http_response_header[0], $m)) {
        $http_code = (int)$m[1];
    }
    if ($http_code !== 200) return ['error' => "Lỗi HTTP $http_code"];
    return json_decode($response, true);
}

function baoloi($ads_id, $object_id, $account_id, $loai) {
    global $headers;

    $json_data1 = [
        'description' => 'Báo cáo hoàn thành thất bại',
        'users_advertising_id' => $ads_id,
        'type' => 'ads',
        'provider' => 'tiktok',
        'fb_id' => $account_id,
        'error_type' => 6
    ];
    $ctx1 = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => buildHeaders($headers),
            'content' => json_encode($json_data1),
            'timeout' => 25,
            'ignore_errors' => true
        ]
    ]);
    @file_get_contents('https://gateway.golike.net/api/report/send', false, $ctx1);

    $json_data2 = [
        'ads_id' => $ads_id,
        'object_id' => $object_id,
        'account_id' => $account_id,
        'type' => $loai
    ];
    $ctx2 = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => buildHeaders($headers),
            'content' => json_encode($json_data2),
            'timeout' => 25,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents('https://gateway.golike.net/api/advertising/publishers/tiktok/skip-jobs', false, $ctx2);
    return json_decode($response ?: 'null', true);
}

/* ==== LẤY GIÁ JOB CHUẨN HÓA ==== */
function get_job_price($payload) {
    if (!is_array($payload)) return 0;
    $get = function($arr, $path) {
        $cur = $arr;
        foreach (explode('.', $path) as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) return null;
            $cur = $cur[$k];
        }
        return $cur;
    };
    $priority_paths = [
        'data.price_after_cost',
        'data.price_per_after_cost',
        'lock.fix_coin',
        'data.price_per',
        'data.prices','data.price','data.coin','data.coins','data.amount','data.reward',
        'price_after_cost','price_per_after_cost','fix_coin','price_per','prices','price','coin','coins','amount','reward'
    ];
    foreach ($priority_paths as $p) {
        $v = $get($payload, $p);
        if ($v !== null) {
            if (is_numeric($v)) return (int)$v;
            if (is_string($v) && preg_match('/^\d+(\.\d+)?$/', $v)) return (int)$v;
        }
    }
    $text_paths = [
        'data.prices_text','data.price_text','data.reward_text','data.note','data.message',
        'data.title','data.details','data.desc','data.description','data.label','data.hint',
    ];
    foreach ($text_paths as $p) {
        $v = $get($payload, $p);
        if (is_string($v) && preg_match('/\+?\s*(\d{1,6})\s*(xu|đ|coin|coins)?/ui', $v, $m)) {
            return (int)$m[1];
        }
    }
    $scan = function($v) use (&$scan) {
        if (is_array($v)) {
            foreach ($v as $vv) {
                $x = $scan($vv);
                if ($x > 0) return $x;
            }
        } elseif (is_string($v)) {
            if (preg_match('/\+?\s*(\d{1,6})\s*(xu|đ|coin|coins)?/ui', $v, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    };
    $fallback = $scan($payload);
    return $fallback > 0 ? $fallback : 0;
}

/* ==== Ghi log debug job ==== */
function debug_dump_job($payload) {
    if (!is_dir('logs')) @mkdir('logs');
    $line = date('Y-m-d H:i:s') . " " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents('logs/debug_job.jsonl', $line, FILE_APPEND);
}

// ===== LẤY DS ACC VÀ HIỂN THỊ =====
$chontktiktok = chonacc();

function dsacc() {
    global $chontktiktok;
    while (true) {
        try {
            if (!isset($chontktiktok["status"]) || $chontktiktok["status"] != 200) {
                err("Authorization hoặc T sai hãy nhập lại!!!");
                echo "════════════════════════════════════════════════\n";
                exit();
            }
            banner();
            echo "════════════════════════════════════════════════\n";
            ok("Danh sách acc Tik Tok : ");
            echo "════════════════════════════════════════════════\n";
            for ($i = 0; $i < count($chontktiktok["data"]); $i++) {
                echo "\033[1;32m[".($i + 1)."] ✈ ID : ".$chontktiktok["data"][$i]["unique_username"]." | : Hoạt Động\033[0m\n";
            }
            echo "════════════════════════════════════════════════\n";
            break;
        } catch (Exception $e) {
            ok(json_encode($chontktiktok));
            sleep(3);
        }
    }
}

dsacc();

// ===== CHỌN ACC BẰNG STT =====
while (true) {
    prompt("Nhập STT Acc Tiktok làm việc: ");
    $stt = intval(trim(fgets(STDIN)));
    if ($stt < 1 || $stt > count($chontktiktok["data"])) {
        err("❌ STT không hợp lệ! Vui lòng nhập lại.");
        continue;
    }
    $account_id = $chontktiktok["data"][$stt - 1]["id"];
    $idacc = $chontktiktok["data"][$stt - 1]["unique_username"];
    ok("✅ Đã chọn acc: {$idacc}");
    debug_log("SELECTED ACCOUNT: id=$account_id, username=$idacc");
    break;
}

// ===== INPUT CÀI ĐẶT =====
while (true) {
    try {
        prompt("Delay làm job (giây): ");
        $delay = intval(trim(fgets(STDIN)));
        break;
    } catch (Exception $e) {
        err("Sai định dạng!!!");
    }
}

// ===== [MỚI] HỎI NGƯỠNG ĐỔI ACC THEO SỐ LẦN KHÔNG TÌM THẤY NÚT FOLLOW LIÊN TIẾP =====
$max_fail_follow = 0;           // 0 = tắt
while (true) {
    prompt("Không tìm thấy nút Follow liên tiếp bao nhiêu lần thì đổi acc (0 = tắt): ");
    $inp = trim(fgets(STDIN));
    if ($inp === '' || !ctype_digit($inp)) {
        err("❌ Hãy nhập số nguyên không âm!");
        continue;
    }
    $max_fail_follow = (int)$inp;
    break;
}
$fail_follow_streak = 0;        // đếm chuỗi fail cho FOLLOW

// ===== ADB / AUTO FOLLOW-LIKE =====
$like_x = null; $like_y = null;
while (true) {
    try {
        prompt("Bạn có muốn sử dụng ADB không? (y/n): ");
        $auto_follow = strtolower(trim(fgets(STDIN)));
        if ($auto_follow != "y" && $auto_follow != "n") {
            err("Nhập sai hãy nhập lại!!!");
            continue;
        }
        if ($auto_follow == "y") {
            $device_id = load_device_info();
            if (!$device_id) {
                err("Thiết bị chưa được kết nối qua ADB. Vui lòng thêm thiết bị.");
                while (true) {
                    echo "════════════════════════════════════════════════\n";
                    ok("Nhập 1 Để kết nối thiết bị Android 10 .");
                    ok("Nhập 2 Để kết nối thiết bị Android 11 .");
                    echo "════════════════════════════════════════════════\n";
                    prompt("Vui lòng chọn: ");
                    $choose_HDH = trim(fgets(STDIN));
                    if ($choose_HDH != "1" && $choose_HDH != "2") {
                        err("Nhập sai hãy nhập lại!!!");
                        continue;
                    }
                    if ($choose_HDH == "1") { if (connect_android_10()) break; }
                    else { if (connect_android_11()) break; }
                }
            }

            // CHỈ HỎI TỌA ĐỘ LIKE
            $coordinates = load_coordinates_like();
            if (!$coordinates) {
                while (true) {
                    try {
                        echo "════════════════════════════════════════════════\n";
                        prompt("Nhập tọa độ X nút Like TikTok: ");   $like_x   = intval(trim(fgets(STDIN)));
                        prompt("Nhập tọa độ Y nút Like TikTok: ");   $like_y   = intval(trim(fgets(STDIN)));
                        echo "════════════════════════════════════════════════\n";
                        save_coordinates_like($like_x, $like_y);
                        break;
                    } catch (Exception $e) {
                        err("Nhập vào một số hợp lệ!!!");
                    }
                }
            } else {
                while (true) {
                    echo "════════════════════════════════════════════════\n";
                    prompt("Bạn có muốn sử dụng Tọa Độ LIKE Đã Lưu? (y/n): ");
                    $choose = strtolower(trim(fgets(STDIN)));
                    if ($choose != "y" && $choose != "n") {
                        err("Nhập sai hãy nhập lại!!!");
                        continue;
                    }
                    if ($choose == "y") {
                        $like_x   = $coordinates["like_x"];
                        $like_y   = $coordinates["like_y"];
                        ok("Sử dụng tọa độ Like đã lưu: ($like_x, $like_y)");
                        break;
                    } else {
                        if (file_exists("coordinates.txt")) {
                            @unlink("coordinates.txt");
                            err("Đã xóa tọa độ LIKE đã lưu.");
                        }
                        while (true) {
                            try {
                                echo "════════════════════════════════════════════════\n";
                                prompt("Nhập tọa độ X nút Like TikTok: ");   $like_x   = intval(trim(fgets(STDIN)));
                                prompt("Nhập tọa độ Y nút Like TikTok: ");   $like_y   = intval(trim(fgets(STDIN)));
                                echo "════════════════════════════════════════════════\n";
                                save_coordinates_like($like_x, $like_y);
                                break;
                            } catch (Exception $e) {
                                err("Nhập vào một số hợp lệ!!!");
                            }
                        }
                        break;
                    }
                }
            }
        } else {
            ok("Bỏ qua kết nối ADB.");
        }
        break;
    } catch (Exception $e) {
        err("Đã xảy ra lỗi: " . $e->getMessage());
    }
}

// ===== CHỌN CHẾ ĐỘ =====
while (true) {
    try {
        echo "════════════════════════════════════════════════\n";
        ok("Nhập 1 : Chỉ nhận nhiệm vụ Follow");
        ok("Nhập 2 : Chỉ nhận nhiệm vụ Like");
        echo "════════════════════════════════════════════════\n";
        prompt("Chọn lựa chọn: ");
        $chedo = intval(trim(fgets(STDIN)));
        if ($chedo == 1 || $chedo == 2 || $chedo == 12) break;
        else err("Chỉ được nhập 1, 2 hoặc 12!");
    } catch (Exception $e) {
        err("Nhập vào 1 số!!!");
    }
}

$lam = ($chedo == 1) ? ["follow"] : (($chedo == 2) ? ["like"] : ["follow","like"]);

// ===== LỌC GIÁ =====
$debug_filter = false;
$enable_filter = false;
$min_coin = 0;

echo "════════════════════════════════════════════════\n";
prompt("Bạn có muốn lọc job không? (1: Có / 2: Không): ");
$chon_loc = trim(fgets(STDIN));
if ($chon_loc === "1") {
    $enable_filter = true;
    while (true) {
        prompt("Nhập mức xu tối thiểu (vd 20): ");
        $min_coin_in = fgets(STDIN);
        if ($min_coin_in === false) { continue; }
        $min_coin_in = trim($min_coin_in);
        if ($min_coin_in !== '' && ctype_digit($min_coin_in)) {
            $min_coin = (int)$min_coin_in;
            clear_status_line();
            echo PHP_EOL;
            break;
        } else {
            err("❌ Vui lòng nhập số nguyên hợp lệ!");
        }
    }
} elseif ($chon_loc !== "2") {
    err("❌ Lựa chọn không hợp lệ! Bỏ lọc.");
}

// ===== [MỚI] HỎI SAU BAO NHIÊU JOB THÌ BUỘC DỪNG =====
$force_after = 0;     // 0 = tắt tính năng
$since_force = 0;     // đếm job kể từ lần buộc dừng gần nhất
echo "════════════════════════════════════════════════\n";
prompt("Sau bao nhiêu job thì BUỘC DỪNG TikTok? (0 = tắt): ");
$line_force = trim(fgets(STDIN));
if ($line_force !== '' && ctype_digit($line_force)){
    $force_after = (int)$line_force;
    if ($force_after < 0) $force_after = 0;
} else {
    err("❌ Giá trị không hợp lệ, tắt tính năng buộc dừng (0).");
    $force_after = 0;
}
if ($force_after > 0){
    ok("⏱ Sẽ buộc dừng TikTok sau mỗi {$force_after} job.");
} else {
    ok("⏹ Không tự buộc dừng TikTok.");
}

// ===== BẮT ĐẦU LÀM NHIỆM VỤ =====
$dem = 0;
$tong = 0;
$skip_count = 0; // ĐẾM JOB BỊ LOẠI ĐỂ IN 1 DÒNG TRẠNG THÁI

while (true) {
    echo "\033[1;32mĐang Tìm Nhiệm vụ:>        \033[0m\r";
    while (true) {
        try {
            $nhanjob = nhannv($account_id);
            break;
        } catch (Exception $e) {}
    }
    debug_log("JOB RAW: ".json_encode($nhanjob ?? [], JSON_UNESCAPED_UNICODE));

    // ==== CHẶN JOB TRÙNG AN TOÀN ====
    static $previous_job = null;
    if (
        $previous_job !== null
        && isset(
            $previous_job["data"]["link"], $previous_job["data"]["type"],
            $nhanjob["data"]["link"],      $nhanjob["data"]["type"]
        )
        && $previous_job["data"]["link"] === $nhanjob["data"]["link"]
        && $previous_job["data"]["type"] === $nhanjob["data"]["type"]
    ) {
        status_line("Bỏ qua job trùng với job trước đó...");
        try {
            baoloi(
                $nhanjob["data"]["id"]        ?? null,
                $nhanjob["data"]["object_id"] ?? null,
                $account_id,
                $nhanjob["data"]["type"]      ?? ''
            );
        } catch (Exception $e) {}
        $skip_count++;
        continue;
    }
    $previous_job = $nhanjob;

    if (isset($nhanjob["status"]) && $nhanjob["status"] == 200) {
        $ads_id   = $nhanjob["data"]["id"];
        $link     = $nhanjob["data"]["link"] ?? "";
        $object_id= $nhanjob["data"]["object_id"] ?? "";
        $loai     = $nhanjob["data"]["type"] ?? "";

        if (empty($link)) {
            static $notified = false;
            if (!$notified) { status_line("Job die - Không có link!"); $notified = true; }
            try { baoloi($ads_id, $object_id, $account_id, $loai); } catch (Exception $e) {}
            $skip_count++;
            continue;
        }
        if (!in_array($loai, $lam)) {
            try { baoloi($ads_id, $object_id, $account_id, $loai); status_line("Đã bỏ qua job {$loai}!"); $skip_count++; continue; }
            catch (Exception $e) {}
        }

        // ====== LỌC GIÁ (CHẶT CHẼ) ======
        $job_price = get_job_price($nhanjob);
        if ($debug_filter) {
            $showType = $loai ?: 'unknown';
            echo "\r\033[1;32m[DEBUG] type={$showType}, price_detected={$job_price}, min={$min_coin}\033[0m       \r";
            debug_dump_job([
                'type'   => $showType,
                'min'    => $min_coin,
                'price'  => $job_price,
                'keys'   => array_keys($nhanjob['data'] ?? []),
                'sample' => array_intersect_key(($nhanjob['data'] ?? []), array_flip([
                    'id','type','link','object_id','prices','price','coin','coins','amount','reward',
                    'price_after_cost','price_per_after_cost','price_per','status_message'
                ])),
                'lock'   => array_intersect_key(($nhanjob['lock'] ?? []), array_flip(['fix_coin','type','ads_id']))
            ]);
            usleep(150000);
        }
        if ($enable_filter) {
            if ($job_price === 0) {
                $skip_count++;
                status_line("Đang bỏ qua job (không rõ giá) ≥ {$min_coin} — Tổng bỏ qua: {$skip_count}");
                try { baoloi($ads_id, $object_id, $account_id, $loai); } catch (Exception $e) {}
                continue;
            }
            if ($job_price < $min_coin) {
                $skip_count++;
                status_line("Đang bỏ qua job giá {$job_price} < yêu cầu {$min_coin} — Tổng bỏ qua: {$skip_count}");
                try { baoloi($ads_id, $object_id, $account_id, $loai); } catch (Exception $e) {}
                continue;
            }
        }

        if ($skip_count > 0) { clear_status_line(); $skip_count = 0; }

        debug_log("OPEN LINK: $link (type=$loai)");
        exec("termux-open-url $link", $output, $return_var);
        if ($return_var !== 0) {
            $skip_count++;
            status_line("Không thể mở link - Job die! — Tổng bỏ qua: {$skip_count}");
            try { baoloi($ads_id, $object_id, $account_id, $loai); } catch (Exception $e) {}
            continue;
        }
        sleep(3);

        if (isset($auto_follow) && $auto_follow == "y") {
            if ($loai == "follow") {
                // ---- FOLLOW BẰNG UI DUMP ---- (GIỮ NGUYÊN HÀM)
                $did = tap_follow_ui(6);
                if ($did) {
                    ok("✅ Đã bấm nút Follow ");
                    $fail_follow_streak = 0; // reset streak khi thành công
                } else {
                    err("❌ Không tìm thấy nút Follow ");
                    $fail_follow_streak++;    // tăng streak khi thất bại

                    // Báo skip job vì không follow được
                    try { baoloi($ads_id, $object_id, $account_id, $loai); } catch (Exception $e) {}

                    // Nếu đạt ngưỡng → đổi acc (chọn STT)
                    if ($max_fail_follow > 0 && $fail_follow_streak >= $max_fail_follow) {
                        clear_status_line();
                        ok("⚠️ Không tìm thấy nút Follow {$fail_follow_streak} lần liên tiếp → Đổi acc.");
                        dsacc();
                        while (true) {
                            prompt("Nhập STT acc để đổi: ");
                            $stt2 = intval(trim(fgets(STDIN)));
                            if ($stt2 < 1 || $stt2 > count($chontktiktok["data"])) {
                                err("❌ STT không hợp lệ! Vui lòng nhập lại.");
                                continue;
                            }
                            $account_id = $chontktiktok["data"][$stt2 - 1]["id"];
                            $idacc = $chontktiktok["data"][$stt2 - 1]["unique_username"];
                            ok("✅ Đã đổi sang acc: {$idacc}");
                            break;
                        }
                        $fail_follow_streak = 0; // reset sau khi đổi acc
                    }

                    // Quay lại và sang vòng lặp tiếp theo (không countdown, không claim)
                    adb_shell("input keyevent 4");
                    continue;
                }
                sleep(2);
                // Quay lại
                adb_shell("input keyevent 4");
            } elseif ($loai == "like") {
                if ($like_x !== null && $like_y !== null) {
                    tap_screen($like_x, $like_y);
                } else {
                    err("❌ Chưa cấu hình toạ độ LIKE.");
                }
            }
        }

        // Countdown gọn, không lag (in đè 1 dòng)
        for ($remaining_time = $delay; $remaining_time >= 0; $remaining_time--) {
            status_line("⏱ Đợi: {$remaining_time}s");
            sleep(1);
        }
        clear_status_line();

        // ====== NHẬN TIỀN CHỈ 1 LẦN ======
        echo "\033[1;32mĐang Nhận Tiền:>        \033[0m\r";
        while (true) {
            try {
                $nhantien = hoanthanh($ads_id, $account_id);
                break;
            } catch (Exception $e) {}
        }
        debug_log("PAYOUT RAW: ".json_encode($nhantien ?? [], JSON_UNESCAPED_UNICODE));
        clear_status_line();

        if (isset($nhantien["status"]) && $nhantien["status"] == 200) {
            $dem++;
            $tien = $nhantien["data"]["prices"];
            $tong += $tien;
            $t = date('H:i:s');
            $chuoi = ("\033[1;32m| {$dem} | {$t} | success | {$nhantien['data']['type']} | Ẩn ID | +{$tien} | {$tong} | Giá: {$job_price}\033[0m");
            echo $chuoi . "\n";

            // ---- BUỘC DỪNG + MỞ LẠI APP THEO CHU KỲ ----
            if ($force_after > 0) {
                $since_force++;
                if ($since_force >= $force_after) {
                    status_line("Đạt {$since_force}/{$force_after} job — buộc dừng TikTok...");
                    try {
                        // 1) Buộc dừng TikTok
                        force_stop_tiktok();
                        clear_status_line();

                        // 2) Mở lại TikTok ngay lập tức
                        $pkg = detect_tiktok_pkg();
                        ok("→ Đang mở lại TikTok ($pkg)...");
                        adb_shell("monkey -p $pkg -c android.intent.category.LAUNCHER 1");
                        sleep(2);

                        // 3) Chờ 20s cho app khởi động
                        for ($i = 20; $i > 0; $i--) {
                            status_line("⏳ Đang chờ TikTok khởi động: {$i}s");
                            sleep(1);
                        }
                        clear_status_line();
                    } catch (Exception $e) {
                        err("Lỗi khi restart TikTok: ".$e->getMessage());
                    }
                    $since_force = 0;
                }
            }
            // -------------------------------------------

        } else {
            try {
                baoloi($ads_id, $object_id, $account_id, $loai);
                $skip_count++;
                status_line("Đã bỏ qua job. — Tổng bỏ qua: {$skip_count}");
            } catch (Exception $e) {}
        }
    } else {
        for ($i = 10; $i >= 0; $i--) { status_line("Không có job, đợi {$i}s..."); sleep(1); }
        clear_status_line();
    }
}