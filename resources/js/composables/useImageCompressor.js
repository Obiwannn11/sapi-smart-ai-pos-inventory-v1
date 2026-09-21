/**
 * Kompresi gambar SEBELUM berkasnya menyeberangi jaringan.
 *
 * Server sudah mengecilkan foto produk (lihat ImageService), tapi ia baru bisa
 * melakukannya setelah 5 MB itu sampai. Di jaringan warung, dan lebih-lebih di
 * kasir yang sedang offline, "dikecilkan setelah sampai" tidak menolong sama
 * sekali: foto 12 MP menunggu di antrean IndexedDB dengan ukuran penuh sampai
 * sinyal kembali.
 *
 * Tiga aturan yang membentuk modul ini:
 *
 *   1. TIDAK PERNAH MENGHALANGI UNGGAHAN. Setiap kegagalan — peramban tanpa
 *      createImageBitmap, berkas yang tak bisa didekode, canvas yang menolak
 *      toBlob — berakhir dengan mengembalikan berkas ASLI, bukan melempar
 *      error. Kompresi adalah penghematan, bukan syarat sah.
 *
 *   2. BUKAN GAMBAR DILEWATKAN APA ADANYA. Bukti transfer langganan boleh
 *      berupa PDF; melewatkannya ke encoder WEBP akan merusaknya. Pemanggil
 *      tidak perlu tahu tipe berkasnya lebih dulu.
 *
 *   3. HASIL YANG LEBIH BESAR DIBUANG. Tangkapan layar PNG kecil dan gambar
 *      yang sudah WEBP kerap membengkak setelah dikodekan ulang. Bila itu
 *      terjadi, yang dipakai tetap yang asli.
 */

/** Sisi terpanjang setelah dikecilkan. 1600 masih tajam untuk layar retina. */
const MAX_DIMENSION = 1600;

/** 0.82 — sedikit di atas ImageService (0.8) karena server masih mengecilkan lagi. */
const QUALITY = 0.82;

/** Di bawah ini tidak ada yang perlu dihemat; dekode ulangnya justru mahal. */
const SKIP_BELOW_BYTES = 200 * 1024;

const isImage = (file) => Boolean(file?.type) && file.type.startsWith('image/');

/** Ukuran tujuan yang menjaga rasio; null bila gambarnya sudah cukup kecil. */
const fitWithin = (width, height, max) => {
    if (width <= max && height <= max) {
        return null;
    }

    const ratio = Math.min(max / width, max / height);

    return {
        width: Math.max(1, Math.round(width * ratio)),
        height: Math.max(1, Math.round(height * ratio)),
    };
};

const toWebpName = (name) => name.replace(/\.[^.]+$/, '') + '.webp';

/**
 * @param {{ maxDimension?: number, quality?: number, skipBelowBytes?: number }} options
 */
export function useImageCompressor(options = {}) {
    const maxDimension = options.maxDimension ?? MAX_DIMENSION;
    const quality = options.quality ?? QUALITY;
    const skipBelowBytes = options.skipBelowBytes ?? SKIP_BELOW_BYTES;

    const supported = typeof createImageBitmap === 'function'
        && typeof document !== 'undefined'
        && typeof HTMLCanvasElement !== 'undefined';

    /**
     * Kecilkan dan konversi ke WEBP.
     *
     * @param {File|Blob|null} file
     * @returns {Promise<File|Blob|null>} Berkas baru, atau yang asli bila tak ada untungnya.
     */
    const compress = async (file) => {
        if (!file || !isImage(file) || !supported) {
            return file;
        }

        // GIF beranimasi kehilangan animasinya begitu digambar ke canvas —
        // satu frame diam bukan hasil yang sama, jadi ia tidak disentuh.
        if (file.type === 'image/gif') {
            return file;
        }

        let bitmap;

        try {
            bitmap = await createImageBitmap(file);
        } catch {
            return file;
        }

        try {
            const target = fitWithin(bitmap.width, bitmap.height, maxDimension);

            // Sudah kecil dan sudah ringan: tidak ada yang bisa dihemat.
            if (!target && file.size <= skipBelowBytes) {
                return file;
            }

            const width = target?.width ?? bitmap.width;
            const height = target?.height ?? bitmap.height;

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            const context = canvas.getContext('2d');
            if (!context) {
                return file;
            }

            context.drawImage(bitmap, 0, 0, width, height);

            const blob = await new Promise((resolve) => {
                canvas.toBlob(resolve, 'image/webp', quality);
            });

            // toBlob mengembalikan null bila format tidak didukung, dan hasil
            // yang lebih besar berarti dekode ulang tadi merugikan.
            if (!blob || blob.size >= file.size) {
                return file;
            }

            return new File([blob], toWebpName(file.name ?? 'image'), {
                type: 'image/webp',
                lastModified: Date.now(),
            });
        } catch {
            return file;
        } finally {
            bitmap.close?.();
        }
    };

    return { compress, supported };
}
