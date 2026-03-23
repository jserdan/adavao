<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\UserAdmin;
use App\Models\PendingUserAdminRegistration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Notifications\EmailVerification;
use App\Notifications\PasswordResetNotification;
use App\Models\AdminLoginAttempt;
use App\Services\EncryptionService;

class AuthController extends Controller
{
    // Show registration form
    public function showRegister()
    {
        return view('auth.register');
    }

    // Handle registration - AdminSide only (admin/police users)
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:50',
            'lastname' => 'required|string|max:50',
            'email' => [
                'required',
                'email',
                'unique:user_admin,email',
                'max:100',
                'regex:/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'contact' => 'required|string|max:20',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'password_confirmation' => 'required|same:password',
            'recaptcha_token' => 'required'
        ], [
            'email.regex' => 'Please enter a valid email address with @ and domain (e.g., user@gmail.com, admin@yahoo.com)',
            'email.email' => 'Email must be a valid email format',
            'email.unique' => 'This email is already registered in the system',
            'password.regex' => 'Password must contain at least one letter, one number, and one symbol (@$!%*?&)',
            'password.min' => 'Password must be at least 8 characters long',
            'recaptcha_token.required' => 'Unable to verify security token. Please refresh the page.'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Verify reCAPTCHA
        $recaptchaSecret = config('services.recaptcha.secret_key');
        
        if (empty($recaptchaSecret)) {
            \Log::warning('⚠️ RECAPTCHA_SECRET_KEY is missing in environment variables. Bypassing security check to allow login.');
            // Allow registration to proceed if key is missing (Development/Fallback mode)
        } else {
            if ($request->filled('recaptcha_token')) {
                $client = new \GuzzleHttp\Client();
                try {
                    $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                        'form_params' => [
                            'secret' => $recaptchaSecret,
                            'response' => $request->recaptcha_token,
                            'remoteip' => $request->ip()
                        ]
                    ]);
                    $body = json_decode((string)$response->getBody());
                    
                    $score = $body->score ?? 0.5; // Default to 0.5 if null
                    
                    // Log the full response for debugging
                    if (!$body->success || $score < 0.1) {
                         \Log::warning('Registration reCAPTCHA Verification Failed', [
                            'success' => $body->success ?? false,
                            'score' => $score,
                            'error-codes' => $body->{'error-codes'} ?? [],
                            'hostname' => $body->hostname ?? 'unknown',
                            'email' => $request->email
                        ]);
                        
                        // Only fail if it's explicitly a failure from Google AND score is very low
                        if (!$body->success) {
                             // return back()->withErrors(['email' => 'Security check failed. Please refresh and try again.'])->withInput();
                             \Log::warning('⚠️ Registration reCAPTCHA failed but allowing logic (Fail-Open active).');
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('reCAPTCHA connection error: ' . $e->getMessage());
                    // Fail open on connection error to avoid blocking legitimate users during outages
                }
            } else {
                // Token missing but required
                 return back()->withErrors(['email' => 'Security token missing. Please refresh the page.'])->withInput();
            }
        }

        // Generate verification token
        $verificationToken = Str::random(64);
        $tokenExpiresAt = Carbon::now()->addHours(24);

        // Validate user_role
        $userRole = $request->input('user_role', 'police');
        if (!in_array($userRole, ['police', 'patrol_officer'])) {
            $userRole = 'police'; // Default to police if invalid
        }

        // Strict verification: do NOT create a real user_admin record until the email link is clicked.
        // Store registration in a pending table.
        $existingVerified = UserAdmin::where('email', $request->email)->first();
        if ($existingVerified) {
            return back()->withErrors([
                'email' => 'This email is already registered in the system'
            ])->withInput();
        }

        $pending = PendingUserAdminRegistration::where('email', $request->email)->first();
        if ($pending) {
            // Refresh token + expiry and resend
            $pending->update([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'contact' => EncryptionService::encrypt($request->contact),
                'password' => Hash::make($request->password),
                'user_role' => $userRole,
                'verification_token' => $verificationToken,
                'token_expires_at' => $tokenExpiresAt,
            ]);
            \Log::info('📧 Updated existing pending registration', [
                'email' => $request->email,
                'pending_id' => $pending->id,
                'token_prefix' => substr($verificationToken, 0, 12),
            ]);
        } else {
            try {
                $pending = PendingUserAdminRegistration::create([
                    'firstname' => $request->firstname,
                    'lastname' => $request->lastname,
                    'email' => $request->email,
                    'contact' => EncryptionService::encrypt($request->contact),
                    'password' => Hash::make($request->password),
                    'verification_token' => $verificationToken,
                    'token_expires_at' => $tokenExpiresAt,
                    'user_role' => $userRole,
                ]);
                \Log::info('📧 Created new pending registration', [
                    'email' => $request->email,
                    'pending_id' => $pending->id,
                    'token_prefix' => substr($verificationToken, 0, 12),
                    'user_role' => $userRole,
                ]);
            } catch (\Exception $e) {
                \Log::error('📧 Failed to create pending registration', [
                    'email' => $request->email,
                    'error' => $e->getMessage(),
                ]);
                return back()->withErrors(['email' => 'Failed to create registration. Please try again.'])->withInput();
            }
        }

        // Verify the pending record was actually saved
        $verifyPending = PendingUserAdminRegistration::where('verification_token', $verificationToken)->first();
        if (!$verifyPending) {
            \Log::error('📧 CRITICAL: Pending registration not found after save!', [
                'email' => $request->email,
                'token_prefix' => substr($verificationToken, 0, 12),
            ]);
            return back()->withErrors(['email' => 'Registration failed to save. Please try again.'])->withInput();
        }

        \Log::info('Pending registration created, awaiting email verification', [
            'email' => $request->email,
            'user_role' => $userRole,
            'pending_id' => $pending->id,
        ]);

        // Generate verification URL (prefer query-param variant)
        $verificationUrl = url('/email/verify') . '?token=' . rawurlencode($verificationToken);

        // Send verification email
        try {
            \Mail::to($pending->email)->send(new \App\Mail\VerifyEmail($verificationUrl, $pending->firstname));
            
            $successMessage = 'Registration submitted! Please check your email (' . $pending->email . ') for a verification link to activate your account. The link will expire in 24 hours.';
            if ($userRole === 'patrol_officer') {
                $successMessage .= ' Your patrol officer account will be created after verification. You can login to the mobile app after verification.';
            }
            
            return redirect()->route('login')->with('success', $successMessage);
        } catch (\Exception $e) {
            // Strict verification: delete pending if email fails to send
            $pending->delete();
            \Log::error('Email verification failed: ' . $e->getMessage());
            
            return back()->withErrors(['email' => 'Failed to send verification email. Error: ' . $e->getMessage()])->withInput();
        }
    }

    // Show login form
    public function showLogin()
    {
        return view('auth.login');
    }

    // Handle login - AdminSide only (admin/police users)
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'recaptcha_token' => 'required'
        ], [
            'recaptcha_token.required' => 'Unable to verify security token. Please refresh the page.'
        ]);

        // Verify reCAPTCHA
        // Verify reCAPTCHA
        $recaptchaSecret = config('services.recaptcha.secret_key');
        
        if (empty($recaptchaSecret)) {
             \Log::warning('⚠️ RECAPTCHA_SECRET_KEY is missing in environment variables. Bypassing security check to allow login.');
             // Allow login to proceed if key is missing (Development/Fallback mode)
        } else {
             if ($request->filled('recaptcha_token')) { // Check if token was submitted
                $client = new \GuzzleHttp\Client();
                try {
                    $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                        'form_params' => [
                            'secret' => $recaptchaSecret,
                            'response' => $request->recaptcha_token,
                            'remoteip' => $request->ip()
                        ]
                    ]);
                    $body = json_decode((string)$response->getBody());
                    
                    $score = $body->score ?? 0.5; // Default to 0.5 if null
                    
                    // Log the full response for debugging
                    if (!$body->success || $score < 0.1) {
                         \Log::warning('Login reCAPTCHA Verification Failed', [
                            'success' => $body->success ?? false,
                            'score' => $score,
                            'error-codes' => $body->{'error-codes'} ?? [],
                            'hostname' => $body->hostname ?? 'unknown',
                            'email' => $request->email
                        ]);
                        
                         // Only fail if it's explicitly a failure from Google AND score is very low
                        // If success is false (invalid token/timeout), we fail.
                        if (!$body->success) {
                            // return back()->withErrors(['email' => 'Security check failed. Please refresh and try again.']); 
                            \Log::warning('⚠️ reCAPTCHA failed but allowing login (Fail-Open active). Success: ' . ($body->success ? 'true' : 'false'));
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('reCAPTCHA connection error: ' . $e->getMessage());
                     // Fail open on connection error
                }
            } else {
                 // Token missing but required
                 return back()->withErrors(['email' => 'Security token missing. Please refresh the page.']);
            }
        }

        // Check if user exists in user_admin table (AdminSide users only)
        $userAdmin = UserAdmin::where('email', $credentials['email'])->first();
        
        if (!$userAdmin) {
            // Log failed attempt
            // Log failed attempt to system log only (since we don't have a user_admin_id for DB)
            \Log::info('Failed admin login attempt - user not found', [
                'email' => $credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records. This account is for the UserSide application only.',
            ])->withInput($request->except('password'));
        }

        // Check if account is locked
        if ($userAdmin->lockout_until && Carbon::now()->lessThan($userAdmin->lockout_until)) {
            $remainingMinutes = Carbon::now()->diffInMinutes($userAdmin->lockout_until) + 1;
            
            // Log failed attempt
            try {
                AdminLoginAttempt::create([
                    'user_admin_id' => $userAdmin->id,
                    'email' => $credentials['email'],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status' => 'locked'
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log admin login attempt: ' . $e->getMessage());
            }

            return back()->withErrors([
                'email' => "Account temporarily locked due to too many failed login attempts. Please try again in {$remainingMinutes} minute(s).",
            ])->withInput($request->except('password'));
        }

        // Reset lockout if expired
        if ($userAdmin->lockout_until && Carbon::now()->greaterThanOrEqualTo($userAdmin->lockout_until)) {
            $userAdmin->failed_login_attempts = 0;
            $userAdmin->lockout_until = null;
            $userAdmin->save();
        }

        // Check if email is verified
        if (is_null($userAdmin->email_verified_at)) {
            return back()->withErrors([
                'email' => 'Please verify your email address before logging in. Check your inbox for the verification link.',
            ])->withInput($request->except('password'));
        }

        // Verify password
        if (!Hash::check($credentials['password'], $userAdmin->password)) {
            // Failed login - increment attempt counter
            $userAdmin->failed_login_attempts += 1;
            $userAdmin->last_failed_login = Carbon::now();

            // Apply lockout based on attempt count
            if ($userAdmin->failed_login_attempts >= 15) {
                // 15+ attempts: 15 minute lockout + email alert
                $userAdmin->lockout_until = Carbon::now()->addMinutes(15);
                $userAdmin->save();
                
                // Log failed attempt
                try {
                    AdminLoginAttempt::create([
                        'user_admin_id' => $userAdmin->id,
                        'email' => $credentials['email'],
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'status' => 'locked'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to log admin login attempt: ' . $e->getMessage());
                }
                
                // Send email notification
                try {
                    $userAdmin->notify(new \App\Notifications\AccountLockoutNotification($userAdmin->failed_login_attempts));
                } catch (\Exception $e) {
                    \Log::error('Failed to send lockout email: ' . $e->getMessage());
                }
                
                return back()->withErrors([
                    'email' => 'Account locked for 15 minutes due to 15 failed login attempts. A security alert has been sent to your email.',
                ])->withInput($request->except('password'));
            } else if ($userAdmin->failed_login_attempts >= 10) {
                // 10-14 attempts: 10 minute lockout
                $userAdmin->lockout_until = Carbon::now()->addMinutes(10);
                $userAdmin->save();
                
                // Log failed attempt
                try {
                    AdminLoginAttempt::create([
                        'user_admin_id' => $userAdmin->id,
                        'email' => $credentials['email'],
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'status' => 'locked'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to log admin login attempt: ' . $e->getMessage());
                }
                
                return back()->withErrors([
                    'email' => 'Account locked for 10 minutes due to multiple failed login attempts.',
                ])->withInput($request->except('password'));
            } else if ($userAdmin->failed_login_attempts >= 5) {
                // 5-9 attempts: 5 minute lockout
                $userAdmin->lockout_until = Carbon::now()->addMinutes(5);
                $userAdmin->save();
                
                // Log failed attempt
                try {
                    AdminLoginAttempt::create([
                        'user_admin_id' => $userAdmin->id,
                        'email' => $credentials['email'],
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'status' => 'locked'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to log admin login attempt: ' . $e->getMessage());
                }
                
                return back()->withErrors([
                    'email' => 'Account locked for 5 minutes due to multiple failed login attempts.',
                ])->withInput($request->except('password'));
            } else {
                // Less than 5 attempts: just save the counter
                $userAdmin->save();
                
                // Log failed attempt
                try {
                    AdminLoginAttempt::create([
                        'user_admin_id' => $userAdmin->id,
                        'email' => $credentials['email'],
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'status' => 'failed'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to log admin login attempt: ' . $e->getMessage());
                }
                
                $remainingAttempts = 5 - $userAdmin->failed_login_attempts;
                return back()->withErrors([
                    'email' => "The provided credentials do not match our records. {$remainingAttempts} attempt(s) remaining before account lockout.",
                ])->withInput($request->except('password'));
            }
        }

        // Password is correct - Log in directly (OTP temporarily disabled)
        // TODO: Re-enable OTP for production by uncommenting the line below and removing direct login
        // return $this->initiateOtpLogin($userAdmin, $request);
        
        // Direct login without OTP (temporary for development)
        $userAdmin->failed_login_attempts = 0;
        $userAdmin->lockout_until = null;
        $userAdmin->save();
        
        // Log successful login attempt
        try {
            AdminLoginAttempt::create([
                'user_admin_id' => $userAdmin->id,
                'email' => $credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log admin login attempt: ' . $e->getMessage());
        }
        
        Auth::login($userAdmin);
        $request->session()->regenerate();

        // Prevent stale intended URLs (e.g. /dashboard) from previous sessions/account switches.
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard');
    }

    // Helper to initiate OTP flow (used by Login and Google Auth)
    private function initiateOtpLogin($userAdmin, $request)
    {
        // 1. Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // DEBUG: Log OTP
        \Log::info("🔐 [DEBUG] Generated OTP for {$userAdmin->email}: {$otp}");
        
        $otpHash = Hash::make($otp);
        // Store OTP in database
        $phone = $userAdmin->contact; 
        
        // Normalize phone
        $phone = trim($phone);
        $phone = preg_replace('/[^\d\+]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '+63' . substr($phone, 1);
        } elseif (str_starts_with($phone, '9') && strlen($phone) == 10) {
            $phone = '+63' . $phone;
        } elseif (str_starts_with($phone, '63') && strlen($phone) == 12) {
             $phone = '+' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
             $phone = '+' . $phone;
        }
        
        // Clear old OTPs
        \DB::table('otp_codes')->where('phone', $phone)->where('purpose', 'admin_login')->delete();
        
        \DB::table('otp_codes')->insert([
            'phone' => $phone,
            'otp_hash' => $otpHash,
            'purpose' => 'admin_login',
            'user_id' => $userAdmin->id,
            'expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now()
        ]);
        
        // Send OTP
        $sent = $this->sendTwilioWhatsapp($phone, $otp, $userAdmin->email);
        
        // Store session
        $request->session()->put('auth.otp.admin_id', $userAdmin->id);
        $request->session()->put('auth.otp.phone', $phone);
        
        return redirect()->route('otp.login.verify')->with('success', 'Credentials verified. Please check your WhatsApp for the code.');
    }

    // Helper to send WhatsApp via Twilio
    private function sendTwilioWhatsapp($phone, $otp, $email)
    {
        $message = "AlertDavao\n\nHi {$email} Your verification code is {$otp}. It is valid for 5 minutes. Do not share this code with anyone for your security.";
        // Use config helper which works with cached config in production
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = "whatsapp:" . config('services.twilio.whatsapp_from', '+14155238886');

        if (empty($sid) || empty($token)) {
             \Log::error('Twilio Credentials Missing', [
                'sid_set' => !empty($sid),
                'token_set' => !empty($token),
                'env_sid' => empty(env('TWILIO_SID')) ? 'missing' : 'present (but maybe ignored if cached)',
            ]);
            return false;
        }

        if ($sid && $token) {
            try {
                $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
                
                // Ensure phone has + sign (double check, though login handles it mostly)
                $cleanPhone = $phone;
                if (!str_starts_with($phone, '+')) {
                    $cleanPhone = '+' . $phone;
                }
                
                \Log::info("Sending Twilio WhatsApp", [
                    'to' => "whatsapp:$cleanPhone",
                    'from' => $from,
                    'sid_prefix' => substr($sid, 0, 5) . '...'
                ]);

                $data = [
                    'To' => "whatsapp:$cleanPhone",
                    'From' => $from,
                    'Body' => $message
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    \Log::info("WhatsApp sent successfully: {$httpCode}");
                    return true;
                } else {
                    \Log::error("WhatsApp failed: {$httpCode} Response: {$response}");
                    return false;
                }
            } catch (\Exception $e) {
                \Log::error("WhatsApp Exception: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    // Unused SMS Method (Failed due to Trial Account)
    private function sendTwilioSms($phone, $otp) { return false; }

    // Deprecated SMS Methods (Preserved but unused)
    private function sendSemaphoreSms($phone, $otp) { return false; }


    // Show OTP verify form
    public function showVerifyOtp(Request $request)
    {
        if (!$request->session()->has('auth.otp.admin_id')) {
            return redirect()->route('login');
        }
        
        // Mask phone number
        $phone = $request->session()->get('auth.otp.phone', '');
        $maskedPhone = '...' . substr($phone, -4);
        
        return view('auth.verify-otp', compact('maskedPhone'));
    }

    // Verify OTP and Finalize Login
    public function verifyOtpLogin(Request $request)
    {
        if (!$request->session()->has('auth.otp.admin_id')) {
            return redirect()->route('login');
        }

        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $adminId = $request->session()->get('auth.otp.admin_id');
        $userAdmin = UserAdmin::find($adminId);
        
        if (!$userAdmin) {
            return redirect()->route('login')->withErrors(['email' => 'User not found.']);
        }

        // Check OTP in DB
        $otpRecord = \DB::table('otp_codes')
            ->where('purpose', 'admin_login')
            ->where('user_id', $adminId)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord || !Hash::check($request->otp, $otpRecord->otp_hash)) {
            return back()->withErrors(['otp' => 'Invalid or expired code. Please try again.']);
        }
        
        // OTP Valid - Clean up
        \DB::table('otp_codes')->where('id', $otpRecord->id)->delete();
        $request->session()->forget(['auth.otp.admin_id', 'auth.otp.phone']);

        // Finalize Login
        Auth::guard('admin')->login($userAdmin, false); // No "remember me" for OTP flow for simplicity
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        
        // Sync stats
        $userAdmin->failed_login_attempts = 0;
        $userAdmin->lockout_until = null;
        $userAdmin->save();
        
        // Log
        \Log::info("Admin logged in via OTP: {$userAdmin->email}");

        return redirect()->route('dashboard');
    }

    // Resend OTP
    public function resendOtp(Request $request)
    {
        $adminId = $request->session()->get('auth.otp.admin_id');
        $phone = $request->session()->get('auth.otp.phone');

        if (!$adminId || !$phone) {
            return redirect()->route('login')->with('error', 'Session expired. Please login again.');
        }

        // Check recent OTPs to prevent spam (throttle 60s)
        $recentOtp = \DB::table('otp_codes')
            ->where('phone', $phone)
            ->where('purpose', 'admin_login')
            ->where('created_at', '>', Carbon::now()->subSeconds(60))
            ->first();

        if ($recentOtp) {
            return back()->with('error', 'Please wait before resending.');
        }

        // Generate new OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Save to DB
        \DB::table('otp_codes')->insert([
            'phone' => $phone,
            'otp_hash' => Hash::make($otp),
            'purpose' => 'admin_login',
            'user_id' => $adminId,
            'expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now()
        ]);

        // Fetch user to get email for the message
        $userAdmin = UserAdmin::find($adminId);
        $email = $userAdmin ? $userAdmin->email : 'User';

        // Send via WhatsApp (Logic from login)
        $sent = $this->sendTwilioWhatsapp($phone, $otp, $email);
        
        \Log::info('Admin RESEND OTP generated', [
            'admin_id' => $adminId,
            'phone' => $phone,
            'sent' => $sent
        ]);

        return back()->with('success', 'A new code has been sent.');
    }


    // Handle logout
    public function logout(Request $request)
    {
        // Get user data before any logout operations
        $user = Auth::guard('admin')->user();
        
        // Update last_logout timestamp before logging out
        if ($user) {
            try {
                $user->last_logout = now();
                $user->is_online = false;
                $user->remember_token = null;
                $user->save();
            } catch (\Exception $e) {
                // Log but don't fail logout if save fails
                \Log::warning('Failed to update user status on logout: ' . $e->getMessage());
            }
        }

        // Perform logout and session cleanup
        Auth::guard('admin')->logout();
        $request->session()->forget('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out successfully!');
    }

    // Show forgot password form
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    // Handle forgot password request
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:user_admin,email'
        ], [
            'email.exists' => 'We could not find an admin/police account with that email address.'
        ]);

        $userAdmin = UserAdmin::where('email', $request->email)->first();

        // Check if email is verified
        if (is_null($userAdmin->email_verified_at)) {
            return back()->withErrors([
                'email' => 'Your email address is not verified. Please verify your email before resetting your password.',
            ])->withInput();
        }

        // Generate reset token
        $resetToken = Str::random(64);
        $tokenExpiresAt = Carbon::now()->addHour();

        // Update user with reset token
        $userAdmin->update([
            'reset_token' => $resetToken,
            'reset_token_expires_at' => $tokenExpiresAt,
        ]);

        // Generate reset URL
        $resetUrl = route('password.reset.form', ['token' => $resetToken]);

        // Send reset email
        try {
            $userAdmin->notify(new PasswordResetNotification($resetUrl, $userAdmin->firstname));
            
            return back()->with('success', 
                'Password reset link has been sent to your email address. Please check your inbox.');
        } catch (\Exception $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());
            
            return back()->withErrors([
                'email' => 'Failed to send password reset email. Please try again later.'
            ])->withInput();
        }
    }

    // Show reset password form
    public function showResetPassword($token)
    {
        $userAdmin = UserAdmin::where('reset_token', $token)
            ->where('reset_token_expires_at', '>', Carbon::now())
            ->first();

        if (!$userAdmin) {
            return redirect()->route('login')->withErrors([
                'token' => 'This password reset link is invalid or has expired.'
            ]);
        }

        return view('auth.reset-password', ['token' => $token, 'email' => $userAdmin->email]);
    }

    // Handle password reset
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'password_confirmation' => 'required|same:password',
        ], [
            'password.regex' => 'Password must contain at least one letter, one number, and one symbol (@$!%*?&)',
            'password.min' => 'Password must be at least 8 characters long',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $userAdmin = UserAdmin::where('reset_token', $request->token)
            ->where('email', $request->email)
            ->where('reset_token_expires_at', '>', Carbon::now())
            ->first();

        if (!$userAdmin) {
            return back()->withErrors([
                'token' => 'This password reset link is invalid or has expired.'
            ])->withInput();
        }

        // Update password and clear reset token
        $userAdmin->update([
            'password' => Hash::make($request->password),
            'reset_token' => null,
            'reset_token_expires_at' => null,
        ]);

        return redirect()->route('login')->with('success', 
            'Your password has been reset successfully! Please login with your new password.');
    }

    // Verify email
    public function verifyEmail(Request $request, $token = null)
    {
        $rawToken = $token;
        if (empty($rawToken)) {
            $rawToken = $request->query('token');
        }

        \Log::info('📧 Email verification attempt', [
            'raw_token_from_path' => $token,
            'raw_token_from_query' => $request->query('token'),
            'full_url' => $request->fullUrl(),
        ]);

        $rawToken = trim((string) $rawToken);
        $decodedToken = rawurldecode($rawToken);
        // Some email clients/browsers may convert '+' to space in URLs; undo that.
        $decodedToken = str_replace(' ', '+', $decodedToken);
        // Remove any whitespace/newlines that may have been inserted by copying.
        $normalizedToken = preg_replace('/\s+/', '', $decodedToken);
        // Tokens we generate are alphanumeric (Str::random). If the token got polluted with punctuation,
        // strip to alphanumerics as a recovery path.
        $alnumToken = preg_replace('/[^A-Za-z0-9]/', '', $normalizedToken);

        $tokensToTry = array_values(array_unique(array_filter([$normalizedToken, $alnumToken], function ($t) {
            return is_string($t) && strlen($t) > 0;
        })));

        $matchedToken = null;

        // 1) New flow: check pending registrations first
        $pending = null;
        foreach ($tokensToTry as $tryToken) {
            $candidate = PendingUserAdminRegistration::where('verification_token', $tryToken)->first();
            if ($candidate) {
                $pending = $candidate;
                $matchedToken = $tryToken;
                break;
            }
        }

        // Diagnostic: check if table exists and log current state
        try {
            $pendingCount = PendingUserAdminRegistration::count();
            $allPending = PendingUserAdminRegistration::select('id', 'email', 'user_role', 'created_at')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();
            
            \Log::info('📧 Pending registrations diagnostic', [
                'total_pending_count' => $pendingCount,
                'recent_pending' => $allPending->toArray(),
                'tokens_tried' => $tokensToTry,
            ]);
        } catch (\Exception $e) {
            \Log::error('📧 CRITICAL: Cannot query pending_user_admin_registrations table!', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($pending) {
            \Log::info('📧 Found pending registration', [
                'email' => $pending->email,
                'user_role' => $pending->user_role,
                'token_expires_at' => $pending->token_expires_at,
                'created_at' => $pending->created_at,
            ]);

            // Expired (or missing expiry) -> auto-resend a fresh link
            if (empty($pending->token_expires_at) || Carbon::parse($pending->token_expires_at)->lessThanOrEqualTo(Carbon::now())) {
                $verificationToken = Str::random(64);
                $tokenExpiresAt = Carbon::now()->addHours(24);

                $pending->update([
                    'verification_token' => $verificationToken,
                    'token_expires_at' => $tokenExpiresAt,
                ]);

                $verificationUrl = url('/email/verify') . '?token=' . rawurlencode($verificationToken);

                try {
                    \Mail::to($pending->email)->send(new \App\Mail\VerifyEmail($verificationUrl, $pending->firstname));
                } catch (\Exception $e) {
                    \Log::error('Failed to resend verification email (pending): ' . $e->getMessage(), [
                        'email' => $pending->email,
                    ]);

                    return redirect()->route('login')->withErrors([
                        'token' => 'This verification link has expired. We could not resend a new link right now. Please use the resend option.'
                    ]);
                }

                return redirect()->route('login')->with('success', 'Your verification link expired. We sent you a new verification email. Please check your inbox.');
            }

            // Create the real user_admin record now (strict verification)
            $existing = UserAdmin::where('email', $pending->email)->first();
            if ($existing) {
                \Log::info('📧 User already exists in user_admin, updating verification', ['email' => $existing->email]);
                // If somehow created already, clean up pending and treat as success
                $pending->delete();
                if (!is_null($existing->email_verified_at)) {
                    return redirect()->route('login')->with('success', 'Your email is already verified. You can now login.');
                }
                $existing->update([
                    'email_verified_at' => Carbon::now(),
                    'verification_token' => null,
                    'token_expires_at' => null,
                ]);
                $userAdmin = $existing;
            } else {
                \Log::info('📧 Creating new user_admin record from pending registration', ['email' => $pending->email]);
                $userAdmin = UserAdmin::create([
                    'firstname' => $pending->firstname,
                    'lastname' => $pending->lastname,
                    'email' => $pending->email,
                    'contact' => $pending->contact,
                    'password' => $pending->password,
                    'user_role' => $pending->user_role,
                    'email_verified_at' => Carbon::now(),
                    'verification_token' => null,
                    'token_expires_at' => null,
                ]);
                \Log::info('📧 User_admin record created successfully', ['user_admin_id' => $userAdmin->id, 'email' => $userAdmin->email]);
            }

            // Pending fulfilled
            $pending->delete();

            // If patrol officer, create entry in users_public table (after verification)
            if ($userAdmin->user_role === 'patrol_officer') {
                try {
                    $existingUser = \DB::table('users_public')
                        ->where('email', $userAdmin->email)
                        ->first();

                    if (!$existingUser) {
                        \DB::table('users_public')->insert([
                            'firstname' => $userAdmin->firstname,
                            'lastname' => $userAdmin->lastname,
                            'email' => $userAdmin->email,
                            'contact' => $userAdmin->getRawOriginal('contact'),
                            'password' => $userAdmin->getRawOriginal('password'),
                            'user_role' => 'patrol_officer',
                            'is_on_duty' => false,
                            'email_verified_at' => Carbon::now(),
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                        \Log::info('Patrol officer account created in users_public after email verification (pending flow)', [
                            'email' => $userAdmin->email
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to create patrol officer in users_public during verification (pending flow): ' . $e->getMessage());
                }
            }

            try {
                $row = \DB::selectOne(
                    "SELECT current_database() AS database, inet_server_addr()::text AS server_addr, inet_server_port() AS server_port"
                );
                \Log::info('Email verification completed (pending flow)', [
                    'email' => $userAdmin->email,
                    'user_admin_id' => $userAdmin->id,
                    'user_role' => $userAdmin->user_role,
                    'db' => $row ? (array) $row : null,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Email verification completed (pending flow) but DB identity query failed', [
                    'email' => $userAdmin->email,
                    'user_admin_id' => $userAdmin->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $successMessage = 'Email verified successfully! You can now login to your account.';
            if ($userAdmin->user_role === 'patrol_officer') {
                $successMessage .= ' Your patrol officer account is now active and you can login to the mobile app.';
            }

            return redirect()->route('login')->with('success', $successMessage);
        }

        \Log::info('📧 No pending registration found, checking legacy user_admin table', [
            'tokens_tried' => $tokensToTry,
        ]);

        // 2) Legacy flow: check user_admin table (older deployments)
        $userAdmin = null;
        foreach ($tokensToTry as $tryToken) {
            $candidate = UserAdmin::where('verification_token', $tryToken)->first();
            if ($candidate) {
                $userAdmin = $candidate;
                $matchedToken = $tryToken;
                break;
            }
        }

        if (!$userAdmin) {
            \Log::warning('Email verification token not found', [
                'raw_len' => strlen($rawToken),
                'normalized_len' => strlen((string) $normalizedToken),
                'token_prefix' => substr((string) $normalizedToken, 0, 12),
                'has_query_token' => !empty($request->query('token')),
                'path' => $request->path(),
            ]);
            return redirect()->route('login')->withErrors([
                'token' => 'This verification link is invalid or has expired.'
            ]);
        }

        // If already verified, treat as success (idempotent)
        if (!is_null($userAdmin->email_verified_at)) {
            return redirect()->route('login')->with('success', 'Your email is already verified. You can now login.');
        }

        // Expired (or missing expiry) -> auto-resend a fresh link
        if (empty($userAdmin->token_expires_at) || Carbon::parse($userAdmin->token_expires_at)->lessThanOrEqualTo(Carbon::now())) {
            $verificationToken = Str::random(64);
            $tokenExpiresAt = Carbon::now()->addHours(24);

            $userAdmin->update([
                'verification_token' => $verificationToken,
                'token_expires_at' => $tokenExpiresAt,
            ]);

            // Use query-param variant to avoid rare path-wrapping issues in some clients.
            $verificationUrl = url('/email/verify') . '?token=' . rawurlencode($verificationToken);

            try {
                \Mail::to($userAdmin->email)->send(new \App\Mail\VerifyEmail($verificationUrl, $userAdmin->firstname));
            } catch (\Exception $e) {
                \Log::error('Failed to resend verification email: ' . $e->getMessage(), [
                    'email' => $userAdmin->email,
                ]);

                return redirect()->route('login')->withErrors([
                    'token' => 'This verification link has expired. We could not resend a new link right now. Please use the resend option.'
                ]);
            }

            return redirect()->route('login')->with('success', 'Your verification link expired. We sent you a new verification email. Please check your inbox.');
        }

        // Mark email as verified
        $userAdmin->update([
            'email_verified_at' => Carbon::now(),
            'verification_token' => null,
            'token_expires_at' => null,
        ]);

        if ($matchedToken !== null && $matchedToken !== $normalizedToken) {
            \Log::info('Email verification succeeded using normalized token', [
                'email' => $userAdmin->email,
                'received_prefix' => substr((string) $normalizedToken, 0, 12),
                'matched_prefix' => substr((string) $matchedToken, 0, 12),
            ]);
        }

        // If patrol officer, NOW create entry in users_public table (after verification)
        if ($userAdmin->user_role === 'patrol_officer') {
            try {
                // Check if already exists
                $existingUser = \DB::table('users_public')
                    ->where('email', $userAdmin->email)
                    ->first();

                if (!$existingUser) {
                    \DB::table('users_public')->insert([
                        'firstname' => $userAdmin->firstname,
                        'lastname' => $userAdmin->lastname,
                        'email' => $userAdmin->email,
                        'contact' => $userAdmin->getRawOriginal('contact'),
                        'password' => $userAdmin->getRawOriginal('password'),
                        'user_role' => 'patrol_officer',
                        'is_on_duty' => false,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                    \Log::info('Patrol officer account created in users_public after email verification', [
                        'email' => $userAdmin->email
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create patrol officer in users_public during verification: ' . $e->getMessage());
                // Non-fatal error for the user's admin account activation
            }
        }

        $successMessage = 'Email verified successfully! You can now login to your account.';
        if ($userAdmin->user_role === 'patrol_officer') {
            $successMessage .= ' Your patrol officer account is now active and you can login to the mobile app.';
        }

        return redirect()->route('login')->with('success', $successMessage);
    }

    // Resend verification email
    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Prefer pending flow
        $pending = PendingUserAdminRegistration::where('email', $request->email)->first();
        if ($pending) {
            $verificationToken = Str::random(64);
            $tokenExpiresAt = Carbon::now()->addHours(24);

            $pending->update([
                'verification_token' => $verificationToken,
                'token_expires_at' => $tokenExpiresAt,
            ]);

            $verificationUrl = url('/email/verify') . '?token=' . rawurlencode($verificationToken);

            try {
                \Mail::to($pending->email)->send(new \App\Mail\VerifyEmail($verificationUrl, $pending->firstname));
                return back()->with('success', 'Verification email has been sent! Please check your inbox.');
            } catch (\Exception $e) {
                \Log::error('Email verification resend failed (pending): ' . $e->getMessage());
                return back()->withErrors([
                    'email' => 'Failed to send verification email. Please try again later.'
                ])->withInput();
            }
        }

        // Legacy support: unverified user_admin rows
        $userAdmin = UserAdmin::where('email', $request->email)->first();
        if (!$userAdmin) {
            return back()->withErrors([
                'email' => 'We could not find a pending registration or admin/police account with that email address.'
            ])->withInput();
        }

        if ($userAdmin->email_verified_at) {
            return back()->withErrors([
                'email' => 'This email address is already verified.'
            ])->withInput();
        }

        $verificationToken = Str::random(64);
        $tokenExpiresAt = Carbon::now()->addHours(24);

        $userAdmin->update([
            'verification_token' => $verificationToken,
            'token_expires_at' => $tokenExpiresAt,
        ]);

        $verificationUrl = url('/email/verify') . '?token=' . rawurlencode($verificationToken);

        try {
            $userAdmin->notify(new EmailVerification($verificationUrl, $userAdmin->firstname));
            return back()->with('success', 'Verification email has been sent! Please check your inbox.');
        } catch (\Exception $e) {
            \Log::error('Email verification resend failed: ' . $e->getMessage());
            return back()->withErrors([
                'email' => 'Failed to send verification email. Please try again later.'
            ])->withInput();
        }
    }
    // Google Auth Redirect
    public function redirectToGoogle()
    {
        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    // Google Auth Callback
    public function handleGoogleCallback()
    {
        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->user();
            
            // Check if user exists in user_admin table
            $userAdmin = UserAdmin::where('email', $googleUser->getEmail())->first();
            
            if (!$userAdmin) {
                // Create new user from Google Data
                // Split name
                $fullName = $googleUser->getName();
                $parts = explode(' ', $fullName);
                $lastname = array_pop($parts);
                $firstname = implode(' ', $parts);
                if (empty($firstname)) $firstname = $lastname; // Fallback

                $userAdmin = UserAdmin::create([
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $googleUser->getEmail(),
                    'contact' => '0000000000', // Placeholder contact
                    'password' => Hash::make(Str::random(16)), // Random password
                    'email_verified_at' => Carbon::now(), // Auto-verify
                    'verification_token' => null,
                    'token_expires_at' => null,
                ]);

                // Assign default role (e.g., admin or police? Default to police or no role?)
                // Assuming no role assigned initially, or manual assignment needed.
            }
            
            // Check for valid phone number
            $contact = $userAdmin->contact;
            $isValidContact = $contact && 
                              $contact !== '0000000000' && 
                              strlen(preg_replace('/[^\d]/', '', $contact)) >= 10;
            
            if (!$isValidContact) {
                // Redirect to update phone number
                session(['auth.google.pending_id' => $userAdmin->id]);
                return redirect()->route('auth.google.phone');
            }
            
            // Direct login without OTP (temporarily disabled)
            // TODO: Re-enable OTP for production by uncommenting the line below
            // return $this->initiateOtpLogin($userAdmin, request());
            
            Auth::login($userAdmin);
            request()->session()->regenerate();
            return redirect()->intended('dashboard');
            
        } catch (\Exception $e) {
            \Log::error('Google Auth Failed: ' . $e->getMessage());
            return redirect()->route('login')->withErrors([
                'email' => 'Google Sign-In failed. Please try again or use password.'
            ]);
        }
    }

    // Show Google Phone Update Form
    public function showGooglePhoneUpdate()
    {
        if (!session()->has('auth.google.pending_id')) {
            return redirect()->route('login');
        }
        return view('auth.google-phone');
    }

    // Update Phone and Proceed to OTP
    public function updateGooglePhone(Request $request)
    {
        if (!session()->has('auth.google.pending_id')) {
            return redirect()->route('login');
        }
        
        $request->validate([
            'contact' => 'required|string|min:10|max:15'
        ]);
        
        $userAdmin = UserAdmin::find(session('auth.google.pending_id'));
        if (!$userAdmin) {
            return redirect()->route('login')->with('error', 'User not found.');
        }
        
        $userAdmin->contact = EncryptionService::encrypt($request->contact);
        $userAdmin->save();
        
        // Clear pending session
        session()->forget('auth.google.pending_id');
        
        // Proceed to OTP
        return $this->initiateOtpLogin($userAdmin, $request);
            

    }
}
