## PHPMGen

[![Latest Unstable Version](http://imgh.us/unstable_1.svg)](https://github.com/NotAFanOfCamelCase/PHPMGen.git)

> **Note:** Linux compatible only. This project has currently only been tested on CentOS 6.3. It is in its alpha release and therefore not ready for production. Use with caution.

The PHP Media Generator is an open source project that provides a means of composing and rendering basic animations. It implements the concept of a timeline,
keyframes and objects on stage.

## Setup

Run setup/build-deps.sh --include=all

## Dependencies

* libjpeg-turbo: svn managed
* lame: yum managed
* libmad: yum managed
* gdata: static-version tar download (1.12.3)
* imagemagick: variable-version tar download
* x264: git managed
* ffmpeg: git managed
* sox: git managed

## Authors
Designed by:
Carlos Granados [NotAFanOfCamelCase](https://github.com/NotAFanOfCamelCase)
<granados.carlos91@gmail.com>

Dependecy Builder by:
Brett Wilson [icanhazpython](https://github.com/icanhazpython)
<brettwilson85@gmail.com>

### License

PHPMGen is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)