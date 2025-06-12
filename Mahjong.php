<?php

class Mahjong
{
    // 144 張牌
    // 0-35:    萬子
    // 36-71:   筒子
    // 72-107:  條子
    // 108-135: 字牌
    // 136-143: 花牌(春夏秋冬梅蘭菊竹)
    public static function mapToWord($idx)
    {
        $n_map = ['一', '二', '三', '四', '五', '六', '七', '八', '九'];
        if ($idx < 36) {
            $type = '萬';
        } else if ($idx < 72) {
            $type = '筒';
        } else if ($idx < 108) {
            $type = '條';
        }
        if ($idx < 108) {
            return "{$n_map[floor(($idx % 36) / 4)]}{$type}";
        }
        if ($idx < 136) {
            $n_map = ['東風', '南風', '西風', '北風', '紅中', '發財', '白板'];
            return $n_map[floor(($idx - 108) / 4)];
        }
        $n_map = ['春', '夏', '秋', '冬', '梅', '蘭', '菊', '竹'];
        return $n_map[$idx - 136];
    }

    public static function getRandomTileSet()
    {
        // $tile_set = range(0, 143);
        $tile_set = range(0, 135); // 不發花牌
        // 統一轉成一樣的牌
        $tile_set = array_map(function($t) {
            if ($t < 136) {
                return intval(floor($t / 4) * 4);
            }
            return $t; // 花牌不變
        }, $tile_set);
        shuffle($tile_set);
        return $tile_set;
    }

    public static function printTile($set)
    {
        return implode(',', array_map(function ($idx) {
            return self::mapToWord($idx);
        }, $set));
    }

    public static function getTileID($word)
    {
        $n_map = ['一', '二', '三', '四', '五', '六', '七', '八', '九'];
        if (preg_match('/^([一二三四五六七八九])([萬筒條])$/u', $word, $matches)) {
            return array_search($matches[1], $n_map) * 4 + array_search($matches[2], ['萬', '筒', '條']);
        }
        $n_map = ['東風', '南風', '西風', '北風', '紅中', '發財', '白板'];
        if (in_array($word, $n_map)) {
            return 108 + array_search($word, $n_map) * 4;
        }
        $n_map = ['春', '夏', '秋', '冬', '梅', '蘭', '菊', '竹'];
        if (in_array($word, $n_map)) {
            return 136 + array_search($word, $n_map);
        }
        return false;
    }

    public static function can暗槓($set)
    {
        $count = array_count_values($set);
        foreach ($count as $tile => $num) {
            if ($num >= 4) {
                return $tile;
            }
        }
        return false;
    }
}

class GameTable
{
    protected $tile_set;
    protected $players = [];
    protected $public_events = [];
    protected $player_names = ['聶小倩', '祝英台', '白素貞', '花木蘭'];

    public function getInitMessage($player_idx)
    {
        if (true == $this->players[$player_idx]['init']) {
            return '';
        }    
        $this->players[$player_idx]['init'] = true;
        return sprintf("你是一個台灣十六張麻將的玩家，你是 %s ，你的手牌是 %s",
            $this->player_names[$player_idx],
            Mahjong::printTile($this->players[$player_idx]['hand']),
        );
    }

    public function init()
    {
        $this->tile_set = Mahjong::getRandomTileSet();

        // 洗牌
        for ($player = 0; $player < 4; $player++) {
            $this->players[$player] = [
                'hand' => array_slice($this->tile_set, 0, 16),
                'door' => [],
                'saw_event_id' => 0,
                'init' => false,
                'drawn_tile' => null,
            ];
            sort($this->players[$player]['hand']);
            $this->tile_set = array_slice($this->tile_set, 16);
            // 補花
            while ($this->players[$player]['hand'][15] >= 136) {
                $this->players[$player]['door'][] = array_pop($this->players[$player]['hand']);
                $this->players[$player]['hand'][] = array_shift($this->tile_set);
                sort($this->players[$player]['hand']);
            }
        }

        // 開打
        $playing_player = 0;
        $players = ['聶小倩', '祝英台', '白素貞', '花木蘭'];

        // 先檢查是否能夠槓
        foreach ($this->players as $player_idx => $player_data) {
            $kan_tile = Mahjong::can暗槓($player_data['hand']);
            if ($kan_tile !== false) {
                echo "TO: " . $players[$player_idx] . "\n";

                echo $this->getInitMessage($player_idx);
                $allow = [];
                $allow[] = ['暗槓'];
                $input = $this->parseInput($allow, $player_idx);
            }
        }

        while (true) {
            // 抽牌
            $this->players[$playing_player]['drawn_tile'] = array_shift($this->tile_set);

            $message = $this->getInitMessage($playing_player);
            echo $message;
            printf("，你摸到的牌是 %s",
                Mahjong::mapToWord($this->players[$playing_player]['drawn_tile'])
            );
            $allow = $this->checkAllowAction($playing_player);
            $input = $this->parseInput($allow, $playing_player);

            // 檢查有沒有人可以碰或是槓
        }

    }

    public function checkAllowAction($check_player_id, $throw_player_id = null, $throw_drawn_tile = null)
    {
        $allow = [];
        // 如果摸一張牌，基本上可以打一張牌
        if (!is_null($this->players[$check_player_id]['drawn_tile'])) {
            $allow[] = ['tile']; // 可以打出一張牌
        }

        // 如果手上有三張相同，又摸到一張一樣，允許可以暗槓
        if (count(array_filter($this->players[$check_player_id]['hand'], function($tile) use ($check_player_id) {
            return $tile == $this->players[$check_player_id]['drawn_tile'];
            })) >= 3) {
            $allow[] = ['暗槓']; // 可以暗槓
        }

        // 檢查是否可以碰牌
        if (!is_null($throw_player_id) && !is_null($throw_drawn_tile)) {
            $count = array_count_values($this->players[$check_player_id]['hand']);
            if (isset($count[$throw_tile]) && $count[$throw_tile] >= 2) {
                $allow[] = ['碰']; // 可以碰牌
            }
            // 檢查是否可以槓牌
            if ($count[$throw_tile] >= 3) {
                $allow[] = ['槓']; // 可以槓牌
            }
        }

        // 檢查是否可以吃牌
        while (!is_null($throw_player_id) and ($throw_player_id + 1) % 4 == $check_player_id) {
            $merge_tile = array_merge($this->players[$check_player_id]['hand'], [$throw_drawn_tile]);
            sort($merge_tile);

            if ($throw_drawn_tile >= 108) { // 只有萬、筒、條可以吃牌
                break;
            }

            $base = 36 * floor($throw_drawn_tile / 36);
            // 檢查是否可以吃牌
            $drown_number = $throw_drawn_tile % 36 / 4;
            for ($check_id = max(0, $drown_number - 2); $check_id < $drown_number; $check_id ++) {
                for ($hit = 0; $hit < 3; $hit ++) {
                    if (!in_array($base + ($check_id + $hit) * 4, $merge_tile)) {
                        continue 2; // 沒有吃牌的可能
                    }
                }
                $allow[] = ['吃', $base + $check_id * 4]; // 可以吃牌
            }
        }

        if (!is_null($throw_drawn_tile)) {
            if ($this->check胡牌(array_merge($this->players[$check_player_id]['hand'], [$throw_drawn_tile]))) {
                $allow[] = ['胡']; // 可以胡牌
            }
        } else {
            if ($this->check胡牌(array_merge($this->players[$check_player_id]['hand'], [$this->players[$check_player_id]['drawn_tile']]))) {
                $allow[] = ['自摸']; // 可以胡牌
            }
        }
        return $allow;
    }

    public function check胡牌($tiles)
    {
    }

    public function parseInput($allow, $player_idx)
    {
        while (true) {
            $message = "，請問您要做什麼？（";
            $terms = [];
            foreach ($allow as $term) {
                if ($term == 'tile') {
                    $terms[] = "如果要打出一張牌請直接用 {\"tile\":\"牌名\"} 的格式回答我你要打的牌";
                } elseif ($term == '暗槓') {
                    $terms[] = "如果要暗槓請直接用 {\"暗槓\":true} 的格式回答我你是否要暗槓";
                }
            }
            $terms[] = "不需要分析為什麼，如果你想要垃圾話，可以透過 \"talk\":\"垃圾話內容\" 補充";
            echo $message . implode('，', $terms) . "）\n";

            $ret = readline("TO Player " . $this->player_names[$player_idx] . " > ");
            if (!$ret = json_decode($ret)) {
                continue;
            }
            foreach ($allow as $term) {
                if ($term == 'tile') {
                    if (!($ret->tile ?? false)) {
                        continue;
                    }
                    $tile_id = Mahjong::getTileID($ret->tile);
                    if ($tile_id === false) {
                        echo "無效的牌名，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                    if ($tile_id === $this->players[$player_idx]['drawn_tile']) {
                        return $ret;
                    } else if (in_array($tile_id, $this->players[$player_idx]['hand'])) {
                        return $ret;
                    } else {
                        echo "你沒有 {$ret->tile}(tile_id={$tile_id}) 這張牌，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                }
            }
            break;
        }
        return $ret;
    }
}

