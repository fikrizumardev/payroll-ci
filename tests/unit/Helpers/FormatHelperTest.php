<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class FormatHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('format');
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function rupiahProvider(): iterable
    {
        yield 'nol'      => [0, 'Rp 0'];
        yield 'ribuan'   => [1_000, 'Rp 1.000'];
        yield 'jutaan'   => [1_000_000, 'Rp 1.000.000'];
        yield 'contoh'   => [9_574_785, 'Rp 9.574.785'];
        yield 'miliar'   => [1_250_000_000, 'Rp 1.250.000.000'];
        yield 'negatif'  => [-25_000, '-Rp 25.000'];
    }

    #[DataProvider('rupiahProvider')]
    public function testRupiah(int $amount, string $expected): void
    {
        $this->assertSame($expected, rupiah($amount));
    }

    public function testRupiahWithoutPrefixAndStringInput(): void
    {
        $this->assertSame('9.574.785', rupiah('9574785', false));
        $this->assertSame('Rp 0', rupiah(null));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function terbilangProvider(): iterable
    {
        yield 'nol'       => [0, 'nol'];
        yield 'sebelas'   => [11, 'sebelas'];
        yield 'belasan'   => [19, 'sembilan belas'];
        yield 'seratus'   => [100, 'seratus'];
        yield 'seribu'    => [1_000, 'seribu'];
        yield 'ribuan'    => [1_115, 'seribu seratus lima belas'];
        yield 'sejuta'    => [1_000_000, 'satu juta'];
        yield 'contoh'    => [9_574_785, 'sembilan juta lima ratus tujuh puluh empat ribu tujuh ratus delapan puluh lima'];
        yield 'miliar'    => [1_250_000_000, 'satu miliar dua ratus lima puluh juta'];
        yield 'ribu-juta' => [21_600, 'dua puluh satu ribu enam ratus'];
    }

    #[DataProvider('terbilangProvider')]
    public function testTerbilang(int $number, string $expected): void
    {
        $this->assertSame($expected, terbilang($number));
    }

    public function testTerbilangRupiah(): void
    {
        $this->assertSame('Satu juta lima ratus ribu rupiah', terbilang_rupiah(1_500_000));
    }

    public function testTanggalId(): void
    {
        $this->assertSame('21 Okt 2026', tanggal_id('2026-10-21'));
        $this->assertSame('21 Oktober 2026', tanggal_id('2026-10-21', 'long'));
        $this->assertSame('Rabu, 21 Oktober 2026', tanggal_id('2026-10-21', 'full'));
        $this->assertSame('21 Okt 2026, 14:05', tanggal_id('2026-10-21 14:05:00', 'datetime'));
        $this->assertSame('Oktober 2026', tanggal_id('2026-10-01', 'month'));
        $this->assertSame('—', tanggal_id(null));
    }

    public function testBulanDanPersen(): void
    {
        $this->assertSame('Agustus', bulan_id(8));
        $this->assertSame('Agu', bulan_id(8, true));
        $this->assertSame('September 2026', periode_id(2026, 9));
        $this->assertSame('2,5%', percent_id(2.5));
        $this->assertSame('0,24%', percent_id('0.240'));
        $this->assertSame('4%', percent_id(4));
    }
}
