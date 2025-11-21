/**
 * Create placeholder PWA icons using Canvas API
 * Run this in the browser console or as a Node.js script
 */

const sizes = [72, 96, 128, 144, 152, 192, 384, 512];

function createIcon(size) {
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    // Background gradient
    const gradient = ctx.createLinearGradient(0, 0, size, size);
    gradient.addColorStop(0, '#6366f1');
    gradient.addColorStop(1, '#4f46e5');
    ctx.fillStyle = gradient;

    // Rounded rectangle
    const radius = size * 0.15;
    ctx.beginPath();
    ctx.moveTo(radius, 0);
    ctx.lineTo(size - radius, 0);
    ctx.quadraticCurveTo(size, 0, size, radius);
    ctx.lineTo(size, size - radius);
    ctx.quadraticCurveTo(size, size, size - radius, size);
    ctx.lineTo(radius, size);
    ctx.quadraticCurveTo(0, size, 0, size - radius);
    ctx.lineTo(0, radius);
    ctx.quadraticCurveTo(0, 0, radius, 0);
    ctx.closePath();
    ctx.fill();

    // Text "SB"
    ctx.fillStyle = '#ffffff';
    ctx.font = `bold ${size * 0.4}px Arial`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('SB', size / 2, size * 0.42);

    // Subtitle "Billing"
    ctx.font = `${size * 0.1}px Arial`;
    ctx.fillText('Billing', size / 2, size * 0.75);

    return canvas;
}

// For browser usage
if (typeof document !== 'undefined') {
    console.log('Creating PWA icons...');
    sizes.forEach(size => {
        const canvas = createIcon(size);
        canvas.toBlob(blob => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `icon-${size}x${size}.png`;
            a.click();
            console.log(`Created icon-${size}x${size}.png`);
        });
    });
    console.log('Download all icons and place them in the icons/ folder');
}

module.exports = { createIcon, sizes };
