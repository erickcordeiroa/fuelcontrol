<?php

namespace Tests\Unit;

use App\Support\Polyline;
use PHPUnit\Framework\TestCase;

class PolylineTest extends TestCase
{
    public function test_encodes_the_reference_example(): void
    {
        $this->assertSame('_p~iF~ps|U_ulLnnqC_mqNvxq`@', Polyline::encode([[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]]));
    }

    public function test_decodes_back_to_the_same_points(): void
    {
        $points = [[-24.49068, -47.84203], [-24.48993, -47.8416], [-24.4933, -47.8469]];

        $this->assertSame($points, Polyline::decode(Polyline::encode($points)));
    }

    public function test_empty_input(): void
    {
        $this->assertSame('', Polyline::encode([]));
        $this->assertSame([], Polyline::decode(null));
    }
}
