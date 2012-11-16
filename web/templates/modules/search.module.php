<form method="get" action="/search" id="homepage-search-form">
	<p>
		<label control="locale"><?=__("label_your_locale")?></label>
		<select name="locale">
			<option value=""><?=__("call_to_action_select")?></option>
			<? foreach ($GLOBALS["locale_codes"]["crx"] as $_locale_code) { ?>
				<option value="<?=$_locale_code?>"<? if ((isset($_GET["locale"]) && $_GET["locale"] == $_locale_code) || (!isset($_GET["locale"]) && (get_locale() == $_locale_code))) { ?> selected="selected"<? } ?>><?=$_locale_code?> - <?=locale_code_to_name($_locale_code)?></option>
			<? } ?>
		</select>
	</p>
	<p>
		<label control="q"><?=__("label_keywords")?></label>
		<input type="text" name="q" <? if (!empty($q)) { ?> value="<?=san($q)?>" <? } ?> />
	</p>

	<input type="submit" value="<?=__("label_search")?>" />
</form>