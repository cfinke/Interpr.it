<div class="extension-stub">
	<? if ($extension->icon) { ?>
		<a href="<?=$extension->permalink()?>"><img src="<?=$extension->icon_permalink()?>" style="float: left; max-height: 48px; margin-right: 10px;" /></a>
	<? } ?>
	
	<p class="detail-title"><a href="<?=$extension->permalink()?>"><?=san($extension->name())?></a> <small><?=$extension->version?></small></p>

	<? if ($extension->description) { ?>
		<p class="detail-summary"><?=san($extension->description())?></p>
	<? } ?>
	
	<? if ($show_developer_actions) { ?>
		<p><a href="/api/download?extension_id=<?=$extension->id?>"><?=__("action_download_locales")?></a> &bull; <a href="/upload"><?=__("action_upload_update")?></a></p>
	<? } ?>
	
	<? if ($show_translator_actions) { ?>
		<p>
			<? if ($extension->pending_messages) { ?>
				<a href="<?=$extension->locale($locale_code)->permalink()?>"><?=__("translate_call_to_action", array(san($extension->name()), locale_code_to_name($locale_code)))?></a>
				<?=__("missing_phrase_count", $extension->pending_messages)?>
			<? } else { ?>
				<?=__("locale_is_complete", array(san($extension->name()), '<a href="<?=$extension->locale($locale_code)->permalink()?>"><em>'.locale_code_to_name($locale_code).'</em></a>'))?>
			<? } ?>
		</p>
	<? } ?>
</div>