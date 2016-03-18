##################################################
# Generated by phansible.com
##################################################

Vagrant.configure("2") do |config|

    config.vm.provider :virtualbox do |v|
        v.name = "bewelcome"
        v.customize [
            "modifyvm", :id,
            "--name", "bewelcome",
            "--memory", 1024,
            "--natdnshostresolver1", "on",
            "--cpus", 1,
        ]
    end

    config.vm.box = "debian/jessie64"
    config.vm.box_version = "8.2.1"

    config.vm.network :private_network, ip: "192.168.33.10"
    config.vm.network "forwarded_port", guest: 80, host: 8080
    config.ssh.forward_agent = true
    # config.vm.hostname = "bewelcome"

    config.vm.provision :shell, path: "ansible/windows.sh", args: ["dev"]

    config.vm.synced_folder "./", "/vagrant", :group=>"www-data", :mount_options=>["dmode=775","fmode=665"]
end
