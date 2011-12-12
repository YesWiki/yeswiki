$(document).ready(function() {
	$("body").prepend($("#signin-div, #user-form"));
	$("a.login-link").overlay({
		mask: '#999',
		onLoad: function() {
			$("#signin-div form.login-form input.login-input:first").focus();
		}
	});
	$("a.login-user-link").overlay({
		mask: '#999',
		onLoad: function() {}
	});
});
