<VirtualHost 127.0.0.80:UID>

    # FQDN principal
    ServerName SERVERNAME
    #ServerAlias SERVERNAME

    # Repertoire principal
    DocumentRoot HOME_DIR/XXX/www/

    # Propriete du repertoire
    <Directory HOME_DIR/XXX/www/>
        #Options +Indexes +SymLinksIfOwnerMatch
        Options +SymLinksIfOwnerMatch
        AllowOverride AuthConfig Limit FileInfo
        Require all granted
    </Directory>

    # CGI
    ScriptAlias /cgi-RANDOM /usr/lib/cgi-bin/
    <Directory /usr/lib/cgi-bin/>
        Options +ExecCGI -MultiViews
        AllowOverride None

        AuthName "Restricted"
        AuthUserFile HOME_DIR/XXX/.htpasswd
        AuthType Basic
        Require valid-user

        #Include /etc/apache2/ipaddr_whitelist.conf
    </Directory>

    # LOG
    CustomLog HOME_DIR/XXX/log/access.log combined
    ErrorLog  HOME_DIR/XXX/log/error.log

    # AWSTATS
    SetEnv AWSTATS_FORCE_CONFIG XXX

    # For Wordpress and other PHP apps that wants to do HTTPS redirection themselves
    SetEnvIf X-Forwarded-Proto https HTTPS=on

    # PHP
