/**
 * Downscales an image so its longest side is at most `maxSide` px and re-encodes it as WebP.
 * Falls back to the original file when the browser cannot decode or encode it.
 */
export async function compressImage(
    file: File,
    maxSide = 1600,
    quality = 0.8,
): Promise<File> {
    if (
        !file.type.startsWith('image/') ||
        typeof createImageBitmap !== 'function'
    ) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const scale = Math.min(
            1,
            maxSide / Math.max(bitmap.width, bitmap.height),
        );
        const width = Math.round(bitmap.width * scale);
        const height = Math.round(bitmap.height * scale);

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');
        if (!context) return file;

        context.drawImage(bitmap, 0, 0, width, height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/webp', quality),
        );

        // Keep the original when re-encoding does not make it smaller.
        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], renamed(file.name), { type: 'image/webp' });
    } catch {
        return file;
    }
}

function renamed(name: string): string {
    return `${name.replace(/\.[^.]+$/, '')}.webp`;
}
