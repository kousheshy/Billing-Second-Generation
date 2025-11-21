#!/usr/bin/env python3
"""
Generate PWA icons for ShowBox Billing Panel
Creates simple gradient icons with "SB" text
"""

import os
import sys

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    print("PIL/Pillow not found. Installing...")
    os.system(f"{sys.executable} -m pip install Pillow")
    from PIL import Image, ImageDraw, ImageFont

def create_icon(size):
    """Create a single icon of the specified size"""
    # Create image with gradient background
    img = Image.new('RGB', (size, size), color='#6366f1')
    draw = ImageDraw.Draw(img)

    # Create gradient effect (simple version)
    for y in range(size):
        # Gradient from #6366f1 to #4f46e5
        r = int(99 + (79 - 99) * y / size)
        g = int(102 + (70 - 102) * y / size)
        b = int(241 + (229 - 241) * y / size)
        color = (r, g, b)
        draw.line([(0, y), (size, y)], fill=color)

    # Add text "SB"
    try:
        # Try to use a bold system font
        font_size = size // 3
        font = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", font_size)
    except:
        # Fallback to default font
        font = ImageFont.load_default()

    # Calculate text position
    text = "SB"
    bbox = draw.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]
    x = (size - text_width) // 2
    y = (size - text_height) // 2 - size // 10

    # Draw text with shadow
    shadow_offset = max(2, size // 100)
    draw.text((x + shadow_offset, y + shadow_offset), text, fill=(0, 0, 0, 128), font=font)
    draw.text((x, y), text, fill='white', font=font)

    # Add subtitle
    try:
        subtitle_font_size = size // 10
        subtitle_font = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", subtitle_font_size)
    except:
        subtitle_font = ImageFont.load_default()

    subtitle = "Billing"
    bbox = draw.textbbox((0, 0), subtitle, font=subtitle_font)
    subtitle_width = bbox[2] - bbox[0]
    sx = (size - subtitle_width) // 2
    sy = size * 3 // 4
    draw.text((sx, sy), subtitle, fill='white', font=subtitle_font)

    return img

def main():
    """Generate all icon sizes"""
    sizes = [72, 96, 128, 144, 152, 192, 384, 512]
    icons_dir = 'icons'

    # Create icons directory
    os.makedirs(icons_dir, exist_ok=True)

    print("Generating PWA icons...")
    for size in sizes:
        filename = f"{icons_dir}/icon-{size}x{size}.png"
        print(f"Creating {filename}...")
        icon = create_icon(size)
        icon.save(filename, 'PNG')

    print(f"\n✓ Successfully generated {len(sizes)} icons in {icons_dir}/")
    print("\nIcon files created:")
    for size in sizes:
        print(f"  - icon-{size}x{size}.png")

if __name__ == '__main__':
    main()
