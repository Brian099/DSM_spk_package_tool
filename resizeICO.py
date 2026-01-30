import os
from PIL import Image
import shutil

def resize_icon(input_path, sizes=[256, 128, 64, 32, 16]):
    """
    将PNG图标调整为多种尺寸
    
    参数:
    input_path: 输入图片路径
    sizes: 需要生成的尺寸列表
    """
    
    # 检查输入文件是否存在
    if not os.path.exists(input_path):
        print(f"错误: 文件 {input_path} 不存在")
        return
    
    # 检查文件是否为PNG格式
    if not input_path.lower().endswith('.png'):
        print("警告: 文件不是PNG格式，但将继续处理")
    
    try:
        # 打开原始图片
        with Image.open(input_path) as img:
            # 获取原始文件名（不含扩展名）
            base_name = os.path.splitext(os.path.basename(input_path))[0]
            output_dir = os.path.dirname(input_path)
            
            print(f"开始处理: {input_path}")
            
            for size in sizes:
                # 生成输出文件名
                output_filename = f"{base_name}_{size}x{size}.png"
                output_path = os.path.join(output_dir, output_filename)
                
                # 调整图片尺寸
                resized_img = img.resize((size, size), Image.LANCZOS)
                
                # 保存图片
                resized_img.save(output_path, 'PNG')
                print(f"已生成: {output_path}")
                
    except Exception as e:
        print(f"处理图片时出错: {e}")

def copy_icon_multiple_sizes(input_path, sizes=[256, 128, 64, 32, 16]):
    """
    复制PNG图标为多种尺寸（保持原始质量）
    
    参数:
    input_path: 输入图片路径
    sizes: 需要生成的尺寸列表
    """
    
    # 检查输入文件是否存在
    if not os.path.exists(input_path):
        print(f"错误: 文件 {input_path} 不存在")
        return
    
    # 检查文件是否为PNG格式
    if not input_path.lower().endswith('.png'):
        print("警告: 文件不是PNG格式，但将继续处理")
    
    try:
        # 打开原始图片
        with Image.open(input_path) as img:
            # 获取原始文件名（不含扩展名）
            base_name = os.path.splitext(os.path.basename(input_path))[0]
            output_dir = os.path.dirname(input_path)
            
            print(f"开始处理: {input_path}")
            
            for size in sizes:
                # 生成输出文件名
                #output_filename = f"{base_name}_{size}x{size}.png"
                output_filename = f"MyIcon_{size}.png"
                output_path = os.path.join(output_dir, output_filename)
                
                # 调整图片尺寸
                resized_img = img.resize((size, size), Image.LANCZOS)
                
                # 保存图片（保持高质量）
                resized_img.save(output_path, 'PNG', optimize=True)
                print(f"已生成: {output_path}")
                
    except Exception as e:
        print(f"处理图片时出错: {e}")

# 使用示例
if __name__ == "__main__":
    # 指定输入文件路径
    input_icon = "logo.png"  # 修改为你的图标路径
    
    # 定义需要生成的尺寸
    target_sizes = [256, 192, 128, 72, 64, 48, 32, 24, 16]
    
    # 检查输入文件是否存在
    if os.path.exists(input_icon):
        # 生成多种尺寸的图标
        copy_icon_multiple_sizes(input_icon, target_sizes)
        print("所有尺寸的图标已生成完成！")
    else:
        print(f"请先将你的图标文件命名为 'icon.png' 放在同一目录下，或修改脚本中的文件路径")