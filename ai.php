<?php

include(__DIR__ . "/Mahjong.php");
include(__DIR__ . "/config.inc.php");

$log_file = __DIR__ . "/logs/" . date('YmdHis') . ".log";
file_put_contents($log_file, json_encode("AI Log Start") . "\n", FILE_APPEND);

class AIHelper
{
    public static function geminiQuery($data, $retry = 3)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . getenv('gemini_api_key');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        // 移除第一個 system
        if (isset($data[0]) && $data[0]->role == 'system') {
            array_shift($data);
        }
        $input = [
            'contents' => array_map(function($item) {
                return [
                    'role' => $item->role,
                    'parts' => [['text' => $item->text ?? ""]],
                ];
            }, $data),
        ];
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($input));
        $content = curl_exec($curl);
        $obj = json_decode($content);
        curl_close($curl);
        file_put_contents($GLOBALS['log_file'], json_encode([
            'time' => date('Y-m-d H:i:s'),
            'type' => 'gemini',
            "data" => $input,
            "response" => $obj
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        if (($obj->error->status ?? false) == 'RESOURCE_EXHAUSTED') {
            if ($retry > 0) {
                error_log("Retrying due to RESOURCE_EXHAUSTED, attempts left: " . $retry);
                sleep(30); // Wait a bit before retrying
                return self::geminiQuery($data, $retry - 1);
            } else {
                throw new Exception("Resource exhausted after retries");
            }
        }
        $parts = $obj->candidates[0]->content->parts ?? [];
        if (!$parts) {
            print_r($obj);
            throw new Exception("No response from AI");
        }
        $text = $parts[0]->text;
        return $text;
    }

    public static function deepseekQuery($data, $retry = 3)
    {
        $data = array_map(function($item) {
            if ($item->role == 'model') {
                $item->role = 'assistant';
            }
            return (object)[
                'role' => $item->role,
                'content' => $item->text ?? ""
            ];
        }, $data);
        $url = "https://api.deepseek.com/v1/chat/completions";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('deekseek_api_key'),
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        $data = [
            'model' => "deepseek-chat",
            'messages' => $data,
            'stream' => false,
        ];
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        $content = curl_exec($curl);
        $obj = json_decode($content);
        curl_close($curl);
        file_put_contents($GLOBALS['log_file'], json_encode([
            'time' => date('Y-m-d H:i:s'),
            'type' => 'deepseek',
            "data" => $data,
            "response" => $obj
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

        $message = $obj->choices[0]->message->content ?? null;
        if (is_null($message)) {
            echo $content;
            throw new Exception("No response from AI");
        }
        return $message;
    }

    protected static $bot_contents = [];

    public static function talkToBot($idx, $message)
    {
        self::$bot_contents[$idx] = self::$bot_contents[$idx] ?? [(object)[
            'role' => 'system',
            'text' => "你是一個麻將機器人，請根據我給你的資訊回答我需要的動作。你需要用 JSON 格式回答我，不需要給我你分析的過程，只需要給我 JSON 回應就好。",
        ]];
        self::$bot_contents[$idx][] = (object)[
            "role" => "user",
            'text' => $message,
        ];
        $text = self::geminiQuery(self::$bot_contents[$idx]);
        //$text = self::deepseekQuery(self::$bot_contents[$idx]);
        error_log("AI Response: " . $text);
        if (!preg_match("#(\{.*\})#s", $text, $matches)) {
            throw new Exception("Invalid response format: " . $text);
        }

        self::$bot_contents[$idx][] = (object)[
            "role" => "model",
            "text" => $text
        ];
        return $matches[1];
    }
}

$table = new GameTable();
$table->init(function($prompt, $player_idx, $player_name) use ($log_file, $table) {
    file_put_contents($log_file, json_encode([
        'type' => 'table',
        'time' => date('Y-m-d H:i:s'),
        'table' => $table,
    ]) . "\n", FILE_APPEND);

    file_put_contents($log_file, json_encode([
        'type' => 'prompt',
        'time' => date('Y-m-d H:i:s'),
        "prompt" => $prompt,
        "player_idx" => $player_idx,
        "player_name" => $player_name
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

    echo $prompt . "\n";
    echo "TO Player {$player_name} > \n";
    $ret = AIHelper::talkToBot($player_idx, $prompt);
    file_put_contents($log_file, json_encode([
        'type' => 'response',
        'time' => date('Y-m-d H:i:s'),
        "prompt" => $prompt,
        "player_idx" => $player_idx,
        "player_name" => $player_name,
        'response' => $ret
    ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    return $ret;
});
