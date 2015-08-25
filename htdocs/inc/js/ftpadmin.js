
// vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2

function fill_directory_field(value) {
	if(document.forms['addftp'].path.value == '') {
		document.forms['addftp'].path.value = value;
	}
}

function check_form_ftp_add() {
	field_passwd = document.getElementById('passwd').value;
	if(field_passwd.length < 6) {
		alert('Le mot de passe doit contenir au moins 6 caractères ');
		return false;
	}
	return true;
}

document.observe("dom:loaded", function() {
    document.getElementById('login').onblur = function() {
        path = document.getElementById('path');
        if(!path.value) {
            path.value = document.getElementById('login').value;
        }
    }
});

