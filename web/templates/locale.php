<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<div class="detail-header">
	<? if ($extension->icon) { ?>
		<img src="<?=$extension->icon_permalink()?>" style="float: left; max-height: 48px; margin-right: 10px;" />
	<? } ?>
	<p class="detail-title"><?=san($extension->name())?></p>

	<? if ($extension->description) { ?>
		<div class="detail-summary"><?=san($extension->description())?></div>
	<? } ?>
</div>

<div class="detail-subheader"><?=__("header_locale_page", locale_code_to_name($locale_code))?></div>

<? if (sess_anonymous()) { ?>
	<div class="warning"><?=__("translate_error_signin", "<a href=\"/signin?next=".urlencode($_SERVER["REQUEST_URI"])."\">")?></div>
<? } ?>

<div style="padding-top: 10px;">
	<div class="panel column-panel" style="width: 750px;">
		<form id="translation-form" action="" method="post">
			<? $default_locale = $extension->default_locale_object; $messages = $default_locale->files_messages; ?>
			
			<? foreach ($messages as $file => $_messages) { ?>
				<? if (count($messages) > 0) { ?><h2 id="<?=htmlspecialchars($file)?>"><?=htmlspecialchars($file)?></h2><? } ?>
				<? foreach ($_messages as $name => $message) { $locale_message = $locale->message($name, $file); ?>
					<fieldset id="message-<?=san($name)?>">
						<div class="mod-head">
							<?=san($name)?>
						</div>
						<div class="mod-body-container">
							<div class="mod-center">
								<label>
									<?=nl2br(san($message->message))?>
								</label>
						
								<textarea file="<?=san($file)?>" original_value="<?=($locale_message ? san($locale_message->message) : "")?>" name="<?=san($name)?>"<? if (sess_anonymous()) { ?> disabled="disabled" readonly="readonly"<? } ?>><?=($locale_message ? san($locale_message->message) : "")?></textarea>
						
								<? if ($message->description) { ?>
									<blockquote><?=nl2br(__("developer_explanation", $message->description))?></blockquote>
								<? } ?>
						
								<? if ($message->placeholders) { 
									$show_placeholders = false; $p = json_decode($message->placeholders); ?>
									<? foreach ($p as $placeholder_name => $placeholder_data) { ?>
										<? if ($placeholder_data->example) { ?>
											<? $show_placeholders = true; ?>
										<? } ?>
									<? } ?>
							
									<? if ($show_placeholders) { ?>
										<div class="placeholders">
											<div class="placeholders-title"><?=__("header_placeholder_examples")?></div>
											<ul>
												<? foreach ($p as $placeholder_name => $placeholder_data) { ?>
													<? if ($placeholder_data->example) { ?>
														<li><em>$<?=san($placeholder_name)?>$</em>: <?=san($placeholder_data->example)?></li>
													<? } ?>
												<? } ?>
											</ul>
										</div>
									<? } ?>
								<? } ?>
							</div>
							<? if ($locale_message && $locale_message->has_history()) { ?>
								<div class="mod-history">
									<a class="history-toggle" name="<?=san($name)?>" title="<?=__("action_message_history")?>"><?=__("action_message_history")?></a>
								</div>
							<? } ?>
						</div>
					</fieldset>
				<? } ?>
			<? } ?>
		</form>
		<br style="width: 750px;" />
	</div>
	<div style="width: 210px;" class="panel panel-no-rightmargin column-panel" id="sidebar">
		<div>
			<div class="panel panel-">
				<div class="mod-panel">
					<div class="mod-head">
						<?=__("progress")?>
					</div>
					<div class="mod-body">
						<span class="translation-progress"><?=$locale->progress?></span>%
					</div>
				</div>
			</div>
			<? if (count($messages) > 0) { ?>
				<div class="panel panel-">
					<div class="mod-panel">
						<div class="mod-head">
							<?=__("files")?>
						</div>
						<div class="mod-body">
							<? foreach ($messages as $file => $_messages) { ?>
								<a href="#<?=htmlspecialchars($file)?>"><?=htmlspecialchars($file)?></a><br />
							<? } ?>
						</div>
					</div>
				</div>
			<? } ?>
			<div class="panel panel-">
				<div class="mod-panel">
					<div class="mod-head">
						<?=__("header_translator_actions")?>
					</div>
					<div class="mod-body">
						<!-- <a href="">Contact Developer</a><br /><br />-->
						<a href="#" class="hide-completed-translations"><?=__("hide_completed")?><br /></a>
						<a href="#" class="show-completed-translations" style="display: none;"><?=__("show_all")?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	var extension_id = <?=json_encode($extension->id)?>;
	var locale_code = <?=json_encode($locale->locale_code)?>;
	
	function calculate_progress() {
		var textareas = $("#translation-form textarea");
		
		var total_strings = textareas.length;
		
		var translated_strings = 0;
		textareas.each(function () {
			if ($(this).val()) {
				translated_strings++;
			}
		});
		
		$(".translation-progress").text( Math.floor(translated_strings / total_strings * 100) );
	}
	
	var notification_timeout = null;
	
	$(document).ready(function () {
		$("textarea").blur(function () {
			var textarea = $(this);
			
			if (textarea.val() == textarea.attr("original_value")) {
				return;
			}
			
			clearTimeout(notification_timeout);
			
			$("#notification").text(<?=json_encode(__("saving_notification"))?>).show();
			
			$.post("/api/translate", "extension_id=" + encodeURIComponent(extension_id) + "&locale_code=" + encodeURIComponent(locale_code) + "&name=" + encodeURIComponent($(this).attr("name")) + "&message=" + encodeURIComponent($(this).val()) + "&file=" + encodeURIComponent($(this).attr("file")), function (data) {
				if (!data.status) {
					alert(data.msg);
					textarea.focus();
					$("#notification").hide();
				}
				else {
					textarea.attr("original_value", textarea.val());
					
					calculate_progress();
					$("#notification").text(<?=json_encode(__("saved_notification"))?>);
					setTimeout(function () { $("#notification").hide(); }, 1000);
				}
			}, "json");
		});
		
		$(".hide-completed-translations").click(function (e) {
			e.preventDefault();
			
			$("#translation-form textarea").each(function () {
				if ($(this).val()) {
					$(this).parents("fieldset:first").hide();
				}
			});
			
			$(".hide-completed-translations").hide();
			$(".show-completed-translations").show();
		});

		$(".show-completed-translations").click(function (e) {
			e.preventDefault();
			
			$("#translation-form fieldset").show();
			
			$(".hide-completed-translations").show();
			$(".show-completed-translations").hide();
		});
		
		$(".history-toggle").click(function (e) {
			e.preventDefault();
			
			var link = $(this);
			var message_name = link.attr("name");
			
			if (link.attr("active")) {
				link.removeAttr("active");
				$("#history-" + message_name).remove();
			}
			else {
				link.attr("active", "active");

				$.get("/api/history", "extension_id=" + encodeURIComponent(extension_id) + "&locale_code=" + encodeURIComponent(locale_code) + "&name=" + encodeURIComponent($(this).attr("name")), function (data) {
					if ("status" in data && !data.status) {
						alert(data.msg);
					}
					else {
						var container = $("<div/>");
						container.attr("id", "history-" + message_name);
						
						container.html('<table style="width: 100%;"><thead><tr><th>&nbsp;</th><th>' + <?=json_encode(__("column_header_message_version"))?> + '</th><th>' + <?=json_encode(__("column_header_changed_by"))?>+'</th>' + (SESS_ANONYMOUS ? '<th></th>' : '') + '</tr></thead><tbody></tbody></table>');
						
						for (var i = 0, _len = data.history.length; i < _len; i++) {
							var newContainer = $("<tr/>");
							
							var td1 = $("<td/>");
							td1.text(data.history[i].date);
							
							var td2 = $("<td/>");
							td2.text(data.history[i].message);
							
							var td3 = $("<td/>");
							var td3a = $("<a/>");
							td3a.text(data.history[i].user.username);
							td3a.attr("href", data.history[i].user.permalink);
							td3.append(td3a);
							
							if (!SESS_ANONYMOUS) {
								var td4 = $("<td/>");
								
								if (i > 0) {
									var td4a = $("<a/>");
									td4a.text(<?=json_encode(__("action_revert"))?>);
									td4a.addClass("revert-message");
									td4a.attr("name", message_name);
									td4a.attr("history_id", data.history[i].history_id);
									td4.append(td4a);
								}
							}
							
							newContainer.append(td1).append(td2).append(td3).append(td4);
							container.find("tbody:first").append(newContainer);
						}
						
						link.closest("fieldset").find(".mod-history").append(container).show();
					}
				}, "json");
			}
		});
		
		$(document).on("click", "a.revert-message", function (e) {
			e.preventDefault();
			
			var link = $(this);
			var message_name = link.attr("name");
			var history_id = link.attr("history_id");
			
			$.post("/api/revert", "extension_id=" + encodeURIComponent(extension_id) + "&locale_code=" + encodeURIComponent(locale_code) + "&name=" + encodeURIComponent(message_name) + "&history_id=" + encodeURIComponent(history_id), function (data) {
				if ("status" in data && !data.status) {
					alert(data.msg);
				}
				else {
					$("textarea[name='" + message_name + "']").val(data.message);
					
					link.closest("fieldset").find(".history-toggle").click();
				}
			}, "json");
		});
	});
</script>

<?php include INCLUDE_PATH . "/templates/footer.php"; ?>