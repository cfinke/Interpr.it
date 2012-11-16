<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<? if ($extensions) { ?>
	<div>
		<h2><?=__("dashboard_header_extensions")?></h2>
	
		<div class="extension-stub-list">
			<? foreach ($extensions as $extension) { ?>
				<div class="extension-stub">
					<? if ($extension->icon) { ?>
						<a href="<?=$extension->permalink()?>"><img src="<?=$extension->icon_permalink()?>" style="float: left; max-height: 48px; margin-right: 10px;" /></a>
					<? } ?>
				
					<p class="detail-title"><a href="<?=$extension->permalink()?>"><?=san($extension->name())?></a> <small><?=$extension->version?></small></p>
				
					<? if ($extension->description) { ?>
						<p class="detail-summary"><?=san($extension->description())?></p>
					<? } ?>
				
					<p>
						<a href="/api/download?extension_id=<?=$extension->id?>" class="launch-mini-popup-menu"><?=__("action_download_locales")?></a>
						<? if (0 && $extension->type == 'xpi') { ?>
							<span class="mini-popup-menu-anchor">
								<div class="mini-popup-menu">
									<a href="/api/download?extension_id=<?=$extension->id?>">Missing strings skipped</a>
									<a href="/api/download?extension_id=<?=$extension->id?>&amp;missing=replaced">Missing strings replaced</a>
									<a href="/api/download?extension_id=<?=$extension->id?>&amp;missing=blank">Missing strings blank</a>
								</div>
							</span>
						<? } ?>
						&bull; <a href="/upload"><?=__("action_upload_update")?></a>
					</p>
				</div>
			<? } ?>
		</div>
	</div>
<? } ?>

<? if ($locales) { ?>
	<div style="margin-top: 30px;">
		<h2><?=__("dashboard_header_locales")?></h2>
	
		<table class="dashboard-locale-table">
			<thead>
				<tr>
					<th>
						<?=__("extension")?>
					</th>
					<th>
						<?=__("locale")?>
					</th>
					<th>
						<?=__("progress")?>
					</th>
				</tr>
			</thead>
			<tbody>
				<? foreach ($locales as $locale) { ?>
					<tr<? if ($locale->progress == 100) { ?> class="locale-complete"<? } ?>>
						<td><a href="<?=$locale->extension->permalink()?>"><?=$locale->extension->name?></a></td>
						<td><a href="<?=$locale->permalink()?>"><?=$locale->locale_code?></a></td>
						<td><?=$locale->progress?>%</td>
					<tr>
				<? } ?>
			</tbody>
		</table>
	</div>
<? } ?>

<? if ($user_id == sess_id()) { ?>
	<div style="margin-top: 30px;">
		<h2 id="email-preferences"><?=__("email_preference_header")?></h2>
		<form method="post" action="/api/email-preferences" class="ajax-form" callback="cb_user">
			<fieldset style="margin-top: 10px;">
				<p>
					<input type="checkbox" name="EXTENSION_UPDATE" value="1" <? if ($GLOBALS["user"]->email_preferences & User::$EMAIL_FLAG_EXTENSION_UPDATE) { ?> checked="checked"<? } ?> />
					<label>
						<?=__("email_preference_extension_updates")?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="LOCALE_COMPLETE" value="1" <? if ($GLOBALS["user"]->email_preferences & User::$EMAIL_FLAG_LOCALE_COMPLETE) { ?> checked="checked"<? } ?>/>
					<label>
						<?=__("email_preference_locale_completed")?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="MESSAGE_CHANGE" value="1" <? if ($GLOBALS["user"]->email_preferences & User::$EMAIL_FLAG_MESSAGE_CHANGE) { ?> checked="checked"<? } ?>/>
					<label>
						<?=__("email_preference_message_changed")?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="EXTENSION_INSERT" value="1" <? if ($GLOBALS["user"]->email_preferences & User::$EMAIL_FLAG_EXTENSION_INSERT) { ?> checked="checked"<? } ?> />
					<label>
						<?=__("email_preference_extension_created")?>
					</label>
				</p>
				<p><?=__("email_batch_description")?></p>
				<input type="submit" value="<?=__("save_changes_button")?>" />
			</fieldset>
		</form>
	</div>
	
	<div style="margin-top: 30px;">
		<h2 id="api_key"><?=__("dashboard_api_key_header")?></h2>
		<p><code id="api_key_content"><?=$GLOBALS["user"]->api_key?></code> (<a href="/api"><?=__("api_documentation")?></a>)</p>

		<form method="post" action="/api/api-key" class="ajax-form" callback="cb_api_key_reset">
			<input type="hidden" name="action" value="reset" />
	
			<button><?=__("action_reset_api_key")?></button>
		</form>
	</div>
	
	<script type="text/javascript">
		function cb_api_key_reset(data, form) {
			$("#api_key_content").text(data.api_key);
		}
		
		function cb_user(data, form) {
			document.location.reload();
		}
	</script>
<? } ?>

<?php include INCLUDE_PATH . "/templates/footer.php"; ?>