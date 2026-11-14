<?php

namespace Tests\Unit\Orthodontics;

use App\Modules\Orthodontics\Cbct\DicomHeaderParser;
use App\Modules\Orthodontics\Services\OrthodonticsException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Orthodontics\DicomFixture;

class DicomHeaderParserTest extends TestCase
{
    public function test_reads_explicit_vr_header_and_skips_sequences(): void
    {
        $h = (new DicomHeaderParser())->parse(DicomFixture::make(['instance' => 7, 'z' => -12.5, 'sequence' => true]));

        $this->assertSame('1.2.3.4.100', $h['study_instance_uid']);
        $this->assertSame('1.2.3.4.100.1', $h['series_instance_uid']);
        $this->assertSame('1.2.3.4.100.1.7', $h['sop_instance_uid']);
        $this->assertSame('CT', $h['modality']);
        $this->assertSame(7, $h['instance_number']);
        $this->assertSame(64, $h['rows']);
        $this->assertSame(32, $h['columns']);
        $this->assertSame([0.25, 0.25], $h['pixel_spacing']);
        $this->assertSame(1, $h['frames']);
        // Axial slice: normal = +z, so the position is the z coordinate.
        $this->assertEqualsWithDelta(-12.5, $h['slice_position'], 1e-9);
    }

    public function test_reads_implicit_vr_header(): void
    {
        $h = (new DicomHeaderParser())->parse(DicomFixture::make(['implicit' => true, 'instance' => 3]));
        $this->assertSame(3, $h['instance_number']);
        $this->assertSame(64, $h['rows']);
        $this->assertSame('1.2.840.10008.1.2', $h['transfer_syntax']);
    }

    public function test_rejects_non_dicom(): void
    {
        $this->expectException(OrthodonticsException::class);
        (new DicomHeaderParser())->parse(str_repeat("\0", 200));
    }
}
