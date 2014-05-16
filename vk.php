<?php

class vk {

    const API_URL = 'https://api.vk.com/method';

    private $app_id = null;
    private $token = null;
    private $current_user = null;
    private $users = array();

    private $default_permission = array(
        'messages',
        'audio',
        'offline',
        'status',
        'docs',
        'notes',
        'friends'
    );

    private function getUrl($method, array $parameters = array(), $api_url = self::API_URL) {
        $vars = array();
        foreach ($parameters as $name => $value) {
            $vars[] = $name . '=' . urlencode($value);
        }
        $method = empty($method) ? '' : '/'.$method;
        $parameters = (empty($vars) ? '' : '?' . join('&', $vars));
        return $api_url.$method.$parameters;
    }

    public function __construct($app_id, $token = null) {
        $this->app_id = $app_id;
        if (is_null($token)) {
            echo $this->getLoginUrl();
        }
        $this->token = $token;
        $this->isLogin();
    }

    private function isLogin() {
        $url = $this->getUrl(
            'users.get',
            array(
                'access_token' => $this->token
            )
        );
        $data = $this->runCommand($url);
        if (empty($data->response[0]->uid)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
        $this->current_user = array(
            'id' => $data->response[0]->uid,
            'first_name' => $data->response[0]->first_name,
            'last_name' => $data->response[0]->last_name
        );
        $this->log('Токин кривой =(', true);
        return true;
    }

    public function getUserName($id) {
        if (empty($this->current_user)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
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

    public function getLoginUrl(array $permission = array()) {
        if (empty($permission)) {
            $permission = $this->default_permission;
        }
        return $this->getUrl(
            'authorize',
            array(
                'client_id' => $this->app_id,
                'scope' => join(',', $permission),
                'redirect_uri' => 'blank.html',
                'display' => 'popup',
                'response_type' => 'token'
            ),
            'https://oauth.vk.com'
        );
    }

    public function getLongPoll() {
        if (empty($this->current_user)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
        $url = $this->getUrl(
            'messages.getLongPollServer',
            array(
                'access_token' => $this->token
            )
        );
        return $url;
    }

    public function connectToLongPoll($call_bake) {
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
                    var_dump($mess);
                    exit;
                }
                foreach ($mess->updates as $m) {
                    if ($m[0] == 4 && ($m[2] & 2) == 0) {
                        $name = $this->getUserName($m[3]);
                        $this->log($name.': '.$m[6]);
                        $call_bake($name, $m[6]);
                    }
                }
            }
            return true;
        }
        return null;
    }

    public function getDocs() {
        if (empty($this->current_user)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
        $url = $this->getUrl(
            'docs.get',
            array(
                'access_token' => $this->token,
            )
        );
        $response = $this->runCommand($url);
        return $response->response;
    }

    public function getNotices() {
        if (empty($this->current_user)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
        $url = $this->getUrl(
            'notes.get',
            array(
                'access_token' => $this->token,
            )
        );
        $response = $this->runCommand($url);
        return $response->response;
    }

    public function createNotice($title, $text) {
        if (empty($this->current_user)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
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

    private function log($text, $is_system = false) {
        $type = $is_system ? 'system' : 'info';
        $date = date('d.m.Y H:i.s');
        echo "$type: ($date) -> $text\n";
    }

    public function uploadFile($file_name, $file_content, $is_base_64 = false) {
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

    private function uploadData($file_name, $file_content, $upload_server) {

        $delimiter = '-------------' . uniqid();

        $fileFields = array(
            $file_name => array(
                //'type' => 'text/plain',
                'content' => $file_content
            ),
        );

        $postFields = array(
            //'otherformfield'   => 'content of otherformfield is this text',
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
        curl_setopt($handle, CURLOPT_HTTPHEADER , array(
            'Content-Type: multipart/form-data; boundary='.$delimiter,
            'Content-Length: '.strlen($data)));
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($handle);
        $response_data = json_decode($response);
        return $this->saveFile($response_data->file);
    }

    private function saveFile($file_string) {
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

    public function setUserTextStatus($text) {
        if (empty($this->current_user)) {
            $this->log('Токин кривой =(', true);
            return false;
        }
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

    public function getUserTextStatus() {

    }

    public function runCommand($url) {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        $result = json_decode($response);
        return $result ? $result : null;
    }

    public function downloadAudiFromUser($user, $dir) {

        $url = $this->getUrl(
            'audio.get',
            array(
                'owner_id' => $user,
                'need_user' => 0,
                'access_token' => $this->token
            )
        );

        $data = $this->runCommand($url);
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

}
