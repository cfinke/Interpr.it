/**
 * Sets a cookie.
 *
 * @param string name The name of the cookie.
 * @param string value The value of the cookie.
 * @param int seconds The life-length of the cookie, from now. If not specified, then it's a session cookie.
 */

function set_cookie(name, value, seconds) {
	var cookieString = name + "=" + value;
	
	if (seconds) {
		var expiration = new Date();
		expiration.setTime(expiration.getTime() + (1000 * seconds));
		
		cookieString += ";expires=" + expiration.toUTCString();
	}
	
	document.cookie = cookieString;
}

/**
 * Retrieves a cookie value.
 *
 * @param string name The name of the cookie.
 * @return string|bool Either the value of the cookie, or false if the cookie is not set.
 */

function get_cookie(name) {
	if (document.cookie.length > 0) {
		var cookieStart = document.cookie.indexOf(name + "=");
		
		if (cookieStart != -1) {
			var cookieEnd = document.cookie.indexOf(";", cookieStart);
			
			if (cookieEnd == -1) {
				cookieEnd = document.cookie.length;
			}
			
			return unescape(document.cookie.substring(cookieStart + (name.length + 1), cookieEnd));
		}
	}
	
	return false;
}

/**
 * Utility handler for AJAX forms.
 */

function ajax_submit_handler(e){
	e.preventDefault();
	var self = $(e.target).closest("form");
	
	$(self).find(".form_error").removeClass("form_error");
	$(self).find(".msg_error").hide().html("");
	
	var valid = true;

	$(self).find("input[required='true'], textarea[required='true']").each(function () {
		if (!$(this).val()) {
			$(this).parent().addClass("form_error");
			valid = false;
		}
	});

	if (!valid) return false;
	
	if (self.attr("validate")) {
		var func = eval(self.attr("validate"));

		if (!func(self)) {
			return;
		}
	}

	$(self).find("input[type='submit']").addClass("btn_working").attr("disabled", "disabled");

	if (self.attr("method").toLowerCase() == "post") {
		var handler = $.post;
	}
	else {
		var handler = $.get;
	}

	handler(self.attr("action"), self.serializeArray(), function (data) {
		$(self).find(".btn_working").removeClass("btn_working").removeAttr("disabled");

		if ("field" in data) {
			$(self).find("input[name="+data.field+"], textarea[name="+data.field+"]").each(function (e) {
				$(this).parent().addClass("form_error");
			});
		}
		
		if ("status" in data && !data.status && data.msg) {
			form_error(self, data.msg);
		}
		else {
			var callback = self.attr("callback");
			var func = eval(callback);

			if (typeof func != 'undefined') {
				func(data, self);
			}
		}
	}, "json");
}

$(document).ready(function () { 
	$(".ajax-form").on("submit", ajax_submit_handler).find("input[type=submit]").on("click", ajax_submit_handler);
});

function form_error(form, msg) {
	form.find(".msg_error").remove();
	
	var error = $("<p />");
	error.addClass("msg_error");
	error.html(msg);
	
	form.prepend(error);
}

function hide_locale_picker() {
	$("#locale-picker").hide();
	
	$("body").off("click", hide_locale_picker);
}

function hide_mini_popups() {
	$(".mini-popup-menu").hide();
	
	$("body").off("click", hide_mini_popups);
}

$(document).ready(function () {
	$("#launch-locale-picker").click(function (e) {
		e.preventDefault();
		e.stopPropagation();
		
		if (!$("#locale-picker").is(":visible")) {
			$("#locale-picker").show();
			
			$("body").click(hide_locale_picker);
		}
	});
	
	$(".launch-mini-popup-menu").on(function (e) {
		e.preventDefault();
		e.stopPropagation();
		
		if (!$(this).find(".mini-popup-menu").is(":visible")) {
			$(this).find(".mini-popup-menu").show();
			
			$("body").on("click", hide_mini_popups);
		}
	});
});