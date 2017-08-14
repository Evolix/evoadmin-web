server {
        server_name www.DOMAIN DOMAIN;

        listen   0.0.0.0:80;
        listen   [::]:80;
#        listen   0.0.0.0:443 ssl http2;
#        listen   [::]:443 ssl http2;
#
#        # Redirect HTTP to HTTPS
#        if ( $scheme = http )  {
#                return      301 https://$server_name$request_uri;
#        }
#
#        include /etc/nginx/ssl/LOGIN.conf;

        # Redirect alias to main server_name
        if ($http_host != "www.DOMAIN") {
                return      301 $scheme://www.DOMAIN$request_uri;
        }

        access_log  /home/LOGIN/log/access.log;
        error_log  /home/LOGIN/log/error.log;

        root   /home/LOGIN/www;
        index  index.html index.php;

        # Set X-Forwarded-For, when you use reverse proxy such as Varnish.
        #set_real_ip_from 127.0.0.1;
        #real_ip_header X-Forwarded-For;

        location / {
                try_files $uri $uri/ /index.php?$args;
                # Symphony
                #try_files $uri /app.php$is_args$args;
        }

        location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass   unix:/var/run/php-fpm-LOGIN.sock;
        }
}
