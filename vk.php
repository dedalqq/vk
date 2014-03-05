<?php

class vk
{

    private $app_id = null;
    private $token = null;
    private $current_user = null;
    private $users = array();

    private $default_permission = array(
        'messages',
        'audio',
        'offline',
        'status'
    );

    private function getUrl($host, array $parameters = array())
    {
        $vars = array();
        foreach ($parameters as $name => $value) {
            $vars[] = $name . '=' . $value;
        }
        return $host . (empty($vars) ? '' : '?' . join('&', $vars));
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
            'https://api.vk.com/method/users.get',
            array(
                'access_token' => $this->token
            )
        );
        $data = $this->runCommand($url);
        if (empty($data->response[0]->uid)) {
            echo "Токин кривой =(\n";
            return false;
        }
        $this->current_user = array(
            'id' => $data->response[0]->uid,
            'first_name' => $data->response[0]->first_name,
            'last_name' => $data->response[0]->last_name
        );
        echo "Токин верный =)\n";
        return true;
    }

    public function getUserName($id) {
        if (empty($this->current_user)) {
            echo "Токин кривой =(\n";
            return false;
        }
        if (empty($this->users[$id])) {
            $url = $this->getUrl(
                'https://api.vk.com/method/users.get',
                array(
                    'uids' => $id,
                    'access_token' => $this->token
                )
            );
            $data = file_get_contents($url);
            $data = json_decode($data);
            $this->users[$id] = $data->response[0]->last_name . ' ' . $data->response[0]->first_name;
        }
        return $this->users[$id];
    }

    public function getLoginUrl(array $permission = array()) {
        if (empty($permission)) {
            $permission = $this->default_permission;
        }
        return $this->getUrl(
            'https://oauth.vk.com/authorize',
            array(
                'client_id' => $this->app_id,
                'scope' => join(',', $permission),
                'redirect_uri' => 'blank.html',
                'display' => 'popup',
                'response_type' => 'token'
            )
        );
    }

    public function getLongPoll() {
        if (empty($this->current_user)) {
            echo "Токин кривой =(\n";
            return false;
        }
        $url = $this->getUrl(
            'https://api.vk.com/method/messages.getLongPollServer',
            array(
                'access_token' => $this->token
            )
        );
        return $url;
    }

    public function connectToLongPoll($call_bake) {
        while (true) {
            $url = $this->getLongPoll();
            $data = file_get_contents($url);
            $data = json_decode($data);
            if (empty($data->response->server)) {
                echo "Хрень какая то =( \n";
                var_dump($data);
                return false;
            }
            $ts = $data->response->ts;
            while (true) {
                $url = $this->getUrl(
                    'http://' . $data->response->server,
                    array(
                        'act' => 'a_check',
                        'key' => $data->response->key,
                        'ts' => $ts,
                        'wait' => 25,
                        'mode' => 2
                    )
                );
                $mess = file_get_contents($url);
                $mess = json_decode($mess);
                if (empty($mess->ts)) {
                    continue(2);
                }
                $ts = $mess->ts;
                if (!is_array($mess->updates)) {
                    var_dump($mess);
                    exit;
                }
                foreach ($mess->updates as $m) {
                    if ($m[0] == 4 && ($m[2] & 2) == 0) {
                        $name = $this->getUserName($m[3]);
                        echo $name . ': ' . $m[6] . "\n";
                        $call_bake($name, $m[6]);
                    }
                }
            }
            return true;
        }
        return null;
    }

    public function setUserTextStatus($text) {
        if (empty($this->current_user)) {
            echo "Токин кривой =(\n";
            return false;
        }
        $url = $this->getUrl(
            'https://api.vk.com/method/status.set',
            array(
                'access_token' => $this->token,
                'text' => urlencode($text)
            )
        );
        $response = $this->runCommand($url);
        return (bool)$response->response;
    }

    public function getUserTextStatus() {

    }

    public function runCommand($url) {
        $request = file_get_contents($url);
        return json_decode($request);
    }

    public function downloadAudiFromUser($user, $dir) {

        $url = $this->getUrl(
            'https://api.vk.com/method/audio.get',
            array(
                'owner_id' => $user,
                'need_user' => 0,
                'access_token' => $this->token
            )
        );

        $data = file_get_contents($url);
        $data = json_decode($data);
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