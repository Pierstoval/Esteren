<?php

/**
 * Fonction mkurl()
 *
 * @param array $base_params Le contenu à noter.<br />
 *    Attributs :<br />
 * &nbsp; &nbsp;val = $_PAGE['id'] &nbsp; &nbsp; La valeur à rechercher dans la liste.<br />
 * &nbsp; &nbsp;field = 'page_id' &nbsp; &nbsp; Le champ de la BDD à scanner.<br />
 * &nbsp; &nbsp;type = 'href' &nbsp; &nbsp; Quel type de contenu sera généré en retour <br />
 * &nbsp; &nbsp;ext = 'html' &nbsp; &nbsp; Extension visible dans l'url. Utilisé seulement pour type = tag ou type = href.<br />
 * &nbsp; &nbsp;get &nbsp; &nbsp; Attributs GET de l'url. Seulement pour type = href ou type = tag.<br />
 * &nbsp; &nbsp;anchor = $_PAGE['anchor'] &nbsp; &nbsp; Ancre de la balise &lt;a&gt;. Utilisé seulement si type = tag.<br />
 * &nbsp; &nbsp;attr &nbsp; &nbsp; Attributs de la balise &lt;a&gt;. Utilisé seulement si type = tag<br />
 * &nbsp; &nbsp;params &nbsp; &nbsp; Paramètres d'url à envoyer. Utilisé seulement pour type = tag ou type = href<br />
 * &nbsp; &nbsp;aftertag &nbsp; &nbsp; Texte ou code html à rajouter APRÈS la balise &lt;a&gt;. Utilisé seulement si type = tag<br />
 * &nbsp; &nbsp;beforetag &nbsp; &nbsp; Texte ou code html à rajouter AVANT la balise &lt;a&gt;. Utilisé seulement si type = tag<br />
 * &nbsp; &nbsp;post = $_POST &nbsp; &nbsp; Envoyer des données POST supplémentaires à la page<br />
 * &nbsp; &nbsp;custom &nbsp; &nbsp; Ce paramètre permet de créer une url externe ou personnalisée. Elle sera envoyée à $params['val']<br />
 *
 * @return string
 * @author Pierstoval 26/12/2012
 */
function mkurl($base_params = [])
{
    global $_PAGE;
    $params = (array) $base_params;
    $final = '';//Cette variable contient la chaîne de caractère du résultat

    $baseUrl = base_url();

    $params_default = [## On définit les valeurs par défaut de tous les attributs
        'val'       => $_PAGE['id'],
        'field'     => 'page_id',
        'type'      => 'href',
        'ext'       => 'html',
        'get'       => [],
        'anchor'    => '',
        'attr'      => [],
        'params'    => [],
        'aftertag'  => '',
        'beforetag' => '',
        'lang'      => null,
        'post'      => $_POST,
        'custom'    => false,
        'trans'     => false,
    ];

    $params = array_merge($params_default, $params);//On récupère les paramètres de l'utilisateur

    $params = [//On sécurise toutes les variables
        'val'       => is_numeric($params['val']) ? (int) $params['val'] : (string) $params['val'],
        'field'     => strtolower((false === strpos($params['field'], 'page_') ? 'page_' : '').$params['field']),
        'type'      => strtolower($params['type']),
        'anchor'    => (string) $params['anchor'],
        'ext'       => (string) $params['ext'],
        'get'       => (array) $params['get'],
        'attr'      => (array) $params['attr'],
        'params'    => (array) $params['params'],
        'aftertag'  => (string) $params['aftertag'],
        'beforetag' => (string) $params['beforetag'],
        'post'      => (array) $params['post'],
        'lang'      => $params['lang'],
        'custom'    => (bool) $params['custom'],
        'trans'     => (bool) $params['trans'],
    ];
    if (defined('P_MKURL_FIELDS')) {
        $fields_ok = explode(',', P_MKURL_FIELDS);//On récupère les champs paramétrés par l'utilisateur
    } else {
        $fields_ok = explode(
            ',',
            'page_id,page_getmod,page_anchor'
        );//Sinon on récupère manuellement une liste de champs
    }
    if (!in_array($params['field'], $fields_ok, true)) {//Vérifie que le champ existe
        return 'Erreur de lien #001';
    }
    $id = 0;//On définit un id par défaut pour générer une erreur si le champ n'est pas trouvé dans la liste
    if ($params['field'] === 'page_id') {//Si le champ demandé est l'id, alors on récupère directement celui-ci
        $id = $params['val'];
    } else {//Sinon, on va chercher dans la liste des pages la correspondance champ=>valeur avec les attributs field=>val de $params
        foreach ($_PAGE['list'] as $page_id => $page) {
            if ($page[$params['field']] === $params['val']) {
                $id = $page_id;//Si la correspondance est bonne, l'id sera celui de cette page
                break;//On stoppe la boucle foreach quand on a trouvé
            }
        }
    }

    if (!$params['lang']) {
        $params['lang'] = P_LANG;
    }
    if ($params['lang'] !== 'fr' && $params['lang'] !== 'en') {
        $params['lang'] = 'fr';
    }

    if ($id && $params['custom'] === false) {
        if (!isset($_PAGE['list'][$id])) {
            return 'Erreur de lien #002';
        }//Si l'id n'existe pas dans la liste, alors le champ n'est pas trouvé

        $page = $_PAGE['list'][$id];//On récupère le contenu de la page
        foreach ($page as $k => $v) {
            if (is_numeric($v)) {
                $page[$k] = (int) $v;
            }
        }//On passe les chaînes numériques en int (simple formatage)
    } elseif ($params['custom'] === true) {
        $page = ['page_getmod' => '', 'page_anchor' => ''];
    } else {
        return 'Erreur de lien #002';//Si on n'a pas d'id et que l'on ne crée pas d'url perso, on renvoie l'erreur
    }

    //Gestion des paramètres additionnels d'url
    $all_params = '';
    if (!empty($params['params']) && $params['type'] !== 'get') {//On ajoute les paramètres (s'il y en a) pour les types 'href' et 'tag'
        foreach ($params['params'] as $k => $v) {
            if (is_numeric($k)) {//Si le paramètre n'a pas de clé, on l'ajoute directement
                $all_params .= '/'.$v;
            } else {//S'il a une clé, l'association clé=>valeur sera faite à l'aide du signe ":"
                $all_params .= '/'.$k.':'.$v;
            }
        }
    }

    //Gestion des paramètres GET de l'url
    $get_params = '';
    if (!empty($params['get']) && $params['type'] !== 'get') {//On ajoute les paramètres (s'il y en a) pour les types 'href' et 'tag'
        foreach ($params['get'] as $k => $v) {
            if ($get_params) {
                $get_params .= ($params['type'] === 'tag' ? '&amp;' : '&');
            }
            $get_params .= $k.'='.urlencode($v);
        }
    }
    if ($get_params) {
        $get_params = '?'.$get_params;
    }

    //Création du résultat
    if ($params['type'] === 'get') {//Uniquement la valeur du getmod
        return $page['page_getmod'];
    }

    if ($params['type'] === 'href') {//Uniquement le lien complet
        if ($page['page_getmod'] === 'index') {
            return $baseUrl.'/'.$params['lang'].'/'.($all_params ? 'index/'.$all_params : '').($get_params ? $get_params : '');//Pour l'accueil, on définit une url plus "jolie", notamment pour le référencement
        }

        if ($params['custom'] === false) {
            return $baseUrl.'/'.$params['lang'].'/'.$page['page_getmod'].$all_params.'.'.$params['ext'].($get_params ? $get_params : '');
        }

        $final = $params['val'];

        if (strpos($final, 'http') === false) {
            $final = 'http'.(is_ssl() ? 's' : '').'://'.$final;
        }

        return $final;
    }

    if ($params['type'] === 'tag') {//Création d'une balise <a> complète
        if ($page['page_getmod'] === 'index') {
            $href = $baseUrl.'/'.$params['lang'].'/'.($all_params ? 'index/'.$all_params : '').($get_params ? $get_params : '');//Pour l'accueil, on définit une url plus "jolie", notamment pour le référencement
        } elseif ($params['custom'] === false) {
            $href = $baseUrl.'/'.$params['lang'].'/'.$page['page_getmod'].$all_params.'.'.$params['ext'].($get_params ? $get_params : '');
        } elseif ($params['custom'] === true) {
            $href = $params['val'];
            if (strpos($href, 'http') === false) {
                $href = 'http'.(is_ssl() ? 's' : '').'://'.$href;
            }
        } else {
            $href = '';
        }

        $attr = '';

        if (!isset($params['attr']['title'])
            || (isset($params['attr'][0])
                && strpos($params['attr'][0], 'title') === false && !isset($params['attr']['title'])
            ) || empty($params['attr'])
        ) {
            $params['attr']['title'] = $page['page_anchor'];//On définit un attribut title s'il n'a pas été ajouté dans les paramètres 'attr' du lien
        }

        //pr($params['attr']);
        foreach ($params['attr'] as $param => $value) {
            if (is_numeric($param)) {
                $attr .= ' '.$value;
            } else {
                $attr .= ' '.$param.'="'.$value.'"';
            }
        }

        if (!$params['anchor']) {
            if ($params['custom'] === false) {
                $params['anchor'] = $page['page_anchor'];// Si $params['anchor'] est vide et qu'on crée une url interne, alors on affiche l'ancre par défaut
            } else {
                $params['anchor'] = $href;// Si $params['anchor'] est vide et qu'on crée une url personnaliée, alors on affiche l'url elle-même par défaut
            }

        }

        if ($params['trans']) {
            $params['anchor'] = tr($params['anchor'], true);
        }

        $final = $params['beforetag'].'<a href="'.$href.'"'.$attr.'>'.$params['anchor'].'</a>'.$params['aftertag'];
    }

    return $final;
}

function mkurl_to_internal_url($url)
{
    return str_replace(
        ['/fr/', '/en/', BASE_URL, '/', '\\', DS.DS],
        ['/', '/', ROOT.DS.'webroot'.DS, DS, DS, DS],
        $url
    );
}

function mkurl_to_client_url($url, $lang = true)
{
    return str_replace([ROOT.DS.'webroot', '\\', '/'], [$lang ? base_url(true) : BASE_URL, '/', '/'], $url);
}

function is_ssl()
{
    if (isset($_SERVER['HTTPS'])) {
        if (strtolower($_SERVER['HTTPS']) === 'on' || ((int)$_SERVER['HTTPS']) === 1) {
            return true;
        }
    }

    return isset($_SERVER['SERVER_PORT']) && (((int) $_SERVER['SERVER_PORT']) === 443);
}
