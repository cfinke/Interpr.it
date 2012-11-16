<div class="upload-form-container">
	<h2><?=__("header_upload")?></h2>
	
	<form method="post" enctype="multipart/form-data" action="/upload" class="upload-form">
		<input type="file" name="package" />
		<input type="submit" value="<?=__("upload_page_link_label")?>" /> 
	</form>
	
</div>

<ul class="upload-instructions">
	<li><?=__("upload_instructions_1")?></li>
	<li><?=__("upload_instructions_2")?></li>
	<li class="upload-page-text"><?=__("upload_instructions_3")?></li>
</ul>