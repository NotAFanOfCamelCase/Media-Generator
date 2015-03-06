#!/bin/sh

# Dependency Build Bot
# version 1.0.0
# By Brett Wilson <wilsonbrett85@gmail.com>

# Dependency Versions:
# libjpeg-turbo: svn managed
# lame: yum managed
# libmad: yum managed
# gdata: static-version tar download (1.12.3)
# imagemagick: variable-version tar download
# x264: git managed
# ffmpeg: git managed
# sox: git managed

# Build Process for Git Projects:
# 1. check_sourcedir: Check for presence of source directories in /usr/local/src
# 2. check_repo: Check git commits, make sure we have the latest source
# 3. build_x264|build_ffmpeg|build_sox: Proceed to clean, make and install

# TODO:
# 1. Work on image magick build process: separate lockfiles for php extension, differentiate between checks and build function
# 2. Restructure checks to take advantage of function return for less nested conditional statements
# 3. Check that directory is there before distclean
# 4. Restructure libmad build
# 5. Imagemagick module not properly detecting source folder

# Expects arguments: name, operation_code
# Returns 0 if directory exists, 1 if it does not
function check_sourcedir {

	local MOD="check_sourcedir"
	local LIB_NAME="$1"
	local OPTION=$2
	
	begin_module $MOD
	
	# All operations disabled for this module
	if [ $OPTION = 0 ]; then
		echo "$warning All operations for $LIB_NAME excluded"
		return
	fi
	
	# Proceed to check for source directories

	if [ $LIB_NAME = "x264" ]; then
		local RREPO_PATH="$RREPO_X264"
	elif [ $LIB_NAME = "ffmpeg" ]; then
		local RREPO_PATH="$RREPO_FFMPEG"
	elif [ $LIB_NAME = "sox" ]; then
		local RREPO_PATH="$RREPO_SOX"
	else
		echo "$fatal Invalid name '$LIB_NAME' passed to check_repo"
		exit 1
	fi
	
	# Source directory is present, proceed to check git commit versions
	if [ -d $SRCPATH_BASE/$LIB_NAME ]; then
		echo "$info $LIB_NAME source directory is present."
		check_repo $LIB_NAME $OPTION
		
	# Source directory is missing, proceed to clone repository and build it
	else
		echo "$warning $LIB_NAME source directory is not present."
		echo "$info Git-Cloning $LIB_NAME"
		cd $SRCPATH_BASE
		
		git clone $RREPO_PATH $LIB_NAME >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "git-clone"; fi
		
		eval build_$LIB_NAME
	fi

}


# Expects arguments: name, operation_option
# Returns 0 if local repo is up to date, 1 if it is out of date	
function check_repo {

	local LIB_NAME="$1"
	local MOD="check_repo($LIB_NAME)"
	local OPTION=$2
	
	begin_module $MOD
	
	echo "$info Checking $LIB_NAME commits..."

	if [ $LIB_NAME = "x264" ]; then
		local RREPO_PATH="$RREPO_X264"
		local EXE_PATH="/usr/local/bin/x264"
	elif [ $LIB_NAME = "ffmpeg" ]; then
		local RREPO_PATH="$RREPO_FFMPEG"
		local EXE_PATH="/usr/local/bin/ffmpeg"
	elif [ $LIB_NAME = "sox" ]; then
		local RREPO_PATH="$RREPO_SOX"
		local EXE_PATH="/usr/local/bin/sox"
	else
		echo "$fatal Invalid name '$LIB_NAME' passed to check_repo"
		exit 1
	fi
	
	cd $SRCPATH_BASE/$LIB_NAME
	
	# Get local/remote git revisions
	REV_LOCAL="`git rev-list --max-count=1 HEAD | tr -d ' '`"
	REV_REMOTE="`git ls-remote $RREPO_PATH HEAD | cut -f1 | tr -d ' '`"
	
	echo "$info $LIB_NAME commits: $yellow Local: $reset $REV_LOCAL $yellow Remote: $reset $REV_REMOTE"
	
	# Git revisions match. Check for missing executable, lockfile or force rebuild option.
	if [ "$REV_LOCAL" = "$REV_REMOTE" ]; then
		echo "$info $LIB_NAME local repo is up-to-date."
		
		# Check for missing executable, force rebuild or lockfile
		if [ ! -f $EXE_PATH ]; then
			echo "$warning $LIB_NAME executable missing. Force rebuild triggered."
			eval build_$LIB_NAME
		elif [ -f $SRCPATH_BASE/lock_$LIB_NAME ]; then
			echo "$warning Last $LIB_NAME build was interrupted. Force rebuild triggered."
			eval build_$LIB_NAME
		elif [ $OPTION = 2 ]; then
			echo "$warning Force $LIB_NAME rebuild triggered."
			eval build_$LIB_NAME
		fi

	# Git revisions do not match. Proceed to git-pull and rebuild.
	else
		echo "$warning $LIB_NAME local repo is out-of-date."
		echo "$info Git-Pulling $LIB_NAME"
		
		git pull >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "git-pull"; fi
		
		eval build_$LIB_NAME
	fi
	
}


function build_x264 {
	
	local MOD="build_x264"
	local LIB_NAME="x264"
	
	begin_module $MOD
	
	# Create lockfile
	touch $SRCPATH_BASE/lock_x264

	# Build and install x264
	if [ ! -d $SRCPATH_X264 ]; then
		echo "$fatal x264 source folder does not exist"
		exit 1
	fi
			
	# Don't know why we have to do this!
	echo "$info Cleaning $LIB_NAME libs from /usr/local/lib..."
	cd /usr/local/lib
	rm -f libavcodec.a libavdevice.a libavfilter.a libavformat.a libavutil.a libpostproc.a libswresample.a libswscale.a
	
	cd $SRCPATH_X264
	
	# Clean build dirs
	echo "$info Cleaning $LIB_NAME..."
	make distclean >> $PATH_BUILDLOG 2>&1 
	#if [ $? -ne 0 ]; then fatal_error "$MOD" "clean build dir"; fi
	
	# Configure x264
	echo "$info Configuring $LIB_NAME..."
	./configure --enable-static --enable-shared >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure"; fi

	# Make and install x264
	echo "$info Compiling and Installing $LIB_NAME..."
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install"; fi
	
	# Configure dynamic bindings
	ldconfig
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure dynamic bindings (ldconfig)"; fi

	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_x264
	
	echo "$success $LIB_NAME Built and Installed"
}


function build_ffmpeg {

	local MOD="build_ffmpeg"
	local LIB_NAME="ffmpeg"
	
	begin_module $MOD
	
	# Create lockfile
	touch $SRCPATH_BASE/lock_ffmpeg

	# Build and install ffmpeg
	if [ ! -d $SRCPATH_FFMPEG ]; then
		echo "$fatal ffmpeg source folder does not exist."
		exit 1
	fi

	cd $SRCPATH_FFMPEG
	
	# Clean build dirs
	echo "$info Cleaning $LIB_NAME..."
	make distclean >> $PATH_BUILDLOG 2>&1 
	#if [ $? -ne 0 ]; then fatal_error "$MOD" "clean build dir"; fi
	
	# Configure ffmpeg
	echo "$info Configuring $LIB_NAME..."
	./configure --enable-gpl --enable-libmp3lame --enable-nonfree --enable-version3 --enable-libx264 >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure"; fi
	
	# Compile and install ffmpeg
	echo "$info Compiling and Installing $LIB_NAME..."
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install"; fi

	echo "/usr/local/lib" > /etc/ld.so.conf.d/custom-libs.conf
	
	# Configure dynamic bindings
	ldconfig
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure dynamic bindings (ldconfig)"; fi

	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_ffmpeg
	
	echo "$success $LIB_NAME Built and Installed"
}


function build_sox {

	local MOD="build_sox"
	local LIB_NAME="sox"
	
	begin_module $MOD
	
	# Create lockfile
	touch $SRCPATH_BASE/lock_sox

	if [ ! -d $SRCPATH_SOX ]; then echo "$fatal Sox source folder does not exist"; exit 1; fi
	
	cd $SRCPATH_SOX
	
	# Clean build dirs
	echo "$info Cleaning $LIB_NAME..."
	make distclean >> $PATH_BUILDLOG 2>&1 
	#if [ $? -ne 0 ]; then fatal_error "$MOD" "clean build dir"; fi
	
	# Configure sox
	echo "$info Configuring $LIB_NAME..."
	if [ ! -f configure ]; then
		./release.sh >> $PATH_BUILDLOG 2>&1 
	else
		./configure >> $PATH_BUILDLOG 2>&1 
	fi
	if [ $? -ne 0 ]; then 
		# Check for some stupid error first
		if `tail -n 3 $PATH_BUILDLOG | grep -iq "sox--macosx.zip files not found"`; then
			echo "$warning $LIB_NAME dumb build error encountered. Restarting build to fix it."
			build_sox
		else
			fatal_error "$MOD" "make and install"
		fi
	else
	
		# Compile and install sox
		echo "$info Compiling and Installing $LIB_NAME..."
		(make && make install) >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install"; fi
		
		# Configure dynamic bindings
		ldconfig
		if [ $? -ne 0 ]; then fatal_error "$MOD" "configure dynamic bindings (ldconfig)"; fi
		
		# Remove lockfile
		rm -f $SRCPATH_BASE/lock_sox
		
		echo "$success $LIB_NAME Built and Installed"
	fi
}


# Expects 1 argument: operation option
function check_lame {

	local LIB_NAME="lame-3.99.5"
	local MOD="check_lame"
	local OPTION=$1
	
	begin_module $MOD
	
	# All operations disabled for this module
	if [ $OPTION = 0 ]; then
		echo "$warning All operations for $LIB_NAME excluded"
		return
	fi
	
	# Continue to intelligent check
	
	cd $SRCPATH_BASE
	
	if [ ! -d $LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to missing source directory"
		build_lame $OPTION
	elif [ $OPTION = 2 ]; then
		echo "$warning Build of $LIB_NAME triggered due to force-rebuild flag"
		build_lame $OPTION
	elif [ -f $SRCPATH_BASE/lock_$LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to lockfile (previous build interrupted)"
		build_lame $OPTION
	elif [ ! -f /usr/local/include/lame/lame.h ]; then
		echo "$warning Build of $LIB_NAME triggered due to missing header file /usr/local/include/lame/lame.h"
		build_lame $OPTION
	elif [ ! -f /usr/local/bin/lame ]; then
		echo "$warning Build of $LIB_NAME triggered due to missing executable at /usr/local/bin/lame"
		build_lame $OPTION
	else
		echo "$info $LIB_NAME Up to date"
	fi
	
}

# Expects 1 argument: operation_option
function build_lame {

	local LIB_NAME="lame-3.99.5"
	local LIB_ARCHIVE="$LIB_NAME.tar.gz"
	local LIB_URL="http://downloads.sourceforge.net/project/lame/lame/3.99/lame-3.99.5.tar.gz"
	local MOD="build_lame"
	local OPTION=$1
	
	begin_module $MOD

	# Build Libmad dependency
	#build_libmad_launcher
	
	cd $SRCPATH_BASE

	# Create lockfile
	touch $SRCPATH_BASE/lock_$LIB_NAME
	
	# Delete old
	if [ -d $LIB_NAME ]; then
		echo "$warning Uninstalling/deleting existing $LIB_NAME..."
		cd $LIB_NAME
		make uninstall >> $PATH_BUILDLOG 2>&1 
		cd $SRCPATH_BASE
		rm -rf $LIB_NAME
	fi
	
	echo "$info Downloading $LIB_NAME..."
	
	# Download, unzip, delete archive
	(wget -N -nv "$LIB_URL" && tar -xzf $LIB_ARCHIVE && rm -f $LIB_ARCHIVE) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "download/unzip/delete $LIB_NAME archive"; fi
	
	cd $LIB_NAME

	# Configure
	echo "$info Configuring $LIB_NAME..."
	./configure --enable-shared --enable-static --enable-nasm >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure $LIB_NAME"; fi
	
	# Compile and install
	echo "$info Compiling and Installing $LIB_NAME..."
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install $LIB_NAME"; fi
	
	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_$LIB_NAME
	
	echo "$success Success! $LIB_NAME Built and Installed"
	
}


# Expects 1 argument: operation_option
function check_gdata {

	local MOD="check_gdata"
	local LIB_NAME="ZendGdata-1.12.3"
	local LIB_BASEDIR="/usr/share"
	local OPTION=$1
	
	begin_module $MOD

	# All operations disabled for this module
	if [ $OPTION = 0 ]; then
		echo "$warning All operations for $LIB_NAME excluded"
		return
	fi
	
	# Continue to intelligent check
	cd $SRCPATH_BASE
	
	if [ ! -d $LIB_BASEDIR/$LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to missing installed files"
		install_gdata $OPTION
	elif [ $OPTION = 2 ]; then
		echo "$warning Build of $LIB_NAME triggered due to force-rebuild flag"
		install_gdata $OPTION
	else
		echo "$info $LIB_NAME Up to date"
	fi
	
}


# Expects 1 argument: operation_option
function install_gdata {

	local MOD="install_gdata"
	local LIB_NAME="ZendGdata-1.12.3"
	local LIB_ARCHIVE="$LIB_NAME.tar.gz"
	local LIB_URL="https://packages.zendframework.com/releases/ZendGdata-1.12.3/ZendGdata-1.12.3.tar.gz"
	local LIB_BASEDIR="/usr/share"
	local LIB_BASEDIR_ESC="\/usr\/share"
	local OPTION=$1
	
	begin_module $MOD
	
	# Remove old if exists
	
	if [ -d $LIB_BASEDIR/$LIB_NAME ]; then
		echo "$warning Uninstalling/deleting existing $LIB_NAME..."
		rm -rf $LIB_BASEDIR/$LIB_NAME
	fi

	cd $SRCPATH_BASE
	
	echo "$info Downloading $LIB_NAME..."
	
	# Download, unzip, delete archive
	(wget -N -nv "$LIB_URL" && tar -xzf $LIB_ARCHIVE && rm -f $LIB_ARCHIVE) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "download/unzip/delete $LIB_NAME archive"; fi
	
	mv $LIB_NAME $LIB_BASEDIR/
	echo "$success Zend Gdata library installed"
	
	echo "$info Checking php.ini include_path..."

	if [ -f $INI_PATH ]; then

		local NEW_ITEM="$LIB_BASEDIR/$LIB_NAME/library"
		local ITEM_ESCAPED="$LIB_BASEDIR_ESC\/$LIB_NAME\/library"

		
		# Modification already made
		if `grep -iq "$NEW_ITEM" $INI_PATH`; then
			echo "$success Php.ini include_path was previously patched"
		# Some path stuff already exists, append to it
		elif `grep -iq "^include_path \?= \?\".:" $INI_PATH`; then
			sed -i.bak "0,/include_path \?= \?\"\([^\"]*\)\"/ s//include_path = \"\1:$ITEM_ESCAPED\"/g" "$INI_PATH"
			echo "$success Patched php.ini include_path. Placed backup at $INI_PATH.bak."
		# Path line exists but has nothing in it
		elif `grep -iq "^include_path \?= \?\"\"" $INI_PATH`; then
			sed -i.bak "0,/include_path \?= \?\"\"/ s//include_path = \".:$ITEM_ESCAPED\"/g" "$INI_PATH"
			echo "$success Patched php.ini include_path. Placed backup at $INI_PATH.bak."
		# Only path line is commented - usually a default install is setup like this
		elif `grep -iq "^;include_path \?= \?\"" $INI_PATH`; then
			sed -i.bak "0,/;include_path \?= \?\".*/ s//include_path = \".:$ITEM_ESCAPED\"/g" "$INI_PATH"
			echo "$success Patched php.ini include_path. Placed backup at $INI_PATH.bak."
		# Couldn't find pre-existing path line. Just append a new one to the end of the file
		else
			/bin/cp -f $INI_PATH $INI_PATH.bak
			echo "include_path = \".:$NEW_ITEM\"" >> $INI_PATH
			echo "$success Appended php.ini include_path. Placed backup at $INI_PATH.bak."
		fi
		
	else 
		echo "$fatal Php.ini does not exist at /etc/php.ini"
		exit 1
	fi
		
}


# Expects arguments: lib_name, lib_archive, lib_url, operation_option
function check_libmad {

	local LIB_NAME="$1"
	local LIB_ARCHIVE="$2"
	local LIB_URL="$3"
	local OPTION=$4
	local MOD="check_libmad($LIB_NAME)"
	
	begin_module $MOD
	
	# Download & extract libid3tag-0.15.1b if it does not exist
	cd $SRCPATH_BASE
	
	# All operations disabled
	if [ $OPTION = 0 ]; then
		echo "$warning All operations for $LIB_NAME excluded"
		return
	fi
		
	# Continue to intelligent check
	
	if [ ! -d $LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to missing source directory"
		build_libmad $LIB_NAME $LIB_ARCHIVE "$LIB_URL" $OPTION
	elif [ $OPTION = 2 ]; then
		echo "$warning Build of $LIB_NAME triggered due to force-rebuild flag"
		build_libmad $LIB_NAME $LIB_ARCHIVE "$LIB_URL" $OPTION
	elif [ -f $SRCPATH_BASE/lock_$LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to lockfile (previous build interrupted)"
		build_libmad $LIB_NAME $LIB_ARCHIVE "$LIB_URL" $OPTION
	else
		echo "$info $LIB_NAME Up to date"
	fi

	
}


function build_libmad {

	local LIB_NAME="$1"
	local LIB_ARCHIVE="$2"
	local LIB_URL="$3"
	local OPTION=$4
	local MOD="build_libmad($LIB_NAME)"
	
	begin_module $MOD
	
	# Download & extract libid3tag-0.15.1b if it does not exist
	cd $SRCPATH_BASE
		
	# Create lockfile
	touch $SRCPATH_BASE/lock_$LIB_NAME
	
	# Delete old
	if [ -d $LIB_NAME ]; then
		echo "$warning Uninstalling/deleting existing $LIB_NAME..."
		cd $LIB_NAME
		make uninstall >> $PATH_BUILDLOG 2>&1 
		cd $SRCPATH_BASE
		rm -rf $LIB_NAME
	fi	
	
	echo "$info Downloading $LIB_NAME..."
	
	# Download, unzip, delete archive
	(wget -N -nv "$LIB_URL" && tar -xzf $LIB_ARCHIVE && rm -f $LIB_ARCHIVE) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "download/unzip/delete $LIB_NAME archive"; fi
	
	cd $LIB_NAME
	
	# Fix -fforce-mem (deprecated build option)
	find . -type f -exec sed -i "s/-fforce-mem//g" {} \;
	if [ $? -ne 0 ]; then fatal_error "$MOD" "find/replace -fforce-mem for $LIB_NAME"; fi
	
	echo "$info Configuring $LIB_NAME..."
	
	# Configure
	./configure LDFLAGS=-L/usr/local/lib CPPFLAGS=-I/usr/local/include >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure $LIB_NAME"; fi
	
	echo "$info Compiling and Installing $LIB_NAME..."
	
	# Make and install
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install $LIB_NAME"; fi
	
	# Configure dynamic bindings
	ldconfig
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure dynamic bindings (ldconfig)"; fi
	
	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_$LIB_NAME
	
	echo "$success $LIB_NAME Built and Installed"

}

# Required for sox mp3 support
# Expects 1 argument: operation_option
function build_libmad_launcher {

	local MOD="build_libmad_launcher"
	local OPTION=$1
	
	# Download & extract libid3tag-0.15.1b if it does not exist
	local LIB_NAME="libid3tag-0.15.1b"
	local LIB_ARCHIVE="$LIB_NAME.tar.gz"
	local LIB_URL="http://downloads.sourceforge.net/project/mad/libid3tag/0.15.1b/libid3tag-0.15.1b.tar.gz"
	check_libmad $LIB_NAME $LIB_ARCHIVE "$LIB_URL" $OPTION
	
	# Download & extract libmad-0.15.1b if it does not exist
	local LIB_NAME="libmad-0.15.1b"
	local LIB_ARCHIVE="$LIB_NAME.tar.gz"
	local LIB_URL="http://downloads.sourceforge.net/project/mad/libmad/0.15.1b/libmad-0.15.1b.tar.gz"
	check_libmad $LIB_NAME $LIB_ARCHIVE "$LIB_URL" $OPTION
	
	# Download & extract madplay-0.15.2b if it does not exist
	local LIB_NAME="madplay-0.15.2b"
	local LIB_ARCHIVE="$LIB_NAME.tar.gz"
	local LIB_URL="http://downloads.sourceforge.net/project/mad/madplay/0.15.2b/madplay-0.15.2b.tar.gz"
	check_libmad $LIB_NAME $LIB_ARCHIVE "$LIB_URL" $OPTION

}

# Expects 1 arg: operation_option
function check_imagemagick {
	
	local MOD="check_imagemagick"
	local LIB_NAME="ImageMagick"
	local LIBPHP_NAME="imagick"
	local LIBPHP_ARCHIVE="imagick-3.1.0RC2.tgz"
	local LIB_ARCHIVE="$LIB_NAME.tar.gz"
	local LIB_URL="http://www.imagemagick.org/download/ImageMagick.tar.gz"
	local OPTION=$1
	
	begin_module $MOD
	
	# All operations disabled for this module
	if [ $OPTION = 0 ]; then
		echo "$warning All operations for $LIB_NAME excluded"
		return
	fi
	
	# Continue to intelligent check

	cd $SRCPATH_BASE
	
	if [ ! -d $LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to missing source directory"
		build_imagemagick $LIB_NAME $OPTION
	elif [ $OPTION = 2 ]; then
		echo "$warning Build of $LIB_NAME triggered due to force-rebuild flag"
		build_imagemagick $LIB_NAME $OPTION
	elif [ -f $SRCPATH_BASE/lock_$LIB_NAME ]; then
		echo "$warning Build of $LIB_NAME triggered due to lockfile (previous build interrupted)"
		build_imagemagick $LIB_NAME $OPTION
	else
	
		echo "$info Local $LIB_NAME source exists. Checking for updated version..."

		cd $SRCPATH_BASE/$LIB_NAME
		
		# Get local release version
		local LOCAL_RELEASE="`cat version.sh | grep -iE "PACKAGE_RELEASE" | head -n1 | cut -d= -f2 | tr -d "'| |\\""`"
		local LOCAL_VERSION="`cat version.sh | grep -iE "PACKAGE_VERSION" | head -n1 | cut -d= -f2 | tr -d "'| |\\""`"
	
		#`# Create temp download dir and move into it
		local TMP_DLDIR="$SRCPATH_BASE/temp"
		[[ -d $TMP_DLDIR ]] && rm -rf $TMP_DLDIR
		mkdir $TMP_DLDIR
		cd $TMP_DLDIR
		
		echo "$info Downloading $LIB_NAME..."
		
		# Download, unzip archive
		if [ ! -d $LIB_NAME ]; then
			mkdir $LIB_NAME
		fi
		(wget -N -nv "$LIB_URL" && tar -xzf $LIB_ARCHIVE -C $LIB_NAME --strip-components=1) >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "download/unzip $LIB_NAME archive"; fi

		# Get path to downloaded Image Magick folder in temp dir and move into it
		local TMP_SRC=$TMP_DLDIR/$LIB_NAME
		cd $TMP_SRC
		
		# Get Remote Release version
		local REMOTE_RELEASE="`cat version.sh | grep -iE "PACKAGE_RELEASE" | head -n1 | cut -d= -f2 | tr -d "'| |\\""`"
		local REMOTE_VERSION="`cat version.sh | grep -iE "PACKAGE_VERSION" | head -n1 | cut -d= -f2 | tr -d "'| |\\""`"
		
		#`# Display versions
		echo "$info $LIB_NAME version: $yellow Current: $reset $LOCAL_VERSION.$LOCAL_RELEASE $yellow Downloaded: $reset $REMOTE_VERSION.$REMOTE_RELEASE"
		
		# If versions don't match, replace the current source with the new source and rebuild
		if [ "$LOCAL_RELEASE" != "$REMOTE_RELEASE" ] || [ "$LOCAL_VERSION" != "$REMOTE_VERSION" ]; then
		
			echo "$warning $LIB_NAME scheduled for rebuild due to outdated version"
		
			cd $SRCPATH_BASE/$LIB_NAME

			make uninstall >> $PATH_BUILDLOG 2>&1

			# Remove ImageMagick source dir
			rm -rf $SRCPATH_BASE/$LIB_NAME
			
			# Move new extracted source to source base dir
			mv $TMP_SRC $SRCPATH_BASE/
	
			# Configure, compile and install
			build_imagemagick $LIB_NAME $OPTION
		
		else
			echo "$info $LIB_NAME Up to date"
		fi
		
		# Remove temp dl dir
		rm -rf $TMP_DLDIR
	
	fi
	
	cd $SRCPATH_BASE
	
	# Check imagick php extension 
	if [ ! -d $SRCPATH_BASE/$LIBPHP_NAME ]; then
		echo "$warning Build of $LIBPHP_NAME PHP Lib triggered due to missing source directory"
		build_imagick $LIBPHP_NAME $OPTION
	elif [ $OPTION = 2 ]; then
		echo "$warning Build of $LIBPHP_NAME PHP Lib triggered due to force-rebuild flag"
		build_imagick $LIBPHP_NAME $OPTION
	elif [ -f $SRCPATH_BASE/lock_$LIBPHP_NAME ]; then
		echo "$warning Build of $LIBPHP_NAME PHP Lib triggered due to lockfile (previous build interrupted)"
		build_imagick $LIBPHP_NAME $OPTION
	else
		echo "$info $LIBPHP_NAME PHP Lib Up to date"
	fi

}

# Expects one param: lib_name, operation_option
function build_imagemagick {

	local MOD="build_imagemagick"
	local LIB_NAME="$1"
	local LIBPHP_NAME="imagick"
	local LIBPHP_ARCHIVE="imagick-3.1.0RC2.tgz"
	local LIBPHP_URL="http://pecl.php.net/get/$LIBPHP_ARCHIVE"
	local OPTION="$2"
	
	begin_module $MOD
	
	echo "$info Removing any previously installed versions of $LIB_NAME..."
	
	# Create lockfile
	touch $SRCPATH_BASE/lock_$LIB_NAME
	
	
	cd $SRCPATH_BASE
		
	echo "$info Downloading $LIB_NAME..."
	
	# Download, unzip, delete archive
	if [ ! -d $LIB_NAME ]; then
		mkdir $LIB_NAME
	fi
	(wget -N -nv "$LIB_URL" && tar -xzf $LIB_ARCHIVE -C $LIB_NAME --strip-components=1 && rm -f $LIB_ARCHIVE) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "download/unzip/delete $LIB_NAME archive"; fi

	# Change to ImageMagick source dir
	cd $SRCPATH_BASE/$LIB_NAME

	echo "$info Configuring $LIB_NAME..."
	
	# Configure ImageMagick
	./configure >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure"; fi
	
	echo "$info Compiling and Installing $LIB_NAME..."
	
	# Make and install ImageMagick
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install"; fi
	
	# Configure dynamic bindings
	ldconfig /usr/local/lib
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure dynamic bindings (ldconfig)"; fi
	
	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_$LIB_NAME
	
	echo "$success $LIB_NAME Built and Installed"
	
	

}

function build_imagick {

	local MOD="build_imagick"
	local LIB_NAME="$1"
	local LIBPHP_NAME="imagick"
	local LIBPHP_ARCHIVE="imagick-3.1.0RC2.tgz"
	local LIBPHP_URL="http://pecl.php.net/get/$LIBPHP_ARCHIVE"
	local OPTION="$2"
	
	begin_module $MOD

	# Create lockfile
	touch $SRCPATH_BASE/lock_$LIBPHP_NAME
	
	cd $SRCPATH_BASE
	
	# Uninstall and delete php extension
	if [ -d $LIBPHP_NAME ]; then
		cd $LIBPHP_NAME
		pecl uninstall imagick >> $PATH_BUILDLOG 2>&1 
		make uninstall >> $PATH_BUILDLOG 2>&1 
		rm -rf $LIBPHP_NAME
	fi
	
	# Download and compile PECL/PHP extension
	echo "$info Downloading PECL Extension for $LIBPHP_NAME..."
	
	#printf "\n" | pecl install imagick
	#if [ $? -ne 0 ]; then fatal_error "$MOD" "PECL Install imagick"; fi
	
	cd $SRCPATH_BASE
	
	# Download, unzip, delete archive
	if [ ! -d $LIBPHP_NAME ]; then
		mkdir $LIBPHP_NAME
	fi
	(wget -N -nv "$LIBPHP_URL" && tar -xzf $LIBPHP_ARCHIVE -C $LIBPHP_NAME --strip-components=1 && rm -f $LIBPHP_ARCHIVE) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "download/unzip/delete $LIB_NAME php extension archive"; fi
	
	# Change to PHP extension extracted dir
	cd $LIBPHP_NAME
	if [ $? -ne 0 ]; then fatal_error "$MOD" "could not change to $LIBPHP_NAME php extension source dir"; fi
	
	# Some stuff that allows the php extension to actually build!
	export PKG_CONFIG_PATH="/usr/local/lib/pkgconfig"
	ln -s /usr/local/include/ImageMagick-6/wand /usr/local/include/wand
	ln -s /usr/local/include/ImageMagick-6/magick /usr/local/include/magick
	
	# Find/replace in build config file
	sed -i.bak "0,/if test -r \$WAND_DIR\/include\/ImageMagick\/wand\/MagickWand\.h; then/ s//if test -r \$WAND_DIR\/include\/ImageMagick-6\/wand\/MagickWand\.h; then/g" config.m4
	if [ $? -ne 0 ]; then fatal_error "$MOD" "find and replace path in imagick config.m4"; fi
	
	sed -i.bak "0,/found in \$WAND_DIR\/include\/ImageMagick\/wand\/MagickWand\.h/ s//found in \$WAND_DIR\/include\/ImageMagick-6\/wand\/MagickWand\.h/g" config.m4
	if [ $? -ne 0 ]; then fatal_error "$MOD" "find and replace path in imagick config.m4"; fi
	
	echo "$info PHPizing PECL Extension for $LIBPHP_NAME..."
	
	# Phpize
	phpize >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "phpize imagick"; fi
	
	echo "$info Configuring $LIBPHP_NAME..."
	
	# Configure imagick extension
	./configure >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure imagick php extension"; fi
	
	echo "$info Compiling and installing $LIBPHP_NAME..."
	
	# Make and install imagick extension
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install imagick php extension"; fi
	
	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_$LIBPHP_NAME

	# Check php.ini
	if `grep -iq "extension=imagick.so" $INI_PATH`; then
		echo "$info imagick.so already added to php.ini"
	else
		echo "extension=imagick.so" >> $INI_PATH
		echo "$info imagick.so appended to php.ini"
	fi
	
	echo "$success $LIBPHP_NAME Built and Installed"
	
}


# Expects one parameter: operation_option
function check_libjpeg {

	local MOD="check_libjpeg"
	local LIB_NAME="libjpeg-turbo"
	local LIB_URL="svn://svn.code.sf.net/p/libjpeg-turbo/code/branches/1.2.x"
	local OPTION=$1

	
	begin_module $MOD

	# All operations disabled for this module
	if [ $OPTION = 0 ]; then
		echo "$warning All operations for $LIB_NAME excluded"
		return
	fi
	
	# Get SVN remote revision
	REV_REMOTE="`svn info $LIB_URL | grep -i "revision" | cut -d':' -f2 | xargs`"
	
	# Source dir exists
	if [ -d $SRCPATH_BASE/$LIB_NAME ]; then
	
		echo "$info Checking $LIB_NAME revisions..."
		
		# Get local revision
		cd $SRCPATH_BASE/$LIB_NAME
		REV_LOCAL="`svn info | grep -i "revision" | cut -d':' -f2 | xargs`"
		echo "$info $LIB_NAME revisions: $yellow Local: $reset $REV_LOCAL $yellow Remote: $reset $REV_REMOTE"
		
		# Revisions are not matching, update and rebuild
		if [ "$REV_REMOTE" != "$REV_LOCAL" ]; then
			echo "$warning Build of $LIB_NAME triggered due to outdated source"
			build_libjpeg
		elif [ $OPTION = 2 ]; then
			echo "$warning Build of $LIB_NAME triggered due to force-rebuild flag"
			build_libjpeg
		elif [ -f $SRCPATH_BASE/lock_$LIB_NAME ]; then
			echo "$warning Build of $LIB_NAME triggered due to lockfile (previous build interrupted)"
			build_libjpeg
		# Source is up-to-date. Return from function.
		else
			echo "$info $LIB_NAME Up to date"
			return
		fi
	else
		echo "$warning Build of $LIB_NAME triggered due to missing source"
		build_libjpeg
	fi
	

}


# Expects one parameter: operation_option
function build_libjpeg {

	local MOD="build_libjpeg"
	local LIB_NAME="libjpeg-turbo"
	local LIB_URL="svn://svn.code.sf.net/p/libjpeg-turbo/code/branches/1.2.x"
	local OPTION=$1
	
	begin_module $MOD

	if [ ! -d $SRCPATH_BASE/$LIB_NAME ]; then
		echo "$info Downloading $LIB_NAME svn source..."
	
		cd $SRCPATH_BASE
		
		svn co $LIB_URL $LIB_NAME >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "download svn source"; fi
	
	else
	
		# Clean build dirs
		echo "$info Cleaning $LIB_NAME..."
		
		cd $SRCPATH_BASE/$LIB_NAME
		
		make distclean >> $PATH_BUILDLOG 2>&1 
		#if [ $? -ne 0 ]; then fatal_error "$MOD" "clean build dir"; fi
		
		echo "$info Updating $LIB_NAME svn source..."
		
		svn update >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "update local svn source"; fi
		
	fi
	

	cd $SRCPATH_BASE/$LIB_NAME
	
	# Create lockfile
	touch $SRCPATH_BASE/lock_$LIB_NAME
	
	# Generate configuration
	autoreconf -fiv >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "autoreconf"; fi
	
	# Configure 
	echo "$info Configuring $LIB_NAME..."
	./configure --enable-static --enable-shared --prefix=/usr/local >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure"; fi
	
	# Compile and install 
	echo "$info Compiling and Installing $LIB_NAME..."
	(make && make install) >> $PATH_BUILDLOG 2>&1 
	if [ $? -ne 0 ]; then fatal_error "$MOD" "make and install"; fi

	# Configure dynamic bindings
	ldconfig
	if [ $? -ne 0 ]; then fatal_error "$MOD" "configure dynamic bindings (ldconfig)"; fi

	# Remove lockfile
	rm -f $SRCPATH_BASE/lock_$LIB_NAME
	
	echo "$success $LIB_NAME Built and Installed"

}


function install_fonts {

	local MOD="install_fonts"
	begin_module $MOD
	
	cd $SRCPATH_BASE
	
	ARCH="`lscpu | grep -i architecture | cut -f2 -d ':' | xargs`"
	if [ $ARCH = "i386" ]; then
		ARCH="i686"
	fi
	
	if ! `convert -list font | grep -iq msttcorefonts`; then
	
		echo "$warning Microsoft fonts are missing"
		echo "$info Downloading microsoft fonts..."
		
		rpm --import http://packages.atrpms.net/RPM-GPG-KEY.atrpms >> $PATH_BUILDLOG 2>&1 
		#if [ $? -ne 0 ]; then fatal_error "$MOD" "import GPG key"; fi
		
		rpm -Uvh http://dl.atrpms.net/all/atrpms-repo-6-6.el6.$ARCH.rpm >> $PATH_BUILDLOG 2>&1 
		#if [ $? -ne 0 ]; then fatal_error "$MOD" "install atrpms repo"; fi
		
		yum -y install chkfontpath cabextract >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "yum install chkfontpath"; fi
		
		wget http://corefonts.sourceforge.net/msttcorefonts-2.0-1.spec >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "wget msttcorefonts"; fi
		
		echo "$info Building and installing fonts..."
		
		rpmbuild -bb msttcorefonts-2.0-1.spec >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "build rpm msttcorefonts"; fi
		
		rpm -ivh /root/rpmbuild/RPMS/noarch/msttcorefonts-2.0-1.noarch.rpm >> $PATH_BUILDLOG 2>&1 
		if [ $? -ne 0 ]; then fatal_error "$MOD" "install msttfonts rpm"; fi
		
		mkfontscale
		mkfontdir
		
		echo "$success Microsoft fonts installed"
		
	else
		echo "$info Microsoft fonts already installed"
	fi
}


# Expects two params: module_name, operation_step
function fatal_error {
	
	echo "[$red FATAL ERROR $reset] An external command failed. $yellow Module: $reset $1 $yellow Operation: $reset $2"; 
	echo "${yellow}Showing the last 10 lines of the build log: $reset"
	tail -n 10 $PATH_BUILDLOG
	exit 1;

}


# Expects 1 param: module_name
function begin_module {
	
	echo ""
	echo "${green}====  Starting Module: $yellow $1 $green ====$reset"; 

}

function echo_help {
	
	echo "
${yellow}Usage:    ${green} build-ytdeps.sh ${reset}
${yellow}Options:   ${blue}--include=${reset}all|x264|ffmpeg|sox|lame|imagick|gdata|libjpeg            (runs intelligent rebuild on value only)
           ${blue}--exclude=${reset}x264|ffmpeg|sox|lame|imagick|gdata|libjpeg                (runs intelligent rebuild on everything except value)
           ${blue}--force-rebuild=${reset}all|x264|ffmpeg|sox|lame|imagick|gdata|libjpeg      (forces rebuild of value)
			 
${red}If no options are specified, intelligent rebuild will be enabled for all modules.${reset}
	"
	exit 1
}

#######################################
## ENTRY POINT 
#######################################

# Git repos
RREPO_X264="git://git.videolan.org/x264.git"
RREPO_FFMPEG="git://source.ffmpeg.org/ffmpeg.git"
RREPO_SOX="git://sox.git.sourceforge.net/gitroot/sox/sox"

# Source dirs
SRCPATH_BASE="/usr/local/src"
SRCPATH_X264="$SRCPATH_BASE/x264"
SRCPATH_FFMPEG="$SRCPATH_BASE/ffmpeg"
SRCPATH_SOX="$SRCPATH_BASE/sox"

INI_PATH="/etc/php.ini"


# Alerts & Other
reset=$(tput sgr0)
red=$(tput setaf 1)
green=$(tput setaf 2)
yellow=$(tput setaf 3)
blue=$(tput setaf 6)
fail="[$red FAILED $reset]"
success="[$green OK $reset]"
warning="[$yellow WARNING $reset]"
info="[$blue INFO $reset]"

# Check OS
if [ "`cat /etc/*-release | head -n 1 | cut -d' ' -f1-3 | xargs`" != "CentOS release 6.3" ]; then
	echo "$warning This script is only tested on CentOS release 6.3. Please use with care."
fi

# All options enabled by default
# 0 = disable
# 1 = automatic check
# 2 = force rebuild


# All modules disabled by default, only those specified to include are run
if [ "`echo $* | grep -iE \"\--include=.+\"`" != "" ]; then
	OPTION_LIBJPEG=0
	OPTION_LAME=0
	OPTION_GDATA=0 
	OPTION_IMAGICK=0 
	OPTION_X264=0
	OPTION_FFMPEG=0
	OPTION_SOX=0
	OPTION_LIBMAD=0
else
	OPTION_LIBJPEG=1
	OPTION_LAME=1 
	OPTION_GDATA=1 
	OPTION_IMAGICK=1 
	OPTION_X264=1
	OPTION_FFMPEG=1
	OPTION_SOX=1
	OPTION_LIBMAD=1
fi

ARG_EMPTY=1

for var in "$@"; do
	case "$var" in
		"`echo $var | grep -iE \"\--include=.+\"`")
			REBUILD_OPTION="`echo \"$var\" | cut -f2 -d'=' | xargs`"
			ARG_EMPTY=0
			
			case "$REBUILD_OPTION" in
				"libjpeg") 	OPTION_LIBJPEG=1 ;;
				"lame") 	OPTION_LAME=1 ;;
				"gdata") 	OPTION_GDATA=1 ;;
				"imagick") 	OPTION_IMAGICK=1 ;;
				"x264") 	OPTION_X264=1 ;;
				"ffmpeg") 	OPTION_FFMPEG=1 ;;
				"sox") 		OPTION_SOX=1 ;;
				"libmad") 	OPTION_LIBMAD=1 ;;
				"all")
					OPTION_LIBJPEG=1
					OPTION_LAME=1
					OPTION_GDATA=1
					OPTION_IMAGICK=1
					OPTION_X264=1
					OPTION_FFMPEG=1
					OPTION_SOX=1
					OPTION_LIBMAD=1
				;;
				*) echo_help
			esac
			
		;;
		"`echo $var | grep -iE \"\--exclude=.+\"`")
			REBUILD_OPTION="`echo \"$var\" | cut -f2 -d'=' | xargs`"
			ARG_EMPTY=0
	
			case "$REBUILD_OPTION" in
				"libjpeg") 	OPTION_LIBJPEG=0 ;;
				"lame") 	OPTION_LAME=0 ;;
				"gdata") 	OPTION_GDATA=0 ;;
				"imagick") 	OPTION_IMAGICK=0 ;;
				"x264") 	OPTION_X264=0 ;;
				"ffmpeg") 	OPTION_FFMPEG=0 ;;
				"sox") 		OPTION_SOX=0 ;;
				"libmad") 	OPTION_LIBMAD=0 ;;
				*) echo_help
			esac

		;;
		"`echo $var | grep -iE \"\--force-rebuild=.+\"`")
			REBUILD_OPTION="`echo \"$var\" | cut -f2 -d'=' | xargs`"
			ARG_EMPTY=0
			
			case "$REBUILD_OPTION" in
				"libjpeg") 	OPTION_LIBJPEG=2 ;;
				"lame") 	OPTION_LAME=2 ;;
				"gdata") 	OPTION_GDATA=2 ;;
				"imagick") 	OPTION_IMAGICK=2 ;;
				"x264") 	OPTION_X264=2 ;;
				"ffmpeg") 	OPTION_FFMPEG=2 ;;
				"sox") 		OPTION_SOX=2 ;;
				"libmad") 	OPTION_LIBMAD=2 ;;
				"all")
					OPTION_LIBJPEG=2
					OPTION_LAME=2
					OPTION_GDATA=2
					OPTION_IMAGICK=2
					OPTION_X264=2
					OPTION_FFMPEG=2
					OPTION_SOX=2
					OPTION_LIBMAD=2
				;;
				*) echo_help
			esac
			
		;;
		"")
			ARG_EMPTY=0
		;;
	  *)
		echo_help
		exit 1
	esac
done

# No options selected
[[ $ARG_EMPTY = 1 ]] && echo_help


# Info banner
echo "$blue"
echo "****     Media Generator Dependency Build Bot     ****"
echo "****   By Brett Wilson <wilsonbrett85@gmail.com   ****"
echo "$reset"

DATE=`date +%Y-%m-%d-%H.%M`
PATH_BUILDLOG="/var/log/generator_build-$DATE.log"
touch $PATH_BUILDLOG
echo "$info Build info is redirected to a file. To watch live build, open a new terminal and tail -f $PATH_BUILDLOG"

# Remove existing packages
echo "$info Removing existing packages..."
yum -y erase ffmpeg x264 x264-devel lame lame-devel mplayer libxvid xvidcore xvidcore-devel libvorbis libvorbis-devel >> $PATH_BUILDLOG 2>&1 
if [ $? -ne 0 ]; then echo "$fatal Error removing old yum packages"; exit 1; fi

# Install yum deps
echo "$info Installing Yum Dependencies..."
yum -y install gcc git make nasm pkgconfig wget zlib-devel yasm php-devel libmad-devel lame lame-devel >> $PATH_BUILDLOG 2>&1 
if [ $? -ne 0 ]; then echo "$fatal Error installing yum deps"; exit 1; fi

# Run modules - option checks are included within modules

check_libjpeg $OPTION_LIBJPEG
#build_libmad_launcher $OPTION_LIBMAD
#check_lame $OPTION_LAME
check_gdata $OPTION_GDATA
check_imagemagick $OPTION_IMAGICK
check_sourcedir "x264" $OPTION_X264
check_sourcedir "ffmpeg" $OPTION_FFMPEG
check_sourcedir "sox" $OPTION_SOX
install_fonts

echo ""
echo "$success Success! All dependencies built and installed."