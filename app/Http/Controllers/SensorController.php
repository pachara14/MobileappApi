<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class SensorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | POST Sensor Data
    |--------------------------------------------------------------------------
    */

    #[OA\Post(
        path: '/api/sensor/data',
        summary: 'รับข้อมูล Sensor',
        description: 'รับข้อมูล Temperature และ Humidity จาก Arduino ผ่าน Python',
        tags: ['Sensor'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['temperature', 'humidity'],
                properties: [
                    new OA\Property(
                        property: 'temperature',
                        type: 'number',
                        format: 'float',
                        example: 22.70
                    ),
                    new OA\Property(
                        property: 'humidity',
                        type: 'number',
                        format: 'float',
                        example: 52.00
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'บันทึกข้อมูลสำเร็จ'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            ),
            new OA\Response(
                response: 422,
                description: 'ข้อมูลไม่ถูกต้อง'
            )
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'temperature' => [
                'required',
                'numeric',
                'between:-40,80'
            ],

            'humidity' => [
                'required',
                'numeric',
                'between:0,100'
            ],
        ]);

        $createdAt = now();

        $sensorId = DB::table('sensordata')->insertGetId([
            'temperature' => $validated['temperature'],
            'Humidity' => $validated['humidity'],
            'created_at' => $createdAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'บันทึกข้อมูล Sensor สำเร็จ',

            'data' => [
                'id' => $sensorId,
                'humidity' => $validated['humidity'],
                'temperature' => $validated['temperature'],
                'created_at' => $createdAt,
            ]
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | GET Sensor Data
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/sensor/data',
        summary: 'ดึงข้อมูล Sensor',
        description: 'ดึงข้อมูล Temperature และ Humidity จากฐานข้อมูล',
        tags: ['Sensor'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'ดึงข้อมูลสำเร็จ'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ]
    )]
    public function index()
    {
        // $data = DB::table('sensordata')
        //     ->orderBy('created_at', 'desc')
        //     ->get();
        $data = DB::table('sensordata')
            ->orderBy('created_at', 'desc') // เรียงจากเวลาล่าสุด (หรือใช้ 'id' แทนก็ได้)
            ->limit(100)                    // จำกัดแค่ 100 รายการ
            ->get();

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GET Latest Sensor Data
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/sensor/latest',
        summary: 'ดึงข้อมูล Sensor ล่าสุด',
        description: 'ดึงค่าข้อมูล Sensor ล่าสุดเพียงรายการเดียว',
        tags: ['Sensor'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'ดึงข้อมูลล่าสุดสำเร็จ'
            ),
            new OA\Response(
                response: 404,
                description: 'ไม่พบข้อมูล'
            )
        ]
    )]
    public function latest()
    {
        $sensor = DB::table('sensordata')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$sensor) {
            return response()->json([
                'success' => false,
                'message' => 'ยังไม่มีข้อมูล Sensor'
            ], 404);
        }

        return response()->json([
            'success' => true,

            'data' => [
                'id' => $sensor->id,
                'humidity' => $sensor->Humidity,
                'temperature' => $sensor->temperature,
                'created_at' => $sensor->created_at,
            ]
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | GET Sensor Data by Date
    |--------------------------------------------------------------------------
    */
    #[OA\Get(
        path: '/api/sensor/date',
        summary: 'ดึงข้อมูล Sensor ตามช่วงวันที่/เวลา',
        description: 'ดึงข้อมูล Sensor โดยระบุช่วงวันที่ (รูปแบบ YYYY-MM-DD) หรือวันที่และเวลา (YYYY-MM-DD HH:MM:SS)',
        tags: ['Sensor'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'start_date',
                in: 'query',
                required: true,
                description: 'วัน/เวลาที่เริ่มต้น (เช่น 2026-09-03 หรือ 2026-09-03 08:00:00)',
                schema: new OA\Schema(type: 'string', example: '2026-09-03')
            ),
            new OA\Parameter(
                name: 'end_date',
                in: 'query',
                required: false,
                description: 'วัน/เวลาที่สิ้นสุด (ถ้าไม่ระบุจะใช้ค่าเดียวกับ start_date)',
                schema: new OA\Schema(type: 'string', example: '2026-09-05')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'ดึงข้อมูลสำเร็จ'
            ),
            new OA\Response(
                response: 404,
                description: 'ไม่พบข้อมูล'
            ),
            new OA\Response(
                response: 422,
                description: 'รูปแบบวันที่ไม่ถูกต้อง'
            )
        ]
    )]
    public function getByDate(Request $request)
    {
        // รองรับรูปแบบวันที่ (YYYY-MM-DD) และวันที่พร้อมเวลา (YYYY-MM-DD HH:MM:SS)
        $dateTimeRule = 'regex:/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/';

        $validated = $request->validate([
            'start_date' => ['required', $dateTimeRule],
            'end_date' => ['nullable', $dateTimeRule],
        ]);

        $startInput = $validated['start_date'];
        $endInput = $validated['end_date'] ?? $startInput;

        // ถ้าไม่ระบุเวลามา ให้ครอบคลุมตั้งแต่ต้นวันจนถึงสิ้นวัน
        $start = str_contains($startInput, ':')
            ? Carbon::parse($startInput)
            : Carbon::parse($startInput)->startOfDay();

        $end = str_contains($endInput, ':')
            ? Carbon::parse($endInput)
            : Carbon::parse($endInput)->endOfDay();

        if ($end->lessThan($start)) {
            return response()->json([
                'success' => false,
                'message' => 'end_date ต้องไม่ก่อน start_date'
            ], 422);
        }

        // ดึงข้อมูลตามช่วงเวลาที่ระบุ เรียงจากใหม่ไปเก่า
        $sensors = DB::table('sensordata')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($sensors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบข้อมูล Sensor ในช่วงวันที่ ' . $start->toDateTimeString() . ' ถึง ' . $end->toDateTimeString()
            ], 404);
        }

        return response()->json([
            'success' => true,
            'start_date' => $start->toDateTimeString(),
            'end_date' => $end->toDateTimeString(),
            'total' => $sensors->count(),
            'data' => $sensors
        ]);
    }
}
