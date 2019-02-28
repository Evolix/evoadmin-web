# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant::DEFAULT_SERVER_URL.replace('https://vagrantcloud.com')

# Load ~/.VagrantFile if exist, permit local config provider
vagrantfile = File.join("#{Dir.home}", '.VagrantFile')
load File.expand_path(vagrantfile) if File.exists?(vagrantfile)

Vagrant.configure('2') do |config|
  config.vm.synced_folder "./", "/vagrant", type: "rsync", rsync__exclude: [ '.vagrant', '.git']

  config.vm.network "forwarded_port", guest: 80, host: 8080, auto_correct: true
  config.vm.network "forwarded_port", guest: 443, host: 8443, auto_correct: true

  # Prevent TTY Errors (copied from laravel/homestead: "homestead.rb" file)... By default this is "bash -l".
  config.ssh.shell = "bash -c 'BASH_ENV=/etc/profile exec bash'"

  $deps = <<SCRIPT
sed -e '/RewriteRule/ s/^#*/#/' -i /etc/apache2/sites-available/evoadmin.conf
sed -e '/RewriteCond/ s/^#*/#/' -i /etc/apache2/sites-available/evoadmin.conf
systemctl restart apache2

rm -rf /home/evoadmin/www/
ln -s /vagrant/ /home/evoadmin/www
SCRIPT

  config.vm.define :packweb do |node|
    node.vm.hostname = "evoadmin-web.example.com"
    node.vm.box = "evolix/packweb"

    node.vm.provision "deps", type: "shell", :inline => $deps

  end

end
