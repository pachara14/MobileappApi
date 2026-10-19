<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;


Class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PATCH Change User Role (Superadmin Only)
    |--------------------------------------------------------------------------
    */

#[OA\Patch(
        path: '/api/admin/users/role',
        summary: 'เปลี่ยน Role ผู้ใช้งาน (เฉพาะ Superadmin)',
        description: 'อัปเดตสิทธิ์ (Role) ของผู้ใช้งาน โดยผู้ทำรายการต้องมี Role เป็น superadmin เท่านั้น',
        tags: ['Admin Management'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'role'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 2, description: 'ID ของผู้ใช้ที่ต้องการเปลี่ยน Role'),
                    new OA\Property(property: 'role', type: 'string', enum: ['superadmin', 'admin', 'user'], example: 'admin', description: 'Role ใหม่ที่ต้องการกำหนด')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'เปลี่ยน Role สำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'เปลี่ยน Role ของผู้ใช้เรียบร้อยแล้ว'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                            new OA\Property(property: 'role', type: 'string', example: 'admin')
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'ไม่อนุญาตให้แก้ไข Role ของตนเอง'),
            new OA\Response(response: 403, description: 'ไม่มีสิทธิ์เข้าถึง (ต้องเป็น superadmin เท่านั้น)'),
            new OA\Response(response: 404, description: 'ไม่พบผู้ใช้ที่ต้องการแก้ไข'),
            new OA\Response(response: 422, description: 'ข้อมูล Role ไม่ถูกต้องตาม ENUM')
        ]
    )]
    public function changeUserRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role'    => 'required|in:superadmin,admin,user',
        ]);

        try {
            // ดึง ID ผู้เรียกใช้จาก JWT Token
            $payload = JWTAuth::parseToken()->getPayload();
            $authUserId = $payload->get('sub');

            $currentUser = DB::table('users')->where('id', $authUserId)->first();

            // ตรวจสอบว่าผู้ทำรายการต้องเป็น superadmin เท่านั้น
            if (!$currentUser || $currentUser->role !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้ (เฉพาะ Superadmin เท่านั้น)'
                ], 403);
            }

            // ป้องกัน superadmin ปลดหรือเปลี่ยน Role ตัวเองโดยไม่ตั้งใจ
            if ((int)$authUserId === (int)$request->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่อนุญาตให้แก้ไข Role ของตนเอง'
                ], 400);
            }

            // ตรวจสอบผู้ใช้เป้าหมาย
            $targetUser = DB::table('users')->where('id', $request->user_id)->first();
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบข้อมูลผู้ใช้งานที่ต้องการเปลี่ยน Role'
                ], 404);
            }

            // อัปเดต Role
            DB::table('users')->where('id', $request->user_id)->update([
                'role' => $request->role
            ]);

            return response()->json([
                'success' => true,
                'message' => 'เปลี่ยน Role ของผู้ใช้เรียบร้อยแล้ว',
                'user' => [
                    'id'       => $targetUser->id,
                    'username' => $targetUser->username,
                    'role'     => $request->role
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ], 500);
        }
    }
}
