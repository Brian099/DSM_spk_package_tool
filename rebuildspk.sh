#!/bin/bash
 
# 检查参数是否存在 
if [ $# -ne 1 ]; then
    echo "Usage: $0 <directory>"
    exit 1
fi
 
target_dir="$1"
echo $target_dir
info_file="${target_dir}/INFO"
 
# 校验目录和INFO文件 
if [ ! -d "$target_dir" ]; then 
    echo "Error: Directory $target_dir does not exist" >&2
    exit 1 
fi 
 
if [ ! -f "$info_file" ]; then 
    echo "Error: INFO file not found in $target_dir" >&2 
    exit 1
fi
 
# 解析INFO文件 (安全处理引号/空格)
package=$(grep '^package=' "$info_file" | cut -d'=' -f2- | tr -d '"')
version=$(grep '^version=' "$info_file" | cut -d'=' -f2- | tr -d '"')
 
# 校验关键字段
if [ -z "$package" ]; then
    echo "Error: 'package' field not found in INFO" >&2 
    exit 1
fi
 
if [ -z "$version" ]; then
    echo "Error: 'version' field not found in INFO" >&2
    exit 1 
fi 
 
# 构建文件名 
output_file="${package}-${version}.spk"
 
# 执行打包 (安全处理特殊字符)
echo "Packaging ${target_dir} to ${output_file}..."
tar cf "$output_file" -C "$target_dir" $(ls $target_dir) 2>/dev/null
 
# 结果校验
if [ $? -eq 0 ]; then 
    echo "Successfully created package: $output_file"
    ls -lh "$output_file"
else
    echo "Error: Failed to create package" >&2 
    exit 1
fi