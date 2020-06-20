#!/bin/bash

alias=rre4

. /srv/http/bash/addons-functions.sh

[[ ! -s /boot/cmdline.txt ]] && echo 'root=PARTUUID=6cf48b40-02 rw rootwait selinux=0 plymouth.enable=0 smsc95xx.turbo_mode=N dwc_otg.lpm_enable=0 elevator=noop fsck.repair=yes isolcpus=3 console=tty3 quiet loglevel=0 logo.nologo vt.global_cursor_default=0' > /boot/cmdline.txt

installstart $@

getinstallzip

installfinish $@

restartlocalbrowser
