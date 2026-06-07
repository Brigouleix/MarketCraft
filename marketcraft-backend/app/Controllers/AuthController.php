<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Captcha;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Models\User;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    // ------------------------------------------------------------------
    // GET /auth/captcha  – Generate a math captcha challenge
    // ------------------------------------------------------------------

    public function captcha(array $params = []): void
    {
        $this->json(['success' => true, 'data' => Captcha::generate()]);
    }

    // ------------------------------------------------------------------
    // POST /auth/register
    // ------------------------------------------------------------------

    public function register(array $params = []): void
    {
        $body = $this->getBody();

        $errors = $this->validate($body, [
            'nom'      => 'required|min:2|max:100',
            'prenom'   => 'required|min:2|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8|max:255',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        if ($this->userModel->emailExists($body['email'])) {
            $this->error('This email address is already in use.', 409);
            return;
        }

        $role = in_array($body['role'] ?? '', ['client', 'vendeur'], true)
            ? $body['role']
            : 'client';

        try {
            $user = $this->userModel->create([
                'nom'       => $body['nom'],
                'prenom'    => $body['prenom'],
                'email'     => $body['email'],
                'password'  => $body['password'],
                'role'      => $role,
                'telephone' => $body['telephone'] ?? null,
            ]);

            if ($user === null) {
                $this->error('User creation failed.', 500);
                return;
            }

            $token = Auth::generateToken($user);

            Logger::activity('user_register', "New user registered: {$user['email']}", [
                'role' => $user['role'],
            ], $user['id']);

            $this->json([
                'success'      => true,
                'message'      => 'Registration successful.',
                'access_token' => $token,
                'user'         => $user,
            ], 201);
        } catch (\Throwable $e) {
            $this->error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    // ------------------------------------------------------------------
    // POST /auth/login
    // ------------------------------------------------------------------

    public function login(array $params = []): void
    {
        $body           = $this->getBody();
        $ip             = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitKey   = "login:{$ip}";

        // ── 1. Rate limit check ───────────────────────────────────────
        if (RateLimiter::isBlocked($rateLimitKey)) {
            $remaining = RateLimiter::remainingSeconds($rateLimitKey);

            Logger::activity('login_blocked', "IP temporarily blocked: {$ip}", [
                'retry_after' => $remaining,
            ]);

            http_response_code(429);
            echo json_encode([
                'success'     => false,
                'error'       => 'Trop de tentatives de connexion. Réessayez dans ' . ceil($remaining / 60) . ' min.',
                'retry_after' => $remaining,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ── 2. Captcha verification ───────────────────────────────────
        $captchaToken  = $body['captcha_token']  ?? '';
        $captchaAnswer = $body['captcha_answer']  ?? '';

        if (!Captcha::verify((string) $captchaToken, (string) $captchaAnswer)) {
            $this->error('Code de sécurité invalide ou expiré. Rechargez la page.', 422);
            return;
        }

        // ── 3. Field validation ───────────────────────────────────────
        $errors = $this->validate($body, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        // ── 4. Credential check ───────────────────────────────────────
        $user = $this->userModel->findByEmail($body['email']);

        if ($user === null || !$this->userModel->verifyPassword($body['password'], $user['password_hash'])) {
            $attempts = RateLimiter::hit($rateLimitKey);

            Logger::activity('login_failure', "Failed login attempt for: {$body['email']}", [
                'attempts_in_window' => $attempts,
                'ip'                 => $ip,
            ]);

            $remaining = self::MAX_ATTEMPTS - $attempts;
            $suffix    = $remaining > 0
                ? " ({$remaining} tentative(s) restante(s) avant blocage)"
                : '';

            $this->error('Email ou mot de passe invalide.' . $suffix, 401);
            return;
        }

        // ── 5. Success ────────────────────────────────────────────────
        RateLimiter::reset($rateLimitKey);

        $userPublic = $this->userModel->toArray($user);
        $token      = Auth::generateToken($userPublic);

        Logger::activity('login_success', "User logged in: {$userPublic['email']}", [
            'role' => $userPublic['role'],
        ], $userPublic['id']);

        $this->json([
            'success'      => true,
            'message'      => 'Login successful.',
            'access_token' => $token,
            'user'         => $userPublic,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /auth/me  (JWT required)
    // ------------------------------------------------------------------

    public function me(array $params = []): void
    {
        $authPayload = Auth::getCurrentUser();

        if ($authPayload === null) {
            $this->error('Unauthorized.', 401);
            return;
        }

        $user = $this->userModel->findById((int) $authPayload['sub']);

        if ($user === null) {
            $this->error('User not found.', 404);
            return;
        }

        $this->success($user);
    }

    // ------------------------------------------------------------------
    // PUT /auth/me  (JWT required)
    // ------------------------------------------------------------------

    public function updateMe(array $params = []): void
    {
        $authPayload = Auth::getCurrentUser();

        if ($authPayload === null) {
            $this->error('Unauthorized.', 401);
            return;
        }

        $body   = $this->getBody();
        $userId = (int) $authPayload['sub'];

        $errors = $this->validate($body, [
            'nom'    => 'min:2|max:100',
            'prenom' => 'min:2|max:100',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        if (!empty($body['password'])) {
            if (empty($body['current_password'])) {
                $this->error('Current password is required to set a new one.', 422);
                return;
            }

            $currentUser = $this->userModel->findByEmail($authPayload['email']);
            if ($currentUser === null || !$this->userModel->verifyPassword($body['current_password'], $currentUser['password_hash'])) {
                $this->error('Current password is incorrect.', 403);
                return;
            }
        }

        $user = $this->userModel->update($userId, $body);

        if ($user === null) {
            $this->error('Update failed.', 500);
            return;
        }

        $this->success($user, 'Profile updated successfully.');
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private const MAX_ATTEMPTS = 5;
}
