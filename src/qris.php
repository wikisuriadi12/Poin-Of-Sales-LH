<?php
declare(strict_types=1);

/**
 * Penyedia pembayaran QRIS dinamis. Aplikasi hanya bergantung pada antarmuka ini,
 * sehingga mengganti simulasi menjadi BNI cukup dengan melengkapi kelas BniQris.
 */
interface QrisProvider {
    /** Buat tagihan QR untuk satu pesanan. @return array{ref:string,qr_string:string} */
    public function createCharge(string $orderNo, int $amount, DateTimeImmutable $expiresAt): array;

    /**
     * Verifikasi dan baca callback pembayaran dari penyedia.
     * @param array<string,string> $headers nama header huruf kecil
     * @return array{ref:string,amount:int}|null null bila bukan event "dibayar"
     * @throws RuntimeException kode 401 bila tanda tangan tidak valid
     */
    public function parseWebhook(string $rawBody, array $headers): ?array;
}

function webhook_signature_valid(string $raw, string $signature, string $secret): bool {
    if ($secret === '' || str_starts_with($secret, 'ISI_') || strlen($secret) < 16) return false;
    return hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($signature));
}

/** Simulasi untuk uji coba. QR yang dihasilkan bukan kode pembayaran sungguhan. */
final class MockQris implements QrisProvider {
    public function createCharge(string $orderNo, int $amount, DateTimeImmutable $expiresAt): array {
        $ref = 'MOCK-' . bin2hex(random_bytes(8));
        return ['ref' => $ref, 'qr_string' => 'SIMULASI-BUKAN-QRIS|' . $ref . '|' . $amount];
    }

    public function parseWebhook(string $rawBody, array $headers): ?array {
        if (!webhook_signature_valid($rawBody, $headers['x-signature'] ?? '', (string)cfg('webhook_secret', ''))) {
            throw new RuntimeException('Tanda tangan callback tidak valid.', 401);
        }
        $d = json_decode($rawBody, true);
        if (!is_array($d) || !is_string($d['ref'] ?? null)) throw new RuntimeException('Isi callback tidak valid.', 422);
        if (($d['status'] ?? '') !== 'PAID') return null;
        return ['ref' => $d['ref'], 'amount' => to_int($d['amount'] ?? null, 1, 1000000000000, 'Nominal')];
    }
}

/**
 * Kerangka integrasi BNI. SENGAJA belum diisi: alamat API, format permintaan, cara tanda tangan,
 * dan format callback harus mengikuti dokumentasi resmi yang diberikan BNI setelah pendaftaran merchant.
 * Jangan menebak. Lengkapi dua metode di bawah, lalu ubah 'qris_provider' => 'bni' di config.php.
 *
 * Kunci konfigurasi yang disiapkan: bni_merchant_id, bni_api_key, bni_api_secret, bni_base_url.
 * Syarat minimal implementasi:
 *  - createCharge: minta QR dinamis ke BNI dengan nominal dan nomor pesanan; kembalikan ref unik dan string QR.
 *  - parseWebhook: verifikasi tanda tangan sesuai aturan BNI SEBELUM membaca isi; kembalikan ref dan nominal
 *    untuk pembayaran sukses; lempar RuntimeException kode 401 bila tanda tangan salah.
 *  - Callback bisa datang lebih dari sekali; mark_paid() sudah idempoten.
 */
final class BniQris implements QrisProvider {
    public function createCharge(string $orderNo, int $amount, DateTimeImmutable $expiresAt): array {
        throw new RuntimeException('Integrasi BNI belum dilengkapi. Lihat src/qris.php dan README.');
    }
    public function parseWebhook(string $rawBody, array $headers): ?array {
        throw new RuntimeException('Integrasi BNI belum dilengkapi. Lihat src/qris.php dan README.', 401);
    }
}

function qris_provider(): QrisProvider {
    return match ((string)cfg('qris_provider', 'mock')) {
        'mock' => new MockQris(),
        'bni' => new BniQris(),
        default => throw new RuntimeException('Penyedia QRIS tidak dikenal.'),
    };
}
