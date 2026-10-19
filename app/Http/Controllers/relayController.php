<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class relayController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | POST Relay Control
    |--------------------------------------------------------------------------
    */

    #[OA\Post(
        path: '/api/relay/control',
        summary: 'สั่งอัปเดตสถานะ Relay (0 หรือ 1)',
        tags: ['Relay'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'command'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'relay_1', description: 'ชื่อของอุปกรณ์'),
                    new OA\Property(property: 'command', type: 'string', enum: ['0', '1'], example: '1', description: '0 = ปิด, 1 = เปิด')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'อัปเดตคำสั่งสำเร็จ'),
            new OA\Response(response: 400, description: 'ข้อมูลไม่ถูกต้อง'),
            new OA\Response(response: 404, description: 'ไม่พบอุปกรณ์')
        ]
    )]
    public function updateStatus(Request $request)
    {
        // 1. ตรวจสอบข้อมูลที่ส่งมา
        $validated = $request->validate([
            'name' => 'required|string',
            'command' => 'required|in:0,1',
        ]);

        // 2. อัปเดตข้อมูลลงฐานข้อมูล (สมมติว่าตารางชื่อ devices)
        $updated = DB::table('devices')
            ->where('name', $validated['name'])
            ->update(['command' => $validated['command']]);

        // 3. ส่ง Response กลับ
        if ($updated) {
            return response()->json([
                'message' => 'Command updated successfully',
                'command' => $validated['command']
            ], 200);
        }

        return response()->json(['message' => 'Device not found or no changes made'], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | GET Relay Status (สำหรับให้ Python ดึงไปใช้งาน)
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/relay/status',
        summary: 'ดึงสถานะคำสั่ง Relay ปัจจุบัน',
        tags: ['Relay'],
        parameters: [
            new OA\Parameter(name: 'name', in: 'query', required: false, description: 'ชื่ออุปกรณ์ (ค่าเริ่มต้นคือ relay_1)', example: 'relay_1')
        ],
        responses: [
            new OA\Response(response: 200, description: 'ดึงข้อมูลสำเร็จ')
        ]
    )]
    public function getStatus(Request $request)
    {
        // รับค่าชื่ออุปกรณ์ผ่าน Query String เช่น ?name=relay_1
        $deviceName = $request->query('name', 'relay_1');

        $device = DB::table('devices')->where('name', $deviceName)->first();

        if ($device) {
            // ส่งค่ากลับไปเป็น JSON ให้ Python แกะอ่านได้ง่ายๆ
            return response()->json([
                'command' => (string) $device->command
            ], 200);
        }

        // หากไม่พบ ให้ส่ง 0 กลับไปเป็นค่า Defualt ป้องกันระบบค้าง
        return response()->json([
            'command' => '0',
            'message' => 'Device not found'
        ], 404);
    }
}
?>
