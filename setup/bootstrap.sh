# To bootstrap a new minimal install server, type: yum -y install wget
# wget -N http://titan.l3office.net/bootstrap.sh
# sh bootstrap.sh

ARCH="`lscpu | grep -i architecture | cut -f2 -d ':' | xargs`"
if [ $ARCH = "i686" ]; then
	EPEL_ARCH="i386"
else
	EPEL_ARCH="$ARCH"
fi
rpm -Uvh "http://download.fedoraproject.org/pub/epel/6/$EPEL_ARCH/epel-release-6-8.noarch.rpm"
rpm -Uvh "http://pkgs.repoforge.org/rpmforge-release/rpmforge-release-0.5.2-2.el6.rf.$ARCH.rpm"

yum -y groupinstall "Additional Development" Base "Console internet tools" "Development tools" "PHP Support" "MySQL Database client"
yum -y install python python-setuptools
yum -y install gcc git make nasm pkgconfig wget zlib-devel yasm php-devel php-mysql #ruby ruby-devel rubygems

easy_install pip
pip install -i http://f.pypi.python.org/simple ipython

service ntpdate start
chkconfig ntpdate on

echo 0 > /selinux/enforce
sed -i 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/selinux/config
