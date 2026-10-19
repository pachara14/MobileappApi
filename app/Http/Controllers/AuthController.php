<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Storage;

#[OA\Info(
    version: "1.0.0",
    description: "API Documentation for Mobile IoT Project",
    title: "Mobile IoT API"
)]
#[OA\Server(
    url: 'http://localhost/mobileiot/',
    description: 'Local Server'
)]
// เพิ่ม Security Scheme เพื่อให้หน้า Swagger มีช่องให้กรอก Bearer Token
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        summary: 'เข้าสู่ระบบ (Login)',
        description: 'เข้าสู่ระบบด้วย Username หรือ Email เพื่อรับ JWT Token',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: 'johndoe', description: 'Username หรือ Email'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123', description: 'รหัสผ่าน')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'เข้าสู่ระบบสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'เข้าสู่ระบบสำเร็จ'),
                        new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                            new OA\Property(property: 'role', type: 'string', example: 'user')
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'ข้อมูลไม่ถูกต้อง')
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = DB::table('users')->where($loginType, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'ชื่อผู้ใช้งาน อีเมล หรือรหัสผ่านไม่ถูกต้อง'
            ], 401);
        }

        $customClaims = [
            'sub' => $user->id,
            'role' => $user->role,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];

        $payload = JWTFactory::customClaims($customClaims)->make();
        $token = JWTAuth::encode($payload)->get();

        unset($user->password);

        return response()->json([
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'user' => $user
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'ออกจากระบบ (Logout)',
        description: 'ทำลาย JWT Token ปัจจุบัน (จำเป็นต้องแนบ Bearer Token ใน Header)',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'ออกจากระบบสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'ออกจากระบบสำเร็จ')
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'ไม่สามารถออกจากระบบได้')
        ]
    )]
    public function logout(Request $request)
    {
        try {
            // ให้ระบบ Parse Token จาก Header ก่อนแล้วค่อย Invalidate (นำเข้า Blacklist)
            JWTAuth::parseToken()->invalidate();

            return response()->json([
                'success' => true,
                'message' => 'ออกจากระบบสำเร็จ'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                // แสดงข้อความ Error ที่แท้จริงออกมาเพื่อดูว่าติดปัญหาที่จุดไหน
                'message' => 'ไม่สามารถออกจากระบบได้: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/refresh',
        summary: 'ต่ออายุ Token (Refresh Token)',
        description: 'ขอ JWT Token ใหม่แทน Token เดิม (จำเป็นต้องแนบ Bearer Token ใน Header)',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'รีเฟรชโทเค็นสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'รีเฟรชโทเค็นสำเร็จ'),
                        new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600)
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'ไม่สามารถรีเฟรชโทเค็นได้')
        ]
    )]
    public function refresh(Request $request)
    {
        try {
            // ใช้ parseToken() เพื่อดึงจาก Header อย่างถูกต้องก่อนทำการ refresh
            $newToken = JWTAuth::parseToken()->refresh();

            return response()->json([
                'success' => true,
                'message' => 'รีเฟรชโทเค็นสำเร็จ',
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                // แสดงข้อความ Error ที่แท้จริงออกมา
                'message' => 'ไม่สามารถรีเฟรชโทเค็นได้: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/register',
        summary: 'สมัครสมาชิก (Register)',
        description: 'สมัครสมาชิกใหม่ในระบบ',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                // เพิ่ม first_name, last_name (และ password_confirmation ถ้าคุณเปิดใช้งานไว้)
                required: ['username', 'email', 'password', 'password_confirmation', 'first_name', 'last_name'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'newuser123'),
                    new OA\Property(property: 'email', type: 'string', example: 'newuser@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', example: 'password123'),
                    new OA\Property(property: 'first_name', type: 'string', example: 'John'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Doe')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'สมัครสมาชิกสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'สมัครสมาชิกสำเร็จ'),
                        new OA\Property(property: 'user_id', type: 'integer', example: 2)
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'ข้อมูลไม่ถูกต้อง หรือ Email/Username ซ้ำซ้อน'),
            new OA\Response(response: 500, description: 'ข้อผิดพลาดจากเซิร์ฟเวอร์หรือฐานข้อมูล')
        ]
    )]
    public function register(Request $request)
    {
        // 1. เพิ่ม Validate ให้บังคับกรอกชื่อและนามสกุล
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed', // มี confirmed เพื่อเช็คคู่กับ password_confirmation
            'first_name' => 'required|string',
            'last_name' => 'required|string',
        ]);

        try {
            // 2. บันทึกข้อมูลทั้งหมดลงฐานข้อมูล
            $user = DB::table('users')->insertGetId([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'role' => 'user',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'สมัครสมาชิกสำเร็จ',
                'user_id' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
            ], 500);
        }
    }
    #[OA\Post(
        path: '/api/profile/upload',
        summary: 'อัปโหลดรูปโปรไฟล์',
        description: 'อัปโหลดรูปภาพโปรไฟล์ของผู้ใช้งานตาม Token ปัจจุบัน',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_image'],
                    properties: [
                        new OA\Property(
                            property: 'profile_image',
                            type: 'string',
                            format: 'binary',
                            description: 'ไฟล์รูปภาพ (jpg, jpeg, png ไม่เกิน 2MB)'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'อัปโหลดสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'อัปโหลดรูปโปรไฟล์สำเร็จ'),
                        new OA\Property(property: 'image_url', type: 'string', example: 'http://localhost/mobileiot/storage/profiles/profile_1_1717000000.png')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Token ไม่ถูกต้องหรือหมดอายุ'),
            new OA\Response(response: 422, description: 'ไฟล์ไม่ตรงเงื่อนไขหรือไม่ได้แนบไฟล์'),
            new OA\Response(response: 500, description: 'เกิดข้อผิดพลาด')
        ]
    )]
    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            // ถอด User ID จาก Token
            $payload = JWTAuth::parseToken()->getPayload();
            $userId = $payload->get('sub');

            // ค้นหา User ด้วย DB::table
            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบข้อมูลผู้ใช้'
                ], 404);
            }

            // ถ้ามีรูปเก่า ลบรูปเดิมออกจาก storage ก่อน
            if (!empty($user->profile_image) && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            // ตั้งชื่อไฟล์ใหม่ และบันทึกลงโฟลเดอร์ profiles (ใน storage/app/public)
            $file = $request->file('profile_image');
            $fileName = 'profile_' . $userId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profiles', $fileName, 'public');

            // อัปเดตชื่อพาธลงตาราง users คอลัมน์ profile_image
            DB::table('users')->where('id', $userId)->update([
                'profile_image' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'อัปโหลดรูปโปรไฟล์สำเร็จ',
                'image_url' => asset('storage/' . $path)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/profile/image',
        summary: 'ดึงรูปโปรไฟล์ตาม Token',
        description: 'ดึง URL รูปโปรไฟล์ของผู้ใช้งานจาก Bearer Token',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'ดึงข้อมูลสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'http://localhost/mobileiot/storage/profiles/profile_1_1717000000.png')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Token ไม่ถูกต้องหรือหมดอายุ'),
            new OA\Response(response: 404, description: 'ไม่พบผู้ใช้')
        ]
    )]
    public function getProfileImage(Request $request)
    {
        try {
            // ถอด User ID จาก Token
            $payload = JWTAuth::parseToken()->getPayload();
            $userId = $payload->get('sub');

            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบข้อมูลผู้ใช้'
                ], 404);
            }

            // เช็คว่ามีไฟล์รูปอยู่จริงใน Storage ไหม ถ้ามีให้ทำ Full URL ถ้าไม่มีให้เป็น null
            $imageUrl = null;
            if (!empty($user->profile_image) && Storage::disk('public')->exists($user->profile_image)) {
                $imageUrl = asset('storage/' . $user->profile_image);
            }

            return response()->json([
                'success' => true,
                'image_url' => $imageUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ], 500);
        }
    }
    #[OA\Post(
        path: '/api/resetpassword',
        summary: 'เปลี่ยนรหัสผ่าน (Change Password)',
        description: 'เปลี่ยนรหัสผ่านของผู้ใช้งานตาม Token ปัจจุบัน (ต้องระบุรหัสผ่านเดิมและรหัสผ่านใหม่)',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', example: 'password123', description: 'รหัสผ่านเดิม'),
                    new OA\Property(property: 'new_password', type: 'string', example: 'newpassword456', description: 'รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', example: 'newpassword456', description: 'ยืนยันรหัสผ่านใหม่')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'เปลี่ยนรหัสผ่านสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่อีกครั้ง')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'รหัสผ่านเดิมไม่ถูกต้อง หรือ รหัสใหม่ซ้ำกับรหัสเดิม'),
            new OA\Response(response: 401, description: 'Token ไม่ถูกต้องหรือหมดอายุ'),
            new OA\Response(response: 422, description: 'ข้อมูลที่ส่งมาไม่ถูกต้องตามเงื่อนไข Validation')
        ]
    )]
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        try {
            // ดึง User ID จาก JWT Token
            $payload = JWTAuth::parseToken()->getPayload();
            $userId = $payload->get('sub');

            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบข้อมูลผู้ใช้'
                ], 404);
            }

            // 1. ตรวจสอบว่ารหัสผ่านเดิมถูกต้องหรือไม่
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านเดิมไม่ถูกต้อง'
                ], 400);
            }

            // 2. ป้องกันไม่ให้ตั้งรหัสผ่านใหม่ซ้ำกับรหัสผ่านเดิม
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม'
                ], 400);
            }

            // 3. อัปเดตรหัสผ่านใหม่ลงตาราง users
            DB::table('users')->where('id', $userId)->update([
                'password' => Hash::make($request->new_password)
            ]);

            // 4. ทำลาย Token เดิมทิ้งทันที เพื่อความปลอดภัย บังคับให้ Login ใหม่
            JWTAuth::parseToken()->invalidate();

            return response()->json([
                'success' => true,
                'message' => 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่อีกครั้งด้วยรหัสผ่านใหม่'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ], 500);
        }
    }
    #[OA\Post(
        path: '/api/resetpassword/direct',
        summary: 'รีเซ็ตรหัสผ่านตรง (ไม่ต้องใช้ Token)',
        description: 'เปลี่ยนรหัสผ่านโดยระบุ Username หรือ Email พร้อมรหัสผ่านเดิมเพื่อตั้งรหัสผ่านใหม่ทันที',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: 'johndoe', description: 'Username หรือ Email'),
                    new OA\Property(property: 'current_password', type: 'string', example: 'oldpass123', description: 'รหัสผ่านเดิม'),
                    new OA\Property(property: 'new_password', type: 'string', example: 'newpass456', description: 'รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', example: 'newpass456', description: 'ยืนยันรหัสผ่านใหม่')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'เปลี่ยนรหัสผ่านสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'รีเซ็ตรหัสผ่านสำเร็จ สามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันที')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'รหัสผ่านใหม่ซ้ำกับรหัสผ่านเดิม'),
            new OA\Response(response: 401, description: 'รหัสผ่านเดิมไม่ถูกต้อง'),
            new OA\Response(response: 404, description: 'ไม่พบบัญชีผู้ใช้'),
            new OA\Response(response: 422, description: 'ข้อมูลไม่ถูกต้องตาม Validation')
        ]
    )]
    public function resetPasswordDirect(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        try {
            // เช็คว่ากรอกเป็น Email หรือ Username
            $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            $user = DB::table('users')->where($loginType, $request->login)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบข้อมูลผู้ใช้งานนี้ในระบบ'
                ], 404);
            }

            // ตรวจสอบรหัสผ่านเดิม
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านเดิมไม่ถูกต้อง'
                ], 401);
            }

            // เช็คไม่ให้รหัสผ่านใหม่ซ้ำกับของเดิม
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม'
                ], 400);
            }

            // อัปเดตรหัสผ่านใหม่
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'รีเซ็ตรหัสผ่านสำเร็จ สามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันที'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ], 500);
        }
    }
    #[OA\Post(
        path: '/api/profile/update',
        summary: 'แก้ไขข้อมูลผู้ใช้งาน (Update Profile)',
        description: 'แก้ไขข้อมูลส่วนตัวของผู้ใช้งานตาม Token ปัจจุบัน (ชื่อ, นามสกุล, username, email)',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'username', 'email'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'John', description: 'ชื่อจริง'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Doe', description: 'นามสกุล'),
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe_new', description: 'Username ใหม่'),
                    new OA\Property(property: 'email', type: 'string', example: 'john_new@example.com', description: 'Email ใหม่')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'แก้ไขข้อมูลสำเร็จ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'username', type: 'string', example: 'johndoe_new'),
                            new OA\Property(property: 'first_name', type: 'string', example: 'John'),
                            new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
                            new OA\Property(property: 'email', type: 'string', example: 'john_new@example.com'),
                            new OA\Property(property: 'role', type: 'string', example: 'user'),
                            new OA\Property(property: 'profile_image', type: 'string', nullable: true, example: 'profiles/profile_1_1717000000.png')
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Token ไม่ถูกต้องหรือหมดอายุ'),
            new OA\Response(response: 404, description: 'ไม่พบข้อมูลผู้ใช้'),
            new OA\Response(response: 422, description: 'Username หรือ Email ซ้ำกับผู้อื่น หรือข้อมูลไม่ถูกต้องตามเงื่อนไข'),
            new OA\Response(response: 500, description: 'เกิดข้อผิดพลาดในการบันทึกข้อมูล')
        ]
    )]
    public function updateProfile(Request $request)
    {
        try {
            // ดึง ID ผู้ใช้จาก Token
            $payload = JWTAuth::parseToken()->getPayload();
            $userId = $payload->get('sub');

            // ตรวจสอบข้อมูล โดยเว้น ID ตัวเองไว้กรณีไม่ได้เปลี่ยน username/email เดิม
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'username'   => 'required|string|max:255|unique:users,username,' . $userId,
                'email'      => 'required|string|email|max:255|unique:users,email,' . $userId,
            ]);

            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบข้อมูลผู้ใช้'
                ], 404);
            }

            // บันทึกข้อมูลที่แก้ไข
            DB::table('users')->where('id', $userId)->update([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'username'   => $request->username,
                'email'      => $request->email,
            ]);

            // ดึงข้อมูลล่าสุดกลับมาแสดง
            $updatedUser = DB::table('users')->where('id', $userId)->first();
            unset($updatedUser->password);

            return response()->json([
                'success' => true,
                'message' => 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว',
                'user'    => $updatedUser
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'ข้อมูลไม่ถูกต้อง',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ], 500);
        }
    }
}
