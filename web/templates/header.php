<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="<?=str_replace("_", "-", get_locale())?>">
	<head>
		<title><?=$meta["title"]?> :: Interpr.it</title>
		<meta http-equiv="X-UA-Compatible" content="IE=Edge">
		
		<? if (isset($meta["index"]) && !$meta["index"]) { ?>
			<meta name="robots" content="noindex" />
		<? } ?>
		
		<? if (isset($meta["canonical"])) { ?>
			<link rel="canonical" href="http://<?=$_SERVER["SERVER_NAME"]?><?=$meta["canonical"]?>" />
		<? } ?>
		
		<link rel="stylesheet" type="text/css" href="/content/css/base.css?20101228" />
		<? /* <link rel="shortcut icon" type="image/png" href="/content/images/favicon.png" /> */ ?>
		<script type="text/javascript">
			var SESS_ANONYMOUS = <?=json_encode(sess_anonymous())?>;
			var SESS_EMAIL = <?php echo sess_anonymous() ? "null" : json_encode( $GLOBALS['user']->email ); ?>;
		</script>
		<script src="/content/js/jquery.js"></script>
		<script src="/content/js/util.js?v=20150204230100"></script>
		<script type="text/javascript">
			var _gaq = _gaq || [];
			_gaq.push(['_setAccount', 'UA-20386361-1']);
			_gaq.push(['_trackPageview']);
			_gaq.push(['_trackPageLoadTime']);
			
			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();
		</script>
	</head>
	
	<body>
		<div id="page">
			<table class="header">
				<tr>
					<td>
						<a href="/" style="text-transform: lowercase; font: 'Arial Narrow Condensed', 'Arial Narrow', Arial, sans; letter-spacing: 1px; font-size: 27px; color: #1335b8; font-weight: normal;">
							<?=__("homepage_title")?>
						</a>
					</td>
					<td class="signin">
						<? if (sess_anonymous()) { ?>
							<a class="signin" href="#signin"><?php echo __("action_signin_signup"); ?></a>
						<? } else { ?>
							<span><?=san($GLOBALS["user"]->email)?></span>
							<a href="/dashboard"><?=__("dashboard_page_title")?></a>
							<a href="/upload"><?=__("upload_page_link_label")?></a>
							<a href="/signout"><?=__("action_signout")?></a>
						<? } ?>
						
						<span id="launch-locale-picker"><abbr title="<?=locale_code_to_name(get_locale())?>"><?=str_replace("_", "-", get_locale())?></abbr></span>
						
						<span id="locale-picker-popup-anchor">
							<div id="locale-picker">
								<? foreach ($GLOBALS["website_locales"] as $_locale_code) { ?>
									<? if ($_locale_code != get_locale()) { ?>
										<a href="//<?=strtolower(str_replace("_", "-", $_locale_code))?>.interpr.it<?=$_SERVER["REQUEST_URI"]?>" title="<?=locale_code_to_name($_locale_code)?>"><?=str_replace("_", "-", $_locale_code)?></a>
									<? } ?>
								<? } ?>
							</div>
						</span>
					</td>
				</tr>
			</table>
			
			<? if (!empty($breadcrumbs)) { ?>
				<div class="breadcrumbs">
					<? $last_key = array_pop(array_keys($breadcrumbs)); ?>
					<? $last_label = array_pop($breadcrumbs); ?>
					<? foreach ($breadcrumbs as $url => $label) { ?>
						<a href="<?=$url?>"><?=san($label)?></a>
					<? } ?>
					<span><?=san($last_label)?></span>
				</div>
			<? } ?>
			
			<div id="content">