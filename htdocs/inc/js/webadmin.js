
/**
 * Javascript pour la gestion webadmin
 *
 * Copyright (c) 2009 Evolix - Tous droits reserves
 *
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2 
 *
 * @author Thomas Martin <tmartin@evolix.fr>
 * @version 1.0
 */

function switch_disabled(name) {
    element = document.getElementById(name);
    state = element.disabled;
    if(state == true) {
        element.disabled = false;
    } else {
        element.disabled = true;
    }
}

document.observe("dom:loaded", function() {
    if (document.getElementById('vhost-delete-db') != null) {
        document.getElementById('vhost-delete-db').onclick = function() {
            console.log("clicked box");
            switch_disabled('vhost-dbname');
        }
    }
    document.getElementById('password_random').onclick = function() {
        switch_disabled('password');
    }
    document.getElementById('mysql_db').onclick = function() {
        switch_disabled('mysql_dbname');
        switch_disabled('mysql_password_random');
        if(this.checked) {
            
            /* On préremplit le champ mysql_dbname avec la valeur de username,
               mais seulement s'il est vidé au préalable */
            mysql_dbname = document.getElementById('mysql_dbname');
            if(!mysql_dbname.value) {
                mysql_dbname.value = document.getElementById('username').value;
            }

            if(document.getElementById('mysql_password_random').checked) {
                document.getElementById('mysql_password').disabled = true;
            }
        } else {
            document.getElementById('mysql_password').disabled = true;
        }
    }
    document.getElementById('mysql_password_random').onclick = function() {
        switch_disabled('mysql_password');
    }
} );

