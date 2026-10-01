<?php
$TOKEN = "8682501264:AAEj6FZXhec7_rS1jyMveXG_nFvNvcuy_YQ";
$ADMIN = 8013770402; // حط ايديك او خليه 0 للكل

define("BOTS_DIR", __DIR__."/bots");
@mkdir(BOTS_DIR, 0777, true);

function api($method, $data=[]){
    global $TOKEN;
    $ch = curl_init("https://api.telegram.org/bot$TOKEN/$method");
    curl_setopt_array($ch, [
        CURLOPT_POST=>1,
        CURLOPT_RETURNTRANSFER=>1,
        CURLOPT_POSTFIELDS=>$data,
        CURLOPT_TIMEOUT=>30
    ]);
    return json_decode(curl_exec($ch), true);
}

function send($chat_id, $text){
    api("sendMessage", ["chat_id"=>$chat_id, "text"=>$text, "parse_mode"=>"Markdown"]);
}

echo "🐘 PHP HOSTER STARTED - Hosting PHP bots\n";

$offset = 0;
while(true){
    $res = api("getUpdates", ["offset"=>$offset, "timeout"=>25]);
    if(!$res || !$res["ok"]) { sleep(1); continue; }

    foreach($res["result"] as $update){
        $offset = $update["update_id"]+1;
        $msg = $update["message"] ?? null;
        if(!$msg) continue;

        $chat_id = $msg["chat"]["id"];
        $uid = $msg["from"]["id"];
        $text = $msg["text"] ?? "";
        $doc = $msg["document"] ?? null;

        if($ADMIN!=0 && $uid!=$ADMIN) continue;

        if($text=="/start"){
            send($chat_id, "🚀 *استضافة بوتات PHP للأبد*\n\n"
            ."بوت الاستضافة مكتوب PHP\n"
            ."والبوتات المستضافة PHP\n\n"
            ."📤 ارسل ملف `.php`\n"
            ."سيعمل كـ `php bot.php` للأبد\n\n"
            ."/mybots - عرض البوتات\n"
            ."/logs اسم - السجل\n"
            ."/stop اسم - ايقاف\n"
            ."/delete اسم - حذف\n"
            ."/status");
        }
        elseif($text=="/mybots"){
            $list = array_diff(scandir(BOTS_DIR), [".",".."]);
            if(empty($list)){ send($chat_id, "📭 لا يوجد بوتات"); continue; }
            $t = "🐘 *بوتاتك:*\n\n";
            foreach($list as $b){
                if(!is_dir(BOTS_DIR."/$b")) continue;
                $pidFile = BOTS_DIR."/$b/pid.txt";
                $run = file_exists($pidFile) && file_exists("/proc/".trim(file_get_contents($pidFile)));
                $t .= "`$b` - ".($run?"🟢 شغال":"🔴 متوقف")."\n";
            }
            send($chat_id, $t);
        }
        elseif(str_starts_with($text, "/stop ")){
            $name = trim(str_replace("/stop","",$text));
            $pidFile = BOTS_DIR."/$name/pid.txt";
            if(file_exists($pidFile)){
                $pid = trim(file_get_contents($pidFile));
                exec("kill $pid");
                @unlink($pidFile);
                send($chat_id, "⏹️ تم ايقاف $name");
            }else send($chat_id, "❌ مو شغال");
        }
        elseif(str_starts_with($text, "/delete ")){
            $name = trim(str_replace("/delete","",$text));
            $pidFile = BOTS_DIR."/$name/pid.txt";
            if(file_exists($pidFile)) exec("kill ".trim(file_get_contents($pidFile)));
            exec("rm -rf ".escapeshellarg(BOTS_DIR."/$name"));
            send($chat_id, "🗑️ تم حذف $name");
        }
        elseif(str_starts_with($text, "/logs ")){
            $name = trim(str_replace("/logs","",$text));
            $log = BOTS_DIR."/$name/bot.log";
            if(!file_exists($log)){ send($chat_id,"لا يوجد سجل"); continue; }
            $txt = substr(file_get_contents($log), -3500);
            send($chat_id, "📜 *$name:*\n```\n$txt\n```");
        }
        elseif($text=="/status"){
            $count = count(array_filter(scandir(BOTS_DIR), fn($d)=>$d!="."&&$d!=".."&&is_dir(BOTS_DIR."/$d")));
            send($chat_id, "📊 عدد البوتات: $count\n🐘 PHP: ✅ شغال\n♾️ استضافة أبدية");
        }

        // استقبال ملف PHP
        if($doc){
            $fname = $doc["file_name"];
            if(!str_ends_with(strtolower($fname), ".php")){
                send($chat_id, "❌ ارسل ملف .php فقط"); continue;
            }

            $botName = pathinfo($fname, PATHINFO_FILENAME);
            $botName = preg_replace('/[^a-zA-Z0-9_]/','_',$botName);
            $folder = BOTS_DIR."/$botName";
            @mkdir($folder, 0777, true);

            // تحميل
            $finfo = api("getFile", ["file_id"=>$doc["file_id"]]);
            $fpath = $finfo["result"]["file_path"];
            $fileUrl = "https://api.telegram.org/file/bot$TOKEN/$fpath";
            $code = file_get_contents($fileUrl);
            file_put_contents("$folder/main.php", $code);

            send($chat_id, "📥 استلمت `$fname`\n⏳ جاري التشغيل `php main.php` ...");

            // تشغيل للأبد
            exec("cd ".escapeshellarg($folder)." && nohup php main.php > bot.log 2>&1 & echo $! > pid.txt");

            send($chat_id, "✅ *تم تشغيل $botName للأبد!*\n\nشغال الآن كـ PHP\n/mybots للتحكم");
        }
    }
}
