#!/bin/bash

# Generate PWA icons from video-play-icon-20.png
# Uses macOS built-in 'sips' tool

SOURCE_IMAGE="video-play-icon-20.png"
ICONS_DIR="icons"
FAVICON_DIR="."

# Check if source image exists
if [ ! -f "$SOURCE_IMAGE" ]; then
    echo "Error: Source image '$SOURCE_IMAGE' not found!"
    exit 1
fi

# Create icons directory
mkdir -p "$ICONS_DIR"

echo "Generating PWA icons from $SOURCE_IMAGE..."
echo ""

# Define sizes for PWA icons
SIZES=(72 96 128 144 152 192 384 512)

# Generate each icon size
for SIZE in "${SIZES[@]}"; do
    OUTPUT_FILE="$ICONS_DIR/icon-${SIZE}x${SIZE}.png"
    echo "Creating ${SIZE}x${SIZE} icon..."
    sips -z $SIZE $SIZE "$SOURCE_IMAGE" --out "$OUTPUT_FILE" > /dev/null 2>&1

    if [ $? -eq 0 ]; then
        echo "  ✓ Created: $OUTPUT_FILE"
    else
        echo "  ✗ Failed to create: $OUTPUT_FILE"
    fi
done

# Generate favicon sizes
echo ""
echo "Creating favicon sizes..."
sips -z 32 32 "$SOURCE_IMAGE" --out "$FAVICON_DIR/favicon-32x32.png" > /dev/null 2>&1
echo "  ✓ Created: favicon-32x32.png"

sips -z 16 16 "$SOURCE_IMAGE" --out "$FAVICON_DIR/favicon-16x16.png" > /dev/null 2>&1
echo "  ✓ Created: favicon-16x16.png"

# Copy original as favicon.png
cp "$SOURCE_IMAGE" "$FAVICON_DIR/favicon.png"
echo "  ✓ Created: favicon.png"

echo ""
echo "✓ Successfully generated all PWA icons and favicons!"
echo ""
echo "Files created:"
echo "  - ${#SIZES[@]} PWA icons in $ICONS_DIR/"
echo "  - 3 favicon files in root"
echo ""
echo "Your PWA is now ready with custom icons!"
