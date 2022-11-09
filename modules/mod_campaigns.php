<?php

$sendmail = isset($_PAGE['request'][2]) && $_PAGE['request'][2] === 'sendmail';

$game_mj = isset($_PAGE['request'][0]) ? (int) $_PAGE['request'][0] : 0;
$char_id = isset($_PAGE['request'][1]) ? (int) $_PAGE['request'][1] : 0;

if ($game_mj && $char_id && $sendmail) {
	load_module('send_invitation', 'module', array('game_mj'=>$game_mj, 'char_id'=>$char_id));
} elseif ($game_mj && $char_id && !$sendmail) {
	load_module('gift', 'module', array('game_mj'=>$game_mj, 'char_id'=>$char_id));
} elseif ($game_mj && !$char_id) {
	load_module('gm', 'module', array('game_mj' => $game_mj));
} elseif (!$game_mj && !$char_id) {
	load_module('list', 'module');
}
unset($sendmail, $game_mj, $game_player, $char_id);

buffWrite('css', /** @lang CSS */ <<<CSSFILE
	.give_exp { margin-right: 5px; }
CSSFILE
);

buffWrite('js', /** @lang JavaScript */ <<<JSFILE
\$(document).ready(function(){
	$('.select_char').click(function(){
		$(this).toggleClass('btn-inverse').next('input[name="'+$(this).attr('data-valid')+'"]').val($(this).is('.btn-inverse') ? '1' : '0');
	});
});
JSFILE
);
