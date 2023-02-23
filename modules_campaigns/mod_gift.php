<?php

use App\EsterenChar;
use App\FileAndDir;
use App\Session;
use App\Users;

$game_id = isset($_PAGE['request'][0]) ? (int) $_PAGE['request'][0] : 0;

if (!$game_id) {
	Session::setFlash('Une partie doit être sélectionnée', 'error');
	header('Location:'.mkurl());
	exit;
}

$game = $db->row('SELECT %game_name,%game_id,%game_mj FROM %%games WHERE %game_id = ?', $game_id);

if (!$game) {
	Session::setFlash('Aucune partie trouvée', 'warning');
	header('Location:'.mkurl());
	exit;
}
if ($game['game_mj'] != Users::$id) {
	Session::setFlash('Vous n\'êtes pas le maître de jeu de cette partie', 'error');
	header('Location:'.mkurl());
	exit;
}

$char = $db->row('SELECT %char_id FROM
		%%characters WHERE %char_id = :char_id && %game_id = :game_id && (%char_status = :pj || %char_status = :pnj)', array('char_id'=>$char_id,'game_id'=>$game_id,'pj'=>1,'pnj'=>2));

if (!$char) {
    Session::setFlash('Vous ne pouvez pas donner de récompense à ce personnage.', 'error');
    header('Location:'.mkurl(array('params'=>array(0=>$game_id))));
    exit;
}

unset($char);
$char = new EsterenChar($char_id, 'db');

if (!empty($_POST)) {
	load_module('gift_post', 'module', array('char' => $char));
}

if (!isset($char_id)) { return; }

if (isset($_PAGE['request'][2])) {
    if ($_PAGE['request'][2] === 'delete') {
        // Retrait du personnage de la campagne en cours
        $sql = 'UPDATE %%characters SET %game_id = :game_id, %char_status = :char_status WHERE %char_id = :char_id ';
        $datas['game_id'] = null;
        $datas['char_status'] = 0;
        $datas['char_id'] = $char_id;
        $db->noRes($sql, $datas);
        Session::setFlash('Le personnage a été correctement retiré de la campagne.');
        redirect(array('params' => array(0=>$game_id)));
    } elseif ($_PAGE['request'][2] === 'sendmail') {


        $result = $db->row('
            SELECT %c.%char_name, %c.%char_confirm_invite,
                %g.%game_id, %g.%game_name,
                %uMj.%user_name %gm_name,
                %u.%user_name, %u.%user_email
            FROM %%characters %c
            LEFT JOIN %%games %g ON %c.%game_id = %g.%game_id
            LEFT JOIN %%users %u ON %c.%user_id = %u.%user_id
            LEFT JOIN %%users %uMj ON %g.%game_mj = %u.%user_id
            WHERE %c.%char_id = :char_id
              AND %c.%char_status = :status
              AND %g.%game_id = :game_id
            ', array('char_id' => $char_id, 'status' => 0, 'game_id' => $game_id));

        if (!$result) {
            Session::setFlash('Erreur : personnage non trouvé, ou le personnage est déjà inscrit à une campagne.');
            redirect(array('params' => array(0=>$game_id)));
        }

        $msg_invite = $db->row('SELECT %mail_id, %mail_contents, %mail_subject FROM %%mails WHERE %mail_code = ?', array('campaign_invite'));
        $subj = tr($msg_invite['mail_subject'], true, null, 'mails');
        $txt = tr($msg_invite['mail_contents'], true, array(
            '{user_name}' => $result['user_name'],
            '{cp_name}' => $result['game_name'],
            '{char_name}' => $result['char_name'],
            '{cp_mj}' => $result['gm_name'],
            '{link}' => mkurl(array('val'=>64,'type'=>'tag','anchor'=>'Confirmer l\'invitation','trans'=>true,'params'=>array('confirm_campaign_invite', $result['char_confirm_invite']))),
        ), 'mails');

        $dest = array(
            'mail' => $result['user_email'],
            'name' => $result['user_name'],
        );

        try {
            send_mail($dest, $subj, $txt, $msg_invite['mail_id']);
            Session::setFlash('Le mail a bien été renvoyé à l\'utilisateur.');
        } catch (Exception $e) {
            Session::setFlash('Une erreur est survenue dans l\'envoi de l\'email de confirmation au joueur...', 'warning');
        }
    }
    redirect(array('params' => array(0=>$game_id)));
}

$modules_list = array(
	'experience' => 'Expérience',
	'armes' => 'Armes',
	'armures' => 'Armures',
	'daols' => 'Daols',
	'trauma' => 'Trauma',
);

?>
<div class="container">
<form action="<?php echo mkurl(array('params'=>array($game_id,$char_id))); ?>" method="post" class="form-horizontal">
<fieldset>
	<input type="hidden" name="game_id" value="<?php echo $game_id; ?>" />
	<input type="hidden" name="char_id" value="<?php echo $char_id; ?>" />
	<h3><?php tr('Offrir des récompenses à un personnage joueur'); ?></h3>
    <h2><?php echo $char->name(); ?></h2>

	<ul class="nav nav-tabs" id="modify_tabs">
	<?php
		$i = 0; foreach($modules_list as $file => $title) {
			$file_to_load = ROOT.DS.'modules_'.$_PAGE['get'].DS.'mod_gift_'.$file.'.php';
			if (FileAndDir::fexists($file_to_load)) { ?>
			<li<?php echo $i === 0 ? ' class="active"' : ''; ?>><a data-toggle="tab" href="#<?php echo $file; ?>"><?php tr($title); ?></a></li>
			<?php $i++; }
		}
	?>
	</ul>
	<div class="tab-content" id="myTabContent">
		<?php $i = 0; foreach($modules_list as $file => $title) {
			$file_to_load = ROOT.DS.'modules_'.$_PAGE['get'].DS.'mod_gift_'.$file.'.php';
			if (FileAndDir::fexists($file_to_load)) {?>
			<div id="<?php echo $file; ?>" class="tab-pane fade<?php echo $i === 0 ? ' in active' : ''; ?>"><?php require $file_to_load; ?></div>
			<?php $i++; }
		} ?>
	</div>
	<button id="send" class="btn btn-inverse"><?php tr('Envoyer'); ?></button>
</fieldset>
</form>
</div>

<script type="text/javascript">var valid_txt = '<?php tr('Valider les récompenses envoyées au personnage ?'); ?>';</script>

<?php
$_PAGE['more_js'][] = BASE_URL.'/js/pages/pg_'.$_PAGE['get'].'_gift.js';

buffWrite('js', /** @lang JavaScript */ <<<JSFILE

	function remove_chars() {
	}
	$(document).ready(function(){
		$('form').submit(function(){
			return confirm(valid_txt);
		});
		$('.data-slider').each(function(){
		    var _this = $(this);
            _this.slider({
                range: 'min',
                value: 0,
                min: _this.attr('data-slider-min'),
                max: _this.attr('data-slider-max'),
                slide: function( event, ui ) {
                    $(_this.attr('data-slider-input')).val(ui.value);
                }
            });
            $(_this.attr('data-slider-input')).on('mousedown blur focus', function () {
                var val = $(this).val(),
                    text = val.replace(/[^0-9]+/gi, '')
                ;
                //alert(text);
                _this.slider('option', 'value', Number(text));
                $(this).val(text);
            });
		});
		$('.change_value.btn').click(function(){
			$(this).toggleClass('btn-inverse')
				.next('input[type="hidden"]').val($(this).is('.btn-inverse') ? '1' : '0');
		});
	});
JSFILE
, $_PAGE['get'].'_gift');
