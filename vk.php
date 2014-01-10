<?php

class vk {

	private $app_id = '';
	private $token = '';
	private $u_id = '';
	private $secret;
	private $users = array();

	private function getUrl($host, array $parameters = array()) {
		$vars = array();
		foreach($parameters as $name => $value) {
			$vars[] = $name.'='.$value;
		}
		return $host.(empty($vars) ? '' : '?'.join('&', $vars));
	}

	public function __construct() {

	}

	private function getUserName($id) {
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
			$this->users[$id] = $data->response[0]->last_name.' '.$data->response[0]->first_name;
		}
		return $this->users[$id];
	}

	private function getLoginUrl() {
		return $this->getUrl(
			'https://oauth.vk.com/authorize',
			array(
				'client_id' => $this->app_id,
 				//'scope' => 4095,
 				'scope' => 'messages',
				'redirect_uri' => 'blank.html',
				'display' => 'popup',
				'response_type'=> 'token'
			)
		);
	}

	private function getLongPoll() {
		$url = $this->getUrl(
			'https://api.vk.com/method/messages.getLongPollServer',
			array(
				'access_token' => $this->token
			)
		);
		return $url;
	}

	public function connectToLongPoll() {
		while(true) {
			$url = $this->getLongPoll();
			$data = file_get_contents($url);
			$data = json_decode($data);
			if (empty($data->response->server)) {
				echo "Хрень какая то =( \n";
				var_dump($data);
				return false;
			}
			$ts = $data->response->ts;
			while(true) {
				$url = $this->getUrl(
					'http://'.$data->response->server,
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
				foreach($mess->updates as $m) {
					if ($m[0] == 4 && ($m[2] & 2) == 0) {
						$name = $this->getUserName($m[3]);
						echo $name.': '.$m[6]."\n";
						exec("notify-send-q -i ax-applet -h int:x:100 '$name' '{$m[6]}'\n");
					}
				}
			}
			return true;
		}
		return null;
	}

	public function process() {
		echo $this->getLoginUrl()."\n";

		echo $this->connectToLongPoll()."\n";

	}

}

$vk = new vk();
$vk->process();


