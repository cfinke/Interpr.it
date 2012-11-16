<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<div class="panel column-panel" style="width: 155px;">
	<div class="mod-head">
		<?=__("header_search_details")?>
	</div>
	<div class="mod-body search-module">
		<? include INCLUDE_PATH . "/templates/modules/search.module.php"; ?>
	</div>
</div>

<div class="panel panel-mod-group column-panel" style="width: 795px;">
	<div class="mod-head">
		<?=__("header_search_results")?>
	</div>
	<div class="mod-group">
		<div>
			<? if (!$search_results) { ?>
				<p>
					<? if ($q) { ?>
						<? if ($locale_code) { ?>
							<?=__("text_no_results_for_query_and_locale", array("'<em>".san($q)."</em>'", $locale_code))?>
						<? } else { ?>
							<?=__("text_no_results_for_query", "'<em>".san($q)."</em>'")?>
						<? } ?>
					<? } else { ?>
						<? if ($locale_code) { ?>
							<?=__("text_no_results_for_locale", $locale_code)?>
						<? } else { ?>
							<?=__("text_no_results")?>
						<? } ?>
					<? } ?>
				</p>
			<? } else { ?>
				<div></div>
				<? foreach ($search_results as $extension) { ?>
					<? include INCLUDE_PATH . "/templates/modules/extension.module.php"; ?>
				<? } ?>
			<? } ?>
		</div>
	</div>
</div>
<?php include INCLUDE_PATH . "/templates/footer.php"; ?>