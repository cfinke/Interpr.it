<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<p style="margin-bottom: 20px;">
	<?=__("homepage_about")?>
</p>

<div class="panel panel-mod-group" style="width: 480px; float: left;">
	<div class="mod-group" style="height: 300px;">
		<div class="panel">
			<span class="center-mod-title"><?=__("header_developers")?></span>
			
			<div style="text-align: center;" id="homepage-upload">
				<? if (sess_anonymous()) { ?>
					<p style="margin-top: 110px;"><a href="/signin?next=<?=urlencode("/upload")?>"><?=__("action_signin_to_upload")?></a></p>
				<? } else { ?>
					<div style="width: 380px; margin: 20px auto auto auto; text-align: left;">
						<? include INCLUDE_PATH . "/templates/modules/upload.module.php"; ?>
					</div>
				<? } ?>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-mod-group" style="width: 480px; float: left; margin-left: 10px;">
	<div class="mod-group" style="height: 300px;">
		<div class="panel">
			<span class="center-mod-title"><?=__("header_translators")?></span>
			
			<div style="margin-top: 60px; text-align: center;">
				<div style="width: 350px; margin: auto; text-align: left;">
					<h2><?=__("header_extension_search")?></h2>
					<? include INCLUDE_PATH . "/templates/modules/search.module.php"; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<br style="clear: both;" />

<div class="panel" style="width: 316px; float: left;">
	<div class="mod-head"><?=__("header_latest_updates")?></div>
	<div class="mod-body">
		<? foreach ($latest_updates as $extension) { ?>
			<p><a href="<?=$extension->permalink()?>"><?=san($extension->name())?></a></p>
		<? } ?>
	</div>
</div>

<div class="panel" style="width: 316px; float: left; margin-left: 10px;">
	<div class="mod-head"><?=__("header_top_translators")?></div>
	<div class="mod-body">
		<ol style="margin-left: 15px;">
			<? foreach ($translators as $user) { ?>
				<li><a href="<?=$user->permalink()?>"><?=$user->username?></a></li>
			<? } ?>
		</ol>
	</div>
</div>

<div class="panel" style="width: 316px; float: left; margin-left: 10px;">
	<div class="mod-head"><?=__("header_newest_extensions")?></div>
	<div class="mod-body">
		<? foreach ($extensions as $extension) { ?>
			<p><a href="<?=$extension->permalink()?>"><?=san($extension->name())?></a></p>
		<? } ?>
	</div>
</div>

<? /*
<div class="panel" style="width: 235px; float: left; margin-left: 10px;">
	<div class="mod-head"><?=__("header_newest_members")?></div>
	<div class="mod-body">
		<? foreach ($newest_members as $user) { ?>
			<span><?=$user->username?></span><br />
		<? } ?>
	</div>
</div>
*/ ?>
<?php include INCLUDE_PATH . "/templates/footer.php"; ?>