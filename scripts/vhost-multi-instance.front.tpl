<VirtualHost *:80 *:443>
    ServerName SERVERNAME
    #ServerAlias SERVERNAME

    # SSL
    # Apache < 2.4.30 (Jessie, Stretch) va générer une erreur si le fichier
    # désigné sans regex n'existe pas. On contourne ça avec [f] à place de f
    IncludeOptional /etc/apache2-front/ssl/XXX.con[f]

    <IfModule mod_security2.c>
        SecRuleEngine Off
    </IfModule>

    CustomLog /var/log/apache2-front/access.log vhost_combined
    ErrorLog /var/log/apache2-front/error.log

    # REWRITE
    UseCanonicalName On
    RewriteEngine On
    RewriteCond %{HTTP_HOST} !^SERVERNAME$
    RewriteRule ^/(.*) %{REQUEST_SCHEME}://%{SERVER_NAME}/$1 [L,R]

    RequestHeader set "X-Forwarded-Proto" expr=%{REQUEST_SCHEME}
    RequestHeader set "X-Forwarded-SSL" expr=%{HTTPS}
    ProxyPreserveHost On
    ProxyPass / "http://127.0.0.80:UID/"
    ProxyPassReverse / http://127.0.0.80:UID/

    <Proxy "http://127.0.0.80:UID/">
    </Proxy>
</VirtualHost>
