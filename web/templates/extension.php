<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<div class="detail-header">
	<? if ($extension->icon) { ?>
		<img src="<?=$extension->icon_permalink()?>" style="float: left; max-height: 48px; margin-right: 10px;" />
	<? } ?>
	<p class="detail-title"><?=san($extension->name())?></p>

	<? if ($extension->description) { ?>
		<p class="detail-summary"><?=san($extension->description())?></p>
	<? } ?>
	
	<? if ($extension->url) { ?>
		<p><a href="<?=san($extension->url)?>" rel="nofollow"><?=__("link_extension_homepage")?></a></p>
	<? } ?>
</div>


<div style="padding-top: 10px; clear: both;">
	<div class="panel column-panel" style="width: 750px;">
		<div class="mod-head"><?=__("header_progress")?></div>
		<div class="mod-body">
			<table class="locale-table">
				<thead>
					<tr>
						<th style="width: 5em;"><?=__("locale")?></th>
						<th>&nbsp;</th>
						<th style="width: 3em;"><?=__("progress")?></th>
					</tr>
				</thead>
				<tbody>
				<?
				
				$total_translated_phrase_count = 0;
				$i = 0;
				$len = count($GLOBALS["locale_codes"][$extension->type]);
				foreach ($GLOBALS["locale_codes"][$extension->type] as $locale_code) {
					$locale = $extension->locale($locale_code);
					
					$total_translated_phrase_count += $locale->message_count;
					
					?>
					<tr<? if ($locale->progress == 100) { ?> class="locale-complete"<? } ?>>
						<td><a href="<?=$locale->permalink()?>"><abbr title="<?=locale_code_to_name($locale_code)?>"><?=$locale_code?></abbr></a></td>
						<td><div class="translation-progress-container"><div style="width: <?=$locale->progress?>%;">&nbsp;</div></div></td>
						<td><?=$locale->progress?>%</td>
					</tr>
					<? $i++; if ($i == ceil($len / 2)) { ?>
							</tbody>
						</table>
						<table class="locale-table">
							<thead>
								<tr>
									<th style="width: 5em;"><?=__("locale")?></th>
									<th>&nbsp;</th>
									<th style="width: 3em;"><?=__("progress")?></th>
								</tr>
							</thead>
							<tbody>
					<? } ?>
				<? } ?>
				</tbody>
			</table>
		</div>
	</div>
	<div style="width: 210px;" class="panel panel-no-rightmargin column-panel">
		<div>
			<? if (!sess_anonymous() && $GLOBALS["user"]->id == $extension->user_id) { ?>
				<div class="panel panel-">
					<div class="mod-panel">
						<div class="mod-head">
							<?=__("header_developer_actions")?>
						</div>
						<div class="mod-body">
							<a href="/api/download?extension_id=<?=$extension->id?>"><?=__("action_download_locales")?></a>
							<a href="/upload"><?=__("action_upload_update")?></a>
						</div>
					</div>
				</div>
				
			<? } ?>
			<div class="panel panel-">
				<div>
					<div class="detail-info">
						<?=__("label_version", $extension->version)?><br />
						<?=__("label_updated", date("Y/m/d", strtotime($extension->modified_date)))?><br />
						<?=__("label_message_limit", intval($extension->message_limit))?><br />
						<?=__("label_translation_progress", (floor($total_translated_phrase_count / ($extension->message_limit * count($GLOBALS["locale_codes"][$extension->type])) * 10000) / 100) . "%")?><br />
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include INCLUDE_PATH . "/templates/footer.php"; ?>