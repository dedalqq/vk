<?php

namespace vk;

use model\history;

class vk
{

    const API_URL = 'https://api.vk.com/method';

    private $app_id = null;
    private $token = null;

    private $cur_user_id = 0;
    private $cur_user_first_name = '';
    private $cur_user_last_name = '';

    private $client_secret = '';

    private $app_token = '';

    private $users = array();

    private $sand_message_id = 0;

    private $default_permission = array(
        'messages',
        'notify',
        'email',
        'audio',
        'offline',
        'status',
        'docs',
        'notes',
        'friends'
    );

    public function setClientSecret($client_secret) {
        $this->client_secret = $client_secret;
    }

    private function getUrl($method, array $parameters = array(), $api_url = self::API_URL)
    {
        $vars = array();
        foreach ($parameters as $name => $value) {
            $vars[] = $name . '=' . urlencode($value);
        }
        $method = empty($method) ? '' : '/' . $method;
        $parameters = (empty($vars) ? '' : '?' . join('&', $vars));
        return $api_url . $method . $parameters;
    }

    public function __construct($app_id, $token = null)
    {
        $this->app_id = $app_id;

        if (!is_null($token)) {
            $this->token = $token;
            $this->Login();
        }
    }

    public function setAppToken($app_token) {
        $this->app_token = $app_token;
    }

    private function Login()
    {
        $url = $this->getUrl(
            'users.get',
            array(
                'access_token' => $this->token
            )
        );
        $data = $this->runCommand($url);
        if (empty($data->response[0]->uid)) {
            $this->log('Не удалось авторизироваться', true);
            return false;
        }

        $this->cur_user_id = (int)$data->response[0]->uid;
        $this->cur_user_first_name = $data->response[0]->first_name;
        $this->cur_user_last_name = $data->response[0]->last_name;

        $this->log('Авторизация прошла успешно', true);
        return true;
    }

    public function getCurUserId() {
        return $this->cur_user_id;
    }

    public function isLogin()
    {
        return ($this->cur_user_id > 0);
    }

    public function getCurUserName() {
        // todo передедать на объект
        return array(
            $this->cur_user_id,
            $this->cur_user_first_name,
            $this->cur_user_last_name
        );
    }

    public function getUserName($id)
    {
        if (empty($this->users[$id])) {
            $url = $this->getUrl(
                'users.get',
                array(
                    'uids' => $id,
                    'access_token' => $this->token
                )
            );
            $data = $this->runCommand($url);
            $this->users[$id] = $data->response[0]->last_name . ' ' . $data->response[0]->first_name;
        }
        return $this->users[$id];
    }

    public function sandMessage($user, $text, $can_replace = true) {

        $data = array(
            'uid'     => $user,
            'message'    => $text,
            'access_token' => $this->token
        );

        if ($can_replace) {
            ++$this->sand_message_id;
            $data['guid'] = $this->sand_message_id;
        }

        $url = $this->getUrl(
            'messages.send',
            $data
        );

        return $this->runCommand($url);
    }


    public function getHistory($id)
    {
        //$history = '';
        $i = 0;
        $count = 150;
        while (true) {

            $url = $this->getUrl(
                'messages.getHistory',
                array(
                    'rev' => 1,
                    'count' => $count,
                    'offset' => ($count * $i),
                    'uid' => $id,
                    'access_token' => $this->token
                )
            );
            $data = $this->runCommand($url);

            //var_dump($data);

            if (count($data->response) == 1) {
                break;
            }
            echo count($data->response);

            unset($data->response[0]);

            foreach ($data->response as $item) {

                $history = new history();
                $history->user_id = $item->from_id;
                $history->text = $item->body;
                $history->date = $item->date;
                $history->save();

                //$user_name = $this->getUserName($item->from_id);
                //$date = date('d.m.Y H:i:s', $item->date);
                //echo "$user_name [{$item->mid}] ($date): {$item->body} \n";
            }
            ++$i;
            echo "-> $i \n";
            //sleep(1);
        }
        //return $history;

    }

    public function getLoginUrl(array $permission = array(), $redirect_uri = 'blank.html')
    {
        if (empty($permission)) {
            $permission = $this->default_permission;
        }
        return $this->getUrl(
            'authorize',
            array(
                'client_id' => $this->app_id,
                'scope' => join(',', $permission),
                'redirect_uri' => $redirect_uri,
                'display' => 'popup',
                'response_type' => 'token'
            ),
            'https://oauth.vk.com'
        );
    }

    public function getLongPoll()
    {
        $url = $this->getUrl(
            'messages.getLongPollServer',
            array(
                'access_token' => $this->token
            )
        );
        return $url;
    }

    public function connectToLongPoll($call_bake)
    {
        while (true) {
            $url = $this->getLongPoll();
            $data = $this->runCommand($url);
            if (is_null($data) || empty($data->response->server)) {
                $this->log('Не удалось получить сервер', true);
                sleep(10);
                continue;
            }
            $this->log('Подключение установлено', true);
            $ts = $data->response->ts;
            while (true) {
                $url = $this->getUrl(
                    '',
                    array(
                        'act' => 'a_check',
                        'key' => $data->response->key,
                        'ts' => $ts,
                        'wait' => 25,
                        'mode' => 2
                    ),
                    'http://' . $data->response->server
                );
                $mess = $this->runCommand($url);
                if (empty($mess->ts)) {
                    continue(2);
                }
                $ts = $mess->ts;
                if (!empty($mess->failed)) {
                    sleep(10);
                    continue(2);
                }
                if (!is_array($mess->updates)) {
                    exit;
                }
                foreach ($mess->updates as $message_data) {

                    if (is_callable($call_bake)) {
                        $message = new message($this, $message_data);
                        $call_bake($message);
                    }
                }
            }
            return true;
        }
        return null;
    }

    public function getDocs()
    {
        $url = $this->getUrl(
            'docs.get',
            array(
                'access_token' => $this->token,
            )
        );
        $response = $this->runCommand($url);
        return $response->response;
    }

    public function getNotices()
    {
        $url = $this->getUrl(
            'notes.get',
            array(
                'access_token' => $this->token,
            )
        );
        $response = $this->runCommand($url);
        return $response->response;
    }

    public function createNotice($title, $text)
    {
        $url = $this->getUrl(
            'notes.add',
            array(
                'access_token' => $this->token,
                'title' => $title,
                'text' => $text
            )
        );
        $response = $this->runCommand($url);
        return $response->response;
    }

    private function log($text, $is_system = false)
    {
        return null;
        $type = $is_system ? 'system' : 'info';
        $date = date('d.m.Y H:i.s');
        echo "$type: ($date) -> $text\n";
    }

    public function uploadFile($file_name, $file_content, $is_base_64 = false)
    {
        if ($is_base_64) {
            $file_content = base64_decode($file_content);
        }
        $url = $this->getUrl(
            'docs.getUploadServer',
            array(
                'access_token' => $this->token,
            )
        );
        $response = $this->runCommand($url);
        $upload_url = $response->response->upload_url;
        return $this->uploadData($file_name, $file_content, $upload_url);
    }

    private function uploadData($file_name, $file_content, $upload_server)
    {

        $delimiter = '-------------' . uniqid();

        $fileFields = array(
            $file_name => array(
                //'type' => 'text/plain',
                'content' => $file_content
            ),
        );

        $postFields = array(//'otherformfield'   => 'content of otherformfield is this text',
        );

        $data = '';


        foreach ($postFields as $name => $content) {
            $data .= "--" . $delimiter . "\r\n";
            $data .= 'Content-Disposition: form-data; name="' . $name . '"';
            $data .= "\r\n\r\n";
        }

        foreach ($fileFields as $name => $file) {
            $data .= "--" . $delimiter . "\r\n";
            $data .= 'Content-Disposition: form-data; name="' . $name . '";' .
                ' filename="' . $name . '"' . "\r\n";
            $data .= 'Content-Type: ' . $file['type'] . "\r\n";
            $data .= "\r\n";
            $data .= $file['content'] . "\r\n";
        }

        $data .= "--" . $delimiter . "--\r\n";

        $handle = curl_init($upload_server);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Content-Type: multipart/form-data; boundary=' . $delimiter,
            'Content-Length: ' . strlen($data)));
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($handle);
        $response_data = json_decode($response);
        return $this->saveFile($response_data->file);
    }

    private function saveFile($file_string)
    {
        $url = $this->getUrl(
            'docs.save',
            array(
                'access_token' => $this->token,
                'file' => $file_string
            )
        );
        $response = $this->runCommand($url);
        return $response;
    }

    public function setUserTextStatus($text)
    {
        $url = $this->getUrl(
            'status.set',
            array(
                'access_token' => $this->token,
                'text' => $text
            )
        );
        $response = $this->runCommand($url);
        return (bool)$response->response;
    }

    public function getUserTextStatus()
    {

    }

    public function runCommand($url)
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        $result = json_decode($response);
        return $result ? $result : null;
    }

    /**
     * @param $user_id
     * @return mixed|null
     */
    public function getAudioList($user_id) {

        $url = $this->getUrl(
            'audio.get',
            array(
                'owner_id' => $user_id,
                'need_user' => 0,
                'access_token' => $this->token
            )
        );

        return $this->runCommand($url);
    }

    public function downloadAudiFromUser($user, $dir)
    {

        $data = $this->getAudioList($user);

        foreach ($data->response as $i => $v) {

            if (is_numeric($v)) {
                continue;
            }

            $url = $v->url;
            $title = $v->artist . ' - ' . $v->title;
            $content = file_get_contents($url);
            file_put_contents($dir . '/' . $title . '.mp3', $content);
        }

    }

    public function loginApp() {

        $url = $this->getUrl(
            'access_token',
            array(
                'client_id' => $this->app_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'client_credentials'
            ),
            'https://oauth.vk.com'
        );

        $data = $this->runCommand($url);

        if (empty($data->access_token)) {
            return null;
        }

        return (string)$data->access_token;
    }

    public function sendAppNotificationToUser($user_id, $text) {
        $url = $this->getUrl(
            'secure.sendNotification',
            array(
                'user_id' => $user_id,
                'message' => $text,
                'client_secret' => $this->client_secret,
                'access_token' => $this->app_token
            )
        );

        $data = $this->runCommand($url);
        var_dump($data);
    }
}
