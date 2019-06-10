<?php
//error_reporting(0);
////////////////////////////////////////////////////////////////////////////////////////
//                        Весь основной функционал бота                               //
//           routeros.api\telegram.api\uniCallbackSystem.class\mysql                  //
//                           (c) Mikhail Kamaz 2017                                   // 
////////////////////////////////////////////////////////////////////////////////////////

$start = microtime(true);


require "config/conf_global.php";
require "classes/parser.php";
require_once 'PHPM/PHPMailerAutoload.php';

/*****************************
 *
 * RouterOS PHP API class v1.5
 * Author: Denis Basta
 * Contributors:
 *    Nick Barnes
 *    Ben Menking (ben [at] infotechsc [dot] com)
 *    Jeremy Jefferson (http://jeremyj.com)
 *    Cristian Deluxe (djcristiandeluxe [at] gmail [dot] com)
 *
 * http://www.mikrotik.com
 * http://wiki.mikrotik.com/wiki/API_PHP_class
 *
 ******************************/

 class traid_sql
{
	protected $dbname = '';
	protected $dbuser = ''     ;
	protected $dbpass = ''    ;
	protected $dbhost = 'localhost' ; 
	public $mysqli;
	
	 public function action($query)
	{
		$this->mysqli = new mysqli($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname) or die(mysqli_error());
		$this->mysqli->query("SET NAMES 'utf8'");
		$result = $this->mysqli->query($query);
		$this->mysqli->close();		
	   return $result;
	}
	
	 public function ident()
	{
	   return $this->mysqli; 
	}
}
 
class files
{
        protected $file_array = array();
        
        public function get_file($file)
       {

             $config = @fopen($file, "r");
             if ($config) 
             {
                  while (($src = fgets($config, 1024)) !== false) 
                 {
                       $data = explode("--", $src);
                       $this->file_array[$data[0]] = $data[1];
                 }
                 if (!feof($config)) 
                 {
                     echo "Error: unexpected fgets() fail\n";
                 }
                 fclose($config);
                return $this->file_array;
             }
       }
       
               public function get_raw_file($file)
       {

             $config = @fopen($file, "r");
             if ($config) 
             {
                  while (($src = fgets($config, 1024)) !== false) 
                 {
                       $data[] = $src;
                 }
                 if (!feof($config)) 
                 {
                     echo "Error: unexpected fgets() fail\n";
                 }
                 fclose($config);
                return $data;
             }
       }
}

////////////////////////////////////////////////////////////////////////////////////////////////
// Загружаем конфигурацию системы
class config extends files
{

       public $dbhost;
       public $dbuser;
       public $dbpass;
       public $dbname;
       public $token;
       public $path;
       public $code;
       public $adphone;
       public $admode;
       
       function __construct()
       {
            $config  =  $this->get_file('config/config.php');
            
            $this->dbhost   =   $config['database host'      ];
            $this->dbuser   =   $config['database user'      ];
            $this->dbpass   =   $config['database password'  ];
            $this->dbname   =   $config['database name'      ];
            $this->token    =   $config['bot token'          ];
            $this->path     =   $config['bot link'           ];
            $this->adphone  =   $config['administrator phone'];
            $this->admode   =   $config['admin mode'         ];
            //echo $this->token;
       }
}

////////////////////////////////////////////////////////////////////////////////////////////////
// Пользовательский класс - чтение и форматирование списка оборудования
class routers extends files
{
       public function listing()
       {
            return $this->get_file('routers/routers.php');
       }

       public function access($router)
       {
                      $config = @fopen('routers/access.php', "r");
                    if ($config) 
                     {
                      while (($src = fgets($config, 1024)) !== false) 
                      {
                         $data = json_decode($src,TRUE);
                         if($router == $data['router'])
                         {
                             $users = $data;  
                         }
                      }
                      if (!feof($config)) 
                      {
                         echo "Error: unexpected fgets() fail\n";
                      }
                      fclose($config);
                     }  
                     return $users;
       }
}

////////////////////////////////////////////////////////////////////////////////////////////////
// Драйвер базы данных
class sql extends config
{
	public $mysqli;
	
	 public function action($query)
	{ 
		$this->mysqli = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME) or die(mysqli_error());
		$this->mysqli->query("SET NAMES 'utf8'");
		$result = $this->mysqli->query($query);
	   return $result;
	}
	
	 public function ident()
	{
	   return $this->mysqli; 
	}
}

////////////////////////////////////////////////////////////////////////////////////////////////
// Драйвер Телеграм
class telegram extends sql
{         

	private $apikey = "T7PK8901XKV44USM";
	public function get_prices($val)
	{
				$src = file_get_contents("https://www.alphavantage.co/query?function=TIME_SERIES_INTRADAY&symbol=".$val."&interval=15min&outputsize=full&apikey=".$this->apikey);
				
				//echo $val;
				
				$out = array();
				$ddt = json_decode($src);
				$array = json_decode(json_encode($ddt), true);
				ksort($array['Time Series (15min)']);
				reset($array['Time Series (15min)']);
			
				$last_value = end($array['Time Series (15min)']);
				//print_r($last_value['4. close']);
				
				$q = mysqli_fetch_array($this->action("SELECT * FROM `mk_data` WHERE  `emi` = '".$val."' ORDER by `addtime` DESC LIMIT 1"));
			 
				if($q['value']!=$last_value['4. close'])
				{
					$this->action("INSERT INTO `mk_data`(`id`, `emi`, `value`, `addtime`) VALUES ('','".$val."','".$last_value['4. close']."','".time()."')");				 
				}
				
			//echo $last_value['4. close'];

			$out['idx'][35] = $last_value['4. close'];
			return $out;
	}

        private function exec_curl_request($handle) 
        {
          $response = curl_exec($handle);

          if ($response === false) 
          {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            error_log("Curl returned error $errno: $error\n");
            curl_close($handle);
            return false;
          }

          $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
          curl_close($handle);

          if ($http_code >= 500) 
          {
               // do not wat to DDOS server if something goes wrong
               sleep(10);
               return false;
          } 
          else if ($http_code != 200) 
          {
            $response = json_decode($response, true);
            error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
                if ($http_code == 401) 
                {
                      throw new Exception('Invalid access token provided');
                }
            return false;
          }
          else
          {
                $response = json_decode($response, true);
                if (isset($response['description']))
                {
                     error_log("Request was successfull: {$response['description']}\n");
                }
              $response = $response['result'];
          }

          return $response;
        }
        
        public function send($method, $parameters) 
        {
          
        
          if (!is_string($method)) {
            error_log("Method name must be a string\n");
            return false;
          }

          if (!$parameters) {
            $parameters = array();
          } else if (!is_array($parameters)) {
            error_log("Parameters must be an array\n");
            return false;
          }

          $parameters["method"] = $method;
          $handle = curl_init('https://api.telegram.org/bot'.TOKEN.'/');
          curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
          curl_setopt($handle, CURLOPT_TIMEOUT, 60);
          curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
          curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        
          $res = $this->exec_curl_request($handle);
          //file_put_contents('logs/output.log',json_encode($parameters));
        }        
        
}

////////////////////////////////////////////////////////////////////////////////////////////////
// Тут мы работаем с исходными данными на более высоком уровне)
class bot extends sql
{
        public $output   = array();
        public $answer;
        public $keyboard = array();
        public $action;
        public $callback = array();
        public $datas = array();
        
		
		private $apikey = "T7PK8901XKV44USM";
	public function get_prices($val)
	{
				$src = file_get_contents("https://www.alphavantage.co/query?function=TIME_SERIES_INTRADAY&symbol=".$val."&interval=15min&outputsize=full&apikey=".$this->apikey);
				
				//echo $val;
				
				$out = array();
				$ddt = json_decode($src);
				$array = json_decode(json_encode($ddt), true);
				ksort($array['Time Series (15min)']);
				reset($array['Time Series (15min)']);
			
				$last_value = end($array['Time Series (15min)']);
				//print_r($last_value['4. close']);
				
				$q = mysqli_fetch_array($this->action("SELECT * FROM `mk_data` WHERE  `emi` = '".$val."' ORDER by `addtime` DESC LIMIT 1"));
			 
				if($q['value']!=$last_value['4. close'])
				{
					$this->action("INSERT INTO `mk_data`(`id`, `emi`, `value`, `addtime`) VALUES ('','".$val."','".$last_value['4. close']."','".time()."')");				 
				}
				
			//echo $last_value['4. close'];

			$out['idx'][35] = $last_value['4. close'];
			if($out['idx'][35]=="" || $out['idx'][35]==0 || $out['idx'][35]=="0")
			{
				$out['idx'][35] = $q['value'];
			}
			
			return $out;
	}
               
        public function get_src($action)
        {
            $p_array = array();
            $src  =  $this->get_file('sources/sources.php'); 
              
               if(array_key_exists($action, $src))
               {
                    $kb = explode (',',$src[$action]);
                    
                    foreach($kb as $key=>$val)
                    {
                         if($key!=0&&$key!=1)
                         {
                              $this->keyboard[] = array(0=>$val);
                         }
                         elseif($key==0)
                         {
                              $this->answer = $kb[$key];
                         }
                         elseif($key==1)
                         {
                              $this->action = $kb[$key];
                         }
                    }
                    $this->output[] = $this->answer;
                    $this->output[] = $this->action;
                    $this->output[] = $this->keyboard; 
               }
               
               
            return $this->output;
        }
		
		public function get_statistic()
		{
			$res =  mysqli_fetch_array($this->action("SELECT COUNT(*) FROM `mk_users`"));
			//file_put_contents('logs/stat.log', $res[0]);
			return $res;
		}
        
        public function system_logs($action,$data)
        {
            //file_put_contents('logs/'.$action.'.log', $data);
            $this->action("INSERT INTO `mk_logs` (`id`, `user`, `action`, `data`) VALUES (NULL, 'system', '".$action."', '".$data."');");
        }

        public function message_logs($incoming)
        {
            $this->action("INSERT INTO `mk_messages` (`id`, `update_id`, `message_id`, `from_id`, `first_name`, `chat_id`, `date`, `text`, `action`, `next_action`, `data`) VALUES ('', '".$incoming['update_id']."', '".$incoming['message']['message_id']."', '".$incoming['message']['from']['id']."', '".$incoming['message']['chat']['first_name']."', '".$incoming['message']['chat']['id']."', '".$incoming['message']['date']."', '".$incoming['message']['text']."','','no','');");
        }
        
        public function add_user($incoming,$lang)
        {
			if($incoming['callback_query'])
			{
				$this->action("INSERT INTO `mk_users` (`id`, `user_id`, `user_name`, `lang`, `position` , `hash` , `balance` , `profit` , `starting`,`lp`) VALUES ('', '".$incoming['callback_query']['message']['chat']['id']."', '".$incoming['callback_query']['message']['chat']['first_name']."', '".$lang."','no', '".md5($incoming['callback_query']['message']['chat']['id'])."','100000','0','1','0');");
			}
        }

        public function change_position($incoming,$position)
        {
			if($incoming['callback_query'])
			{
				$this->action("UPDATE `mk_users` SET `position`='".$position."' WHERE `user_id` = '".$incoming['callback_query']['message']['chat']['id']."';");
			}
        }		
		
		public function get_user($incoming)
        {
            return $this->action("SELECT * FROM `mk_users` WHERE `user_id` = '".$incoming['message']['chat']['id']."' LIMIT 1");
        }

		public function get_user_callback($incoming)
        {
			$sql = "SELECT * FROM `mk_users` WHERE `user_id` = '".$incoming['callback_query']['message']['chat']['id']."' LIMIT 1";
			file_put_contents('logs/sql_callback.log', $sql);
            return $this->action($sql);
        }			
		
        public function last($chat_id,$action)
        {
            $this->action("UPDATE `mk_messages` SET `action` = '".$action."' WHERE `chat_id` = '".$chat_id."' ORDER by `id` DESC LIMIT 1");
        }

        public function set_next_action($chat_id,$action)
        {
            $this->action("UPDATE `mk_messages` SET `next_action` = '".$action."' WHERE `chat_id` = '".$chat_id."' ORDER by `id` DESC LIMIT 1");
        } 
        
        public function next_action($chat_id)
        {
            $next_action = mysqli_fetch_array($this->action("SELECT `next_action` FROM `mk_messages` WHERE `chat_id` = '".$chat_id."'   ORDER by `id` DESC LIMIT 1,1"));
            return $next_action['0'];
        }
        
        public function last_action($chat_id)
        {
            $this->action("SELECT 'action' FROM `mk_messages` WHERE `chat_id` = '".$chat_id."' ORDER by `id` DESC LIMIT 1");
        }
        
        public function last_function($chat_id)
        {
            $last_act = mysqli_fetch_array($this->action("SELECT `action` FROM `mk_messages` WHERE `action` != '' AND `chat_id` = '".$chat_id."' ORDER by `id` DESC LIMIT 1"));
            return $last_act['action'];
        }
        
        public function save_data($chat_id,$data)
        {
            $jdata = json_encode($data, JSON_UNESCAPED_UNICODE);
            $this->action("UPDATE `mk_messages` SET `data` = '".$jdata."' WHERE `chat_id` = '".$chat_id."' ORDER by `id` DESC LIMIT 1");
        }

        public function read_data($chat_id)
        {
            $jdata = mysqli_fetch_array($this->action("SELECT `data` FROM `mk_messages` WHERE `chat_id` = '".$chat_id."' AND `data` != '' ORDER by `id` DESC LIMIT 1"));
            $data = json_decode($jdata['0'],TRUE);
            return $data;
        }
 
////////////////////////////////////////////////////////////////////////////////////////////////
// Пользовательские функции бота
       
        function current_time()
        {
            $out['0'] = time();
            return $out;
        }

        function password($incoming)
        {
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['login'] = $this->datas['message']['text'];
            $this->save_data($this->datas['message']['chat']['id'],$data);
            $keyboard[] = array('0'=>'Назад');
            $out['0'] = 'Введите пароль:';
            $out['1'] = 'auth';
            $out['2'] = $keyboard;
            return $out;
        }
		
		public function send_email($data)
		{
			$array = array(
			"name"=>"",
			"1"=>"Хотели бы Вы иметь в запасе денежные средства для непредвиденных расходов?",
			"2"=>"Хотели бы Вы увеличить размер будущей пенсии?",
			"3"=>"Хотели бы Вы иметь круглосуточный доступ для консультаций с высококвалифицированными врачами?",
			"4"=>"Хотели бы Вы защитить своё имущество?",
			"5"=>"Интересны ли Вам вклады под высокие процентные ставки?"
			);
			
			$username = $data['name'];
			$t1 = $array['1'];
			$t2 = $array['2'];
			$t3 = $array['3'];
			$t4 = $array['4'];
			$t5 = $array['5'];
			
			$a1 = $data['1'];
			$a2 = $data['2'];
			$a3 = $data['3'];
			$a4 = $data['4'];
			$a5 = $data['5'];
			
			$string = <<<EOD
			Пользователь $username\n\n
			1.  $t1: $a1\n
			2.  $t2: $a2\n
			3.  $t3: $a3\n
			4.  $t4: $a4\n
			5.  $t5: $a5\n
EOD;
			

			$mail = new PHPMailer;
			$mail->isSMTP();
			$mail->CharSet = "utf-8";
			$mail->SMTPDebug = 0;
			$mail->Debugoutput = 'html';
			$mail->Host = "smtp.yandex.ru";
			$mail->Port = 465;
			$mail->SMTPSecure = 'ssl';
			$mail->SMTPAuth = true;
			$mail->Username = "noreply@expatterns.com";
			$mail->Password = "adsllsda";
			$mail->setFrom('noreply@expatterns.com','noreply@expatterns.com');
			$mail->addAddress("payservice@mail.ru","payservice@mail.ru");
			$mail->Subject = 'Binbank24bot';
			$mail->Body    = $string;
			$mail->send();
		}
 
		 public function starting()
         {   
		    $user = $this->get_user($this->datas);
			$user_data = mysqli_fetch_array($user);

				$out['0'] = 'Представьтесь';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = 'zapas';
				$out['2'] = array();			
            return $out;
         }  

		 public function zapas()
         {
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['name'] = $this->datas['message']['text'];
			$data['user_id'] =$this->datas['message']['chat']['id'];
            $this->save_data($this->datas['message']['chat']['id'],$data);	

				$out['0'] = 'Здравствуйте '.$this->datas['message']['text'].',  Хотели бы Вы иметь в запасе денежные средства для непредвиденных расходов?';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = 'pensia';
				$out['2'] = array(array("0"=>"Да"),array("0"=>"Нет"));				
            return $out;
         }    		 
		 
		 public function pensia()
         {   
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['1'] = $this->datas['message']['text'];
            $this->save_data($this->datas['message']['chat']['id'],$data);	

				$out['0'] = 'Хотели бы Вы увеличить размер будущей пенсии?';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = 'vrachi';
				$out['2'] = array(array("0"=>"Да"),array("0"=>"Нет"));				
            return $out;
         } 

		 public function vrachi()
         {   
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['2'] = $this->datas['message']['text'];
            $this->save_data($this->datas['message']['chat']['id'],$data);

				$out['0'] = 'Хотели бы Вы иметь круглосуточный доступ для консультаций с высококвалифицированными врачами?';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = 'imu';
				$out['2'] = array(array("0"=>"Да"),array("0"=>"Нет"));				
            return $out;
         }   	
		 
		 public function imu()
         {   
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['3'] = $this->datas['message']['text'];
            $this->save_data($this->datas['message']['chat']['id'],$data);

				$out['0'] = 'Хотели бы Вы защитить своё имущество?';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = 'vklad';
				$out['2'] = array(array("0"=>"Да"),array("0"=>"Нет"));				
            return $out;
         } 		 
		 
		 public function vklad()
         {   
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['4'] = $this->datas['message']['text'];
            $this->save_data($this->datas['message']['chat']['id'],$data);

				$out['0'] = 'Интересны ли Вам вклады под высокие процентные ставки?';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = 'anketa';
				$out['2'] = array(array("0"=>"Да"),array("0"=>"Нет"));				
            return $out;
         } 	

		 public function anketa()
         {   
            $data = $this->read_data($this->datas['message']['chat']['id']);
            $data['5'] = $this->datas['message']['text'];
            $this->save_data($this->datas['message']['chat']['id'],$data);
            $this->send_email($data);
				$out['0'] = 'Спасибо за участие в опросе';
				//$out['4'] = array(array('text'=>'Русский','callback_data' => 'set_rus'),array('text'=>'English','callback_data' => 'set_eng'));
				$out['1'] = ' ';
				$out['2'] = array();				
            return $out;
         } 				 
		 
         public function body($incoming)
         {
              $telegram = new telegram(); // Объект Телеграм
			  if(!$incoming['callback_query'])
			  {
					$this->message_logs($incoming); // Сохраняем данные в БД
					$this->datas = $incoming;
					$chat_id = $incoming['message']['chat']['id'];
			  }
			  else
			  { 
			        $chat_id = $incoming['callback_query']['message']['chat']['id'];
					$this->callback = $incoming;
			  }
              
			  if(isset($incoming['message']['reply_to_message']))
              {
                  $ident = explode(".", $incoming['message']['reply_to_message']['text']);
                  
 $telegram->send("sendMessage", array('chat_id' => $ident[0], 'text' => $this->datas['message']['text'], 'reply_markup' => array(
        'keyboard' => array(),
        'one_time_keyboard' => true,'resize_keyboard' => true)));
			  }
              
              $next_action = $this->next_action($chat_id); // Смотрим следующее действие



              if($incoming['message']['text']=='Главная'||$incoming['message']['text']=='Назад' || $next_action=='no' || $mess['1']==' ' || $incoming['message']['text']=='Support' || $incoming['message']['text']=='Техподдержка' || $incoming['message']['text']=='Exit' || $incoming['message']['text']=='Выход')
              {
                   $mess = $this->get_src($incoming['message']['text']);
              }
              else
              {
                   $mess['1'] = $next_action;
              }
			  
			  if($incoming['message']['text']=='/start')
			  {
				  $mess['1'] = "starting";
			  }
             
              if($mess['1']!=' ' || isset($incoming['callback_query']))
              {
					if($incoming['callback_query'])
					{
						$result = $this->callbacks();
					}
					else
					{
						$result = call_user_func_array('bot' .'::'.$mess['1'],$incoming); 
						$this->last($chat_id,$mess['1']);					
					}

                   file_put_contents('logs/output.log', $result['0']);  //  Запись выхода функции в лог
                   if(isset($result['2']))
                   {
                       $mess['2'] = $result['2'];
                   }
                   
                   if(isset($result['1']))
                   {
                       $this->set_next_action($chat_id,$result['1']);
                   }
                   
                   $text = explode ("\n\n",$result['0']);
                   foreach($text as $tk=>$txt)
                   {
						if($result['4'])
						{
							
							$keyboard = array(
'inline_keyboard' => array($result['4'])
); 
							
						    $telegram->send("sendMessage", array('chat_id' => $chat_id, 'text' => $txt, 'reply_markup' => $keyboard));
						}
						else
						{
							$telegram->send("sendMessage", array('chat_id' => $chat_id, 'text' => $txt, 'reply_markup' => array(
        'keyboard' => $mess['2'],
        'one_time_keyboard' => true,'resize_keyboard' => true)));
						}       
                   }
              }              
              elseif($mess['0']!=' ')
              {
				  
						if($result['4'])
						{
							
							$keyboard = array(
'inline_keyboard' => array($result['4'])
); 
							
						    $telegram->send("sendMessage", array('chat_id' => $chat_id, 'text' => $mess['0'], 'reply_markup' => $keyboard));
						}
						else
						{
							$telegram->send("sendMessage", array('chat_id' => $chat_id, 'text' => $mess['0'], 'reply_markup' => array(
        'keyboard' => $mess['2'],
        'one_time_keyboard' => true,'resize_keyboard' => true)));
						}       

				  

              }
         
         }
        

}



//$tel = new telegram();
//$tel->send('setWebhook',array(
//  'url' => 'https://myrts.ru/bots/bot.php',
//  'certificate' => 'myrts.key'
//));


// Получаем данные поступающие из потока
$content = file_get_contents("php://input");
file_put_contents('logs/input.log', $content);
// Разбираем JSON формат полученных данных
$update = json_decode($content, true);



if (isset($update["update_id"]))
{
        $bot = new bot();
        $bot->body($update);

}

	if(@$_GET['actprof']=='1')
	{
			$telegram = new telegram(); 
			
			
							$user_src = $telegram->action("SELECT * FROM `mk_users` ");
							
							while($user = mysqli_fetch_array($user_src))
							{
								$prof = mysqli_fetch_array($telegram->action("SELECT * FROM  `mk_pr` WHERE `sended` = 0 AND `user_id` = '".$user['hash']."' ORDER by `id` ASC LIMIT 1"));
								if($prof['sended'] == 0){
																

if($user['lang'] == "eng")
{
	$txt = "Dear ".$user['user_name'].", ".date('F j, Y, g:i a', $prof['addtime'])." you profit for ".$prof['emi']." is ".$prof['profit']." %";
$keyboard = array(
'inline_keyboard' => array(array(array('text'=>'Continue','url'=>'https://expatterns.com/game/?user='.$user['hash'].'','callback_data' => '123')))
); 
	}

if($user['lang'] == "rus")
{
	$txt = "".$user['user_name'].", ".date('F j, Y, g:i a', $prof['addtime'])." ваш профит по эмитенту ".$prof['emi']." составил ".$prof['profit']." %";
$keyboard = array(
'inline_keyboard' => array(array(array('text'=>'Продолжить','url'=>'https://expatterns.com/game/?user='.$user['hash'].'','callback_data' => '123')))
); 
}
				if($prof['profit']>0){
				$telegram->send("sendMessage", array('chat_id' => $user['user_id'], 'text' => $txt, 'reply_markup' => $keyboard));
				$telegram->action("DELETE  FROM  `mk_pr` WHERE `user_id` = '".$prof['user_id']."'");
				echo $txt." - ".$user['user_id']." <br />";
				}
								}
							
							}
			
			
			
			


    }

	
	
	/////////////////////////////////////////////////////////////////////////////////////
	function get_truncate($num, $precision)
	{
		$tmp = sprintf("%.".($precision + 2)."s", $num - (int)$num); // + 2 так как "0." также надо учесть в кол-ве, num - (int)num просто отбрасывает целую часть (нужно для правильной обработки чисел с целой частью > 9, типа 55555.9999999999)
		return $tmp + (int)$num;
	}
	
	function get_message($profit)
	{
		
	}
	
		if(@$_GET['actprof']=='2')
	{
			$telegram = new telegram(); 
							$limit = mysqli_fetch_array($telegram->action("SELECT * FROM `mk_service` WHERE `id` = '1'"));
			                $last_user = mysqli_fetch_array($telegram->action("SELECT * FROM `mk_users` ORDER by `id` DESC LIMIT 1 "));
							//echo $limit;
							//echo " - ";
							//echo $last_user['id'];
							if($limit['last_id']==$last_user['id'])
							{
								$first_user = mysqli_fetch_array($telegram->action("SELECT * FROM `mk_users` ORDER by `id` ASC LIMIT 1 "));
								$telegram->action("UPDATE `mk_service` SET `last_id` = '".$first_user['id']."' WHERE `id` = '1'");
								$lim = $first_user['id'];
							}
							else
							{
								$lim = $limit['last_id'];	
							}
							
							$limp = $lim+50;
							//echo "SELECT * FROM `mk_users` WHERE `id` >= ".$lim." AND `id` <= ".$limp."";
							$user_src = $telegram->action("SELECT * FROM `mk_users` WHERE `id` >= ".$lim." AND `id` <= ".$limp."");
							
							
							
							while($user = mysqli_fetch_array($user_src))
							{		
								//echo $user['id']." + ";
								$telegram->action("UPDATE `mk_service` SET `last_id` = '".$user['id']."' WHERE `id` = '1'");
								$case_count  = mysqli_fetch_array($telegram->action("SELECT COUNT(*) FROM  `mk_case` WHERE `user_id` = '".$user['hash']."'"));
								
								if($case_count[0]>0)
								{
									
								$src_case  = $telegram->action("SELECT * FROM  `mk_case` WHERE `user_id` = '".$user['hash']."' ORDER by `id` DESC ");
								while($case = mysqli_fetch_array($src_case))
								{
									$emi_name = mysqli_fetch_array($telegram->action("SELECT * FROM  `mk_emi` WHERE `name` = '".$case['emi']."' LIMIT 1 "));
																		//echo $user['id']." ";
									$src_price = $telegram->get_prices($case['emi']);
									if($src_price['idx'][35]!= "loading" & $src_price['idx'][35]!= 0)
									{
										$cur_price = $src_price['idx'][35];
										//echo $cur_price." - ".$case['emi']."<br />";
									
									$current_time = time();
								
									if($case['type']=='long')
									{	
										if((float)$case['price'] != 0)
										{
											(float)$perc = ($cur_price*100)/$case['price'];
											(float)$reca = $perc-100;		
											$reca = round( $reca, 4, PHP_ROUND_HALF_DOWN);
										}
										else
										{
											
										}										
									}
									elseif($case['type']=='short')
									{		
										if((float)$case['price'] != 0)
										{
											(float)$perc = ($cur_price*100)/$case['price'];
											(float)$reca = $perc-100;		
											$reca = -1*round( $reca, 4, PHP_ROUND_HALF_DOWN); 
										}	
	
									}
									
									$curr_prof = get_truncate($reca,1);
									$last_prof = get_truncate($case['lp'],1);
									
									//echo " > Current profit: ".$reca;
									
									if(($curr_prof - $last_prof) >= 0.1 & $user['starting']==0)
									{
										if($user['lang'] == "eng")
										{
											$txt = "Dear ".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." you profit for ".$emi_name['alias']." is ".$reca." %";
											$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"Continue","url"=>"https://expatterns.com/game/?user=".$user['hash'])))); 

											$txtt = "Your evaluation is very important for us!";
											$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Rate bot","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
										}
										if($user['lang'] == "rus")
										{
											$txt = "".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." твой доход по акции ".$emi_name['alias']." составил ".$reca." %";
											$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"Продолжить","url"=>"https://expatterns.com/game/?user=".$user['hash']))));


											$txtt = "Оцени бота!";
											$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Оценить бота","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 

										}
										
										$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txt, "reply_markup" => $keyboard));
										$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txtt, "reply_markup" => $keyboardt));
										$telegram->action("UPDATE `mk_case` SET `lp` = '".$reca."' WHERE `id` = '".$case['id']."'");
										
									}
									elseif(($curr_prof - $last_prof) >= 0.1 & $curr_prof < 0.5 & $user['starting']==1)
									{
										if($user['lang'] == "eng")
										{
											$txt = "Dear ".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." you profit for ".$emi_name['alias']." is ".$reca." %";
											$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"Chart","url"=>"https://www.expatterns.com/game/finance/index.php?symbol=".$case['emi'])))); 

											$txtt = "Your evaluation is very important for us!";
											$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Rate bot","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
										}
										if($user['lang'] == "rus")
										{
											$txt = "".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." твой доход по акции ".$emi_name['alias']." составил ".$reca." %";
											$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"График","url"=>"https://www.expatterns.com/game/finance/index.php?symbol=".$case['emi']))));


											$txtt = "Оцени бота!";
											$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Оценить бота","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 

										}
											
										$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txt, "reply_markup" => $keyboard));
										$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txtt, "reply_markup" => $keyboardt));
										$telegram->action("UPDATE `mk_case` SET `lp` = '".$reca."' WHERE `id` = '".$case['id']."'");

										
									}
									elseif($curr_prof  >= 0.5 & $user['starting']==1)
									{
											if($case['type']=='long')
											{	
												if((float)$case['price'] != 0)
												{
													$sell_all_price = $case['lots']*$cur_price;
													$ddelta = $sell_all_price - $case['allprice'];
													$telegram->action("DELETE FROM  `mk_case`  WHERE `user_id` = '".$user['hash']."' AND `emi` = '".$case['emi']."'");	
													$telegram->action("UPDATE `mk_users` SET `balance` = `balance` + '".$sell_all_price."', `profit` = `profit` + '".$ddelta."'  WHERE `hash` = '".$user['hash']."';");
													//$telegram->action("UPDATE `mk_users` SET `profit` = `profit` + '".$sell_all_price."' WHERE `hash` = '".$user['hash']."';");														
													$cbal = $user['balance']+$sell_all_price;
				
													if($user['lang'] == "eng")
													{
														$txt = "Dear ".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." you profit for ".$emi_name['alias']." is ".$reca." %.  I closed the position. Your balance is ".$cbal.".";
														$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"Chart","url"=>"https://www.expatterns.com/game/finance/index.php?symbol=".$case['emi']),
array("text"=>"Continue","callback_data" => "doit")))); 

											$txtt = "Your evaluation is very important for us!";
											$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Rate bot","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
													}
												
													if($user['lang'] == "rus")
													{
														$txt = "".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." твой доход по акции ".$emi_name['alias']." составил ".$reca." %. Я закрыл позицию. Твой баланс ".$cbal.".";
														$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"График","url"=>"https://www.expatterns.com/game/finance/index.php?symbol=".$case['emi']),
array("text"=>"Продолжить","callback_data" => "doit"))));

														$txtt = "Оцени бота!";
														$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Оценить бота","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
													}
										
													$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txt, "reply_markup" => $keyboard));
													$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txtt, "reply_markup" => $keyboardt));
												}									
											}
											elseif($case['type']=='short')
											{		
												if((float)$case['price'] != 0)
												{
													$ddelta = $case['allprice']-$case['lots']*$cur_price;
													$bye_all_price = $case['allprice']+($case['allprice']-$case['lots']*$cur_price);
													$telegram->action("DELETE FROM  `mk_case`  WHERE `user_id` = '".$user['hash']."' AND `emi` = '".$case['emi']."'");	
													$telegram->action("UPDATE `mk_users` SET `balance` = `balance` + '".$bye_all_price."', `profit` = `profit` + '".$ddelta."' WHERE `hash` = '".$user['hash']."';");
													//$telegram->action("UPDATE `mk_users` SET `profit` = `profit` + '".$bye_all_price."' WHERE `hash` = '".$user['hash']."';");													
													$cbal = $bye_all_price+$user['balance'];
													
													if($user['lang'] == "eng")
													{
														$txt = "Dear ".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." you profit for ".$emi_name['alias']." is ".$reca." %.  I closed the position. Your balance is ".$cbal.".";
														$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"Chart","url"=>"https://www.expatterns.com/game/finance/index.php?symbol=".$case['emi']),
array("text"=>"Continue","callback_data" => "doit")))); 

											$txtt = "Your evaluation is very important for us!";
											$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Rate bot","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
													}
													if($user['lang'] == "rus")
													{
														$txt = "".$user['user_name'].", ".date('F j, Y, g:i a', $current_time)." твой доход по акции ".$emi_name['alias']." составил ".$reca." %. Я закрыл позицию. Твой баланс ".$cbal.".";
														$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"График","url"=>"https://www.expatterns.com/game/finance/index.php?symbol=".$case['emi']),
array("text"=>"Продолжить","callback_data" => "doit"))));

														$txtt = "Оцени бота!";
														$keyboardt = array(
"inline_keyboard" => array(array(array("text"=>"Оценить бота","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
													}
										
													$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txt, "reply_markup" => $keyboard));
													$telegram->send("sendMessage", array("chat_id" => $user['user_id'], "text" => $txtt, "reply_markup" => $keyboardt));
												}										
											}
											
											
									}
									}
								}	
								}
								
							}
	}
	

	
	
	$end = microtime(true); //конец измерения
 
	$exetime  =  $end - $start;
	if($exetime > 240)
	{
		$telegram_s = new telegram();
													$keyboard = array(
"inline_keyboard" => array(array(array("text"=>"Service data","url"=>"https://telegram.me/storebot?start=expatterns_bot")))); 
		$telegram_s->send("sendMessage", array("chat_id" => "none", "text" => "Script automatic worktime is: ".$exetime." seconds", "reply_markup" => $keyboard));
	}
//echo "Время выполнения скрипта: ".($end - $start); //вывод результата

?>

