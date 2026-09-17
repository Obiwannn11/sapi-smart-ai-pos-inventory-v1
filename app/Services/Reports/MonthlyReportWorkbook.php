<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap bulanan sebagai buku kerja Excel — satu lembar per tabel.
 *
 * Unduhan sebelumnya satu file CSV berisi beberapa tabel yang ditumpuk dengan
 * baris kosong sebagai sekat. Itu terbaca oleh manusia, tapi tidak oleh
 * spreadsheet: satu lembar dengan beberapa kepala tabel berbeda tidak bisa
 * di-filter, tidak bisa di-pivot, dan kolom "Total" milik metode pembayaran
 * jatuh di kolom yang sama dengan "Omzet" milik rincian harian, sehingga
 * seretan SUM() apa pun menjumlahkan dua hal yang berbeda. Di sini tiap tabel
 * punya lembarnya sendiri, dengan satu baris kepala dan satu jenis baris data.
 *
 * Yang disengaja di seluruh lembar:
 *
 *   - Angka ditulis sebagai ANGKA, bukan teks berformat. "Rp" dan pemisah
 *     ribuannya urusan format sel; nilainya tetap bisa dijumlahkan.
 *   - Tanggal ditulis sebagai tanggal Excel yang sebenarnya, supaya urutan dan
 *     pivot tanggalnya benar — bukan string yang terurut secara alfabet.
 *   - Kolom pajak dan biaya layanan hanya lahir untuk tenant yang memungut,
 *     mengikuti aturan yang sama dengan layarnya ([BL-065], [BL-097]).
 */
class MonthlyReportWorkbook
{
    private const BRAND = '1F6F5C';

    private const HEADER_FILL = '1F6F5C';

    private const HEADER_TEXT = 'FFFFFF';

    private const SECTION_FILL = 'E8F1EE';

    private const MUTED_TEXT = '8A8F98';

    private const GRID = 'D5DAE1';

    private const CLOSED_DAY_FILL = 'F5F6F8';

    private const FORMAT_CURRENCY = '"Rp"\ #,##0;[Red]-"Rp"\ #,##0';

    private const FORMAT_INTEGER = '#,##0';

    private const FORMAT_PERCENT = '0.0%';

    private const FORMAT_DATE = 'dd mmm yyyy';

    /**
     * @param  array{
     *     month: string,
     *     label: string,
     *     range: array{from: string, to: string},
     *     tenant_name: string,
     *     generated_at: \Illuminate\Support\Carbon,
     *     summary: array<string, mixed>,
     *     comparison: array<string, mixed>,
     *     daily_series: array<int, array<string, mixed>>,
     *     payment_summary: array<int, array{id: int, name: string, type: string, total: float}>,
     *     top_products: iterable<int, array<string, mixed>>,
     *     discount_summary: array<string, mixed>,
     *     tax: array{active: bool, label: string},
     *     service_charge: array{active: bool, label: string}
     * }  $report
     */
    public function build(array $report): Spreadsheet
    {
        $book = new Spreadsheet;

        $book->getProperties()
            ->setCreator($report['tenant_name'])
            ->setTitle('Laporan Bulanan '.$report['label'])
            ->setSubject('Rekap penjualan '.$report['label'])
            ->setCompany($report['tenant_name']);

        $this->buildSummarySheet($book->getActiveSheet(), $report);
        $this->buildDailySheet($book->createSheet(), $report);
        $this->buildPaymentSheet($book->createSheet(), $report);

        // Lembar potongan hanya lahir kalau ada yang dipotong, dengan alasan
        // yang sama seperti kolom pajak: lembar berisi nol bukan kejujuran,
        // melainkan satu tab lagi yang harus dibuka dan ditutup tiap bulan oleh
        // mayoritas yang tidak pernah mendiskon.
        if (($report['discount_summary']['items_discounted'] ?? 0) > 0) {
            $this->buildDiscountSheet($book->createSheet(), $report);
        }

        $this->buildProductSheet($book->createSheet(), $report);

        // Lembar pertama yang aktif saat file dibuka, bukan lembar terakhir
        // yang kebetulan baru saja ditulis.
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * Lembar 1 — angka bulan itu, dan pembandingnya terhadap bulan sebelumnya.
     *
     * @param  array<string, mixed>  $report
     */
    private function buildSummarySheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Ringkasan');
        $this->prepare($sheet, ['A' => 40, 'B' => 22, 'C' => 22, 'D' => 14]);

        $summary = $report['summary'];
        $tax = $report['tax'];
        $service = $report['service_charge'];

        $this->title($sheet, 'A1:D1', 'LAPORAN PENJUALAN BULANAN');
        $this->subtitle($sheet, 'A2:D2', $report['tenant_name']);

        $sheet->setCellValue('A4', 'Bulan');
        $sheet->setCellValue('B4', $report['label']);
        $sheet->setCellValue('A5', 'Periode');
        $sheet->setCellValue('B5', $this->rangeLabel($report['range']));
        $sheet->setCellValue('A6', 'Diunduh');
        $sheet->setCellValue('B6', $report['generated_at']->translatedFormat('j F Y, H:i'));
        $sheet->getStyle('A4:A6')->getFont()->setBold(true);

        $row = 8;
        $this->sectionHeader($sheet, "A{$row}:D{$row}", 'RINGKASAN BULAN INI');
        $row++;

        $lines = [['Omzet (dibayar pelanggan)', $summary['total_revenue'], self::FORMAT_CURRENCY]];

        // Pemisahan yang sama dengan CSV dan layarnya: judulnya menyebut apa
        // saja yang sudah dikeluarkan dari angka ini, supaya "omzet sebelum
        // pajak" tidak dipakai menamai angka yang juga sudah dikurangi biaya
        // layanan ([BL-097]).
        if ($tax['active'] || $service['active']) {
            $lines[] = [
                $service['active'] ? 'Omzet toko (sebelum biaya layanan dan pajak)' : 'Omzet sebelum pajak',
                $summary['net_revenue'],
                self::FORMAT_CURRENCY,
            ];
        }
        if ($service['active']) {
            $lines[] = [$service['label'].' terpungut', $summary['service_charge_collected'], self::FORMAT_CURRENCY];
        }
        if ($tax['active']) {
            $lines[] = [$tax['label'].' terpungut', $summary['tax_collected'], self::FORMAT_CURRENCY];
        }

        $lines[] = ['Transaksi selesai', $summary['total_transactions'], self::FORMAT_INTEGER];
        $lines[] = ['Rata-rata per transaksi', $summary['average_transaction'], self::FORMAT_CURRENCY];
        $lines[] = ['Transaksi void', $summary['voided_count'], self::FORMAT_INTEGER];
        $lines[] = ['Hari berjualan', $summary['active_days'].' dari '.$summary['days_in_month'].' hari', null];
        $lines[] = ['Rata-rata omzet per hari berjualan', $summary['average_active_day_revenue'], self::FORMAT_CURRENCY];

        if ($summary['best_day'] !== null) {
            // Tanggal ini ditulis sebagai kalimat berbahasa Indonesia, bukan
            // sebagai tanggal Excel seperti di lembar rincian. Ia satu baris
            // ringkasan yang dibaca, bukan kolom yang diurutkan atau di-pivot —
            // dan nama bulan pada format tanggal dirender Excel menurut locale
            // mesin pembacanya, yang di luar Indonesia akan menulis "Aug".
            $lines[] = [
                'Hari terbaik',
                Carbon::parse($summary['best_day']['date'])->locale('id')->translatedFormat('l, j F Y'),
                null,
            ];
            $lines[] = ['Omzet hari terbaik', $summary['best_day']['revenue'], self::FORMAT_CURRENCY];
        }

        $firstLine = $row;
        foreach ($lines as [$label, $value, $format]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            if ($format !== null) {
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($format);
            }
            $row++;
        }
        $lastLine = $row - 1;

        $this->grid($sheet, "A{$firstLine}:B{$lastLine}");
        $sheet->getStyle("B{$firstLine}:B{$lastLine}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $row += 2;
        $comparison = $report['comparison'];
        $this->sectionHeader($sheet, "A{$row}:D{$row}", 'PERBANDINGAN DENGAN BULAN SEBELUMNYA');
        $row++;

        $head = $row;
        $this->tableHeader($sheet, "A{$head}:D{$head}", ['', $report['label'], $comparison['label'], 'Selisih']);
        $row++;

        $rows = [
            ['Omzet', $summary['total_revenue'], $comparison['total_revenue'], $comparison['revenue_delta_pct'], self::FORMAT_CURRENCY],
            ['Transaksi selesai', $summary['total_transactions'], $comparison['total_transactions'], $comparison['transactions_delta_pct'], self::FORMAT_INTEGER],
        ];

        foreach ($rows as [$label, $now, $before, $delta, $format]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $now);
            $sheet->setCellValue("C{$row}", $before);
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode($format);

            // Bulan tanpa pembanding tidak ditulis "+100%": tumbuh dari nol
            // bukan pertumbuhan, melainkan tidak punya dasar banding.
            if ($delta === null) {
                $sheet->setCellValue("D{$row}", '—');
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setRGB(self::MUTED_TEXT);
            } else {
                $sheet->setCellValue("D{$row}", $delta / 100);
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setRGB($delta < 0 ? 'C0392B' : '1E7E34');
            }
            $row++;
        }

        $this->grid($sheet, "A{$head}:D".($row - 1));
        $sheet->getStyle("B{$head}:D".($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    }

    /**
     * Lembar 2 — satu baris per tanggal, termasuk hari tutup.
     *
     * Hari tanpa penjualan tetap ada barisnya dan ditandai abu-abu. Membuangnya
     * membuat bulan yang bolong terbaca rapat, dan jumlah barisnya tidak lagi
     * sama dengan jumlah hari di bulan itu.
     *
     * @param  array<string, mixed>  $report
     */
    private function buildDailySheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Rincian Harian');

        // Kolom opsional dirakit sebagai daftar, bukan lewat ternary bersarang
        // di tiap tempat yang membutuhkannya: kepala tabel, tiap baris data,
        // dan baris total harus sepakat, dan tiga ternary sejajar akan
        // menyimpang begitu kolom ketiga lahir. Urutannya mengikuti aliran
        // uang: omzet -> biaya layanan -> pajak.
        $optional = [];
        if ($report['service_charge']['active']) {
            $optional[] = ['label' => $report['service_charge']['label'], 'key' => 'service_charge_collected'];
        }
        if ($report['tax']['active']) {
            $optional[] = ['label' => $report['tax']['label'], 'key' => 'tax_collected'];
        }

        $headings = array_merge(
            ['Tanggal', 'Hari', 'Transaksi', 'Omzet'],
            array_column($optional, 'label'),
            ['Void'],
        );
        $lastColumn = Coordinate::stringFromColumnIndex(count($headings));

        $widths = ['A' => 16, 'B' => 12, 'C' => 12, 'D' => 18];
        foreach ($optional as $index => $column) {
            $widths[Coordinate::stringFromColumnIndex(5 + $index)] = 18;
        }
        $widths[$lastColumn] = 10;
        $this->prepare($sheet, $widths);

        $this->title($sheet, "A1:{$lastColumn}1", 'RINCIAN HARIAN');
        $this->subtitle($sheet, "A2:{$lastColumn}2", $report['label'].' • '.$this->rangeLabel($report['range']));

        $head = 4;
        $this->tableHeader($sheet, "A{$head}:{$lastColumn}{$head}", $headings);

        $row = $head + 1;
        foreach ($report['daily_series'] as $day) {
            $sheet->setCellValue("A{$row}", $this->excelDate($day['date']));
            $sheet->setCellValue("B{$row}", Carbon::parse($day['date'])->locale('id')->translatedFormat('l'));
            $sheet->setCellValue("C{$row}", $day['count']);
            $sheet->setCellValue("D{$row}", $day['revenue']);

            foreach ($optional as $index => $column) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(5 + $index).$row, $day[$column['key']]);
            }
            $sheet->setCellValue("{$lastColumn}{$row}", $day['voided']);

            if ($day['count'] === 0) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::CLOSED_DAY_FILL]],
                    'font' => ['color' => ['rgb' => self::MUTED_TEXT]],
                ]);
            }

            $row++;
        }

        $total = $row;
        $sheet->setCellValue("A{$total}", 'TOTAL');
        $sheet->mergeCells("A{$total}:B{$total}");
        $sheet->setCellValue("C{$total}", $report['summary']['total_transactions']);
        $sheet->setCellValue("D{$total}", $report['summary']['total_revenue']);
        foreach ($optional as $index => $column) {
            $sheet->setCellValue(
                Coordinate::stringFromColumnIndex(5 + $index).$total,
                $report['summary'][$column['key']]
            );
        }
        $sheet->setCellValue("{$lastColumn}{$total}", $report['summary']['voided_count']);

        $sheet->getStyle("A{$total}:{$lastColumn}{$total}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_FILL]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => self::BRAND]]],
        ]);

        $this->grid($sheet, "A{$head}:{$lastColumn}{$total}");
        $sheet->getStyle('A'.($head + 1).":A{$total}")->getNumberFormat()->setFormatCode(self::FORMAT_DATE);
        $sheet->getStyle('C'.($head + 1).":C{$total}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
        $sheet->getStyle('D'.($head + 1).':'.Coordinate::stringFromColumnIndex(4 + count($optional)).$total)
            ->getNumberFormat()->setFormatCode(self::FORMAT_CURRENCY);
        $sheet->getStyle("{$lastColumn}".($head + 1).":{$lastColumn}{$total}")
            ->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);

        // Kepala tabel ikut tergulung, dan tetap terlihat di tiap halaman
        // cetak: 31 baris melewati batas satu halaman di kertas mana pun.
        $sheet->freezePane('A'.($head + 1));
        $sheet->setAutoFilter("A{$head}:{$lastColumn}".($total - 1));
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($head, $head);
    }

    /**
     * Lembar 3 — rekap per metode pembayaran, berikut porsinya.
     *
     * @param  array<string, mixed>  $report
     */
    private function buildPaymentSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Metode Pembayaran');
        $this->prepare($sheet, ['A' => 30, 'B' => 16, 'C' => 20, 'D' => 12]);

        $this->title($sheet, 'A1:D1', 'METODE PEMBAYARAN');
        $this->subtitle($sheet, 'A2:D2', $report['label']);

        $head = 4;
        $this->tableHeader($sheet, "A{$head}:D{$head}", ['Metode', 'Tipe', 'Total', 'Porsi']);

        $rows = $report['payment_summary'];
        $grandTotal = (float) array_sum(array_column($rows, 'total'));

        $row = $head + 1;
        if ($rows === []) {
            $this->emptyRow($sheet, "A{$row}:D{$row}", 'Tidak ada pembayaran pada periode ini.');
            $row++;
            $last = $row - 1;
        } else {
            foreach ($rows as $payment) {
                $sheet->setCellValue("A{$row}", $payment['name']);
                $sheet->setCellValue("B{$row}", $this->paymentTypeLabel($payment['type']));
                $sheet->setCellValue("C{$row}", $payment['total']);
                $sheet->setCellValue("D{$row}", $grandTotal > 0 ? $payment['total'] / $grandTotal : 0);
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'TOTAL');
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $grandTotal);
            $sheet->setCellValue("D{$row}", $grandTotal > 0 ? 1 : 0);
            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_FILL]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => self::BRAND]]],
            ]);
            $last = $row;
            $row++;
        }

        $this->grid($sheet, "A{$head}:D{$last}");
        $sheet->getStyle('C'.($head + 1).":C{$last}")->getNumberFormat()->setFormatCode(self::FORMAT_CURRENCY);
        $sheet->getStyle('D'.($head + 1).":D{$last}")->getNumberFormat()->setFormatCode(self::FORMAT_PERCENT);

        // Catatan kaki, bukan hiasan: baris tunai di sini sudah dikurangi
        // kembalian ([BL-109]), dan angkanya memang tidak sama dengan jumlah
        // lembar yang diterima kasir.
        $note = $row + 1;
        $sheet->setCellValue("A{$note}", 'Total tunai sudah dikurangi kembalian yang diserahkan kembali ke pelanggan.');
        $sheet->mergeCells("A{$note}:D{$note}");
        $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->getColor()->setRGB(self::MUTED_TEXT);

        $sheet->freezePane('A'.($head + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
    }

    /**
     * Lembar 4 — apa yang dipotong bulan itu, dan berapa yang dijual rugi.
     *
     * Dua angka yang sengaja berdiri terpisah: seluruh potongan, dan bagian
     * yang jatuh DI BAWAH lantai untung. Tanpa pemisahan itu satu penjualan
     * rugi terlihat persis seperti diskon 5% yang sehat, dan yang kedua yang
     * paling ingin dilihat pemilik toko.
     *
     * @param  array<string, mixed>  $report
     */
    private function buildDiscountSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Potongan Harga');
        $this->prepare($sheet, [
            'A' => 34, 'B' => 10, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 34, 'G' => 22,
        ]);

        $discount = $report['discount_summary'];

        $this->title($sheet, 'A1:G1', 'POTONGAN HARGA');
        $this->subtitle($sheet, 'A2:G2', $report['label']);

        $row = 4;
        $this->sectionHeader($sheet, "A{$row}:B{$row}", 'RINGKASAN POTONGAN');
        $row++;

        $lines = [
            ['Harga normal barang terjual', $discount['gross_sales'], self::FORMAT_CURRENCY],
            ['Total dipotong', $discount['total_given'], self::FORMAT_CURRENCY],
            ['Tertagih setelah potongan', $discount['net_sales'], self::FORMAT_CURRENCY],
            ['Baris penjualan berpotongan', $discount['items_discounted'], self::FORMAT_INTEGER],
            ['Di bawah batas untung', $discount['below_floor_total'], self::FORMAT_CURRENCY],
            ['Baris di bawah batas untung', $discount['below_floor_items'], self::FORMAT_INTEGER],
        ];

        $firstLine = $row;
        foreach ($lines as [$label, $value, $format]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($format);
            $row++;
        }
        $lastLine = $row - 1;

        $this->grid($sheet, "A{$firstLine}:B{$lastLine}");
        $sheet->getStyle("B{$firstLine}:B{$lastLine}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        // Dua baris terakhir ditebalkan: itu angka yang tiap barisnya butuh
        // persetujuan owner satu per satu.
        $sheet->getStyle('A'.($lastLine - 1).":B{$lastLine}")->getFont()->setBold(true);

        if ($discount['below_floor_lines'] === []) {
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);

            return;
        }

        $row += 2;
        $this->sectionHeader($sheet, "A{$row}:G{$row}", 'PENJUALAN DI BAWAH LANTAI UNTUNG');
        $row++;

        $head = $row;
        $this->tableHeader($sheet, "A{$head}:G{$head}", [
            'Barang', 'Qty', 'Harga normal', 'Harga jual', 'Batas', 'Alasan', 'Disetujui',
        ]);
        $row++;

        foreach ($discount['below_floor_lines'] as $line) {
            $sheet->setCellValue("A{$row}", $line['variant_name']);
            $sheet->setCellValue("B{$row}", $line['qty']);
            $sheet->setCellValue("C{$row}", $line['original_unit_price']);
            $sheet->setCellValue("D{$row}", $line['unit_price']);
            $sheet->setCellValue("E{$row}", $line['floor']);
            $sheet->setCellValue("F{$row}", $line['reason']);
            $sheet->setCellValue("G{$row}", $line['approved_by']);
            $row++;
        }
        $last = $row - 1;

        $this->grid($sheet, "A{$head}:G{$last}");
        $sheet->getStyle('B'.($head + 1).":B{$last}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
        $sheet->getStyle('C'.($head + 1).":E{$last}")->getNumberFormat()->setFormatCode(self::FORMAT_CURRENCY);
        // Alasan yang panjang dibungkus ke bawah, bukan dipotong oleh kolom
        // sebelahnya: alasan yang tidak terbaca membuat kolomnya tidak berguna.
        $sheet->getStyle('F'.($head + 1).":F{$last}")->getAlignment()->setWrapText(true);

        $sheet->freezePane('A'.($head + 1));
        $sheet->setAutoFilter("A{$head}:G{$last}");

        // Daftarnya dibatasi; kalau terpotong, lembarnya mengaku alih-alih
        // membiarkan pembacanya menjumlahkan sebagian dan menamainya seluruhnya.
        if ($discount['below_floor_items'] > count($discount['below_floor_lines'])) {
            $note = $last + 2;
            $sheet->setCellValue("A{$note}", 'Menampilkan '.count($discount['below_floor_lines'])
                .' baris potongan terbesar dari '.$discount['below_floor_items'].' baris.');
            $sheet->mergeCells("A{$note}:G{$note}");
            $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->getColor()->setRGB(self::MUTED_TEXT);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    /**
     * Lembar terakhir — sepuluh produk terlaris, dipecah per varian.
     *
     * Satu baris per VARIAN, dengan peringkat dan nama produk diulang di tiap
     * barisnya. Baris total per produk sengaja tidak disisipkan di antaranya:
     * kolom Qty yang memuat total dan rinciannya sekaligus akan terhitung dua
     * kali begitu seseorang menyeret SUM() ke bawahnya. Yang memisahkan satu
     * produk dari produk berikutnya adalah garis, bukan baris.
     *
     * @param  array<string, mixed>  $report
     */
    private function buildProductSheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Produk Terlaris');
        $this->prepare($sheet, ['A' => 11, 'B' => 34, 'C' => 24, 'D' => 14, 'E' => 18]);

        $this->title($sheet, 'A1:E1', 'PRODUK TERLARIS');
        $this->subtitle($sheet, 'A2:E2', $report['label'].' • 10 produk dengan qty terjual tertinggi');

        $head = 4;
        $this->tableHeader($sheet, "A{$head}:E{$head}", ['Peringkat', 'Produk', 'Varian', 'Qty Terjual', 'Omzet']);

        $row = $head + 1;
        $totalQty = 0;
        $totalRevenue = 0.0;
        $products = collect($report['top_products']);

        if ($products->isEmpty()) {
            $this->emptyRow($sheet, "A{$row}:E{$row}", 'Tidak ada penjualan pada periode ini.');
            $this->grid($sheet, "A{$head}:E{$row}");
            $sheet->freezePane('A'.($head + 1));

            return;
        }

        foreach ($products as $rank => $product) {
            $firstOfProduct = $row;

            foreach ($product['variants'] as $variant) {
                $sheet->setCellValue("A{$row}", $rank + 1);
                $sheet->setCellValue("B{$row}", $product['product_name']);
                $sheet->setCellValue("C{$row}", $variant['variant_name']);
                $sheet->setCellValue("D{$row}", $variant['total_qty']);
                $sheet->setCellValue("E{$row}", $variant['total_revenue']);

                $totalQty += (int) $variant['total_qty'];
                $totalRevenue += (float) $variant['total_revenue'];
                $row++;
            }

            // Pemisah antar produk. Produk pertama tidak perlu: kepala tabel
            // sudah jadi garisnya.
            if ($rank > 0) {
                $sheet->getStyle("A{$firstOfProduct}:E{$firstOfProduct}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB(self::GRID);
            }
        }

        $total = $row;
        $sheet->setCellValue("A{$total}", 'TOTAL 10 PRODUK TERATAS');
        $sheet->mergeCells("A{$total}:C{$total}");
        $sheet->setCellValue("D{$total}", $totalQty);
        $sheet->setCellValue("E{$total}", $totalRevenue);
        $sheet->getStyle("A{$total}:E{$total}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_FILL]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => self::BRAND]]],
        ]);

        $this->grid($sheet, "A{$head}:E{$total}");
        $sheet->getStyle('A'.($head + 1).":A{$total}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D'.($head + 1).":D{$total}")->getNumberFormat()->setFormatCode(self::FORMAT_INTEGER);
        $sheet->getStyle('E'.($head + 1).":E{$total}")->getNumberFormat()->setFormatCode(self::FORMAT_CURRENCY);

        $sheet->freezePane('A'.($head + 1));
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    /**
     * Lebar kolom, warna tab, dan garis bantu — sekali per lembar.
     *
     * Lebarnya ditulis eksplisit, bukan lewat auto-size: auto-size mengukur
     * tiap sel dengan metrik font dan membuat pembuatan file jauh lebih lambat,
     * sementara isi kolom di sini sudah diketahui bentuknya.
     *
     * @param  array<string, int>  $widths
     */
    private function prepare(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getTabColor()->setRGB(self::BRAND);
        // Garis bantu dimatikan: tabelnya sudah punya garis sendiri, dan dua
        // jaringan garis yang saling menimpa membuat lembarnya terlihat ramai.
        $sheet->setShowGridlines(false);
    }

    private function title(Worksheet $sheet, string $range, string $text): void
    {
        $first = explode(':', $range)[0];
        $sheet->setCellValue($first, $text);
        $sheet->mergeCells($range);
        $sheet->getStyle($first)->getFont()->setBold(true)->setSize(16)
            ->getColor()->setRGB(self::BRAND);
        $sheet->getRowDimension((int) filter_var($first, FILTER_SANITIZE_NUMBER_INT))->setRowHeight(24);
    }

    private function subtitle(Worksheet $sheet, string $range, string $text): void
    {
        $first = explode(':', $range)[0];
        $sheet->setCellValue($first, $text);
        $sheet->mergeCells($range);
        $sheet->getStyle($first)->getFont()->setSize(11)->getColor()->setRGB(self::MUTED_TEXT);
    }

    private function sectionHeader(Worksheet $sheet, string $range, string $text): void
    {
        $first = explode(':', $range)[0];
        $sheet->setCellValue($first, $text);
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::BRAND]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_FILL]],
        ]);
    }

    /**
     * @param  array<int, string>  $headings
     */
    private function tableHeader(Worksheet $sheet, string $range, array $headings): void
    {
        [$first] = explode(':', $range);
        $row = (int) filter_var($first, FILTER_SANITIZE_NUMBER_INT);

        foreach ($headings as $index => $heading) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$row, $heading);
        }

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function grid(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB(self::GRID);
    }

    private function emptyRow(Worksheet $sheet, string $range, string $text): void
    {
        $first = explode(':', $range)[0];
        $sheet->setCellValue($first, $text);
        $sheet->mergeCells($range);
        $sheet->getStyle($first)->getFont()->setItalic(true)->getColor()->setRGB(self::MUTED_TEXT);
        $sheet->getStyle($first)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Tanggal Excel yang sebenarnya, bukan string.
     */
    private function excelDate(string $date): float
    {
        return ExcelDate::PHPToExcel(Carbon::parse($date)->startOfDay());
    }

    /**
     * @param  array{from: string, to: string}  $range
     */
    private function rangeLabel(array $range): string
    {
        return Carbon::parse($range['from'])->locale('id')->translatedFormat('j F Y')
            .' – '.Carbon::parse($range['to'])->locale('id')->translatedFormat('j F Y');
    }

    /**
     * Nama tipe yang sama dengan yang dibaca owner di layar.
     *
     * `qris_static` di kolom Tipe adalah nama kolom database yang bocor ke
     * laporan; yang dikenali pemilik toko adalah "QRIS".
     */
    private function paymentTypeLabel(string $type): string
    {
        return match ($type) {
            'cash' => 'Tunai',
            'qris_static' => 'QRIS',
            default => 'Transfer',
        };
    }
}
