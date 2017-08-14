[SED_LOGIN]
user = SED_LOGIN
group = SED_LOGIN

listen = /var/run/php-fpm-SED_LOGIN.sock
listen.mode = 0660
listen.owner = SED_LOGIN
listen.group = SED_LOGIN

pm = ondemand
pm.max_children = 10
pm.process_idle_timeout = 10s
pm.status_path = SED_STATUS

php_admin_value[error_log] = /home/SED_LOGIN/phplog/php.log
php_admin_value[post_max_size] = 50M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[max_execution_time] = 600
