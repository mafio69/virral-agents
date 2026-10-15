export const MAX_IMAGE_BYTES = 524288;
export function isInlineImage(value) {
    return typeof value === 'string' && value.length <= 700000
        && /^data:image\/(?:jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}$/.test(value);
}

export function isGeneratedImage(value) {
    return typeof value === 'string' && value.length <= Math.ceil(8388608 / 3) * 4 + 64
        && /^data:image\/(?:jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}$/.test(value);
}

export function appendMessageImages(body, message, documentApi = document) {
    const assistant = message.role === 'assistant';
    if (!Array.isArray(message.images)) return;
    message.images.filter(assistant ? isGeneratedImage : isInlineImage).slice(0, 1).forEach(source => {
        const picture = documentApi.createElement('img');
        picture.className = 'message-image';
        picture.src = source;
        picture.alt = assistant ? 'Obraz wygenerowany przez AI' : 'Obraz przesłany do analizy';
        picture.loading = 'lazy';
        body.append(picture);
        if (assistant) {
            const download = documentApi.createElement('a');
            download.className = 'btn-ghost';
            download.href = source;
            download.download = 'obraz-ai.' + source.slice(11, source.indexOf(';')).replace('jpeg', 'jpg');
            download.textContent = 'Pobierz obraz';
            body.append(download);
        }
    });
}

/** Decode locally and re-encode: smaller payload, no original metadata or filename. */
export async function prepareImage(file) {
    if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
        || file.size <= 0 || file.size > 20 * 1024 * 1024) {
        throw new Error('Wybierz PNG, JPEG lub WebP do 20 MB.');
    }
    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
        if (bitmap.width > 12000 || bitmap.height > 12000 || bitmap.width * bitmap.height > 40000000) {
            throw new Error('Obraz ma zbyt duże wymiary.');
        }
        const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Przeglądarka nie mogła przygotować obrazu.');
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        for (const quality of [0.9, 0.75, 0.55, 0.35]) {
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
            if (!blob || blob.size > MAX_IMAGE_BYTES) continue;
            return await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('Nie udało się odczytać obrazu.'));
                reader.readAsDataURL(blob);
            });
        }
        throw new Error('Obraz jest zbyt szczegółowy. Wybierz mniejszy plik lub wycinek.');
    } catch (error) {
        throw new Error(error.message || 'Nie udało się przygotować obrazu.');
    } finally {
        bitmap?.close();
    }
}
