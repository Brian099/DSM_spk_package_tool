#!/bin/bash

# 脚本名称:    buildSPK.sh
# 描述:    Synology离线套件SPK文件 环境部署及打包工具
# 收集&制作:         LaoK & Giraff
# 创建日期:        2025-07-31
# 版本:        1.0.2

# 官方打包环境配置说明
# https://help.synology.com/developer-guide/getting_started/prepare_environment.html
# 官方打包实例
# https://help.synology.com/developer-guide/getting_started/first_package.html
# 官方示例文件（套件模板）
# https://github.com/SynologyOpenSource/ExamplePackages


# 只要有一行命令失败，脚本就会中断，避免后续误操作。
set -e

#工作目录
workdir="/home/brian/myspktoolkit"
packageDir="web_package_example"
platform="apollolake"
DSMversion="7.2"

if [ ! -f "$workdir/install.lock" ]; then
	mkdir -p $workdir
	cd $workdir

	# 创建套件存放的目录
	mkdir -p $workdir/source

	# 下载示例文件
	if [ ! -d "$workdir/source/ExamplePackage" ] && [ ! -d "$workdir/source/web_package_example" ];then
		cd $workdir/source
		git clone https://github.com/SynologyOpenSource/ExamplePackages
		cp -rf "$workdir/source/ExamplePackages/ExamplePackage" "$workdir/source/ExamplePackage"
		cp -rf "$workdir/source/ExamplePackages/web_package_example" "$workdir/source/web_package_example"
		rm -rf "$workdir/source/ExamplePackages"
	fi

# 复制项目文件夹到source
# 目录结构
# /toolkit/
# ├── build_env/
# │   └── ds.${platform}-${DSMversion}/
# ├── pkgscripts-ng/
# │   ├── EnvDeploy
# │   └── PkgCreate.py
# └── source/
#     └──${packageDir}/ （套件的资源文件放在这里，以套件名称为目录名称）
#         ├── examplePkg.c
#         ├── INFO.sh
#         ├── Makefile
#         ├── PACKAGE_ICON.PNG
#         ├── PACKAGE_ICON_256.PNG
#         ├── scripts/
#         │   ├── postinst
#         │   ├── postuninst
#         │   ├── postupgrade
#         │   ├── postreplace
#         │   ├── preinst
#         │   ├── preuninst
#         │   ├── preupgrade
#         │   ├── prereplace
#         │   └── start-stop-status
#         └── SynoBuildConf/
#             ├── depends
#             ├── build
#             └── install

	echo "==== 检查并清理有问题的 DKMS 模块 ===="

	# 清理无效的 ashmem_xdroid 模块
	if dkms status | grep -q ashmem_xdroid; then
		echo "检测到 ashmem_xdroid 模块，尝试移除..."
		sudo dkms remove ashmem_xdroid/2.0 --all || true
	fi

	# 清理无效的 binder_xdroid 模块
	if dkms status | grep -q binder_xdroid; then
		echo "检测到 binder_xdroid 模块，尝试移除..."
		sudo dkms remove binder_xdroid/2.0 --all || true
	fi

	echo "==== 尝试修复未配置的系统包 ===="
	sudo apt --fix-broken install -y

	echo "==== 更新系统软件包 ===="
	sudo apt update

	echo "==== 安装常规工具依赖 ===="
	sudo apt install -y git cifs-utils python3 python3-pip npm

	echo "==== 安装构建和交叉编译工具 ===="
	sudo apt install -y debootstrap qemu-user-static binfmt-support build-essential jq

	echo "✅ 所有依赖项已成功安装完成！"

	# 修正npm镜像源，Deepin系统可能不需要修正
	sudo npm config set registry https://registry.npm.taobao.org

	# 获取编译工具
	if [ ! -d "$workdir/pkgscripts-ng" ]; then
		git clone https://github.com/SynologyOpenSource/pkgscripts-ng
	else
		echo "目录 pkgscripts-ng 已存在，跳过克隆。"
	fi

	# 创建版本环境
	cd $workdir/pkgscripts-ng

	# 切换到对应版本的分支
	git checkout DSM${DSMversion}

	# 查看支持平台
	./EnvDeploy -l
	# Available platforms: avoton braswell bromolow grantley alpine alpine4k monaco armada38x kvmcloud kvmx64 rtd1296 broadwellnk denverton apollolake armada37xx purley v1000 broadwell geminilake broadwellntbap r1000 broadwellnkv2 rtd1619b epyc7002 geminilakenk r1000nk v1000nk

	# 创建打包平台
	echo "平台环境不存在，开始创建..."
	mkdir -p "$workdir/toolkit_tarballs"
	if [ ! -d "$workdir/build_env/ds.${platform}-${DSMversion}" ]; then
		if [ -f "$workdir/toolkit_tarballs/base_env-${DSMversion}.txz" ] && \
		   [ -f "$workdir/toolkit_tarballs/ds.${platform}-${DSMversion}.dev.txz" ] && \
		   [ -f "$workdir/toolkit_tarballs/ds.${platform}-${DSMversion}.env.txz" ]; then
			echo "环境包已存在，跳过下载..."
			# 假设EnvDeploy中有参数可以跳过下载，例如：-D
			sudo ./EnvDeploy -v ${DSMversion} -p ${platform} -D
		else
			echo "环境包不存在，准备下载..."
			echo "=================================================="
			echo "在线下载可能会很漫长，如遇耗时太久或者失败，请尝试手动下载所需文件。"
			echo "下载地址："
			echo "https://global.synologydownload.com/download/ToolChain/toolkit/${DSMversion}/base/base_env-${DSMversion}.txz"
			echo "https://global.synologydownload.com/download/ToolChain/toolkit/${DSMversion}/${platform}/ds.${platform}-${DSMversion}.dev.txz"
			echo "https://global.synologydownload.com/download/ToolChain/toolkit/${DSMversion}/${platform}/ds.${platform}-${DSMversion}.env.txz"
			echo "下载三个文件，拷贝到toolkit/toolkit_tarballs"
			echo "=================================================="
			echo "开始下载..."
			sudo ./EnvDeploy -v ${DSMversion} -p ${platform}
		fi
	fi
fi

# 创建标记
touch "$workdir/install.lock"

#############################
# 手动方式
# 环境基础包，通用的
# https://dataautoupdate7.synology.com/toolchain/v1/get_download_list/toolkit/7.2/base
# 分平台的包，自行更改版本号和架构代码
# https://dataautoupdate7.synology.com/toolchain/v1/get_download_list/toolkit/7.2/geminilake

# 创建环境包存放目录
# mkdir -p ../toolkit_tarballs

# 手动下载共三个文件，base_env-{DSMversion}.txz, ds.{platform}-{DSMversion}.dev.txz and ds.{platform}-{DSMversion}.env.txz 放在 toolkit/toolkit_tarballs
# 手动释放环境包
# ./EnvDeploy -v 7.2 -p geminilake -D
#############################

# 完整编译环境目录
# /toolkit
# ├── pkgscripts-ng/
# │   ├── include/
# │   ├── EnvDeploy    (deployment tool for chroot environment)
# │   └── PkgCreate.py (build tool for package)
# └── build_env/       (directory to store chroot environments)
# └── source/       (directory to store package source files)

# 特别说明；打包模版中，ui目录需先执行npm install，否则打包时会报错。
if [ -f "$workdir/source/${packageDir}/ui/package.json" ]; then
	echo "进入 UI 目录并安装依赖..."
	cd $workdir/source/${packageDir}/ui
	npm install
fi

### 追加changelog字段
PKG_UTIL_FILE="$workdir/pkgscripts-ng/include/pkg_util.sh"

# 如果文件中不包含 changelog，则自动添加
if ! grep -q 'changelog ' "$PKG_UTIL_FILE"; then
    sed -i 's/local fields="/local fields="changelog /g' "$PKG_UTIL_FILE"
fi

echo "预处理文件..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[替换变量]"
# 获取INFO文件变量（这些只需要获取一次）
info_file=$(ls "$workdir/source/$packageDir"/INFO* 2>/dev/null | head -n 1)
resource_file="$workdir/source/$packageDir/conf/resource"

extract_var() {
    local var_name=$1
    grep -E "^${var_name}=" "$info_file" | sed -E 's/^[^=]+="?([^"]*)"?$/\1/'
}

dsmappname=$(extract_var "dsmappname")
package=$(extract_var "package")
displayname=$(extract_var "displayname")
adminport=$(extract_var "adminport")
packVersion=$(extract_var "version")

replace_in_files() {
    local search=$1
    local replace=$2
    
    echo "开始替换: 【$search】 → 【$replace】"
    
    # 执行替换
    find "$workdir/source/$packageDir" -mindepth 2 -type f -exec grep -Iq . {} \; -print0 | \
    while IFS= read -r -d $'\0' file; do
        relative_path="${file#$workdir/source/$packageDir/}"
        # echo "处理: $relative_path"
        sed -i "s|${search}|${replace}|g" "$file"
    done
        
    # 每次替换后重新获取resource文件的最新值
    resource_app=$(jq -r '.webservice.portals[0].app' "$resource_file")
    resource_name=$(jq -r '.webservice.portals[0].name' "$resource_file")
    resource_display_name=$(jq -r '.webservice.services[0].display_name' "$resource_file")
    resource_http_port=$(jq -r '.webservice.portals[0].http_port[0]' "$resource_file")
}

# 初始获取resource文件值
resource_app=$(jq -r '.webservice.portals[0].app' "$resource_file")
resource_name=$(jq -r '.webservice.portals[0].name' "$resource_file")
resource_display_name=$(jq -r '.webservice.services[0].display_name' "$resource_file")
resource_http_port=$(jq -r '.webservice.portals[0].http_port[0]' "$resource_file")

# 按顺序执行替换（每次替换后会自动更新resource_*变量）
replace_in_files "$resource_name" "$package"
replace_in_files "$resource_app" "$dsmappname"
replace_in_files "$resource_display_name" "$displayname"
replace_in_files "$resource_http_port" "$adminport"

echo "✅ 所有替换操作已完成"

echo "[处理截图名称]"
# 处理截图名称
screenshotDir="$workdir/source/${packageDir}/screenshots"
if [ -d "$screenshotDir" ]; then
    # 初始化计数器
    counter=1
    
    # 遍历截图目录中的所有文件（按名称排序）
    for screenshot in $(ls -1 "$screenshotDir" | sort); do
        # 获取文件完整路径
        filepath="$screenshotDir/$screenshot"
        
        # 检查是否是文件
        if [ -f "$filepath" ]; then
            # 获取文件扩展名
            extension="${screenshot##*.}"
            
            # 构建新文件名
            newname="${package}_screen_${counter}.png"
            
            # 检查新文件名是否已存在
            if [ ! -f "$screenshotDir/$newname" ]; then
                # 重命名文件
                mv "$filepath" "$screenshotDir/$newname"
                echo "重命名: $screenshot -> $newname"
                
                # 计数器递增
                ((counter++))
            else
                echo "跳过: 目标文件已存在 $newname"
            fi
        fi
    done
    
    echo "✅ 截图重命名完成，共处理了 $((counter-1)) 个文件"
else
    echo "截图目录不存在: $screenshotDir"
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "要打包的平台：${platform}"
echo "要打包的系统版本：${DSMversion}"
echo "要打包的套件路径：${packageDir}"
echo "要打包的套件名称：${package}"
echo "要打包的套件版本号：${packVersion}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if [ -f "$workdir/install.lock" ]; then
	echo "输入管理员密码，开始打包进程..."
fi

# 移动历史spk
sudo mkdir -p $workdir/result_spk/old
find "$workdir/result_spk" -mindepth 1 -maxdepth 1 ! -name old -exec sudo mv -f {} "$workdir/result_spk/old/" \;

# 提权
sudo chmod +x $info_file
sudo find "$workdir/source/${packageDir}/scripts" -type f -exec chmod +x {} \;
sudo find "$workdir/source/${packageDir}/SynoBuildConf" -type f -exec chmod +x {} \;

# 打包
cd $workdir/pkgscripts-ng
sudo ./PkgCreate.py -v ${DSMversion} -p ${platform} -c ${packageDir}

# 打包后的目录
# /toolkit/
# ├── pkgscripts-ng/
# ├── build_env/
# │   └── ds.${platform}-${DSMversion}
# └── result_spk/
#     └── ${package}-${DSMversion}/
#         └── *.spk

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ 打包完成，SPK 文件位于："
echo "$workdir/result_spk/${package}-${packVersion}"
ls -lh "$workdir/result_spk/${package}-${packVersion}/"
