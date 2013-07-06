<?php

$characters = $db->req('SELECT %char_name,%char_id FROM %%characters WHERE %user_id = ?', array(Users::$id));

if (is_array($characters) && !empty($characters)) { ?>
	<h4><?php tr('Voici la liste de vos personnages'); ?></h4>
	<ul><?php
		foreach($characters as $v) { ?>
			<li>
				<?php echo mkurl(array('type' => 'tag', 'anchor' => 'Modifier', 'attr' => 'class="btn btn-mini"', 'params' => array($v['char_id']))); ?> &ndash;
				<?php echo mkurl(array('val' => 63, 'type' => 'tag', 'attr' => 'class="btn btn-mini"', 'params' => array($v['char_id']))); ?> &ndash;
				<?php echo mkurl(array('val' => 47, 'type' => 'tag', 'anchor' => $v['char_name'], 'attr' => '', 'params' => array($v['char_id']))); ?>
			</li>
		<?php } ?>
	</ul>
<?php
} else {
	?><p><?php tr('Aucun personnage'); ?></p><?php
}
unset($characters, $v);