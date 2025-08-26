server {
    listen 80;
    listen [::]:80;
# BEGIN EVOADMIN LISTEN 443
#    listen 443 ssl;
#    listen [::]:443 ssl;
# END EVOADMIN LISTEN 443
#    if ($scheme = http) {
#        return 301 https://$server_name$request_uri;
#    }
    include /etc/nginx/snippets/letsencrypt.conf;
    include /etc/nginx/ssl/LOGIN.con[f]

    server_name SERVERNAME;

    root /home/LOGIN/www;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/home/LOGIN/php-fpmPHPVERSION.sock;
        include snippets/fastcgi-php.conf;
    }

    access_log /var/log/access.log;
    access_log /var/log/LOGIN.access.log;
    error_log  /var/log/LOGIN.error.log;
}
