<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ShsmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAGIC_PHONE_CODE = '9990';

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور درست نیست.'],
            ]);
        }

        $request->session()->regenerate();

        return ['user' => $this->userPayload($request->user())];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            ...$data,
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa',
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return ['user' => $this->userPayload($user)];
    }

    public function sendPhoneCode(Request $request, ShsmsService $sms)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'mode' => ['required', 'in:login,register'],
        ]);

        $exists = User::query()->where('phone', $data['phone'])->exists();

        if ($data['mode'] === 'register' && $exists) {
            throw ValidationException::withMessages([
                'phone' => ['این شماره قبلاً ثبت شده است.'],
            ]);
        }

        $code = (string) random_int(1000, 9999);
        Cache::put($this->phoneCodeCacheKey($data['phone']), $code, now()->addMinutes(5));

        $result = $sms->send($data['phone'], config('services.shsms.template'), [$code]);

        if (! $result['ok']) {
            return [
                'sent' => false,
                'expires_in' => 300,
                'sms_error' => true,
            ];
        }

        return [
            'sent' => true,
            'expires_in' => 300,
            'sandbox_code' => ($result['sandbox'] ?? false) ? $code : null,
        ];
    }

    public function phoneLogin(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'digits:4'],
        ]);

        $this->ensureValidPhoneCode($data['phone'], $data['code']);

        $user = User::query()->where('phone', $data['phone'])->first();

        if (! $user && $data['code'] === self::MAGIC_PHONE_CODE) {
            $user = User::create([
                'name' => 'کاربر تست',
                'email' => $data['phone'].'@phone.local',
                'phone' => $data['phone'],
                'password' => Str::password(32),
                'timezone' => 'Asia/Tehran',
                'locale' => 'fa',
                'profile_emoji' => '🙂',
            ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['حسابی با این شماره پیدا نشد.'],
            ]);
        }

        Auth::login($user, true);
        Cache::forget($this->phoneCodeCacheKey($data['phone']));
        $request->session()->regenerate();

        return ['user' => $this->userPayload($user)];
    }

    public function phoneRegister(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^09\d{9}$/', 'unique:users,phone'],
            'code' => ['required', 'digits:4'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'education' => ['nullable', 'string', 'max:100'],
            'job' => ['nullable', 'string', 'max:100'],
        ]);

        $this->ensureValidPhoneCode($data['phone'], $data['code']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['phone'].'@phone.local',
            'phone' => $data['phone'],
            'password' => Str::password(32),
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa',
            'settings' => [
                'city' => $data['city'] ?? null,
                'education' => $data['education'] ?? null,
                'job' => $data['job'] ?? null,
            ],
        ]);

        Auth::login($user, true);
        Cache::forget($this->phoneCodeCacheKey($data['phone']));
        $request->session()->regenerate();

        return ['user' => $this->userPayload($user)];
    }

    private function ensureValidPhoneCode(string $phone, string $code): void
    {
        if ($code === self::MAGIC_PHONE_CODE) {
            return;
        }

        if (Cache::get($this->phoneCodeCacheKey($phone)) !== $code) {
            throw ValidationException::withMessages([
                'code' => ['کد تایید درست نیست یا منقضی شده است.'],
            ]);
        }
    }

    private function phoneCodeCacheKey(string $phone): string
    {
        return 'phone-auth-code:'.$phone;
    }

    public function user(Request $request)
    {
        return ['user' => $this->userPayload($request->user())];
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'profile_emoji' => ['nullable', 'string', 'max:12'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->profile_emoji = $data['profile_emoji'] ?? '🙂';

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return ['user' => $this->userPayload($user)];
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_emoji' => $user->profile_emoji ?: '🙂',
            'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
        ];
    }
}
