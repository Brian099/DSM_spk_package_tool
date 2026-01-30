#!/bin/bash

source /pkgscripts-ng/include/pkg_util.sh
# 包信息
package="WOL"
displayname="WOL"
version="1.0-0000"
dsmappname="com.wol.app"
# 开发信息
maintainer="Giraff"
maintainer_url="https://giraff.fun/"
# 发布者信息
distributor="Giraff"
distributor_url="https://giraff.fun/"
# 程序介绍
description="一个简易的基于php的局域网设备唤醒工具。"
# 软件入口
adminprotocol="http"
adminport="25091"
adminurl=""
# 系统要求
install_dep_packages="WebStation>=3.0.0-0309:PHP8.0>=8.0.17-0101"
install_provide_packages="WEBSTATION_SERVICE"
os_min_ver="7.0-40000"
## 选项
thirdparty="yes"
arch="x86_64"
startable="yes"
dsmuidir="ui"
# 重启服务
instuninst_restart_services="nginx.service"
startstop_restart_services="nginx.service"
# 自动更新
silent_upgrade="yes"
# 更新日志
changelog="首次发布。"

[ "$(caller)" != "0 NULL" ] && return 0

pkg_dump_info
