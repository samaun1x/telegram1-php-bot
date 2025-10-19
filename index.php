<?php
// ==== CONFIG ====
$botToken = "8143727031:AAFT8xp6eu26rP_eMxXvfg4TsGEiVgXW4mk";
$admin = 6906644020;
$channel1 = "@smnfreemium";
$channel2 = "@spammer_smn";
$website = "https://api.telegram.org/bot".$botToken;

// ==== DATABASE ====
$file = "users.txt";
if(!file_exists($file)) file_put_contents($file, "");

// ==== FUNCTIONS ====
function bot($method, $data = []){
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function isMember($user_id, $channel){
    global $botToken;
    $res = file_get_contents("https://api.telegram.org/bot$botToken/getChatMember?chat_id=$channel&user_id=$user_id");
    $data = json_decode($res, true);
    $status = $data['result']['status'] ?? '';
    return in_array($status, ['member', 'administrator', 'creator']);
}

// ==== MAIN CODE ====
$update = json_decode(file_get_contents("php://input"), true);
$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

if($message){
    $chat_id = $message['chat']['id'];
    $text = $message['text'];
    $name = $message['from']['first_name'];
    $username = $message['from']['username'] ?? '';

    // ✅ Save user
    $users = file($file, FILE_IGNORE_NEW_LINES);
    if(!in_array($chat_id, $users)){
        file_put_contents($file, "$chat_id\n", FILE_APPEND);
    }

    // === /start ===
    if($text == "/start"){
        $inlineKeyboard = [
            'inline_keyboard' => [
                [['text'=>" Join Channel 1", 'url'=>"https://t.me/".ltrim($channel1, '@')]],
                [['text'=>" Join Channel 2", 'url'=>"https://t.me/".ltrim($channel2, '@')]],
                [['text'=>" Joined ✅", 'callback_data'=>"joined"]]
            ]
        ];
        
        bot("sendMessage", [
            'chat_id'=>$chat_id,
            'text'=>"👋 Hello *$name*,\n\nBefore using the bot, please join the two channels below 👇",
            'parse_mode'=>"Markdown",
            'reply_markup'=>json_encode($inlineKeyboard)
        ]);

        // === Admin keyboard ===
        if($chat_id == $admin){
            $adminKb = [
                'keyboard' => [
                    [['text'=>"Broadcast"]],
                    [['text'=>"See Users"]],
                    [['text'=>"Target User"]]
                ],
                'resize_keyboard' => true
            ];
            bot("sendMessage", [
                'chat_id'=>$chat_id,
                'text'=>"",
                'reply_markup'=>json_encode($adminKb)
            ]);
        }
    }

    // === Broadcast Mode ===
    elseif($text == "Broadcast" && $chat_id == $admin){
        file_put_contents("mode.txt", "broadcast");
        $cancelKb = [
            'keyboard' => [
                [['text'=>"❌ Cancel Broadcast"]]
            ],
            'resize_keyboard' => true
        ];
        bot("sendMessage", [
            'chat_id'=>$chat_id,
            'text'=>"✍️ Send your broadcast message now or tap ❌ Cancel to stop.",
            'reply_markup'=>json_encode($cancelKb)
        ]);
    }

    // === Cancel Broadcast ===
    elseif($text == "❌ Cancel Broadcast" && $chat_id == $admin){
        file_put_contents("mode.txt", "");
        $adminKb = [
            'keyboard' => [
                [['text'=>"Broadcast"]],
                [['text'=>"See Users"]],
                [['text'=>"Target User"]]
            ],
            'resize_keyboard' => true
        ];
        bot("sendMessage", [
            'chat_id'=>$chat_id,
            'text'=>"❌ Broadcast cancelled.",
            'reply_markup'=>json_encode($adminKb)
        ]);
    }

    // === Broadcast Sending ===
    elseif(file_exists("mode.txt") && trim(file_get_contents("mode.txt")) == "broadcast" && $chat_id == $admin){
        file_put_contents("mode.txt", "");
        $all_users = file($file, FILE_IGNORE_NEW_LINES);
        foreach($all_users as $user){
            bot("sendMessage", [
                'chat_id'=>$user,
                'text'=>$text
            ]);
        }
        $adminKb = [
            'keyboard' => [
                [['text'=>"Broadcast"]],
                [['text'=>"See Users"]],
                [['text'=>"Target User"]]
            ],
            'resize_keyboard' => true
        ];
        bot("sendMessage", [
            'chat_id'=>$admin,
            'text'=>"✅ Broadcast sent successfully.",
            'reply_markup'=>json_encode($adminKb)
        ]);
    }

    // === See Users ===
    elseif($text == "See Users" && $chat_id == $admin){
        $all_users = file($file, FILE_IGNORE_NEW_LINES);
        $count = count($all_users);
        $msg = "👥 Total Users: $count\n\n";
        foreach($all_users as $i => $uid){
            $info = bot("getChat", ['chat_id'=>$uid]);
            $uname = $info['result']['username'] ?? '';
            $display = $uname ? "@$uname" : $uid;
            $msg .= ($i+1).". $display\n";
        }
        bot("sendMessage", [
            'chat_id'=>$admin,
            'text'=>$msg
        ]);
    }

    // === Target User Start ===
    elseif($text == "Target User" && $chat_id == $admin){
        file_put_contents("mode.txt", "target_user_id");
        $cancelKb = [
            'keyboard' => [
                [['text'=>"❌ Cancel Target"]]
            ],
            'resize_keyboard' => true
        ];
        bot("sendMessage", [
            'chat_id'=>$chat_id,
            'text'=>"👤 Send username (like @user) or user ID:",
            'reply_markup'=>json_encode($cancelKb)
        ]);
    }

    // === Cancel Target ===
    elseif($text == "❌ Cancel Target" && $chat_id == $admin){
        file_put_contents("mode.txt", "");
        $adminKb = [
            'keyboard' => [
                [['text'=>"Broadcast"]],
                [['text'=>"See Users"]],
                [['text'=>"Target User"]]
            ],
            'resize_keyboard' => true
        ];
        bot("sendMessage", [
            'chat_id'=>$chat_id,
            'text'=>"❌ Target user cancelled.",
            'reply_markup'=>json_encode($adminKb)
        ]);
    }

    // === Step 1: Get Target ID/Username ===
    elseif(file_exists("mode.txt") && trim(file_get_contents("mode.txt")) == "target_user_id" && $chat_id == $admin){
        $target = trim($text);
        if(str_starts_with($target, "@")){
            $target = substr($target, 1);
            $info = bot("getChat", ['chat_id'=>"@$target"]);
            $uid = $info['result']['id'] ?? null;
        } else {
            $uid = is_numeric($target) ? $target : null;
        }

        if($uid){
            file_put_contents("target.txt", $uid);
            file_put_contents("mode.txt", "target_message");
            bot("sendMessage", [
                'chat_id'=>$admin,
                'text'=>"📨 Send message for this user ID: $uid"
            ]);
        } else {
            bot("sendMessage", [
                'chat_id'=>$admin,
                'text'=>"⚠️ Invalid username or user ID. Try again."
            ]);
        }
    }

    // === Step 2: Send Target Message ===
    elseif(file_exists("mode.txt") && trim(file_get_contents("mode.txt")) == "target_message" && $chat_id == $admin){
        $target_id = trim(file_get_contents("target.txt"));
        bot("sendMessage", [
            'chat_id'=>$target_id,
            'text'=>$text
        ]);

        file_put_contents("mode.txt", "");
        file_put_contents("target.txt", "");
        $adminKb = [
            'keyboard' => [
                [['text'=>"Broadcast"]],
                [['text'=>"See Users"]],
                [['text'=>"Target User"]]
            ],
            'resize_keyboard' => true
        ];
        bot("sendMessage", [
            'chat_id'=>$admin,
            'text'=>"✅ Message sent to user $target_id successfully.",
            'reply_markup'=>json_encode($adminKb)
        ]);
    }
}

// === CALLBACK ===
if($callback){
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];

    if($data == "joined"){
        if(isMember($chat_id, $channel1) && isMember($chat_id, $channel2)){
            bot("deleteMessage", [
                'chat_id'=>$chat_id,
                'message_id'=>$callback['message']['message_id']
            ]);

            bot("sendPhoto", [
                'chat_id'=>$chat_id,
                'photo'=>"https://i.ibb.co.com/8L6Ln0PL/20251016-142920.jpg",
                'caption'=>"🎉 Congratulations! You've joined both channels.\nUse it at your own risk ⚠️",
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [['text'=>"💀 Start Bomber", 'url'=>"t.me/SmnBomberBot/startsms"]],
                        [['text'=>"👤 Developer", 'url'=>"t.me/smn_admin"]]
                    ]
                ])
            ]);
        } else {
            bot("answerCallbackQuery", [
                'callback_query_id'=>$callback['id'],
                'text'=>"❌ You haven't joined both channels yet!",
                'show_alert'=>true
            ]);
        }
    }
}
?>