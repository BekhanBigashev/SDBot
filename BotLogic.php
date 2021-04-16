<?php 
$db = new Database(DB_HOST,DB_USER,DB_PASS,DB_NAME);

class UserStates{
	const MAIN = 0;
	const COMPANYNAME = 1;
	const NEW_REQUEST = 2;
	const EDIT_REQUEST = 3;
	const LIST_REQUEST = 4;
	const FEEDBACK = 5;
	const FEEDBACK_COMMENT = 6;
	const LIKE = 7;
}

class ServiceDeskWABot{
    //specify instance URL and token
    var $APIurl = 'https://api.chat-api.com/instance239832/';
    var $token = '354ge0sf10hjthgx';
    var $database = false;
    var $current_user = false;

    public function __construct(){
    	global $db;
	    //get the JSON body from the instance
	    $json = file_get_contents('php://input');
	    $decoded = json_decode($json,true);
	   // add_log(print_r($decoded, true));
	    $request = new BXRequest();
	    //write parsed JSON-body to the file for debugging
	    ob_start();
	    var_dump($decoded);
	    $input = ob_get_contents();
	    ob_end_clean();
	 //   file_put_contents($_SERVER["DOCUMENT_ROOT"].'/local/components/whatsapp_bot/webhook/logs/'.date("d-m-Y").'.log',$json.PHP_EOL,FILE_APPEND);

	    if(isset($decoded['messages'])){
	    //check every new message
	    	foreach($decoded['messages'] as $message){
	    //delete excess spaces and split the message on spaces. The first word in the message is a command, other words are parameters
	    		$text = explode(' ',trim($message['body']));
	    //current message shouldn't be send from your bot, because it calls recursion
	    		if(!$message['fromMe']){
	    			if ($message['chatId'] == "77073826425@c.us") {
	    				$user = $this->processingUser($message);
	    				$state = $this->getUserState($message["chatId"]);
	    				$state_n = $state['wa_state'];
	    				//$this->setUserState($user['id'],0);
	    				if ($state_n == UserStates::MAIN) {	    				
							$response = $this->processingContact($user['phone']);		
							if(!$response){
								add_log("response undefined");
								$this->setUserState($message["chatId"],UserStates::COMPANYNAME);
								$text = "Не найдена компания с таким номером телефона. Пожалуйста, отправьте название Вашей компании";
								$this->sendMessage($message['chatId'], $text);
							}else{
								add_log("response defined".print_r($response,true));
								$this->setUserCompany($user['id'],$response["ID"],$response["TITLE"]);
								$this->setUserState($message["chatId"],UserStates::NEW_REQUEST);
								$text = "Здравствуйте, ".$message['senderName']."! Вы представляете компанию \"".$message['body']."\"\nПожалуйста, отправьте нам свой вопрос или нажмите '1' чтобы посмотреть список обращений";
								$this->sendMessage($message['chatId'], $text);
							}
	    				}
	    				elseif ($state_n == UserStates::COMPANYNAME) {
	    					$this->setUserCompany($user['id'], 0, $message['body']);
	    					$this->setUserState($message["chatId"],UserStates::NEW_REQUEST);
	    					$text = "Здравствуйте, ".$message['senderName']."! Вы представляете компанию \"".$message['body']."\"\nПожалуйста, отправьте нам свой вопрос или нажмите '1' чтобы посмотреть список обращений";
	    					$this->sendMessage($message['chatId'], $text);
	    				}
						elseif($state_n == UserStates::NEW_REQUEST){
							if($message['body']==1){
								$this->setUserState($message["chatId"],UserStates::LIST_REQUEST);
								$text = $this->sendList($user);
								$this->sendMessage($message['chatId'], $text);
							}else{
								$response = $request->newTicketFromWhatsApp($message['chatId'],$user["phone"],$message['body'],$user["company_name"],$user["company_id"],1,$message['senderName']);
								add_log("Новый запрос");
								add_log(print_r($response,true));
								$this->setUserState($message["chatId"],UserStates::EDIT_REQUEST, $response);
								$text = "Ваше обращение принято! Номер вашего обращения - $response";
								$text .= "\nНажмите цифру для выбора:\n1.Создать новое обращение\n2.Посмотреть список обращений\n\nЧтобы дополнить текущее обращение просто отправьте сообщение.";
								$this->sendMessage($message['chatId'], $text);
							}
						}
						elseif($state_n == UserStates::LIST_REQUEST){
							if ($message['body'] == 1) {
								$this->setUserState($message["chatId"], UserStates::NEW_REQUEST);
								$this->sendQuestion($message);
							}
							elseif ($message['body'] == 2) {
								$text = $this->sendList($user);
								$this->sendMessage($message['chatId'], $text);
							}
							else{
								$text = "Чтобы создать новое обращение, нажмите 1 и отправьте вопрос. Чтобы получить обновленный список обращений, нажмите 2";
								$this->sendMessage($message['chatId'], $text);
							}
						}
						elseif($state_n == UserStates::EDIT_REQUEST){
							if ($message['body'] == 1) {
								$this->setUserState($message["chatId"],UserStates::NEW_REQUEST);
								$this->sendQuestion($message);
							}
							elseif ($message['body'] == 2) {
								$this->setUserState($message["chatId"],UserStates::LIST_REQUEST);
								$text = $this->sendList($user);
								$this->sendMessage($message['chatId'], $text);
							}
							else{
								$response = $request->updateTicketDescr($state["wa_param"],$message['body']);
								$text = "Ваше обращение дополнено! Номер вашего обращения - ".$state["wa_param"];
								$text .= "\nНажмите цифру для выбора:\n1.Создать новое обращение\n2.Посмотреть список обращений\n\nЧтобы дополнить текущее обращение просто отправьте сообщение.";
								$this->sendMessage($message['chatId'], $text);
							}
						}
	    			}
	    			
	    		}
	    	}
	    }
	}

	public function sendList($user){
		$message_text = "Список ваших обращений:\n\n";
		$request = new BXRequest();
		$list = $request->getTicketsFromWhatsApp($user['phone']);
		add_log(print_r($list,true));
		if (count($list) > 0) {
			foreach($list as $item){
				$message_text .= 'Обращение *#'.$item["ID"]."*\n";
				foreach($item["PROPERTY_1431"] as $value){
					$message_text .= "Описание: ".$value."\n";
					break;
				}
				$status = 1;
				$spec = 0;
				if(array_key_exists("PROPERTY_1439", $item)){
					foreach($item["PROPERTY_1439"] as $value){
						$status = $value;
						break;
					}
				}
				if(array_key_exists("PROPERTY_1435", $item)){
					foreach($item["PROPERTY_1435"] as $value){
						$spec = $value;
						break;
					}
					if($spec)
						$spec = $request->getUser($spec);
				}
				$message_text .= "Статус обращения: ";
				if($status==1){
					$message_text .= "Ждёт принятия";
				}elseif($status==2){
					$message_text .= "В обработке\n";
					if($spec){
						$message_text .= "Ответственный специалист: ";
						$message_text .= $spec["NAME"]." ".$spec["LAST_NAME"];
					}
				}elseif($status==3){
					$message_text .= "Выполнено\n";
					if($spec){
						$message_text .= "Ответственный специалист: ";
						$message_text .= $spec["NAME"]." ".$spec["LAST_NAME"];
					}
				}elseif($status==4){
					$message_text .= "На доработке\n";
					if($spec){
						$message_text .= "Ответственный специалист: ";
						$message_text .= $spec["NAME"]." ".$spec["LAST_NAME"];
					}
				}
				$message_text .= "\n-\n";
			}
		}
		else{
			$message_text = "Список ваших обращений пуст\n\n";
		}
		
		$message_text .= "Нажмите цифру для выбора: \n1. Написать новое обращение\n2.обновить список";
		return $message_text;
		//$this->sendMessage($message['chatId'], $message_text);
	}
	public function sendMainMenu($message, $log = false){
		global $db;
		if ($log) {
			$users = $db->selectFrom('users');
			$text = "";
			foreach ($users as $key => $value) {
				$text .= $value['first_name']." ".$value['last_name']."\n";
			}
			//add_log(print_r($users, true));	
		}
		else{
			$text = "Отправите цифру для выбора действия: \n1. Новое обращение \n2. Список обращений";
		}
		$this->sendMessage($message['chatId'], $text );
	}

	public function sendQuestion($message){
		$text = "Отправьте описание сообщения или нажмите 1 для того чтобы посмотреть список обращений";
		$this->sendMessage($message['chatId'], $text);
	}
	public function sendCompanyName($message){
		$text = "";
		$this->sendMessage($message['chatId'], $text );
	}

	public function setUserCompany($user_id,$company_id,$company_name){
		global $db;
		$db->updateWhere("users","id=".$user_id, array("company_id"=>$company_id,"company_name"=>$company_name));
	}

	public function getUserByPhone($phone){
		global $db;
		$user = $db->selectOne('users',"*", "phone=".$phone);
		if ($user) {
			return $user;
		}else{
			return false;
		}
	}

	public function getUserState($user_id){
		global $db;
		$result = $db->selectOne("user_state","wa_state,wa_param","user_id=".$user_id);
		return $result;
	}

	public function processingUser($message){
		global $db;
		$phone = explode("@",$message["chatId"]);
		$user = $this->getUserByPhone($phone[0]);
		if($user){
			return $user;
		}else{
			//Пользователь не найден нужно добавить
			$insert_data = array(
				"t_id"=>"",
				"first_login"=>time(),
				"phone" => $phone[0],
				"first_name"=>$message['senderName'],
				"last_name"=> "",
				"wa_authorizated" => 1,
			);
			$user_id = $db->insertTo("users",$insert_data);
			//add_log(print_r($user, true));
			$db->insertTo("user_state",array("user_id"=>$message["chatId"],"wa_state"=>0));
			$user = $db->selectOne("users", "*", "id=".$user_id);
			return $user;
		}
		
	}

	public function processingContact($phone){
		global $db;
		//$db->updateWhere("users","t_id=".$data->user_id, array("phone"=>$data->phone_number));
		//обработка номера телефона
		$request = new BXRequest();
		if(strlen($phone)==11)
			$phone = substr($phone, 1);
		if(strlen($phone)==12)
			$phone = substr($phone, 2);
		//Ищем компанию по +7
		$company = $request->getCompanyByPhone("+7".$phone);
		//Ищем компанию по 8
		if(!$company)
			$company = $request->getCompanyByPhone("8".$phone);
		return $company;
	}
	public function setUserState($user_id, $state, $param=false){
		global $db;
		$data = array("wa_state"=>$state);
		if($param)
			$data["wa_param"] = $param;
		$db->updateWhere("user_state","user_id=".$user_id, $data);
	}
    public function sendMessage($chatId, $text){
	    $data = array('chatId'=>$chatId,'body'=>$text);
	    $this->sendRequest('message',$data);
	}

    public function sendRequest($method,$data){
	    $url = $this->APIurl.$method.'?token='.$this->token;
	    if(is_array($data)){ 
	    	$data = json_encode($data);
	    }
	    $options = stream_context_create(['http' => [
	    'method'  => 'POST',
	    'header'  => 'Content-type: application/json',
	    'content' => $data]]);
	    $response = file_get_contents($url,false,$options);
	    //file_put_contents('requests.log',$response.PHP_EOL,FILE_APPEND);
	}
}

 ?>