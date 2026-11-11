<?php
namespace xRocket;

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

class Bot {
    private $token;
    private $apiUrl = 'https://api.telegram.org/bot';
    private $http;

    public function __construct() {
        global $token;
        $this->token = $token;
        $this->http = new Client(['timeout' => 30]);
    }

    private function getUpdates($offset = 0) {
        $url = $this->apiUrl . $this->token . "/getUpdates?offset=$offset&timeout=30&limit=10";
        $response = $this->http->get($url);
        return json_decode($response->getBody(), true);
    }

    private function sendMessage($chatId, $text, $keyboard = null) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard) $data['reply_markup'] = $keyboard;

        $this->http->post($this->apiUrl . $this->token . "/sendMessage", ['json' => $data]);
    }

    private function editMessageText($chatId, $messageId, $text, $keyboard = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard) $data['reply_markup'] = $keyboard;

        $this->http->post($this->apiUrl . $this->token . "/editMessageText", ['json' => $data]);
    }

    private function answerCallback($callbackQueryId) {
        $url = $this->apiUrl . $this->token . "/answerCallbackQuery";
        $this->http->post($url, ['form_params' => ['callback_query_id' => $callbackQueryId]]);
    }

    private function saveData() {
        file_put_contents('data/users.json', json_encode($this->users, JSON_PRETTY_PRINT));
    }

    public function run() {
        $offset = 0;

        // ইউজার ডেটা লোড
        if (!file_exists('data/users.json')) {
            file_put_contents('data/users.json', json_encode([], JSON_PRETTY_PRINT));
        }
        $this->users = json_decode(file_get_contents('data/users.json'), true);

        while (true) {
            $updates = $this->getUpdates($offset);

            foreach ($updates['result'] ?? [] as $update) {
                $offset = max($offset, $update['update_id'] + 1);

                $msg = $update['message'] ?? $update['edited_message'] ?? null;
                if (!$msg) continue;

                $chatId = $msg['chat']['id'];
                $text = $msg['text'] ?? '';
                $callback = $update['callback_query'] ?? null;

                // টেস্ট ইউজার
                if ($chatId != 8161656411) continue;

                $user = $this->users[$chatId] ?? [
                    'first_name' => $msg['from']['first_name'] ?? 'User',
                    'balance' => 0.00,
                    'history' => []
                ];

                // /start
                if ($text === '/start') {
                    $keyboard = [
                        'inline_keyboard' => [
                            [['text' => 'Wallet • 0.06 $', 'callback_data' => 'wallet']],
                            [['text' => 'Deposit', 'callback_data' => 'deposit']],
                            [['text' => 'Withdraw', 'callback_data' => 'withdraw']],
                            [['text' => 'Exchange', 'callback_data' => 'exchange']],
                            [['text' => 'Swap', 'callback_data' => 'swap']],
                            [['text' => 'Buy/Sell', 'callback_data' => 'buysell']],
                            [['text' => 'P2P Market', 'callback_data' => 'p2p']],
                            [['text' => 'Pay via QR', 'callback_data' => 'qr']],
                            [['text' => 'Show more', 'callback_data' => 'more']]
                        ]
                    ];

                    $msgText = "🤖 <b>xRocket</b> — is a wallet-bot for receiving, sending, purchasing and storing cryptocurrencies in Telegram.\n\nRead about all the features in the official channel";
                    $this->sendMessage($chatId, $msgText, $keyboard);
                }
                // ক্যাব্যাক
                else if ($callback) {
                    $this->answerCallback($callback['id']);
                    $data = $callback['data'];

                    if ($data === 'wallet') {
                        $this->editMessageText($chatId, $callback['message']['message_id'], "💰 <b>Wallet • 0.06 $</b>\n\nBalance: <b>0.06 $</b>\n\nHistory:\n" . implode("\n", array_slice($user['history'], -5)), null);
                    }
                    else if ($data === 'deposit') {
                        $this->editMessageText($chatId, $callback['message']['message_id'], "🟢 <b>Deposit</b>\n\nপরিমাণ দিন (USD):", null);
                    }
                    else if ($data === 'withdraw') {
                        $this->editMessageText($chatId, $callback['message']['message_id'], "🔴 <b>Withdraw</b>\n\nপরিমাণ দিন (USD):", null);
                    }
                    else if (in_array($data, ['exchange','swap','buysell','p2p','qr','more'])) {
                        $this->editMessageText($chatId, $callback['message']['message_id'], "🛠️ <b>$data</b> ফিচার আসছে...\n\nআরও কমান্ড/ফিচার চান?", null);
                    }
                }
                // ডিপোজিট/উইথড্র কমান্ড
                else if (in_array($text, ['/deposit', '/withdraw'])) {
                    $type = $text === '/deposit' ? 'Deposit' : 'Withdraw';
                    $user['history'][] = "$type: 0.00 $";
                    $this->users[$chatId] = $user;
                    $this->saveData();
                    $this->sendMessage($chatId, "✅ $type সম্পূর্ণ হয়েছে!\n\nনতুন ব্যালেন্স: " . $user['balance']);
                }
            }

            $this->saveData();
            usleep(100000); // 0.1 সেকেন্ড
        }
    }
}
