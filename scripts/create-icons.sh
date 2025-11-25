#!/bin/bash

# Simple icon generator for PWA
# This creates basic placeholder icons using ImageMagick (if available)
# Otherwise, provides instructions for manual icon creation

ICONS_DIR="./icons"
mkdir -p "$ICONS_DIR"

# Check if ImageMagick is installed
if command -v convert &> /dev/null; then
    echo "ImageMagick found. Generating icons..."

    # Define sizes
    SIZES=(72 96 128 144 152 192 384 512)

    for SIZE in "${SIZES[@]}"; do
        echo "Creating ${SIZE}x${SIZE} icon..."
        convert -size ${SIZE}x${SIZE} \
                -background "linear-gradient(135deg,#6366f1,#4f46e5)" \
                -fill white \
                -gravity center \
                -pointsize $((SIZE / 3)) \
                -font Arial-Bold \
                label:"SB" \
                -bordercolor "#6366f1" \
                -border 0 \
                "$ICONS_DIR/icon-${SIZE}x${SIZE}.png"
    done

    echo "✓ Icons generated successfully in $ICONS_DIR/"
else
    echo "ImageMagick not found."
    echo ""
    echo "Please install ImageMagick with:"
    echo "  macOS: brew install imagemagick"
    echo "  Linux: sudo apt-get install imagemagick"
    echo ""
    echo "Or use the generate-icons.html file:"
    echo "  1. Open generate-icons.html in your browser"
    echo "  2. Click 'Generate Icons' button"
    echo "  3. Save the downloaded icons to the icons/ folder"
fi
