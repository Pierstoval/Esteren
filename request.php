<?php

## Récupération de $_POST à partir des réelles données POST, pour obtenir les bons noms de variable entrées en paramètre,
// $_POST = get_post_datas();

## Définition de la constante BASE_URL. Source : http://www.koezion-cms.com/
$baseUrl = '';
$scriptPath = preg_split("#[\\\\/]#", dirname(__FILE__), -1, PREG_SPLIT_NO_EMPTY);
$urlPath = preg_split("#[\\\\/]#", $_SERVER['REQUEST_URI'], -1, PREG_SPLIT_NO_EMPTY);
foreach($urlPath as $k => $v) {
	$key = array_search($v, $scriptPath);
	if($key !== false) {
		$baseUrl .= "/".$v;
	} else {
		break;
	}
}
define('BASE_URL', 'http://'.P_BASE_HOST.$baseUrl);//url absolue du site
unset($baseUrl, $scriptPath, $urlPath, $k, $v, $key);

/**
 * On crée la variable $_PAGE['request'] pour obtenir les paramètres découpés par les '/'
 */
$request = isset($_GET['request']) ? $_GET['request'] : '';
$ext = pathinfo($request);
$ext = isset($ext['extension']) ? strtolower($ext['extension']) : '';//On génère l'extension de l'url
$request = preg_replace('#\.([a-zA-Z0-9]{1,6})$#isUu', '', $request);

if ($request) {
	$request = explode('/', $request);
	$getmod = array_shift($request);
} else {
	$request = array();
	$getmod = '';
}
$t = array();
if ($ext === $getmod) { $ext = ''; }
foreach($request as $v) {
	if (preg_match('#:#isUu', $v)) {
		$v = explode(':', $v, 2);
		$t[$v[0]] = $v[1];
	} else {
		$t[] = $v;
	}
}
$request = $t;

/**
 * On crée la variable $_GET pour obtenir les informations en GET
 */
$get_parameters = $_SERVER['REQUEST_URI'];
if (preg_match('#\?#isUu', $get_parameters)) {
	$get_parameters = preg_replace('#^[^\?]*\?#isUu', '', $get_parameters);
	$get_parameters = explode('&', $get_parameters);
	$t = array();
	foreach($get_parameters as $k => $v) {
		$v = explode('=', $v);
		$t[$v[0]] = @$v[1];
		$_GET[$v[0]] = @$v[1];
	}
	$get_parameters = $t;
} else {
	$get_parameters = array();
}

$_PAGE['request'] = $request;
unset($request);
$_GET = array_map('urldecode', $get_parameters);
unset($_GET['request'], $t, $get_parameters);

/**
 * Définition de la variable $_PAGE
 * Celle-ci est chargée de gérer la liste des pages.
 * On force sa portée en globale notamment en la chargeant dans la plupart des librairies susceptibles de la gérer,
 * comme le gestionnaire d'urls (mkurl) ou le chargement de modules (load_module)
 */
$_PAGE['get'] = is_string($getmod) && $getmod ? $getmod : 'index';
$_PAGE['id'] = is_numeric($getmod) && $getmod ? $getmod : 1;
$_PAGE['extension'] = $ext;
$_PAGE['style'] = 'corahn_rin';//id CSS de la balise body
$_PAGE['anchor'] = '';
$_PAGE['list'] = array();
$result = $db->req('SELECT * FROM %%pages ORDER BY %page_anchor ASC');
if ($result) {
	foreach ($result as $data) {
		$_PAGE['list'][$data['page_id']] = $data;
		if ($_PAGE['get'] === $data['page_getmod'] || $_PAGE['id'] === $data['page_id']) {
			if (Users::$acl > $data['page_acl'] || (P_LOGGED === false && $data['page_require_login'] === '1')) {
				Session::setFlash("Vous n'avez pas les droits pour accéder à cette page.", 'error');
				header('Location:'.mkurl(array('val'=>1)));
				exit;
			}
			$_PAGE['id'] = (int) $data['page_id'];
			$_PAGE['anchor'] = $data['page_anchor'];
			$_PAGE['acl'] = (int) $data['page_acl'];
		}
		if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $data['page_getmod']) !== false) {
			$_PAGE['referer'] = array(
				'id' => (int) $data['page_id'],
				'getmod' => $data['page_getmod'],
				'anchor' => $data['page_anchor'],
				'full_url' => $_SERVER['HTTP_REFERER'],
			);
		}
	}
	unset($result);
}
unset($result, $data);

if (!$_PAGE['extension'] && $_PAGE['get'] !== 'index') {
	redirect(array('val'=>$_PAGE['id'], 'ext' => 'html'));
}

## Si le module chargé est "index" alors on redirige vers une page d'accueil possédant une url "saine"
if ($getmod === 'index') { redirect(BASE_URL); }
unset($getmod);

##On définit le referer en fonction de ce que l'on a dans HTTP_REFERER.
##Si celui-ci est sur ce site, on récupère ses paramètres dans $_PAGE. Sinon, uniquement son url.
if (isset($_SERVER['HTTP_REFERER']) && !isset($_PAGE['referer'])) {
	if ($_SERVER['HTTP_REFERER'] == BASE_URL.'/') {
		$_PAGE['referer'] = array(
			'id' => 1,
			'getmod' => $_PAGE['list'][1]['page_getmod'],
			'anchor' => $_PAGE['list'][1]['page_anchor'],
			'full_url' => $_SERVER['HTTP_REFERER'],
		);
	} else {
		$_PAGE['referer'] = array(
			'id' => 0,
			'getmod' => '',
			'anchor' => 0,
			'full_url' => $_SERVER['HTTP_REFERER'],
		);
		//Si le referer n'existe pas sur ce site, alors on enregistre l'url dans les log pour des raisons statistiques
		$f = fopen(ROOT.DS.'logs'.DS.'referer'.DS.date('Y.m.d').'.log', 'a');##On stocke le temps d'exécution dans le fichier log
		$final = "*|*|*Date=>".json_encode(date(DATE_RFC822))
		.'||Ip=>'.json_encode($_SERVER['REMOTE_ADDR'])
		.'||Referer=>'.json_encode($_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'Accès direct au site')
		.'||Page.get=>'.json_encode($_PAGE['get'])
		.'||Page.request=>'.json_encode((array)@$_PAGE['request'])
		.'||Page.get_params=>'.json_encode((array)$_GET)
		.'||User.id=>'.json_encode(Users::$id);
		$final = preg_replace('#\n|\r|\t#isU', '', $final);
		$final = preg_replace('#\s\s+#isUu', ' ', $final);
		fwrite($f, $final);
		fclose($f);
		unset($f, $final);
	}
}