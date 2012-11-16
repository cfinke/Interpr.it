<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<div id="upload-page">
	<? if (isset($error)) { ?>
		<div class="warning">
			<?=san($error)?>
		</div>
	<? } ?>

	<? include INCLUDE_PATH . "/templates/modules/upload.module.php"; ?>
</div>

<?php include INCLUDE_PATH . "/templates/footer.php"; ?>