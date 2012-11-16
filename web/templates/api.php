<?php include INCLUDE_PATH . "/templates/header.php"; ?>

<h1><?=__("api_docs_page_header")?></h1>

<p><?=__("api_docs_page_summary")?></p>

<div id="api-docs">
	<div>
		<ul>
			<li><a href="#upload">/api/upload</a></li>
			<li><a href="#download">/api/download</a></li>
			<li><a href="#translate">/api/translate</a></li>
			<li><a href="#history">/api/history</a></li>
			<li><a href="#revert">/api/revert</a></li>
		</ul>
	</div>
	<div id="upload">
		<h2>/api/upload</h2>
		
		<p><?=__("api_description_upload")?></p>
		
		<h3><?=__("api_post_parameters_header")?></h3>
		
		<ul>
			<li><?=__("api_parameter_description", array("package", __("api_parameter_description_package")))?></li>
		</ul>
		
		<h3><?=__("api_return_format_header")?></h3>
		
		<p>JSON</p>
		
		<h3><?=__("api_return_values_header")?></h3>
		
		<ul>
			<li><code>{ "status" : true, "extension_id" : 123 }</code></li>
		</ul>
		
	</div>
	
	<div id="download">
		<h2>/api/download</h2>
		
		<p><?=__("api_description_download")?></p>
		
		<h3><?=__("api_get_parameters_header")?></h3>
		
		<ul>
			<li><?=__("api_parameter_description", array("extension_id", __("api_parameter_description_extension_id")))?></li>
		</ul>
		
		<h3><?=__("api_return_format_header")?></h3>
		
		<p><?=__("api_return_format_zip")?></p>
	</div>
	
	<div id="translate">
		<h2>/api/translate</h2>

		<p><?=__("api_description_translate")?></p>

		<h3><?=__("api_post_parameters_header")?></h3>

		<ul>
			<li><?=__("api_parameter_description", array("extension_id", __("api_parameter_description_extension_id")))?></li>
			<li><?=__("api_parameter_description", array("locale_code", __("api_parameter_description_locale_code")))?></li>
			<li><?=__("api_parameter_description", array("name", __("api_parameter_description_name")))?></li>
			<li><?=__("api_parameter_description", array("message", __("api_parameter_description_message")))?></li>
		</ul>

		<h3><?=__("api_return_format_header")?></h3>

		<p>JSON</p>

		<h3><?=__("api_return_values_header")?></h3>
		
		<ul>
			<li><code>{ "status" : true }</code></li>
			<li><code>{ "status" : false, "msg" : "<?=__("api_error_message_not_in_default_locale")?>" }</code></li>
		</ul>
	</div>
	
	<div id="history">
		<h2>/api/history</h2>
		
		<p><?=__("api_description_history")?></p>
		
		<h3><?=__("api_get_parameters_header")?></h3>

		<ul>
			<li><?=__("api_parameter_description", array("extension_id", __("api_parameter_description_extension_id")))?></li>
			<li><?=__("api_parameter_description", array("locale_code", __("api_parameter_description_locale_code")))?></li>
			<li><?=__("api_parameter_description", array("name", __("api_parameter_description_name")))?></li>
		</ul>

		<h3><?=__("api_return_format_header")?></h3>

		<p>JSON</p>

		<h3><?=__("api_return_values_header")?></h3>
		
		<ul>
			<li><code><pre>{ 
	"status" : true,
	"history" : [
		{ 
			"history_id" : 123, 
			"date" : "2010-01-02 03:04:05", 
			"message" : "Hello world.", 
			"user" : { 
				"id" : 1, 
				"username" : "cfinke", 
				"permalink" : "/member/1"
			}
		},
		{ 
			"history_id" : 96, 
			"date" : "2010-01-01 02:03:04", 
			"message" : "Hello world!", 
			"user" : { 
				"id" : 2, 
				"username" : "jsmith", 
				"permalink" : "/member/2"
			}
		}
	]
}</pre></code></li>
		</ul>
	</div>
	
	<div id="revert">
		<h2>/api/revert</h2>

		<p><?=__("api_description_revert")?></p>

		<h3><?=__("api_post_parameters_header")?></h3>

		<ul>
			<li><?=__("api_parameter_description", array("extension_id", __("api_parameter_description_extension_id")))?></li>
			<li><?=__("api_parameter_description", array("locale_code", __("api_parameter_description_locale_code")))?></li>
			<li><?=__("api_parameter_description", array("name", __("api_parameter_description_name")))?></li>
			<li><?=__("api_parameter_description", array("history_id", __("api_parameter_description_history_id")))?></li>
		</ul>

		<h3><?=__("api_return_format_header")?></h3>

		<p>JSON</p>

		<h3><?=__("api_return_values_header")?></h3>

		<ul>
			<li><code>{ "status" : true, "message" : "Hello world!" }</code></li>
		</ul>
	</div>
	
	<p style="margin-top: 20px;"><?=__("api_docs_page_contact")?></p>
</div>

<?php include INCLUDE_PATH . "/templates/footer.php"; ?>