<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

error_reporting(E_ALL);

class Install {
	/**
	 * Название первого шага (используется, если другое не указано)
	 *
	 * @var string
	 */
	const INSTALL_DEFAULT_STEP = 'Start';
	/**
	 * Ключ сессии для хранения название следующего шага
	 *
	 * @var string
	 */
	const SESSSION_KEY_STEP_NAME = 'livestreet_install_step';
	/**
	 * Массив разрешенных шагов инсталяции
	 *
	 * @var array
	 */
	protected $aSteps = array(0=>'Start',1=>'Db',2=>'Admin',3=>'End');
	/**
	 * Массив сообщений для пользователя
	 *
	 * @var array
	 */
	protected $aMessages = array();
	/**
	 * Директория с шаблонами
	 *
	 * @var string
	 */
	protected $sTemplatesDir = 'templates';
	/**
	 * Массив с переменными шаблонизатора
	 *
	 * @var array
	 */
	protected $aTemplateVars = array(
		'___CONTENT___' => '',
		'___FORM_ACTION___' => '',
		'___NEXT_STEP_DISABLED___' => '',
		'___NEXT_STEP_DISPLAY___' => 'block',
		'___SYSTEM_MESSAGES___' => '',
	);
	/**
	 * Описание требований для успешной инсталяции
	 *
	 * @var array
	 */
	protected $aValidEnv = array(
        'safe_mode'  => array ('0','off',''), 
        'register_globals' => array ('0','off',''), 
        'allow_url_fopen' => array ('1','on'), 
        'UTF8_support' => '1', 
        'http_input' => array ('','pass'), 
        'http_output' => array ('0','pass'), 
        'func_overload' => array ('0','4', 'no overload'), 
    );
	/**
	 * Вытягивает переменную из сессии
	 *
	 * @param  string $sKey
	 * @return mixed
	 */
	protected function GetSessionVar($sKey,$mDefault=null) {
		return array_key_exists($sKey,$_SESSION) ? unserialize($_SESSION[$sKey]) : $mDefault;
	}
	/**
	 * Вкладывает переменную в сессию
	 *
	 * @param  string $sKey
	 * @param  mixed  $mVar
	 * @return bool
	 */
	protected function SetSessionVar($sKey,$mVar) {
		$_SESSION[$sKey] = serialize($mVar);
		return true;
	}
	/**
	 * Уничтожает переменную в сессии
	 *
	 * @param  string $sKey
	 * @return bool
	 */
	protected function DestroySessionVar($sKey) {
		if(!array_key_exists($sKey,$_SESSION)) return false;
		
		unset($_SESSION[$sKey]);
		return true;
	}
	
	/**
	 * Функция отвечающая за проверку входных параметров
	 * и передающая управление на фукнцию текущего шага
	 *
	 * @call $this->Step{__Name__} 
	 */
	public function Run() {
		$sStepName = $this->GetSessionVar(self::SESSSION_KEY_STEP_NAME, self::INSTALL_DEFAULT_STEP);
		if(!$sStepName or !in_array($sStepName,$this->aSteps)) die('Unknown step');
		
		$iKey = array_search($sStepName,$this->aSteps);
		if($iKey == count($this->aSteps)-1) {
			$this->Assign('NEXT_STEP_DISPLAY', 'none');
		}
		
		/**
		 * Пердаем управление на метод текущего шага
		 */
		$sFunctionName = 'Step'.$sStepName;
		if(@method_exists($this,$sFunctionName)) { 
			$this->$sFunctionName();
		} else {
			$sFunctionName = 'Step'.self::INSTALL_DEFAULT_STEP;
			$this->SetSessionVar(self::SESSSION_KEY_STEP_NAME,self::INSTALL_DEFAULT_STEP);
			$this->$sFunctionName();
		}
	}
	
	/**
	 * Выполняет рендеринг указанного шаблона
	 *
	 * @param  string $sTemplateName
	 * @return string
	 */
	protected function Fetch($sTemplateName) {
		if(!file_exists($this->sTemplatesDir.'/'.$sTemplateName)) return false;
		
		$sTemplate = file_get_contents($this->sTemplatesDir.'/'.$sTemplateName);
		return str_replace(array_keys($this->aTemplateVars),array_values($this->aTemplateVars),$sTemplate);
	}
	/**
	 * Добавляет переменную для отображение в шаблоне
	 *
	 * @param string $sName
	 * @param string $sValue
	 */
	protected function Assign($sName,$sValue) {
		$this->aTemplateVars['___'.strtoupper($sName).'___'] = $sValue;
	}
	/**
	 * Выполняет рендер layout`а (двухуровневый)
	 *
	 * @param  string $sTemplate
	 * @return null
	 */
	protected function Layout($sTemplate) {
		if(!$sLayoutContent = $this->Fetch($sTemplate)) {
			return false;
		}
		/**
		 * Рендерим сообщения по списку
		 */
		if(count($this->aMessages)) {
			$sMessageContent = "";
			foreach ($this->aMessages as $sMessage) {
				$this->Assign('message_style_class', $sMessage['type']);
				$this->Assign('message_content', $sMessage['text']);
				$sMessageContent.=$this->Fetch('message.tpl');
			}
			$this->Assign('system_messages',$sMessageContent);
		}
		
		$this->Assign('content', $sLayoutContent);
		print $this->Fetch('layout.tpl');
	}
	/**
	 * Проверяем возможность инсталяции
	 * 
	 * @return bool
	 */
	protected function ValidateEnviroment() {
		$bOk = true;
		
		if(!in_array(strtolower(@ini_get('safe_mode')), $this->aValidEnv['safe_mode'])) {
			$bOk = false;
			$this->Assign('validate_safe_mode', '<span style="color:red;">Нет</span>');
		} else {
			$this->Assign('validate_safe_mode', '<span style="color:green;">Да</span>');			
		}

		if(!in_array(strtolower(@ini_get('register_globals')), $this->aValidEnv['register_globals'])) {
			$bOk = false;
			$this->Assign('validate_register_globals', '<span style="color:red;">Нет</span>');
		} else {
			$this->Assign('validate_register_globals', '<span style="color:green;">Да</span>');			
		}
		
		if(@preg_match('//u', '')!=$this->aValidEnv['UTF8_support']) {
			$bOk = false;
			$this->Assign('validate_utf8', '<span style="color:red;">Нет</span>');
		} else {
			$this->Assign('validate_utf8', '<span style="color:green;">Да</span>');			
		}
		    
	    if (@extension_loaded('mbstring')){
	        $aMbInfo=mb_get_info();

			if(!in_array(strtolower($aMbInfo['http_input']), $this->aValidEnv['http_input'])) {
				$bOk = false;
				$this->Assign('validate_http_input', '<span style="color:red;">Нет</span>');
			} else {
				$this->Assign('validate_http_input', '<span style="color:green;">Да</span>');			
			}

			if(!in_array(strtolower($aMbInfo['http_output']), $this->aValidEnv['http_output'])) {
				$bOk = false;
				$this->Assign('validate_http_output', '<span style="color:red;">Нет</span>');
			} else {
				$this->Assign('validate_http_output', '<span style="color:green;">Да</span>');			
			}

			if(!in_array(strtolower($aMbInfo['func_overload']), $this->aValidEnv['func_overload'])) {
				$bOk = false;
				$this->Assign('validate_func_overload', '<span style="color:red;">Нет</span>');
			} else {
				$this->Assign('validate_func_overload', '<span style="color:green;">Да</span>');			
			}
	    }
	    
	    return $bOk;
	}
	
	/**
	 * Первый шаг инсталяции.
	 * Валидация окружения.
	 */
	protected function StepStart() {
		if(!$this->ValidateEnviroment()) {
			$this->Assign('next_step_disabled', 'disabled');
		} else {
			$this->SetSessionVar(self::SESSSION_KEY_STEP_NAME,'Db');			
		}
		$this->Layout('steps/start.tpl');
	}
	
	protected function StepDb() {
		if(!isset($_POST['install_db_params'])) {
			$this->Assign('install_db_server', 'localhost');
			$this->Assign('install_db_port', '3306');
			$this->Assign('install_db_name', 'social');
			$this->Assign('install_db_user', 'root');
			$this->Assign('install_db_password', '');
			$this->Assign('install_db_create_check', '');
			$this->Assign('install_db_prefix', 'prefix_');
			
			$this->Layout('steps/db.tpl');
			return true;
		}
		/**
		 * Если переданны данные формы, проверяем их на валидность
		 */
		$aParams['server']   = $this->GetRequest('install_db_server','');
		$aParams['port']     = $this->GetRequest('install_db_port','');
		$aParams['name']     = $this->GetRequest('install_db_name','');
		$aParams['user']     = $this->GetRequest('install_db_user','');
		$aParams['password'] = $this->GetRequest('install_db_password','');
		$aParams['create']   = $this->GetRequest('install_db_create',0);
		$aParams['prefix']   = $this->GetRequest('install_db_prefix','prefix_');

		$this->Assign('install_db_server', $aParams['server']);
		$this->Assign('install_db_port', $aParams['port']);
		$this->Assign('install_db_name', $aParams['name']);
		$this->Assign('install_db_user', $aParams['user']);
		$this->Assign('install_db_password', $aParams['password']);
		$this->Assign('install_db_create_check', ($aParams['create'])?'checked="checked"':'');
		$this->Assign('install_db_prefix', $aParams['prefix']);
		
		if($oDb=$this->ValidateDBConnection($aParams)) {
			$bSelect = $this->SelectDatabase($aParams['name'],$aParams['create']);
			/**
			 * Если не удалось выбрать базу данных, возвращаем ошибку
			 */
			if(!$bSelect) {
				$this->aMessages[] = array('type'=>'error','text'=>'Невозможно выбрать или создать базу данных');
				$this->Layout('steps/db.tpl');
				return false;
			}
			/**
			 * Открываем .sql файл и добавляем в базу недостающие таблицы
			 */
			list($bResult,$aErrors) = array_values($this->CreateTables('sql.sql'));
			if(!$bResult) {
				foreach($aErrors as $sError) $this->aMessages[] = array('type'=>'error','text'=>$sError);
				$this->Layout('steps/db.tpl');
				return false;
			}
			
			$this->aMessages[] = array('type'=>'notice','text'=>'Сделано. Остальные этапы в разработке.');
			$this->Assign('next_step_disabled','disabled');
			$this->Layout('steps/db.tpl');
			return true;
		} else {
			$this->aMessages[] = array('type'=>'error','text'=>'Не удалось подключиться к базе данных');
			$this->Layout('steps/db.tpl');
			return false;
		}
	}
	
	/**
	 * Проверяет соединение с базой данных
	 *
	 * @param  array $aParams
	 * @return mixed
	 */
	protected function ValidateDBConnection($aParams) {
		$oDb = @mysql_connect($aParams['server'],$aParams['user'],$aParams['password']);
		if( $oDb ) {
			mysql_query('set names utf8');
			return $oDb;
		}
		return null;
	}
	/**
	 * Выбрать базу данных (либо создать в случае необходимости).
	 *
	 * @param  string $sName
	 * @param  bool   $bCreate
	 * @return bool
	 */
	protected function SelectDatabase($sName,$bCreate=false) {
		if(@mysql_select_db($sName)) return true;

		if($bCreate){ 
			@mysql_query("CREATE DATABASE $sName");
			return @mysql_select_db($sName);
		} 
		return false;
	}
	/**
	 * Добавляет в базу данных необходимые таблицы
	 *
	 * @param  string $sFilePath
	 * @return array
	 */
	protected function CreateTables($sFilePath) {
		$sFileQuery = file_get_contents($sFilePath);
		$aQuery=explode(';',$sFileQuery);
		/**
		 * Массив для сбора ошибок
		 */
		$aErrors = array();
		/**
		 * Выполняем запросы по очереди
		 */
		foreach($aQuery as $sQuery){
			$sQuery = trim($sQuery);
			if($sQuery!='') {
				$bResult=mysql_query($sQuery);
				if(!$bResult) $aErrors[] = mysql_error();
			}
		}
		
		if(count($aErrors)==0) {
			return array('result'=>true,'errors'=>null);
		}
		return array('result'=>false,'errors'=>$aErrors);
	}
	
	/**
	 * Получает значение переданных параметров
	 *
	 * @param  string $sName
	 * @param  mixed  $default
	 * @return mixed
	 */
	protected function GetRequest($sName,$default=null) {
		if (isset($_REQUEST[$sName])) {
			if (is_string($_REQUEST[$sName])) {
				return trim(stripcslashes($_REQUEST[$sName]));
			} else {
				return $_REQUEST[$sName];
			}
		}
		return $default;
	}
}

session_start();
$oInstaller = new Install;
$oInstaller->Run();
?>