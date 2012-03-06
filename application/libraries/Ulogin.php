<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

//=====================================================
// ULogin CodeIgniter
//-----------------------------------------------------
// Модуль авторизации и регистрации при помощи uLogin
//-----------------------------------------------------
// http://ulogin.ru/
// team@ulogin.ru
// License GPL3
//-----------------------------------------------------
// Copyright (c) 2011-2012 uLogin
//=====================================================


class Ulogin {
/** Список возможных провайдеров авторизации */
	public $nets=array('vkontakte'=>1,'odnoklassniki'=>2,'mailru'=>3,'facebook'=>4,'twitter'=>5,'google'=>6,'yandex'=>7,'livejournal'=>8,'openid'=>9);
/** Список возможных полей, запрашиваемых у провайдера.
*
* Доступны следующие поля: first_name - имя пользователя, last_name - фамилия, email - e-mail, nickname - псевдоним, bdate - дата рождения, sex - пол, photo - квадратная аватарка (до 100*100), photo_big - самая большая аватарка, которая выдаётся выбранной соц. сетью, city - город, country - страна.
*/
	public $fields=array('first_name','last_name','photo','email','nickname','bdate','sex','photo_big','city','country');
/** Вид виджета авторизации */
	public $types=array('small'=>1,'panel'=>2,'window'=>3);
	private $providers=array();
	private $providers_h=array();
	private $fields_req=array();
	private $fields_opt=array();
	private $providers_default=array('vkontakte','odnoklassniki');
	private $fields_default=array('first_name','last_name','photo');
	private $type='small';
	private $callback=false;
	private $fields_set=0;
	private $providers_set=0;
	private $CI;
	private $token=false;
/** Информация, предоставленная провайдером авторизации */
	protected $userdata=false;
	private $url=false;

/** Конструктор. Позволяет задавать начальные параметры для виджета. Параметры можно передавать в виде ассоциативного
* массива. Также параметры можно устанавливать при помощи соответствующих методов. Доступные ключи массива:
* \param url адрес страницы, на которую будет осщуствлено перенаправление после авторизации. По умолчанию - текущая страница. Подробнее в описании set_url()
* \param providers список доступных провайдеров. Формат аналогичен методу set_providers()
* \param providers_h список скрытых провайдеров - открывается во всплывающем меню виджета. Формат аналогичен методу set_providers()
* \param fields обязательные поля при авторизации. Формат аналогичен методу set_fields()
* \param fields_opt необязательные поля при авторизации. Формат аналогичен методу set_fields()
* \param type вид виджета. Формат аналогичен методу set_type()
* \param callback позволяет производить авторизацию без перезагрузки страницы. Должен представлять собой массив из двух элементов. Подробнее - в описании метода set_callback()
*/
	function __construct($params=array()) {
		$this->CI=& get_instance();
		if(isset($params['url']))
			$this->set_url($params['url']);
		if(isset($params['providers']))
			$this->set_providers($params['providers']);
		if(isset($params['providers_h']))
			$this->set_providers($params['providers'],false);
		if(isset($params['fields']))
			$this->set_fields($params['fields']);
		if(isset($params['fields_opt']))
			$this->set_fields($params['fields_opt'],false);
		if(isset($params['type']))
			$this->set_type($params['type']);
		if(isset($params['callback']))
			$this->set_callback($params['callback']);
	}

/** Возвращает html-код виджета
* \return html-код
*/
	public function get_html() {
		if($this->url===false) {
			$this->CI->load->helper('url');
			$this->url=current_url();
		}
		return	($this->type=='window'?'<a href="#" id="uLogin"><img src="http://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></a>':'<div id="uLogin"></div>').
			'<script src="http://ulogin.ru/js/widget.js'.
			'?display='.$this->type.
			'&fields='.implode(',',($this->fields_set?$this->fields_req:$this->fields_default)).
			'&optional='.implode(',',($this->fields_set?$this->fields_opt:array())).
			'&providers='.implode(',',($this->providers_set?$this->providers:$this->providers_default)).
			'&hidden='.implode(',',($this->providers_set?$this->providers_h:array())).
			'&redirect_uri='.urlencode($this->url).
			($this->callback!==false?'&callback='.$this->callback:'').
			'"></script>';
	}

/** Считывает параметры, передаваемые скрипту, и выбирает из них ключ авторизации ULogin.
* \result истина, если процесс авторизации происходит в данный момент. Ложь в противном случае.
*/
	public function right_now()
	{
		$token=$this->CI->input->post('token');
		if($token===false)
			$token=$this->CI->input->get('token');
		if($token!==false) {
			$this->token=$token;
		}
		return $token!==false;
	}

	private function clean_userdata() {
		if(is_array($this->userdata) && isset($this->userdata["error"])) {
			$this->userdata=false;
			return true;
		}
		return false;
	}

/** Возвращает ассоциативный массив с данными о пользователе. Поля массива описаны в методе set_fields
* \result данные о пользователе от провайдера авторизации
*
* Пример: $userdata=$this->ulogin->userdata();
*
* $userdata содержит данные, предоставленные провайдером авторизации.
*/
	public function userdata() {
		$this->clean_userdata();
		if($this->userdata===false && $this->right_now()) {
			$s = file_get_contents('http://ulogin.ru/token.php?token=' . $this->token . '&host=' . $this->CI->input->server('HTTP_HOST'));
			$this->userdata=json_decode($s, true);
			if($this->clean_userdata())
				$this->logout();
		}
		return $this->userdata;
	}

/** Проверяет, произведен ли вход в систему
* \result истина, если вход в систему произведен. Ложь в противном случае.
*/
	public function is_authorised() {
		if(is_array($this->userdata) && !isset($this->userdata['error']))
			return true;
		$this->userdata();
		return is_array($this->userdata) && !isset($this->userdata['error']);
	}

/** Позволяет выйти пользователю из системы */
	public function logout() {
		$this->token=false;
		$this->userdata=false;
	}

/** Позволяет добавить провайдеров авторизации в список доступных. Если данный метод не вызывается ни разу и список доступных провайдеров не указан ни в конструкторе, то по умолчанию доступны провайдеры vkontakte и odnoklassniki
* \param nets список провайдеров. Список может быть задан в виде массива или в виде строки или с разделителями | или запятая. Элементы списка должны соответствовать переменной nets
* \param visible ложь, если данные провайдеры должен быть скрыт во всплывающем меню виджета
*/
	public function set_providers($nets,$visible=true) {
		$this->providers_set=1;
		if(!is_array($nets)) {
			$nets=strtr($nets,',','|');
			$nets=explode('|',$nets);
		}
		foreach($nets as $i=>$net)
			if(isset($this->nets[$net]))
				if($visible)
					$this->providers[$i]=$net;
				else
					$this->providers_h[$i]=$net;
	}


/** Позволяет пополнить список полей, запрашиваемых у провайдера авторизации. Если данный метод не вызывается ни разу и список запрашиваемых полей не указан ни в конструкторе, то по умолчанию запрашиваются имя, фамилия и фотография
* \param fields список полей. Список может быть задан в виде массива или в виде строки или с разделителями | или запятая. Элементы списка должны соответствовать переменной fields
* \param required истина, если данные поля являются обязательными, ложь в противном случае
*
* Доступны следующие поля: first_name - имя пользователя, last_name - фамилия, email - e-mail, nickname - псевдоним, bdate - дата рождения, sex - пол, photo - квадратная аватарка (до 100*100), photo_big - самая большая аватарка, которая выдаётся выбранной соц. сетью, city - город, country - страна.
*/
	public function set_fields($fields,$required=true) {
		$this->fields_set=1;
		if(!is_array($fields)) {
			$fields=strtr($fields,',','|');
			$fields=explode('|',$fields);
		}
		foreach($fields as $i=>$field)
			if(isset($this->fields[$i]))
				if($required)
					$this->fields_req[$i]=$field;
				else
					$this->fields_opt[$i]=$field;
	}

/** Позволяет задать вид виджета
* \param type тип виджета. Должен соответствовать переменной types
*/
	public function set_type($type) {
		if(isset($this->types[$type]))
			$this->type=$type;
	}

/** Позволяет задать url для перенаправления при авторизации с перезагрузкой страницы
* \param url страница, на которую будет осуществлено перенаправление после авторизации
*
* Если url не задан и используется авторизация с перенаправлением, то после авторизации текущая страница просто обновляется.
*/
	public function set_url($url) {
		if($this->callback===false)
			$this->url=$url;
	}

/** Позволяет производить авторизацию без перезагрузки страницы.
* Параметры этой функции могут быть заданы двумя способами:
*
* 1. Первый параметр - имя javascript-функции, которой в качестве аргумента передаётся token авторизации. Второй параметр - страница вашего сайта,
* которая отображает код, возвращаемый методом get_xd().
*
* 2. Единственный параметр - массив, состоящий из двух элементов. Первый элемент - имя javascript-функции, второй - url для get_xd().
*
* Javascript-функция должна быть организована таким образом, чтобы token передавался посредством методов POST или GET странице, на которой вызывается
* метод userdata() или is_authorised().
*
* В случае использования авторизации без перенаправления нет необходимости указывать url для перенаправления посредством метода set_url или в конструкторе.
*/
	public function set_callback(/*$callback_function,$xd_url*/) {
		$args=func_num_args();
		if($args==1 && is_array(func_get_arg(0)) && count(func_get_arg(0))>1) {
			$arg=func_get_arg(0);
			$callback_function=$arg[0];
			$xd_url=$arg[1];
		}
		else if($args==2) {
			$callback_function=func_get_arg(0);
			$xd_url=func_get_arg(1);
		}
		$this->callback=$callback_function;
		$this->url=$xd_url;
	}

/** Возвращает код, необходимый для авторизации без перезагрузки страницы
*/
	static public function get_xd() {
		return "<html><body><script>a=new Object();b=location.search.substr(1).split('&');for(c=0;c<b.length;c++){d=b[c].split('=');a[d[0]]=d[1];}parent.uLogin.hideAll();parent[a['callback']](a['token']);if(navigator.userAgent.toLowerCase().indexOf('chrome')>-1)location.href=decodeURIComponent(a['q']);else history.back();</script></body></html>";
	}
}

/* End of file Ulogin.php */