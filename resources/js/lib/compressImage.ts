export const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const MAX_BYTES = 2048 * 1024;

export const TYPE_ERROR = 'Foto harus berformat jpg, png, atau webp.';
export const SIZE_ERROR = 'Ukuran foto maksimal 2 MB.';

const QUALITIES = (quality: number) => [quality, quality - 0.2, quality - 0.4];

/**
 * Downscales an image so its longest side is at most `maxSide` px and re-encodes it (WebP when
 * the browser supports it) so it fits the 2 MB upload limit. Retries with lower quality, then a
 * smaller size. Resolves with the original file only when it is already an accepted type and
 * within the limit; rejects with a user-facing message otherwise.
 */
export async function compressImage(
    file: File,
    maxSide = 1600,
    quality = 0.8,
): Promise<File> {
    if (!ACCEPTED_TYPES.includes(file.type)) {
        throw new Error(TYPE_ERROR);
    }

    const original = () => {
        if (file.size > MAX_BYTES) throw new Error(SIZE_ERROR);

        return file;
    };

    if (typeof createImageBitmap !== 'function') return original();

    let bitmap: ImageBitmap;
    try {
        bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
    } catch {
        return original();
    }

    try {
        for (const side of [maxSide, maxSide * 0.75, maxSide * 0.5]) {
            for (const q of QUALITIES(quality)) {
                const blob = await encode(bitmap, side, q);

                if (!blob || !ACCEPTED_TYPES.includes(blob.type)) {
                    return original();
                }

                if (blob.size <= MAX_BYTES) {
                    // Keep the original when re-encoding does not make it smaller.
                    if (blob.size >= file.size && file.size <= MAX_BYTES) {
                        return file;
                    }

                    return new File([blob], renamed(file.name, blob.type), {
                        type: blob.type,
                    });
                }
            }
        }

        return original();
    } finally {
        bitmap.close();
    }
}

async function encode(
    bitmap: ImageBitmap,
    maxSide: number,
    quality: number,
): Promise<Blob | null> {
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));

    const context = canvas.getContext('2d');
    if (!context) return null;

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const toBlob = (type: string) =>
        new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, type, quality),
        );

    const webp = await toBlob('image/webp');

    // Safari ignores WebP and returns PNG: fall back to JPEG at the same quality.
    if (webp?.type === 'image/webp') return webp;

    // JPEG has no alpha: paint white behind transparent pixels so they do not turn black.
    context.globalCompositeOperation = 'destination-over';
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);

    return toBlob('image/jpeg');
}

function renamed(name: string, type: string): string {
    const extension =
        type === 'image/png' ? 'png' : type === 'image/jpeg' ? 'jpg' : 'webp';

    return `${name.replace(/\.[^.]+$/, '')}.${extension}`;
}
