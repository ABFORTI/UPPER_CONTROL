<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Carbon\Carbon;
use App\Http\Controllers\SolicitudController;

class BusinessTimeTest extends TestCase
{
    public function test_business_minutes_simple_weekday()
    {
        $ctl = new SolicitudController();
        $inicio = Carbon::parse('2025-10-14 09:00:00'); // martes
        $fin = Carbon::parse('2025-10-17 09:00:00'); // viernes a la misma hora => 72h
        $min = $ctl->businessMinutesBetween($inicio, $fin);
        $this->assertEquals(72 * 60, $min);
    }

    public function test_business_minutes_pause_weekend()
    {
        $ctl = new SolicitudController();
        $inicio = Carbon::parse('2025-10-17 09:00:00'); // viernes 9:00
        $fin = Carbon::parse('2025-10-20 09:00:00'); // lunes 9:00
        // viernes 9 -> viernes 23:59 = 14h59m ~ 14.983h, plus lunes 00:00->9:00 = 9h
        // but our implementation counts minutes per-day segments; expected total = friday 9:00->end + monday 0:00->9:00
    $min = $ctl->businessMinutesBetween($inicio, $fin);
    // friday remainder = minutos desde 2025-10-17 09:00 hasta 2025-10-18 00:00
    $fridayMinutes = (Carbon::parse('2025-10-18 00:00:00')->getTimestamp() - Carbon::parse('2025-10-17 09:00:00')->getTimestamp()) / 60;
    $mondayMinutes = (Carbon::parse('2025-10-20 09:00:00')->getTimestamp() - Carbon::parse('2025-10-20 00:00:00')->getTimestamp()) / 60;
    $expected = $fridayMinutes + $mondayMinutes;
    $this->assertEquals($expected, $min);
    }

    public function test_business_minutes_same_weekday_partial()
    {
        $ctl = new SolicitudController();
        $inicio = Carbon::parse('2025-10-15 10:30:00');
        $fin = Carbon::parse('2025-10-15 12:00:00');
        $min = $ctl->businessMinutesBetween($inicio, $fin);
        $this->assertEquals(90, $min);
    }
}
