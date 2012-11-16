<p><?=__("email_notification_intro")?></p>

<? if ($events["extension:insert"]) { ?>
	<p><b><?=__("email_notification_header_extension_new")?></b></p>
	
	<p>
		<? foreach ($events["extension:insert"] as $subevent) {
			$e = new Extension($subevent->extension_id);
			
			echo '<a href="'.HOST.$e->permalink().'">'.san($e->name) . "</a>: ".san($e->description)."<br />";
		} ?>
	</p>
<? } ?>

<? if ($events["extension:update"]) { ?>
	<p><b><?=__("email_notification_header_extension_update")?></b></p>
	
	<p>
		<? foreach ($events["extension:update"] as $subevent) {
			$e = new Extension($subevent->extension_id);
			
			echo '<a href="'.HOST.$e->permalink().'">'.san($e->name) . "</a>: ".san($e->description)."<br />";
		} ?>
	</p>
<? } ?>

<? if ($events["locale:complete"]) { ?>
	<p><b><?=__("email_notification_header_locale_complete")?></b></p>
	
	<p>
		<? foreach ($events["locale:complete"] as $subevent) {
			$u = new User($subevent->user_id);
			$e = new Extension($subevent->extension_id);
			
			echo __("email_notification_locale_complete", array($u->username, HOST.$u->permalink(), $subevent->locale_code, $e->name, HOST.$e->permalink()))."<br />";
		} ?>
	</p>
<? } ?>

<? if ($events["message:update"]) { ?>
	<p><b><?=__("email_notification_header_message_modified")?></b></p>
	
	<p>
		<? foreach ($events["message:update"] as $subevent) {
			$u = new User($subevent->user_id);
			$e = new Extension($subevent->extension_id);
		
			echo __("email_notification_message_changed", array($u->username, HOST.$u->permalink(), HOST.$e->permalink().'/'.$subevent->locale_code.'#message-'.san($subevent->name), $e->name, HOST.$e->permalink())) . "<br />";
		} ?>
	</p>
<? } ?>

<hr />
<p><small><a href="<?=HOST?>/dashboard#email-preferences"><?=__("action_change_email_preferences")?></a> &bull; <a href="mailto:chris@interpr.it"><?=__("action_send_feedback")?></a> &bull; <a href="http://interpr.it/">http://interpr.it</a></small></p>